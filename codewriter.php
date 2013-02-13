<?

require_once 'DBConnection.inc';

class DBTestConn extends DBConnection {
	static function ListTables() {
		$stmt = self::CreateStatement('SHOW FULL TABLES');
		self::RunStatement($stmt);
		$tables = array();
		while ($row = $stmt->fetch(PDO::FETCH_NUM))
			$tables[$row[0]] = $row[1];
		return $tables;
	}
	static function Describe($table) {
		$stmt = self::CreateStatement('DESCRIBE '.$table);
		self::RunStatement($stmt);
		return $stmt->fetchAll();
	}
	static function GetForeignKeys($schema, $table) {
		$stmt = self::CreateStatement('SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table AND REFERENCED_TABLE_NAME IS NOT NULL');
		self::RunStatement(
			$stmt, array(
				':schema' => $schema,
				':table' => $table
			)
		);
		return $stmt->fetchAll();
	}
}

if (!isset($_GET['table'])) {
	echo '<form action="" method="get">';
	echo '<select name="table" onchange="change(this);">';
	foreach (DBTestConn::ListTables() as $table => $tableType) {
		printf('<option data-type="%s">%s</option>', $tableType, $table);
	}
	echo '</select>';
	echo ' <label><input type="checkbox" name="op[]" value="select" checked /> select</label>';
	echo ' <label><input type="checkbox" name="op[]" value="selectOne" checked /> selectOne</label>';
	echo ' <label><input type="checkbox" name="op[]" value="insert" checked /> insert</label>';
	echo ' <label><input type="checkbox" name="op[]" value="replace" checked /> replace</label>';
	echo ' <label><input type="checkbox" name="op[]" value="update" checked /> update</label>';
	echo ' <label><input type="checkbox" name="op[]" value="delete" checked /> delete</label>';
	echo ' <button type="submit">Go</button>';
	echo '</form>';

	echo '<script>';
	echo 'function change(s) {'.
		'var v = s.options[s.selectedIndex].dataset.type == "VIEW";'.
		'var c = !v && s.options[s.selectedIndex].value.indexOf("_") > 0;'.
		'var o = document.querySelectorAll("input");'.
		'o[1].disabled = c;'.
		'o[2].disabled = v;'.
		'o[3].disabled = c || v;'.
		'o[4].disabled = c || v;'.
		'o[5].disabled = v;'.
		'}'.
		'change(document.querySelector("select"));';
	echo '</script>';

	exit;
}

if (isset($_GET['op']) && is_array($_GET['op'])) {
	$operation = $_GET['op'];
} else {
	$operation = array(
		'select',
		'selectOne',
		'insert',
		'replace',
		'update',
		'delete'
	);
}

foreach (DBTestConn::ListTables() as $table => $tableType) {
	if ($table != $_GET['table']) {
		continue;
	}

	$upperName = $table;
	$upperName[0] = strtoupper($upperName[0]);
	$upperName = preg_replace_callback('/_[a-z]/', 'camelCase', $upperName);

	$columns = DBTestConn::Describe($table);
	$colTypes = array();
	foreach ($columns as $column) {
		$colName = $column['Field'];
		$colTypes[$colName] = $column['Type'];
	}

	printf('<h2>%s</h2>', $table);
	echo '<button onclick="select(0);">Select PHP</button>';
	echo '<button onclick="select(1);">Select JS</button>';
	echo '<script>';
	echo 'function select(i) {'.
		'var s = window.getSelection();'.
		's.removeAllRanges();'.
		'var r = document.createRange();'.
		'r.selectNodeContents(document.querySelectorAll("pre")[i]);'.
		's.addRange(r);'.
		// 'document.execCommand("copy", null, null)'.
		'}';
	echo '</script>';
	echo '<pre style="tab-size: 4; -moz-tab-size: 4;">';

	$argumentList = array();
	printf("class %s extends DBConnection {\n", $upperName);
	if (preg_match('/(\w+)_(\w+)/', $table, $parts)) {
		$sql = 'SELECT %4$s.* FROM %1$s %2$s LEFT JOIN %3$s %4$s ON %2$s.%5$s = %4$s.%6$s WHERE %2$s.%7$s = :%7$s';

		$keyData = DBTestConn::GetForeignKeys('darktrojan', $table);
		$local1 = $keyData[0]['COLUMN_NAME'];
		$table1 = $keyData[0]['REFERENCED_TABLE_NAME'];
		$upper1 = $table1;
		$upper1[0] = strtoupper($upper1[0]);
		$abbr1 = $table1[0];
		$foreign1 = $keyData[0]['REFERENCED_COLUMN_NAME'];
		$local2 = $keyData[1]['COLUMN_NAME'];
		$table2 = $keyData[1]['REFERENCED_TABLE_NAME'];
		$upper2 = $table2;
		$upper2[0] = strtoupper($upper2[0]);
		$abbr2 = $table2[0];
		$foreign2 = $keyData[1]['REFERENCED_COLUMN_NAME'];
		$abbrLocal = $abbr1.$abbr2;

		if (in_array('select', $operation)) {
			$funcname = sprintf('Select%sFor%s', $upper2, $upper1);
			$args = array($local1);
			PrintFunction(
				$funcname,
				sprintf($sql, $table, $abbrLocal, $table2, $abbr2, $local2, $foreign2, $local1),
				$args
			);
			$argumentList[$funcname] = $args;
	
			$funcname = sprintf('Select%sFor%s', $upper1, $upper2);
			$args = array($local2);
			PrintFunction(
				$funcname,
				sprintf($sql, $table, $abbrLocal, $table1, $abbr1, $local1, $foreign1, $local2),
				$args
			);
			$argumentList[$funcname] = $args;
		}

		if (in_array('insert', $operation)) {
			$funcname = sprintf('Insert%s', $upperName);
			$args = array($local1, $local2);
			PrintFunction(
				$funcname,
				sprintf('INSERT IGNORE INTO %s (%2$s, %3$s) VALUES (:%2$s, :%3$s)', $table, $local1, $local2),
				$args,
				'$stmt->rowCount() == 1'
			);
			$argumentList[$funcname] = $args;
		}

		if (in_array('delete', $operation)) {
			$funcname = sprintf('Delete%s', $upperName);
			PrintFunction(
				$funcname,
				sprintf('DELETE FROM %s WHERE %2$s = :%2$s AND %3$s = :%3$s', $table, $local1, $local2),
				$args,
				'$stmt->rowCount() == 1'
			);
			$argumentList[$funcname] = $args;
		}

		unset($sql, $keyData, $local1, $table1, $upper1, $abbr1, $foreign1, $local2, $table2, $upper2, $abbr2, $foreign2, $abbrLocal, $funcname, $args);
	} else {
		$primaryKey = array();
		$primaryKeyParams = array();
		$selectColNames = array();
		$selectOneColNames = array();
		$insertColNames = array();
		$insertColValues = array();
		$replaceColNames = array();
		$replaceColValues = array();
		$updateSetters = array();
		$updateParams = array();

		foreach ($columns as $column) {
			$colName = $column['Field'];
			$value = ':'.$colName;

			if ($column['Type'] == 'timestamp') {
				$select = 'UNIX_TIMESTAMP('.$colName.') AS '.$colName;
				$value = 'FROM_UNIXTIME('.$value.')';
			} else {
				$select = $colName;
			}
			$match = sprintf('%s = %s', $colName, $value);

			$selectColNames[] = $select;
			$replaceColNames[] = $colName;
			$replaceColValues[] = $value;
			if ($column['Key'] == 'PRI') {
				$primaryKey[] = $match;
				$primaryKeyParams[] = $colName;
			} else {
				$selectOneColNames[] = $select;
				$updateSetters[] = $match;
				$updateParams[] = $colName;
			}
			if ($column['Extra'] != 'auto_increment') {
				$insertColNames[] = $colName;
				$insertColValues[] = $value;
			}
		}

		if (in_array('select', $operation)) {
			PrintFunction(
				'Select'.$upperName,
				sprintf(
					'SELECT %s FROM %s',
					implode(', ', $selectColNames),
					$table
				)
			);
			$argumentList['Select'.$upperName] = array();
		}
		if (in_array('selectOne', $operation)) {
			PrintFunction(
				'SelectOne'.$upperName,
				sprintf(
					'SELECT %s FROM %s WHERE %s',
					implode(', ', $selectOneColNames),
					$table,
					implode(' AND ', $primaryKey)
				),
				$primaryKeyParams,
				'$stmt->fetch() ?: null'
			);
			$argumentList['SelectOne'.$upperName] = $primaryKeyParams;
		}
		if ($tableType != 'VIEW') {
			if (in_array('insert', $operation)) {
				PrintFunction(
					'Insert'.$upperName,
					sprintf(
						'INSERT INTO %s (%s) VALUES (%s)',
						$table,
						implode(', ', $insertColNames),
						implode(', ', $insertColValues)
					),
					$insertColNames,
					'self::LastInsertId()'
				);
				$argumentList['Insert'.$upperName] = $insertColNames;
			}
			if (in_array('replace', $operation)) {
				PrintFunction(
					'Replace'.$upperName,
					sprintf(
						'REPLACE INTO %s (%s) VALUES (%s)',
						$table,
						implode(', ', $replaceColNames),
						implode(', ', $replaceColValues)
					),
					$replaceColNames,
					'$stmt->rowCount() == 2'
				);
				$argumentList['Replace'.$upperName] = $replaceColNames;
			}
			if (in_array('update', $operation)) {
				PrintFunction(
					'Update'.$upperName,
					sprintf(
						'UPDATE %s SET %s WHERE %s',
						$table,
						implode(', ', $updateSetters),
						implode(' AND ', $primaryKey)
					),
					array_merge($primaryKeyParams, $updateParams),
					'$stmt->rowCount() == 1'
				);
				$argumentList['Update'.$upperName] = array_merge($primaryKeyParams, $updateParams);
			}
			if (in_array('delete', $operation)) {
				PrintFunction(
					'Delete'.$upperName,
					sprintf(
						'DELETE FROM %s WHERE %s',
						$table,
						implode(' AND ', $primaryKey)
					),
					$primaryKeyParams,
					'$stmt->rowCount() == 1'
				);
				$argumentList['Delete'.$upperName] = $primaryKeyParams;
			}
		}
	}
	echo "\tstatic function ArgumentList(\$functionName) {\n";
	echo "\t\tswitch (\$functionName) {\n";
	foreach ($argumentList as $func => $params) {
		printf("\t\tcase '%s':\n", $func);
		if ($params) {
			echo "\t\t\treturn array(\n";
			$format = "\t\t\t\t'%s' => %s,\n";
			for ($i = 0, $iCount = sizeof($params) - 1; $i <= $iCount; $i++) {
				$param = $params[$i];
				$colType = $colTypes[$param];
				if ($colType == 'tinyint(1)')
					$type = 'DBConnection::TYPE_BOOLEAN';
				else if (strpos($colType, 'int(') !== false)
					$type = 'DBConnection::TYPE_INTEGER';
				else
					$type = 'DBConnection::TYPE_STRING';

				if ($i == $iCount)
					$format = str_replace(',', '', $format);
				printf($format, $param, $type);
			}
			echo "\t\t\t);\n";
		} else {
			echo "\t\t\treturn array();\n";
		}
	}
	echo "\t\t}\n";
	echo "\t}\n";
	echo "}\n";

	echo '</pre>';
	echo '<hr />';
	echo '<pre style="tab-size: 4; -moz-tab-size: 4;">';

	foreach ($argumentList as $func => $params) {
		printf("function %s(%s) {\n", $func, implode(', ', $params));
		if ($params) {
			echo "\tvar data =\n";
			$format = "\t\t'%s=' + %s +\n";
			for ($i = 0, $iCount = sizeof($params) - 1; $i <= $iCount; $i++) {
				$param = $params[$i];
				$colType = $colTypes[$param];
				if ($colType == 'tinyint(1)')
					$encodedParam = $param;
				else if (strpos($colType, 'int(') !== false)
					$encodedParam = $param;
				else
					$encodedParam = 'encodeURIComponent('.$param.')';

				if ($i == $iCount)
					$format = substr($format, 0, strlen($format) - 3).";\n";
				printf($format, $param, $encodedParam);
			}
		}
		printf(
			"\tXHR.post('%s%s/%s', data);\n",
			'/ajax.php?',
			$upperName, $func
		);
		echo "}\n";
	}


	echo '</pre>';
}

function PrintFunction($name, $sql, $params = array(), $retValue = '$stmt->fetchAll()') {
	global $colTypes;

	printf(
		"\tstatic function %s(%s) {\n",
		$name,
		implode(', ', array_map(create_function('$a', 'return "\$$a";'), $params))
	);
	$nl = false;
	foreach ($params as $param) {
		$colType = $colTypes[$param];
		if ($colType == 'tinyint(1)') {
			printf("\t\t\$%1\$s = boolCast(\$%1\$s);\n", $param);
			$nl = true;
		} else if (strpos($colType, 'int(') !== false) {
			printf("\t\t\$%1\$s = (int)\$%1\$s;\n", $param);
			$nl = true;
		}
	}
	if ($nl)
		echo "\n";

	printf("\t\t\$stmt = self::CreateStatement('%s');\n", $sql);
	if ($params) {
		echo "\t\tself::RunStatement(\n";
		echo "\t\t\t\$stmt, array(\n";

		$format = "\t\t\t\t':%1\$s' => \$%1\$s,\n";
		for ($i = 0, $iCount = sizeof($params) - 1; $i <= $iCount; $i++) {
			$param = $params[$i];
			if ($i == $iCount)
				$format = str_replace(',', '', $format);
			printf($format, $param);
		}
		echo "\t\t\t)\n";
		echo "\t\t);\n";
	} else {
		echo "\t\tself::RunStatement(\$stmt);\n";
	}
	printf("\t\treturn %s;\n", $retValue);
	echo "\t}\n\n";
}

function camelCase($match) {
	return strtoupper($match[0][1]);
}

<?

define('LIVE', false);

spl_autoload_register('autoloadDBClass');
function autoloadDBClass($className) {
	$filepath = dirname(__FILE__).'/'.$className.'.inc';
	if (file_exists($filepath)) {
		require_once $filepath;
	}
}

if (preg_match('/^(\w+)(\/|%2F)(\w+)(&|=|$)/', $_SERVER['QUERY_STRING'], $match)) {
	$className = $match[1];
	$functionName = $match[3];
	$filepath = realpath('.').'/'.$className.'.inc';

	header('Content-Type: application/json; charset=utf-8');

	if (!file_exists($filepath)) {
		require_once '404.php';
	}

	$arguments = array();
	foreach ($className::ArgumentList($functionName) as $argumentName => $argumentType) {
		switch ($argumentType) {
		case DBConnection::TYPE_BOOLEAN:
			$arg = boolCast($_REQUEST[$argumentName]);
			break;
		case DBConnection::TYPE_INTEGER:
		case DBConnection::TYPE_TIMESTAMP:
			$arg = (int)($_REQUEST[$argumentName]);
			break;
		default:
			$arg = $_REQUEST[$argumentName];
			break;
		}
		$arguments[] = $arg;
	}

	$result = call_user_func_array($className.'::'.$functionName, $arguments);

	echo json_encode($result);
	exit;
}

if (!LIVE && preg_match('/^(\w+)$/', $_SERVER['QUERY_STRING'], $match)) {
	$className = $match[1];
	$filepath = realpath('.').'/'.$className.'.inc';

	if (!file_exists($filepath)) {
		require_once '404.php';
	}

	printf('<h1>%s</h1>', $className);
	foreach (get_class_methods($className) as $functionName) {
		if ($functionName != 'ArgumentList') {
			$argumentList = $className::ArgumentList($functionName);
			if (sizeof($argumentList)) {
				printf('<h2>%s</h2>', $functionName);
				printf('<form action="?%s/%s" method="post">', $className, $functionName);
				echo '<table>';
				foreach ($argumentList as $argumentName => $argumentType) {
					printf(
						'<tr><th>%s</th><td>%s</td><td>',
						$argumentName, $argumentType
					);
					switch ($argumentType) {
					case DBConnection::TYPE_BOOLEAN:
						printf(
							'<label><input type="radio" name="%1$s" value="true" /> True</label> '.
							'<label><input type="radio" name="%1$s" value="false" /> False</label>',
							$argumentName
						);
						break;
					case DBConnection::TYPE_INTEGER:
						printf('<input type="number" name="%s" />', $argumentName);
						break;
					case DBConnection::TYPE_TIMESTAMP:
						printf('<input type="number" name="%s" /><input type="button" onclick="this.previousElementSibling.value = Math.floor(Date.now() / 1000);" value="Now" />', $argumentName);
						break;
					default:
						printf('<input type="text" name="%s" />', $argumentName);
						break;
					}
					echo '</td></tr>';
				}
				echo '<tr><td colspan="3" align="right"><input type="submit" value="Go" /></td></tr>';
				echo '</table></form>';
				echo '<pre style="tab-size: 4; -moz-tab-size: 4;">';
				printf("function %s(%s) {\n", $functionName, implode(', ', array_keys($argumentList)));
				echo "\tvar data =\n";
				$format = "\t\t'%s=' + %s +\n";
				$i = 0;
				$iCount = sizeof($argumentList);
				foreach ($argumentList as $argumentName => $argumentType) {
					switch ($argumentType) {
					case DBConnection::TYPE_BOOLEAN:
					case DBConnection::TYPE_INTEGER:
					case DBConnection::TYPE_TIMESTAMP:
						$encodedParam = $argumentName;
						break;
					default:
						$encodedParam = 'encodeURIComponent('.$argumentName.')';
						break;
					}

					if ($i == 1)
						$format = "\t\t'&amp;".substr($format, 3);
					if (++$i == $iCount)
						$format = substr($format, 0, strlen($format) - 3).";\n";
					printf($format, $argumentName, $encodedParam);
				}
				printf(
					"\tXHR.post('%s%s/%s', data);\n",
					'/ajax.php?',
					$className, $functionName
				);
				echo "}\n";
				echo '</pre>';
			} elseif (is_array($argumentList)) {
				printf('<h2><a href="?%1$s/%2$s">%2$s</a></h2>', $className, $functionName);
			}
		}
	}
	exit;
}

if (!LIVE) {
	foreach (glob('*.inc') as $file) {
		printf('<a href="?%s">%s</a><br />', substr($file, 0, strlen($file) - 4), $file);
	}
	exit;
}

require_once '404.php';

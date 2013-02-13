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
	$filepath = realpath('.')."/$className.inc";

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
			$arg = (int)($_REQUEST[$argumentName]);
			break;
		default:
			$arg = $_REQUEST[$argumentName];
			break;
		}
		$arguments[] = $arg;
	}

	$result = call_user_func_array("$className::$functionName", $arguments);

	echo json_encode($result);
	exit;
}

if (!LIVE && preg_match('/^(\w+)$/', $_SERVER['QUERY_STRING'], $match)) {
	$className = $match[1];
	$filepath = realpath('.')."/$className.inc";

	if (!file_exists($filepath)) {
		require_once '404.php';
	}

	echo "<h1>$className</h1>";
	foreach (get_class_methods($className) as $functionName) {
		if ($functionName != 'ArgumentList') {
			$argumentList = $className::ArgumentList($functionName);
			if (sizeof($argumentList)) {
				echo "<h2>$functionName</h2>";
				printf('<form action="?%s/%s" method="post">', $className, $functionName);
				echo '<table>';
				foreach ($argumentList as $argumentName => $argumentType) {
					printf(
						'<tr><th>%s</th><td>%s</td><td>',
						$argumentName, $argumentType
					);
					if ($argumentType == DBConnection::TYPE_BOOLEAN) {
						printf(
							'<label><input type="radio" name="%1$s" value="true" /> True</label> '.
							'<label><input type="radio" name="%1$s" value="false" /> False</label>',
							$argumentName
						);
					} else {
						printf('<input type="text" name="%s" />', $argumentName);
					}
					echo '</td></tr>';
				}
				echo '<tr><td colspan="3" align="right"><input type="submit" value="Go" /></td></tr>';
				echo '</table></form>';
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

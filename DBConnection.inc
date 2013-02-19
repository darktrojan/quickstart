<?

function boolCast($b) {
	return $b === true || $b === 'true';
}

abstract class DBConnection {
	const TYPE_BOOLEAN = 'boolean';
	const TYPE_INTEGER = 'integer';
	const TYPE_STRING = 'string';
	const TYPE_TIMESTAMP = 'timestamp';

	private static $connection = null;
	private static $statementCache = array();
	public static $inTransaction = 0;
	public static $queryCount = 0;
	private static $lastRunStatement = null;

	public static function UniqueQueryCount() {
		return sizeof(self::$statementCache);
	}

	public static function BeginTransaction() {
		self::Connect();
		if (self::$inTransaction == 0) {
			self::$connection->beginTransaction();
		}
		self::$inTransaction++;
	}

	public static function CommitTransaction() {
		self::$inTransaction--;
		if (self::$inTransaction == 0) {
			self::$connection->commit();
		}
	}

	private static function Connect() {
		if (!self::$connection) {
			try {
				self::$connection = new PDO(
					'mysql:', null, null, array(
						PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
						PDO::MYSQL_ATTR_READ_DEFAULT_FILE => dirname(__FILE__).'/mysql.cnf',
						PDO::MYSQL_ATTR_READ_DEFAULT_GROUP => 'test'
					)
				);
			} catch (PDOException $ex) {
				var_dump($ex);
			}
		}
	}

	protected static function CreateStatement($sql) {
		self::Connect();

		$stmtMD5 = md5($sql);
		if (isset(self::$statementCache[$stmtMD5]))
			return self::$statementCache[$stmtMD5];

		$stmt = self::$connection->prepare($sql);
		self::$statementCache[$stmtMD5] = $stmt;
		return $stmt;
	}

	protected static function RunStatement($stmt, $params = array(), $errorMessage = null) {
		self::$queryCount++;
		self::$lastRunStatement = $stmt;
		$stmt->execute($params);
		if ($stmt->errorCode() != '00000') {
			$errorInfo = $stmt->errorInfo();
			if (self::$inTransaction > 0) {
				self::$connection->rollBack();
			}

			trigger_error($errorMessage ? $errorMessage : ($errorInfo[2] ? $errorInfo[2] : 'Database error'), E_USER_ERROR);
		}
	}

	protected static function LastInsertId() {
		return (int)self::$connection->lastInsertId();
	}

	protected static function FoundRows() {
		$stmt = self::CreateStatement('SELECT FOUND_ROWS() AS count');
		self::RunStatement($stmt);
		$line = $stmt->fetch();
		return $line['count'];
	}

	static function ColumnNames() {
		$stmt = self::$lastRunStatement;
		if (!$stmt)
			return null;

		$names = array();
		for ($i = 0, $iCount = $stmt->columnCount(); $i < $iCount; $i++) {
			$meta = $stmt->getColumnMeta($i);
			$names[] = $meta['name'];
		}
		return $names;
	}
}

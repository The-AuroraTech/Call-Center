<?php
/*
dbFacile - The easily used PHP Database Abstraction Class
Version 0.3
See LICENSE for license details.
*/

abstract class dbFacile {
	protected $dbHandle; // handle to Database connection
	protected $query;
	protected $result;
	protected $numberRecords; // records affected by query, or records returned from select
	protected $logFile;
	protected $fields;
	protected $fieldNames;
	protected $schemaNameField;
	protected $schemaTypeField;

	public function __construct($handle = null) {
		if(func_num_args() > 1)
			die('The dbFacile constructor has changed. Please see the README');
		$this->dbHandle = $handle;
		$this->query = $this->result = null;
		$this->numberRecords = 0;
		$this->fields = array();
		$this->fieldNames = array();
		$this->logFile = null;
	}

	public function __destruct() {
		if($this->logFile)
			fclose($this->logFile);
	}

	public function logToFile($file, $method = 'w+') {
		$this->logFile = fopen($file, $method);
	}

	/*
	 * Performs a query using the given string.
	 * Used by the other _query functions.
	 *
	 * Note: You CAN pass additional parameters to this method
	 * If you do, each '?' in the first parameter will be replaced by the successive function arguments
	 * This removes the need to escape and quote values manually
	 * */
	public function execute($sql) {
		// bad thing is, transformPlaceholders will probably be called twice due to next line
		// it should already be called by fetch*() series of methods
		if(is_string($sql)) {
			$sql = $this->transformPlaceholders(func_get_args());
		}
		$this->query = $sql;
		if($this->logFile)
			fwrite($this->logFile, date('Y-m-d H:i:s') . "\n" . $sql . "\n\n");

		$this->_query($this->query); // sets $this->result
		if(!$this->result && (error_reporting() & 1))
			die('dbFacile - Error in query: ' . $this->query . ' : ' . $this->_error());
		$this->_numberRecords(); // sets $this->numberRecords
		if($this->numberRecords > 0)
			return true;
		else
			return false;
	}

	/*
	 * Alias for insert
	 * */
	public function add($data, $table) {
		return $this->insert($data, $table);
	}

	/*
	 * Passed an array and a table name, it attempts to insert the data into the table.
	 * Check for boolean false to determine whether insert failed
	 * */
	public function insert($data, $table) {
		// the following block swaps the parameters if they were given in the wrong order.
		// it allows the method to work for those that would rather it (or expect it to)
		// follow closer with SQL convention:
		// insert into the TABLE this DATA
		if(is_string($data) && is_array($table)) {
			$tmp = $data;
			$data = $table;
			$table = $tmp;
			trigger_error('dbFacile - Parameters passed to insert() were in reverse order, but it has been allowed', E_USER_NOTICE);
		}
		$values = $this->prepareValues($data, $table);

		$sql = 'insert into ' . $table . ' (' . implode(',', array_keys($values)) . ') values(' . implode(',', $values) . ')';

		$this->beginTransaction();	
		if($this->execute($sql)) {
			$id = $this->_lastID($table);
			$this->commitTransaction();
			return $id;
		} else {
			$this->rollbackTransaction();
			return false;
		}
	}

	/*
	 * Passed an array, table name, and a where clause, it attempts to update a record.
	 * Returns the number of affected rows
	 * */
	public function update($data, $table, $where = null) {
		// the following block swaps the parameters if they were given in the wrong order.
		// it allows the method to work for those that would rather it (or expect it to)
		// follow closer with SQL convention:
		// update the TABLE with this DATA
		if(is_string($data) && is_array($table)) {
			$tmp = $data;
			$data = $table;
			$table = $tmp;
			trigger_error('dbFacile - The first two parameters passed to update() were in reverse order, but it has been allowed', E_USER_NOTICE);
		}
		$values = $this->prepareValues($data, $table);

		$sql = 'update ' . $table . ' set ';
		foreach($values as $key => $value) {
			$sql .= $key . '=' . $value . ',';
		}
		$sql = substr($sql, 0, -1); // strip off last comma
		$args = func_get_args();
		$args = array_slice($args, 2); // strip off data array and table name
		if(sizeof($args)) {
			if(is_array($args[0])) // check for where = array('a=?', 1)
				$args = $args[0];
			$sql .= ' where ' . $this->transformPlaceholders($args);
		}
		$this->execute($sql);
		return $this->numberRecords;
	}

	public function delete($table, $where = null) {
		$sql = 'delete from ' . $table;
		$args = func_get_args();
		array_shift($args); // get rid of table name
		if(sizeof($args)) {
			if(is_array($args[0])) // check for where = array('a=?', 1)
				$args = $args[0];
			$sql .= ' where ' . $this->transformPlaceholders($args);
		}
		$this->execute($sql);
		return $this->numberRecords;
	}

	/*
	 * Fetches all of the rows (associatively) from the last performed query.
	 * Most other retrieval functions build off this
	 * */
	public function &fetchAll() {
		$sql = $this->transformPlaceholders(func_get_args());
		$this->execute($sql);
		if($this->numberRecords) {
			return $this->_fetchAll();
		}
		// no records, thus return empty array
		// which should evaluate to false, and will prevent foreach notices/warnings 
		return array();
	}
	/*
	 * This is intended to be the method used for large result sets.
	 * It is intended to return an iterator, and act upon buffered data.
	 * */
	public function &fetch() {
		$sql = $this->transformPlaceholders(func_get_args());
		$this->execute($sql);
		return $this->_fetch();
	}

	/*
	 * Like fetch(), accepts any number of arguments
	 * The first argument is an sprintf-ready query stringTypes
	 * */
	public function &fetchRow() {
		$sql = $this->transformPlaceholders(func_get_args());
		if($this->execute($sql)) {
			return $this->_fetchRow();
		}
		return null;
	}

	/*
	 * Fetches the first call from the first row returned by the query
	 * */
	public function fetchCell() {
		$sql = $this->transformPlaceholders(func_get_args());
		if($this->execute($sql)) {
			return array_shift($this->_fetchRow()); // shift first field off first row
		}
		return null;
	}

	/*
	 * This method is quite different from fetchCell(), actually
	 * It fetches one cell from each row and places all the values in 1 array
	 * */
	public function &fetchColumn() {
		$sql = $this->transformPlaceholders(func_get_args());
		if($this->execute($sql)) {
			$cells = array();
			foreach($this->_fetchAll() as $row) {
				$cells[] = array_shift($row);
			}
			return $cells;
		} else {
			return null;
		}
	}

	/*
	 * Should be passed a query that fetches two fields
	 * The first will become the array key
	 * The second the key's value
	 */
	public function &fetchKeyValue() {
		$sql = $this->transformPlaceholders(func_get_args());
		if($this->execute($sql)) {
			$data = array();
			foreach($this->_fetchAll() as $row) {
				$key = array_shift($row);
				if(sizeof($row) == 1) { // if there were only 2 fields in the result
					// use the second for the value
					$data[ $key ] = array_shift($row);
				} else { // if more than 2 fields were fetched
					// use the array of the rest as the value
					$data[ $key ] = $row;
				}
			}
			return $data;
		} else
			return null;
	}

	public function beginTransaction() {
	}

	public function commitTransaction() {
	}

	public function rollbackTransaction() {
	}

	/*
	 * Open specified database
	 * */
	public function open() {
		// open the database and hold the connection to it
		$args = func_get_args();
		$this->_open($args);
		if(!$this->dbHandle)
			die('dbFacile - Error opening database: ' . $database);
	}
	/*
	 * Same as open()
	 * */
	public function connect() {
		// open the database and hold the connection to it
		$args = func_get_args();
		$this->_open($args);
		if(!$this->dbHandle)
			die('dbFacile - Error opening database: ' . $database);
	}
	public function close() {
	}

	/*
	 * Return query and other debugging data if error_reporting to right settings
	 * */
	private function debugging() {
		if(in_array(error_reporting(), array(E_ALL))) {
			return $this->query;
		}
	}

	/*
	 * Replaces ? with escaped quoted values from args[1] and up
	 * */
	private function transformPlaceholders($args) {
		if(sizeof($args) == 1)
			return $args[0];
		$a = array(str_replace('?', '%s', array_shift($args)));
		foreach($args as $b) {
			$a[] = "'" . $this->_escapeString($b) . "'";
		}
		return call_user_func_array('sprintf', $a);
	}

	/*
	 * Used by insert() and update() to generate arrays that aid in constructing queries
	 * */
	private function prepareValues($data, $table) {
		$columns = $this->getFieldNames($table);
		$values = array();

		foreach($data as $key=>$value) {
			$escape = true;
			if(substr($key,-1) == '=') {
				$escape = false;
				$key = substr($key, 0, strlen($key)-1);
			}
			if(!in_array($key, $columns)) // skip invalid fields
				continue;
			if($escape)
				$values[$key] = "'" . $this->_escapeString($value) . "'";
			else
				$values[$key] = $value;
		}
		return $values;
	}

	public function getTables() {
		return $this->_tables();
	}

	/*
	 * Returns an array, indexed by field name with values of true or false.
	 * True means the field should be quoted
	 * */
	private function getTableInfo($table) {
		$rows = $this->_schema($table);
		if($rows) {
			$fields = array();
			foreach($rows as $row) {
				$type = strtolower(preg_replace('/\(.*\)/', '', $row[ $this->schemaTypeField ])); // remove size specifier
				$name = $row[ $this->schemaNameField ];
				$fields[$name] = $type;
			}
			//$this->fieldsToQuote[$table] = $fieldsToQuote;
			$this->fieldNames[$table] = array_keys($fields);
			$this->fieldTypes[$table] = $fields;
		} else
			die('dbFacile - Table "' . $table . '" does not exist');
	}

	/*
	 * Returns an array of the table's field names
	 * */
	public function getFieldNames($table) {
		if(!array_key_exists($table, $this->fieldNames))
			$this->getTableInfo($table);
		return $this->fieldNames[$table];
	}

	public function getFieldTypes($table) {
		if(!array_key_exists($table, $this->fieldTypes))
			$this->getTableInfo($table);
		return $this->fieldTypes[$table];
	}
}

/*
 * To create a new driver, implement the following:
 * protected _open
 * protected _query
 * protected _escapeString
 * protected _error
 * protected _numberRecords
 * protected _fetch
 * protected _fetchAll
 * protected _fetchRow
 * protected _lastID
 * protected _schema
 * public beginTransaction
 * public commitTransaction
 * public rollbackTransaction
 * public close
 * */

class dbFacile_mssql extends dbFacile {
	function __construct($handle = null) {
		parent::__construct();
		$this->schemaNameField = 'COLUMN_NAME';
		$this->schemaTypeField = 'DATA_TYPE';
		if($handle != null)
			$this->dbHandle = $handle;
	}
	// user, password, database, host
	protected function _open($args) {
		if(!$args[3])
			$args[3] = 'localhost';
		$this->dbHandle = mssql_connect($args[3], $args[0], $args[1]);
		mssql_select_db($args[2], $this->dbHandle);
	}
	protected function _query($query) {
		$this->result = mssql_query($this->query, $this->dbHandle);
	}

	// found this code in the comments of the PHP manual:
	// http://www.php.net/manual/en/function.mssql-query.php
	protected function _escapeString($string) {
		$s = stripslashes($string);
		$s = str_replace( array("'", "\0"), array("''", '[NULL]'), $s);
		return $s;
	}
	protected function _error() {
		return mssql_get_last_message();
	}
	protected function _numberRecords() {
		if(mssql_rows_affected($this->dbHandle)) {
			$this->numberRecords = mssql_rows_affected($this->dbHandle);
		} elseif(!is_bool($this->result)) {
			$this->numberRecords = mssql_num_rows($this->result);
		} else {
			$this->numberRecords = 0;
		}
	}
	protected function _fetch() {
		return $this->_fetchAll();
	}
	protected function _fetchAll() {
		$data = array();
		for($i = 0; $i < $this->numberRecords; $i++) {
			$data[] = mssql_fetch_assoc($this->result);
		}
		mssql_free_result($this->result);
		return $data;
	}
	protected function _fetchRow() {
	}
	protected function _lastID() {
		return $this->fetchCell('select scope_identity()');
	}
	protected function _schema($table) {
		$this->execute('select COLUMN_NAME,DATA_TYPE from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME=\'' . $table . '\'');
		return $this->_fetchAll();
	}
	public function close() {
		mssql_close($this->dbHandle);
	}
} // mssql

class dbFacile_mysql extends dbFacile {
	function __construct($handle = null) {
		parent::__construct($handle);
		$this->schemaNameField = 'Field';
		$this->schemaTypeField = 'Type';
		if($handle != null)
			$this->dbHandle = $handle;
	}
	// user, password, database, host
	protected function _open($args) {
		if(!$args[3])
			$args[3] = 'localhost';
		$this->dbHandle = mysql_connect($args[3], $args[0], $args[1]);
		mysql_select_db($args[2], $this->dbHandle);
	}
	protected function _query($query) {
		$this->result = mysql_query($this->query, $this->dbHandle);
	}
	protected function _escapeString($string) {
		return mysql_real_escape_string($string);
	}
	protected function _error() {
		return mysql_error($this->dbHandle);
	}
	protected function _numberRecords() {
		if(mysql_affected_rows($this->dbHandle)) { // for insert, update, delete
			$this->numberRecords = mysql_affected_rows($this->dbHandle);
		} elseif(!is_bool($this->result)) { // for selects
			$this->numberRecords = mysql_num_rows($this->result);
		} else { // will be boolean for create, drop, and other
			$this->numberRecords = 0;
		}
	}
	protected function _fetch() {
		/*
		 * use mysql_data_seek to get to row index
		 * */
		return $this->_fetchAll();
	}
	protected function _fetchAll() {
		$data = array();
		for($i = 0; $i < $this->numberRecords; $i++) {
			$data[] = mysql_fetch_assoc($this->result);
		}
		mysql_free_result($this->result);
		return $data;
	}
	protected function _fetchRow() {
		return mysql_fetch_assoc($this->result);
	}
	protected function _lastID() {
		return mysql_insert_id($this->dbHandle);
	}
	protected function _schema($table) {
		$this->execute('describe ' . $table);
		return $this->_fetchAll();
	}
	public function beginTransaction() {
		mysql_query('begin', $this->dbHandle);
	}
	public function commitTransaction() {
		mysql_query('commit', $this->dbHandle);
	}
	public function rollbackTransaction() {
		mysql_query('rollback', $this->dbHandle);
	}
	public function close() {
		mysql_close($this->dbHandle);
	}
} // mysql

class dbFacile_mysqli extends dbFacile {
	function __construct($handle = null) {
		parent::__construct();
		$this->schemaNameField = 'Field';
		$this->schemaTypeField = 'Type';
		if($handle != null)
			$this->dbHandle = $handle;
	}
	// user, password, database, host
	protected function _open($args) {
		if(!$args[3])
			$args[3] = 'localhost';
		$this->dbHandle = mysqli_connect($args[3], $args[0], $args[1], $args[2]);
	}
	protected function _query($query) {
		$this->result = mysqli_query($this->dbHandle, $this->query);
	}
	protected function _escapeString($string) {
		return mysqli_real_escape_string($string);
	}
	protected function _error() {
		return mysqli_error($this->dbHandle);
	}
	protected function _numberRecords() {
		if(mysqli_affected_rows($this->dbHandle)) { // for insert, update, delete
			$this->numberRecords = mysqli_affected_rows($this->dbHandle);
		} elseif(!is_bool($this->result)) { // for selects
			$this->numberRecords = mysqli_num_rows($this->result);
		} else { // will be boolean for create, drop, and other
			$this->numberRecords = 0;
		}
	}
	protected function _fetch() {
		return $this->_fetchAll();
	}
	protected function _fetchAll() {
		$data = array();
		for($i = 0; $i < $this->numberRecords; $i++) {
			$data[] = mysqli_fetch_assoc($this->result);
		}
		mysqli_free_result($this->result);
		return $data;
	}
	protected function _fetchRow() {
		return mysqli_fetch_assoc($this->result);
	}
	protected function _lastID() {
		return mysqli_insert_id($this->dbHandle);
	}
	protected function _schema($table) {
		$this->execute('describe ' . $table);
		return $this->_fetchAll();
	}
	public function beginTransaction() {
		mysqli_autocommit($this->dbHandle, false);
	}
	public function commitTransaction() {
		mysqli_commit($this->dbHandle);
		mysqli_autocommit($this->dbHandle, true);
	}
	public function rollbackTransaction() {
		mysqli_rollback($this->dbHandle);
		mysqli_autocommit($this->dbHandle, true);
	}
	public function close() {
		mysqli_close($this->dbHandle);
	}
} // mysqli

class dbFacile_postgresql extends dbFacile {
	function __construct($handle = null) {
		parent::__construct();
		$this->schemaNameField = 'column_name';
		$this->schemaTypeField = 'data_type';
		if($handle != null)
			$this->dbHandle = $handle;
	}
	// user, password, database, host
	protected function _open($args) {
		if(!$args[3])
			$args[3] = 'localhost';
		//die("host=$host dbname=$database user=$user");
		list($user, $password, $database, $host) = $args;
		$this->dbHandle = pg_connect("host=$host dbname=$database port=5432 user=$user password=$password");
		return $this->result;
	}
	protected function _query($query) {
		$this->result = pg_query($this->dbHandle, $this->query);
	}
	protected function _escapeString($string) {
		return pg_escape_string($string);
	}
	protected function _error() {
		return pg_last_error($this->dbHandle);
	}
	protected function _numberRecords() {
		if(pg_affected_rows($this->result)) {
			$this->numberRecords = pg_affected_rows($this->result);
		} else {
			$this->numberRecords = pg_num_rows($this->result);
		}
	}
	protected function _fetch() {
	}
	protected function _fetchAll() {
		$data = array();
		for($i = 0; $i < $this->numberRecords; $i++) {
			$data[] = pg_fetch_assoc($this->result);
		}
		pg_free_result($this->result);
		return $data;
	}
	protected function _fetchRow() {
		return pg_fetch_assoc($this->result);
	}
	protected function _lastID($table) {
		$sequence = $this->fetchCell("SELECT relname FROM pg_class WHERE relkind = 'S' AND relname LIKE '" . $table . "_%'");
		if(strlen($sequence))
			return $this->fetchCell('select last_value from ' . $sequence);
		return 0;
	}
	protected function _schema($table) {
		$this->execute('select column_name,split_part(data_type, \' \', 1) as data_type from information_schema.columns where table_name = \'' . $table . '\' order by ordinal_position');
		return $this->_fetchAll();
	}
	public function beginTransaction() {
		pg_query($this->dbHandle, 'begin');
	}
	public function commitTransaction() {
		pg_query($this->dbHandle, 'commit');
	}
	public function rollbackTransaction() {
		pg_query($this->dbHandle, 'rollback');
	}
	public function close() {
		pg_close($this->dbHandle);
	}
} // postgresql

class dbFacile_sqlite extends dbFacile {
	// $stringTypes = array('text', 'char', 'varchar', 'date', 'time', 'datetime');
	function __construct($handle = null) {
		parent::__construct();
		$this->schemaNameField = 'name';
		$this->schemaTypeField = 'type';
		if($handle != null)
			$this->dbHandle = $handle;
	}
	protected function _open($args) {
		$this->dbHandle = sqlite_open($args[0]);
	}
	protected function _query($sql) {
		$this->result =& sqlite_query($this->dbHandle, $sql);
	}
	protected function _escapeString($string) {
		return sqlite_escape_string($string);
	}
	protected function _error() {
		return sqlite_error_string(sqlite_last_error($this->dbHandle));
	}
	protected function _numberRecords() {
		if(sqlite_changes($this->dbHandle)) {
			$this->numberRecords = sqlite_changes($this->dbHandle);
		} else {
			$this->numberRecords = sqlite_num_rows($this->result);
		}
	}
	protected function _fetch() {
		//$rows =& $this->_fetchAll();
		//return $rows;
		return new dbFacile_sqlite_result($this->result);
	}
	protected function &_fetchAll() {
		/*
		$data = array();
		for($i = 0; $i < $this->numberRecords; $i++) {
			$data[] = sqlite_fetch_array($this->result, SQLITE_ASSOC);
		}
		* */
		$rows =& sqlite_fetch_all($this->result, SQLITE_ASSOC);
		return $rows;
	}
	protected function &_fetchRow() {
		$row =& sqlite_fetch_array($this->result, SQLITE_ASSOC);
		return $row;
	}
	protected function &_lastID() {
		return sqlite_last_insert_rowid($this->dbHandle);
	}
	protected function _schema($table) {
		$this->execute('pragma table_info(' . $table. ')');
		return $this->_fetchAll();
	}
	protected function _tables() {
		return $this->fetchColumn('select name from sqlite_master where type=\'table\' order by name');
	}
	public function beginTransaction() {
		sqlite_query($this->dbHandle, 'begin transaction');
	}
	public function commitTransaction() {
		sqlite_query($this->dbHandle, 'commit transaction');
	}
	public function rollbackTransaction() {
		sqlite_query($this->dbHandle, 'rollback transaction');
	}
	public function close() {
		sqlite_close($this->dbHandle);
	}
} // sqlite

class dbFacile_sqlite_result implements Iterator {
	private $result;
	public function __construct($r) {
		$this->result = $r;
	}
	public function rewind() {
		sqlite_rewind($this->result);
	}
	public function current() {
		$a = sqlite_current($this->result, SQLITE_ASSOC);
		return $a;
	}
	public function key() {
		$a = sqlite_key($this->result);
		return $a;
	}
	public function next() {
		$a = sqlite_next($this->result);
		return $a;
	}
	public function valid() {
		$a = $this->current() !== false;
		return $a;
	}
}

/*
// PDO-based databases
// INCOMPLETE
class dbFacile_pdo_mssql extends dbFacile_pdo {
	protected function _open($database, $user, $pass, $host) {
		$this->dbHandle = new PDO("mssql:host=$host;dbname=$database", $user, $pass);
	}
}
class dbFacile_pdo_mysql extends dbFacile_pdo {
	protected function _open($database) {
		$this->dbHandle = new PDO("mysql:host=$host;dbname=$database", $user, $pass);
	}
}
class dbFacile_pdo_postgresql extends dbFacile_pdo {
	protected function _open($database) {
		$this->dbHandle = new PDO("pgsql:host=$host;dbname=$database;user=$user;password=$pass");
	}
}
class dbFacile_pdo_sqlite extends dbFacile_pdo {
	protected function _open($database) {
		$this->dbHandle = new PDO("sqlite:$database");
	}
}
class dbFacile_pdo_sqlite2 extends dbFacile_pdo {
	protected function _open($database) {
		$this->dbHandle = new PDO("sqlite2:$database");
	}
}

abstract class dbFacile_pdo extends dbFacile {
	function __construct($handle = null) {
		parent::__construct();
		$this->schemaNameField = 'name';
		$this->schemaTypeField = 'type';
		if($handle != null)
			$this->dbHandle = $handle;
	}
	protected function _open($type, $database, $user, $pass, $host) {
		$this->dbHandle = new PDO("$type:host=$host;dbname=$database", $user, $pass);
	}
	protected function _query($sql) {
		$this->result = $this->dbHandle->query($sql);
	}
	protected function _escapeString($string) {
		return sqlite_escape_string($string);
	}
	protected function _error() {
		return print_r($this->dbHandle->errorInfo(), true);
	}
	protected function _numberRecords() {
		$this->numberRecords = $this->result->rowCount();
	}
	protected function _fetch() {
		return $this->result;
	}
	protected function _fetchAll() {
		return $this->result->fetchAll(PDO::FETCH_ASSOC);
	}
	protected function _fetchRow() {
		return $this->result;
	}
	protected function _lastID() {
		return $this->dbHandle->lastInsertId();
	}
	protected function _schema($table) {
		// getAttribute(PDO::DRIVER_NAME) to determine the sql to call
		$this->execute('pragma table_info(' . $table. ')');
		return $this->_fetchAll();
	}
	protected function _begin() {
		$this->dbHandle->beginTransaction();
	}
	protected function _commit() {
		$this->dbHandle->commit();
	}
	protected function _rollback() {
		$this->dbHandle->rollBack();
	}
	public function close() {
		$this->dbHandle = null;
	}
} // pdo
*/

?>

<?php

/**
 * Mediasoft Database Class.
 *
 * Purpose is to wrap database-specific logic to keep it central in once place.
 * By default, uses MySQLi.
 *
 */
class MsDb
{
    protected $connection;
	
	/**
	 * Constructor.
	 * 
	 * @param string $host
	 * @param string $user
	 * @param string $password
	 * @param string $database
	 *
	 * @return void
	 */
	function __construct($host, $user, $password, $database) {
		if (class_exists('mysqli')) {
			// Default: Use MySQLi
			$this->connection = new mysqli($host, $user, $password, $database);
			if ($this->connection->connect_errno) $this->connection = false; //error_log($mysqli->connect_errno, 3, "path/to/error/file");
		} else {
			// Last resort: Use MySQL
		   	if ($this->connection = mysql_connect($host, $user, $password)) {
				mysql_select_db($database);
			} else {
				$this->connection = false;
				//error_log($mysqli->connect_errno, 3, "path/to/error/file");
			}
		}
	}
	
	
	/**
	 * Query.
	 * 
	 * @param string $query SQL query string.
	 *
	 * @return MsDbResult
	 */
	public function query($query) {
		$returnArray = array();
		
		if (!$this->connection) return $returnArray;
		$query = trim($query);
		if ($query == '') return $returnArray;
			/*	
				$trace = debug_backtrace();
				trigger_error(
					'query: ' . $query,
					E_USER_NOTICE);
			*/
		if (class_exists('mysqli')) {
			// Default: Use MySQLi
			return new MsDbResult($this->connection->query($query));
		} else {
			// Last resort: Use MySQL
			return new MsDbResult(mysql_query($query));
		}
	}
	
	/**
	 * Insert.
	 * 
	 * @param string $table ame of table to insert into.
	 * @param mixed[] $values_in Key->Val array of column names->values.
	 * @param bool $allowOverwrite (Optional) True to use "UPDATE INTO" instead of "REPLACE INTO" to overwrite any existing row. Default is false.
	 *
	 * @return string If any autoincrement ID is generated by mysql, this is returned (otherwise returns 0)
	 */
	public function insert($table, $values_in, $allowOverwrite = false) {
		// sanity check
		if (!is_array($values_in)) return false;
		if (sizeof($values_in) < 1) return false;
		
		// generate the SQL
		$columns = '';
		$values = '';
		
		foreach ($values_in as $column => $val) {
			if ($columns != '') $columns .= ',';
			if ($values != '') $values .= ',';
			$columns .= $column;
			$values .= '\'' . $this->escape($val) . '\'';
		}
		
		if ($allowOverwrite) $sql = 'REPLACE INTO ';
			else $sql = 'INSERT INTO ';
		$sql .= $table . ' (' . $columns . ') VALUES (' . $values . ')';

		// execute
		if (class_exists('mysqli')) {
			// Default: Use MySQLi
			$this->connection->query($sql);
			return $this->connection->insert_id;
		} else {
			// Last resort: Use MySQL
			mysql_query($sql);
			return mysql_insert_id();
		}
	}
	
	
	
	/**
	 * Is Connected.
	 *
	 * @return bool True if this instance has a successful db connection associated with it, otherwise false
	 */
	public function isConnected() {
		if (!$this->connection) return false;
			else return true;
	}
	
	
	/**
	 * Close.
	 *
	 * Close the database connection associated with this instance.
	 *
	 * @return void
	 */
	public function close() {
		if (!$this->connection) return;
		
		if (class_exists('mysqli')) {
			// Default: Use MySQLi
			$this->connection->close();
		} else {
			// Last resort: Use MySQL
			mysql_close($this->connection);
		}
	}
	
	
	// CLASS METHODS
	
	/**
	 * Format Select Query.
	 *
	 * Generates a MySQL SELECT query.
	 *
	 * @param mixed[] | string $columns String or array of column names to return i.e.: 'id,first,last' or: array('id', 'first', 'last'), or: '*'.
	 * @param mixed[] | string $table_in Table name.
	 * @param mixed[] $where_in (Optional) Associative array of where criteria i.e.: array('id' => array('value' => '123', 'operator' => '=')) or: array('name' => array('value' => '%john%', 'operator' => 'like', 'conj' => 'OR')).
	 * @param mixed[] $orderby_in (Optional) one or more columns to sort on. i.e.: array('last', 'first') or: array('id').
	 * @param mixed[] $innerjoin_in
	 * @param mixed[] $leftjoin_in
	 * @parameter int|string $limit (Optional) limit the returned rows by a number or span (i.e. 5 or 2,5). In a span, the first number is the starting row, the second is the numbver of rows to return.
	 *
	 * @return string|bool Returns the formatted query or false if there is a problem with the passed parameters.
	 *
	 */
	public function formatSelectQuery($columns, $table_in, $where_in = NULL, $orderby_in = NULL, $innerjoin_in = NULL, $leftjoin_in = NULL, $limit=NULL) {
		if ((!$columns) || (!$table_in)) return false;
		
		// table
		if (is_array($table_in)) {
			foreach ($table_in as $tableLabel => $tableName) {
				if ($table != '') $table .= ',';
				$table .= '`' . $tableName . '` AS ' . $tableLabel;
			}
		} else {
			$table = '`' . $table_in . '`';
		}
		
		// columns
		if (is_array($columns)) $columns = implode(',', $columns);
			
		$where = '';
		if (($where_in) && (is_array($where_in))) {
			foreach ($where_in as $this_column => $where_details) {
				if (array_key_exists('conj', $where_details)) $conj = $where_details['conj'];
					else $conj = 'AND';
				if ($where != '') $where .= ' ' . $conj . ' ';
					else $where = ' WHERE ';
				if ($where_details['value'] !== NULL) $where .= trim($this_column) . ' ' . trim($where_details['operator']) . ' \'' . $this->escape($where_details['value']) . '\'';
					else $where .= trim($this_column) . ' ' . trim($where_details['operator']) . ' ' . trim($where_details['column']);
			}
		}
		
		$orderby = '';
		if (($orderby_in) && (is_array($orderby_in))) $orderby = ' ORDER BY ' . implode(',', $orderby_in);
		
		$innerjoin = '';
		if (($innerjoin_in) && (is_array($innerjoin_in))) $innerjoin = ' INNER JOIN ' . implode(' INNER JOIN ', $innerjoin_in);
		
		$leftjoin = '';
		if (($leftjoin_in) && (is_array($leftjoin_in))) $leftjoin = ' LEFT JOIN ' . implode(' LEFT JOIN ', $leftjoin_in);
		
		if ($limit != NULL) $limit = ' LIMIT ' . $limit;
			else $limit = '';
			
		return trim('SELECT ' . $columns . ' FROM ' . $table . $innerjoin . $leftjoin . $where . $orderby . $limit);
	}
	
	
	/**
	 * Format Delete Query.
	 * 
	 * @param string $table Name of table from which to delete.
	 * @param mixed[] $where_in Associative array of where criteria i.e.: array('id' => array('value' => '123', 'operator' => '=')) or: array('name' => array('value' => '%john%', 'operator' => 'like', 'conj' => 'OR')).
	 * @param int $limit_in (Optional). Limit the number of entries to delete. Default is 1. A value of 0 removes any limit imposition.
	 *
	 * @return void
	 */
	public function formatDeleteQuery($table_in, $where_in, $limit_in=1) {
		// table
		if (is_array($table_in)) {
			foreach ($table_in as $tableLabel => $tableName) {
				if ($table != '') $table .= ',';
				$table .= '`' . $tableName . '` AS ' . $tableLabel;
			}
		} else {
			$table = '`' . $table_in . '`';
		}
		
		// where clause
		$where = '';
		if (($where_in) && (is_array($where_in))) {
			foreach ($where_in as $this_column => $where_details) {
				if (array_key_exists('conj', $where_details)) $conj = $where_details['conj'];
					else $conj = 'AND';
				if ($where != '') $where .= ' ' . $conj . ' ';
					else $where = ' WHERE ';
				if ($where_details['value'] !== NULL) $where .= trim($this_column) . ' ' . trim($where_details['operator']) . ' \'' . $this->escape($where_details['value']) . '\'';
					else $where .= trim($this_column) . ' ' . trim($where_details['operator']) . ' ' . trim($where_details['column']);
			}
		}
		
		// limit clause
		if ($limit_in > 0) $limit = ' LIMIT ' . $limit_in;
			else $limit = '';
		
		// format query
		return trim('DELETE FROM ' . $table . $where . $limit);
	}
	

	
	/**
	 * Format Update Query.
	 *
	 * Generates a MySQL UPDATE query.
	 *
	 * @param string $table Table name.
	 * @param mixed[] $where_in (Optional) Associative array of where criteria i.e.: array('id' => array('value' => '123', 'operator' => '=')) or: array('name' => array('value' => '%john%', 'operator' => 'like', 'conj' => 'OR')).
	 * @param mixed[] $values Associative array where keys are column names and values are the values to set those column(s) to.
	 *
	 * @return string|bool Returns the formatted query or false if there is a problem with the passed parameters.
	 *
	 */
	public function formatUpdateQuery($table, $where_in = NULL, $values) {
		if ($table == '') return false;
		if ((!is_array($values)) || (sizeof($values) < 1)) return false;
		
		$where = '';
		if (($where_in) && (is_array($where_in))) {
			foreach ($where_in as $this_column => $where_details) {
				if (array_key_exists('conj', $where_details)) $conj = $where_details['conj'];
					else $conj = 'AND';
				if ($where != '') $where .= ' ' . $conj . ' ';
					else $where = ' WHERE ';
				if ($where_details['value'] !== NULL) $where .= trim($this_column) . ' ' . trim($where_details['operator']) . ' \'' . $this->escape($where_details['value']) . '\'';
					else $where .= trim($this_column) . ' ' . trim($where_details['operator']) . ' ' . trim($where_details['column']);
			}
		}
		
		$valueString = '';
		foreach ($values as $column => $val) {
			if ($valueString != '') $valueString .= ',';
			$valueString .= $column . '=' . "'" . $this->escape($val) . "'";
		}
		
		return trim('UPDATE ' . $table . ' SET ' . $valueString . ' ' . $where);
	}
	
	
	/**
	 * Escape String.
	 *
	 * Handles escaping a string from user input before inserting into a database.
	 * 
	 * @param string $string The user input.
	 *
	 * @return string
	 */
	protected function escape($string) {
		$string = trim(stripslashes($string));	
		
		if (class_exists('mysqli')) {
			// Default: Use MySQLi
			return $this->connection->real_escape_string($string);
		} else {
			// Last resort: Use MySQL
			return mysql_real_escape_string($string);
		}
		
	}
}




/**
 * Mediasoft Database Result Class.
 *
 * Purpose is to wrap logic for query results to keep it central in once place.
 * By default, uses MySQL.
 *
 */
class MsDbResult implements Iterator
{
	private $resultSet; // array of rows as associative arrays
	
	/**
	 * Constructor.
	 * 
	 * @param mysqli_ result|mysql_result $result_in
	 */
	function __construct($result_in) {
		$this->resultSet = array();
		if (class_exists('mysqli')) {
			// Default: Use MySQLi
			if (is_object($result_in)) {
				while ($row = $result_in->fetch_assoc()) $this->resultSet[] = $row;
				$result_in->free(); // free memory associated with this result
			}
		} else {
			// Last resort: Use MySQL
			if ($result_in) {
				while ($row = mysql_fetch_assoc($result_in)) $this->resultSet[] = $row;
				mysql_free_result($result_in); // free memory associated with this result
			}
		}
	}
	
	
	/**
	 * Is Valid.
	 *
	 * @return bool True if this is a valid (successful) result, otherwise false.
	 */
	public function isValid() {
		return is_array($this->resultSet);
	}
	
	
	/**
	 * Fetch Associative Array.
	 *
	 * @return mixed[] The current row in the resultset as an associative array of key-value pairs.
	 */
	function fetch_assoc() {
		if ($this->isValid()) return current($this->resultSet);
			else return false;
	}
	
	
	/**
	 * Fetch Associative Array By Column.
	 *
	 * Returns the current row in the resultset as an associative array of key-value pairs. The keys are values in the specified column for each row.
	 *
	 * @param string $columnName The colummn whose values will be keys in the returned array.
	 * @return mixed[]
	 *
	 */
	public function fetch_assoc_by_column($columnName) {
		$returnArray = array();
		foreach ($this as $this_row) {	
			$columnVal = $this_row[$columnName];
			$returnArray[$columnVal] = $this_row;
		}
		
		return $returnArray;
	}
	
	
	/**
	 * Iterator Methods.
	 * 
	 * Allow collections to be iterated directly.
	 */
	function rewind() {
		if ($this->isValid()) return reset($this->resultSet);
	}
	function current() {
		if ($this->isValid()) return current($this->resultSet);
			else return NULL;
	}
	function key() {
		if ($this->isValid()) return key($this->resultSet);
			else return NULL;
	}
	function next() {
		if ($this->isValid()) return next($this->resultSet);
	}
	function valid() {
		if ($this->isValid()) return key($this->resultSet) !== null;
			else return false;
	}
	
	
	
	
}
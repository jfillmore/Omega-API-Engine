<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Generalized database abstraction class. */
class OmegaDatabase {
	public $hostname;
	public $username;
	public $password;
	public $dbname;
	public $type; // 'psql', 'mysql', 'mysqli'
	private $conn;
	private $tr_depth; // transaction depth marker
	private $tr_rolling_back; // whether or not the transaction has started rolling back

	public function __construct($hostname, $username, $password, $dbname, $type) {
		$this->hostname = $hostname;
		$this->username = $username;
		$this->password = $password;
		$this->dbname = $dbname;
		$this->type = $type;
		$this->tr_depth = 0;
		$this->tr_rolling_back = false;
		try {
			$this->connect();
		} catch (Exception $e) {
			throw $e; // pass the buck
		}
	}
	
	public function __sleep() {
		if ($this->tr_depth) {
			$this->tr_ditch();
		}
		return array('hostname', 'username', 'password', 'dbname', 'type');
	}

	public function __wakeup() {
		$this->tr_depth = 0;
		$this->tr_rolling_back = false;
	}

	public function __destruct() {
		// don't leave hanging transactions
		if ($this->tr_depth) {
			$this->tr_ditch();
		}
	}

	private function connect() {
		if ($this->conn !== false) { // if we already have a connection then don't bother trying again...
			if ($this->type == 'psql') {
				if ($this->password == "") {
					$this->conn = pg_pconnect("dbname=$this->dbname user=$this->username host=$this->hostname");
				} else {
					$this->conn = pg_pconnect("dbname=$this->dbname user=$this->username password=$this->password host=$this->hostname");
				}
				if ($this->conn === false) {
					throw new Exception("Couldn't connect to database at '" . $this->hostname . "': " . pg_result_error() . ".");
				}
			} else if ($this->type == 'mysqli') {
				$this->conn = new mysqli($this->hostname, $this->username, $this->password); 
				if ($this->conn == false){
					throw new Exception("Couldn't connect to database at '" . $this->hostname . "': " . mysql_error() . ".");
				}
				if (! $this->conn->select_db($this->dbname)) {
					throw new Exception("Couldn't select database '" . $this->dbname . "': " . mysql_error($this->conn) . ".");
				}
			} else if ($this->type == 'mysql') {
				$this->conn = mysql_pconnect($this->hostname, $this->username, $this->password); 
				if ($this->conn == false){
					throw new Exception("Couldn't connect to database at '" . $this->hostname . "': " . mysql_error() . ".");
				}
				if (! mysql_select_db($this->dbname)) {
					throw new Exception("Couldn't select database '" . $this->dbname . "': " . mysql_error($this->conn) . ".");
				}
			} else {
				throw new Exception("Invalid database type: '$this->type'.");
			}
		}
		return true;
	}
	
	private function disconnect() {
		if ($this->type == 'psql') {
			IF (! pg_close($this->conn)) {
				throw new Exception("Couldn't disconnect from database.");
			}
		} elseif ($this->type == 'mysqli') {
			if (! $this->conn->close()) {
				throw new Exception("Couldn't disconnect from database.");
			}
		} elseif ($this->type == 'mysql') {
			if (! mysql_close($this->conn)) {
				throw new Exception("Couldn't disconnect from database.");
			}
		} else {
			throw new Exception("Invalid database type: '$this->type'.");
		}
		return true;
	}

	/** Returns a database engine specific sanitized version of the string
		expects: string=string
		returns: string */
	public function escape($string, $strip_slashes = false) {
		$string = (string)$string;
		if ($strip_slashes) {
			stripslashes($string);
		}
		if ($this->type == 'psql') {
			$string = pg_escape_string($string);
		} else if ($this->type == 'mysqli') {
			$string = $this->conn->real_escape_string($string); 
		} else if ($this->type == 'mysql') {
			$string = mysql_real_escape_string($string); 
		} else {
			throw new Exception("Invalid database type: '$this->type'.");
		}
		return $string;
	}

	/** Starts a transaction the first time it is called. Increases the transaction depth each time it is called. Returns whether or not a transaction was started.
		returns: boolean */
	public function tr_begin() {
		if ($this->tr_rolling_back) {
			throw new Exception("Unable to start or depthen a transaction while rolling back.");
		}
		if ($this->tr_depth) {
			$this->tr_depth += 1;
			return false;
		}
		$this->query('BEGIN');
		$this->tr_depth = 1;
		$this->tr_rolling_back = false;
		return true;
	}

	/** Commits a transaction when the depth has reaches zero, where each call decreases the depth by 1. Cannot commit a transaction once it has started to rollback. Returns whether or not a transaction was commited.
		returns: boolean */
	public function tr_commit() {
		if ($this->tr_rolling_back) {
			throw new Exception("Unable to commit transaction once a rollback has started.");
		} else if ($this->tr_depth < 1) {
			throw new Exception("Unable to commit transaction; no transaction has been started.");
		} else if ($this->tr_depth > 1) {
			$this->tr_depth -= 1;
			return false;
		} else {
			$this->query('COMMIT');
			$this->tr_depth = 0;
			return true;
		}
	}

	/** Rolls back a transaction when the depth has reached zero, where each call decreases the depth by 1. Does nothing for the remainder of the request. Returns whether or not a transaction was rolled back.
		returns: boolean */
	public function tr_rollback() {
		if ($this->tr_depth < 1) {
			throw new Exception("Unable to rollback transaction; no transaction has been started.");
		} else if ($this->tr_depth > 1) {
			$this->tr_depth -= 1;
			$this->tr_rolling_back = true;
			return false;
		} else {
			$this->query('ROLLBACK');
			$this->tr_depth = 0;
			$this->tr_rolling_back = false;
			return true;
		}
	}

	/** Rolls back back a transaction, regardless of depth. */
	public function tr_ditch() {
		if ($this->tr_depth < 1) {
			throw new Exception("Unable to rollback transaction; no transaction has been started.");
		} else {
			$this->query('ROLLBACK');
			$this->tr_depth = 0;
			$this->tr_rolling_back = false;
		}
	}

	/** Execute an SQL query and return the result through a specific parser. Available parsers are 'array' and 'raw'. If 'key_col' is set, the 'array' parser will use the value of $key_col for each row's array index, returning an associative array.
		expects: query=string, parser=string */
	public function query($query, $parser = 'array', $key_col = null) {
		// assumes that input has already been sanitize
		if ($this->type == 'psql') {
			$db_result = @pg_query($this->conn, $query);
			if ($db_result === false) {
				throw new Exception(pg_last_error($this->conn));
			}
			if ($parser == 'array') {
				$result = array();
				while($row = pg_fetch_array($db_result, null, PGSQL_ASSOC)) {
					if ($key_col != null && isset($row[$key_col])) {
						$result[$row[$key_col]] = $row;
					} else {
						$result[] = $row;
					}
				}
			} else if ($parser == 'raw') {
				$result = $db_result;
			} else {
				throw new Exception("Invalid query parser: '$parser'.");
			}
		} else if ($this->type  == 'mysqli') {
			$db_result = @$this->conn->query($query);
			if ($db_result === false) {
				throw new Exception('Error ' . $this->conn->errno . ' - ' . $this->conn->error);
			} else if ($db_result === true) {
				return true;
			} else {
				if ($parser == 'array') {
					$result = array();
					$meta = $db_result->fetch_fields();
					while ($row = $db_result->fetch_object()) {
						$fields = array();
						$i = 0;
						foreach ($row as $name => $field) {
							$fields[$name] = $this->typecast($field, $meta[$i++]->type);
						}
						if ($key_col !== null && isset($fields[$key_col])) {
							$result[$fields[$key_col]] = $fields;
						} else {
							$result[] = $fields;
						}
					}
				} else if ($parser == 'raw') {
					$result = $db_result;
				} else {
					throw new Exception("Invalid query parser: '$parser'.");
				}
			}
		} else if ($this->type  == 'mysql') {
			$db_result = @mysql_query($query, $this->conn);
			if ($db_result === false) {
				throw new Exception(mysql_error($this->conn));
			} else if ($db_result === true) {
				return true;
			} else {
				if ($parser == 'array') {
					$result = array();
					while($row = mysql_fetch_assoc($db_result)) {
						if ($key_col != null && isset($row[$key_col])) {
							$result[$row[$key_col]] = $row;
						} else {
							$result[] = $row;
						}
					}
				} else if ($parser == 'raw') {
					$result = $db_result;
				} else {
					throw new Exception("Invalid query parser: '$parser'.");
				}
			}
		} else {
			throw new Exception("Invalid database type: '$this->type'.");
		}
		return $result;
	}

	private function typecast($value, $type) {
		$types = array(
			0 => "DECIMAL",
			1 => "TINYINT",
			2 => "SMALLINT",
			3 => "INTEGER",
			4 => "FLOAT",
			5 => "DOUBLE",
			7 => "TIMESTAMP",
			8 => "BIGINT",
			9 => "MEDIUMINT",
			10 => "DATE",
			11 => "TIME",
			12 => "DATETIME",
			13 => "YEAR",
			14 => "DATE",
			16 => "BIT",
			246 => "DECIMAL",
			247 => "ENUM",
			248 => "SET",
			249 => "TINYBLOB",
			250 => "MEDIUMBLOB",
			251 => "LONGBLOB",
			252 => "BLOB",
			253 => "VARCHAR",
			254 => "CHAR",
			255 => "GEOMETRY",
		);
		$type = $types[$type];
		if (in_array($type, array('DATE', 'TIME', 'DATETIME', 'VHARCHAR', 'CHAR', 'TIMESTAMP'))) {
			return $value;
		} else if (in_array($type, array('DECIMAL', 'FLOAT', 'DOUBLE'))) {
			return (double)$value;
		} else if (in_array($type, array('BIT'))) {
			return (boolean)$value;
		} else if (in_array($type, array('TINYINT', 'SMALLINT', 'INTEGER'))) {
			return (int)$value;
		}  else {
			return $value;
		}
	}

	/** Returns the ID assigned in the last INSERT query.
		returns: string */
	public function get_last_id() {
		if ($this->type == 'mysqli') {
			return $this->conn->insert_id;
		} else if ($this->type == 'mysql') {
			return mysql_insert_id();
		} else {
			throw new Exception("The database method 'get_last_id' is not support for the database type '" . $this->type . "'.");
		}
	}
}


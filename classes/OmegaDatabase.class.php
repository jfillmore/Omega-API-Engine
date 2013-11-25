<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Generalized database abstraction class with special search methods. */
class OmegaDatabase {
    public $hostname;
    public $username;
    public $password;
    public $dbname;
    public $type; // 'psql', 'mysql', 'mysqli'
    public $error_log = null; // log file to track SQL errors
    protected $conn;
    protected $tr_depth; // transaction depth marker
    protected $tr_rolling_back; // whether or not the transaction has started rolling back
    protected $cypher;

    public function __construct($hostname, $username, $password, $dbname, $type, $error_log = null, $enc_key = null) {
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->dbname = $dbname;
        $this->type = $type;
        $this->tr_depth = 0;
        $this->tr_rolling_back = false;
        $this->set_error_log($error_log);
        if ($enc_key) {
            $this->cypher = new OmegaCypher($enc_key);
        }
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
        return array('hostname', 'username', 'password', 'dbname', 'type', 'error_log');
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

    public function set_error_log($log_file) {
        if ($log_file) {
            $this->error_log = $log_file;
        } else {
            $this->error_log = null;
        }
    }

    protected function connect() {
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
                $this->conn = @new mysqli($this->hostname, $this->username, $this->password); 
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
    
    protected function disconnect() {
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
        expects: string=string, strip_slashes=boolean, quote=boolean
        returns: string */
    public function escape($string, $strip_slashes = false, $quote = false) {
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
        if ($quote) {
            $string = "'$string'";
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

    /** Returns statistics about the last executed query. */
    public function query_stats() {
        if ($this->type == 'mysqli') {
            return array(
                'query_info' => $this->conn->info,
                'rows_affected' => $this->conn->affected_rows
            );
        } else if ($this->type == 'mysql') {
            return array(
                'query_info' => mysqli_info($this->conn),
                'rows_affected' => mysqli_affected_rows($this->conn)
            );
        } else {
            throw new Exception("Unsupported database type: {$this->type}.");
        }
    }

    protected function log_query($query, $data = array(), $error = null) {
        if (! $this->error_log) {
            return;
        }
        $message = '[' . date('r') . ']';
        if ($error) {
            $message .= ' ' . $error->getMessage();
        }
        $message .= "\n  query: $query";
        foreach ($data as $name => $val) {
            $message .= "\n  $name: " . json_encode($val);
        }
        $i = 0;
        if ($error) {
            foreach ($error->getTrace() as $trace) {
                $line = "\n  #$i. ";
                if (isset($trace['file'])) {
                    $line .= $trace['file'] . ' ';
                }
                if (isset($trace['line'])) {
                    $line .= $trace['line'] . ' ';
                }
                if (isset($trace['class'])) {
                    $line .= ' ' . $trace['class'] . $trace['type'];
                }
                // don't return the actual args for security/brevity
                $line .= $trace['function'] . '(' . count($trace['args']) . ' ' . (count($trace['args']) === 1 ? 'arg' : 'args') . ')';
                $message .= $line;
                $i++;
            }
        }
        @file_put_contents($this->error_log, $message . "\n", FILE_APPEND);
        return $message;
    }
    
    /** Execute an SQL query and return the result through a specific parser. Available parsers are 'array' and 'raw'. If 'key_col' is set, the 'array' parser will use the value of $key_col for each row's array index, returning an associative array.
        expects: query=string, parser=string, key_col=string, args=array, max_tries=number
        returns: object */
    public function query($query, $parser = 'array', $key_col = null, $args = array(), $max_tries = 2, $retry_delay = 1) {
        $tries = 0;
        $errors = array();
        while ($tries < $max_tries) {
            $tries++;
            try {
                return $this->_query($query, $parser, $key_col, $args);
            } catch (Exception $e) {
                $errors[] = $e;
                sleep($retry_delay);
            }
        }
        $error = array_shift($errors);
        // didn't work? that's not good! log and throw the first error
        $this->log_query($query, array(
            'key_col' => $key_col,
            'args' => $args,
            'max_tries' => $max_tries,
            'retry_delay' => $retry_delay
        ), $errors[0]);
        throw $error;
    }

    /** Execute an SQL query and return the result through a specific parser. Available parsers are 'array' and 'raw'. If 'key_col' is set, the 'array' parser will use the value of $key_col for each row's array index, returning an associative array. Queries that fail will be automatically retried a set number of times, delaying a one second between attempts.
        expects: query=string, parser=string, key_col=string, arg=array
        returns: object */
    protected function _query($query, $parser = 'array', $key_col = null, $args = array()) {
        // 'MYSQLi' only args!
        $args = OmegaLib::get_args(array(
            'split_joins' => array(), // any of these joined tables will have data split off -- if 'true' all tables will
            'keep_renames' => false, // if split_joins is on then we'll use the original column name, not the renamed version allowing conflicts to resolve easier
            'trim' => array(), // trim out and skip these values
            'decode' => array(), // decode values via $this->cypher
            // TODO? 'dedupe_id' => null,
            // TODO? 'multi_joins' => array() // list of tables to expect multiple JOIN results from, requires 'dedupe_id' to be set
        ), $args);
        if (! (is_array($args['split_joins']) || $args['split_joins'] === true)) {
            throw new Exception("Invalid value for 'split_joins' param; array or 'true' expected.");
        }
        if (! is_array($args['trim'])) {
            throw new Exception("Array expected for 'trim' param; '{$args['trim']}' provided.");
        }
        //if ($args['multi_joins'] & ! $args['dedupe_id']) {
        //    throw new Exception("Unable to perform deduplication with multi-joins without specified the row ID to use.");
        //}
        if ($this->type == 'psql') {
            $db_result = @pg_query($this->conn, $query);
            if ($db_result === false) {
                throw new Exception(pg_last_error($this->conn));
            }
            if ($parser == 'array') {
                $result = array();
                while ($row = pg_fetch_array($db_result, null, PGSQL_ASSOC)) {
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
                    // get a list of join'd tables
                    preg_match_all('/JOIN\s+`?(\w+)`?\s*/i', $query, $joins);
                    if (count($joins) == 2) {
                        // the second row has the goodies!
                        $joins = $joins[1];
                        $joins = array_map('strtolower', $joins);
                    } else {
                        $joins = array();
                    }
                    $result = array();
                    $row_meta = $db_result->fetch_fields();
                    // if doing multi_joins we need to merge duplicate rows
                    $row_bank = array(); // pkey => row
                    while ($row = $db_result->fetch_object()) {
                        $fields = array();
                        $i = 0;
                        /* // TODO: finish this
                        // is this a duplicate row? if so, we only need to merge some shit with the original
                        $dupe = false;
                        if ($args['multi_joins']) {
                            if (! isset($row[$args['dedupe_id']])) {
                                throw new Exception("Unable to perform deduplication; row does not contain a field with the name {$args['dedupe_id']}.");
                            }
                            $row_id = $row[$args['dedupe_id']];
                            if (isset($row_bank[$row_id])) {
                                $dupe = true;
                            }
                        }
                        */
                        // gotta track which meta we've used, as our query might request a row by the same name twice during joins (e.g. "id")
                        $seen_names = array();
                        // format, typecast, trim, decode, etc. return data
                        foreach ($row as $name => $field) {
                            $meta = $row_meta[$i++];
                            while (isset($seen_names[$meta->name])) {
                                // if we've already seen this name we can skip this row of meta info -- it means we've got some rows with the same name
                                $meta = $row_meta[$i++];
                            }
                            $seen_names[$name] = true;
                            // type cast each bit of data to it's proper format (e.g. so 1 => 1, not "1")
                            if ($name != $meta->name) {
                                throw new OmegaException("Invalid meta data for field '$name'.", array(
                                    'cur_meta' => $meta,
                                    'row_meta' => $row_meta,
                                    'i' => $i - 1,
                                    'row' => $row,
                                    'name' => $name,
                                    'field' => $field
                                ));
                            }
                            $value = $this->typecast($field, $meta->type);
                            // if this is in our skip list, pass on
                            foreach ($args['trim'] as $trim) {
                                if ($this->match_meta($meta, $trim)) {
                                    continue 2;
                                }
                            }
                            // do we need to decode the value?
                            foreach ($args['decode'] as $decode) {
                                if ($this->match_meta($meta, $decode) && $value !== null) {
                                    $value = $this->cypher->decode($value);
                                }
                            }
                            // check to see if we're a joined value, and branch as needed
                            if (($args['split_joins'] === true ||
                                in_array($meta->orgtable, $args['split_joins']))
                                && in_array($meta->orgtable, $joins)
                                ) {
                                if (! isset($fields[$meta->table])) {
                                    $fields[$meta->table] = array();
                                }
                                // name collusion forces annoying renames of fields
                                if ($args['keep_renames']) {
                                    $fields[$meta->table][$name] = $value;
                                } else {
                                    $fields[$meta->table][$meta->orgname] = $value;
                                }
                            } else {
                                $fields[$name] = $value;
                            }
                        }
                        if ($key_col !== null && isset($fields[$key_col])) {
                            // if we have a key column we need to order by that
                            $result[$fields[$key_col]] = $fields;
                        } else {
                            // otherwise order by natural order
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
                    while ($row = mysql_fetch_assoc($db_result)) {
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

    protected function match_meta($meta, $field) {
        $parts = explode('.', $field);
        // add the table in front if not given
        if (count($parts) == 1) {
            $parts = array(
                $meta->orgtable,
                $parts[0]
            );
        }
        $field = strtolower(join('.', $parts));
        $match = strtolower($meta->orgtable . '.' . $meta->orgname);
        return $field == $match;
    }

    private function walk_obj(&$obj, $path) {
        $parts = explode('/', trim($path, '/'));
        $last = array_pop($parts);
        $ptr = &$obj;
        foreach ($parts as $part) {
            if (@isset($ptr[$part])) {
                $ptr = &$ptr[$part];
            }
        }
        if (@isset($ptr[$last])) {
            return array(
                'ptr' => $ptr,
                'path' => $last
            ); 
        } else {
            return array();
        }
    }

    protected function typecast($value, $type) {
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
        if (in_array($type, array('DATE', 'TIME', 'DATETIME', 'VARCHAR', 'CHAR', 'TIMESTAMP'))) {
            return $value;
        } else if (in_array($type, array('DECIMAL', 'FLOAT', 'DOUBLE'))) {
            return $value === null ? $value : (double)$value;
        } else if (in_array($type, array('BIT'))) {
            return $value === null ? $value : (boolean)$value;
        } else if (in_array($type, array('TINYINT', 'SMALLINT', 'INTEGER'))) {
            return $value === null ? $value : (int)$value;
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

    /** Generate SQL for generic "flow" arguments.
    @param array $args SQL flow control options:
    $args = array(
        'count' => null, // limit number of results, e.g. 20
        'offset' => null, // offset within results, e.g. 10
        'order_by' => null, // field to order by, e.g. "hostname"
        'group_by' => null, // field to group results by e.g. "allocation"
        'reverse' => false // return results in reverse order
    );
    @return string SQL snippit */
    public function parse_flow($args) {
        $args = OmegaLib::get_args(array(
            'count' => null,
            'offset' => null,
            'order_by' => null,
            'group_by' => null,
            'reverse' => false
        ), $args);
        $sql = '';
        if ($args['group_by']) {
            $sql .= ' GROUP BY ' . $this->add_ticks($args['group_by']);
        }
        if ($args['order_by']) {
            $sql .= ' ORDER BY ' . $this->add_ticks($args['order_by']);
            if ($args['reverse']) {
                $sql .= ' DESC';
            } else {
                $sql .= ' ASC';
            }
        }
        if ($args['count'] !== null) {
            $sql .= ' LIMIT ' . (int)$args['count'];
        }
        if ($args['offset'] && $args['offset'] > 0) {
            $sql .= ' OFFSET ' . (int)$args['offset'];
        }
        return $sql;
    }

    private function add_ticks($field, $escape = true) {
        $parts = explode('.', $field);
        $strs = array();
        foreach ($parts as $part) {
            // bugger any existing quotes
            $part = trim($part, '`');
            // add our own if needed
            if ($part != '*') {
                if ($escape) {
                    $part = $this->escape($part);
                }
                $part = '`' . $part . '`';
            }
            $strs[] = $part;
        }
        return join('.', $strs);
    }

    /** Iterate through an array to recursively gather IDs used to fetch extra data. Returns a mapping of extra IDs to the primary key given.
    @param array $ptr Array to walk through.
    @param string $path Path to locate ID (e.g. 'foo', 'foo.server_id', 'foo.*.id'). The path may contain wildcards.
    @param string $pkey Primary key to associate the fetched IDs with.
    @param boolean $missing_ok If true, ignore any paths that don't resolve to any data within the ptr obj.
    @return array Returns array of IDs where the keys are the extra IDs found and the values are the given primary key. */
    protected function parse_path($ptr, $path, $pkey, $missing_ok = false) {
        $extra_ids = array();
        // walk to the specified value
        $to_walk = $path;
        foreach ($path as $part) {
            array_shift($to_walk);
            // wild cards can be used to collect multiple IDs from a collection of objs within the data
            if ($part == '*' && is_array($ptr)) {
                foreach ($ptr as $val) {
                    if ($to_walk) {
                        // returning multiple results -- can't use PHP merge cause we have numerical indexes
                        $extra_ids = OmegaLib::merge(
                            $extra_ids,
                            $this->parse_path($val, $to_walk, $pkey, $missing_ok = false)
                        );
                    } else {
                        $extra_ids[$val] = $pkey;
                    }
                }
                return $extra_ids;
            } else if (isset($ptr[$part])) {
                $ptr = $ptr[$part];
            } else {
                if ($missing_ok) {
                    return $extra_ids;
                } else {
                    throw new Exception("Key '$part' not found in row.");
                }
            }
        }
        // and gather it up
        $extra_ids[$ptr] = $pkey;
        return $extra_ids;
    }

}



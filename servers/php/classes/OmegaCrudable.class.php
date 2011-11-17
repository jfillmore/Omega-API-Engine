<?php

abstract class OmegaCrudable {
	protected $db;
	abstract protected function _get_table(); // e.g. example
	abstract protected function _get_props();
	/*
	e.g. 
	return array(
		'id' => array(
			'nice_name' => 'comment ID',
			'type' => 'INT',
			'primary_key' => true,
			'flags' => array('AUTO_INCREMENT', 'NOT NULL')
		),
		'user_id' => array(
			'type' => 'INT',
			'default' => null,
			'foreign_key' => array(
				'table' => 'user',
				'prop' => 'id',
				'flags' => array('ON UPDATE CASCADE', 'ON DELETE CASCADE')
			)
		),
		'subject' => array(
			'type' => 'VARCHAR(64)',
			'nice_name' => 'comment subject',
			'description' => 'Subject of comment.',
			'default' => null,
			'index' => true,
			'validate_re' => '/^[a-zA-Z0-9 \.!]$/'
		),
		'comment' => array(
			'type' => 'VARCHAR(160)',
			'nice_name' => 'comment contents',
			'description' => 'Your comments about anything you want to tell me about.',
			'default' => null
		)
	);
	*/

	public function __construct($db) {
		$this->db = $db;
	}

	public function _get_foreign_keys() {
		$info = $this->_describe(true);
		return $info['related_data'];
	}

	public function _get_primary_key() {
		$info = $this->_describe();
		return $info['key'];
	}

	public function _get_indexes() {
		$info = $this->_describe();
		return $info['indexes'];
	}

	public function _has_props($obj) {
		$prop_names = array_keys($this->_get_props());
		$obj_names = array_keys($obj);
		foreach ($prop_names as $prop) {
			if (! in_array($prop, $obj_names)) {
				return false;
			}
		}
		return true;
	}

	public function _has_index($index, $inc_pkey = true) {
		$indexes = $this->_get_indexes();
		if ($inc_pkey) {
			$indexes[] = $this->_get_primary_key();
		}
		$index = strtoupper($index);
		foreach ($indexes as $my_index) {
			$my_index = strtoupper($my_index);
			if ($index === $my_index) {
				return true;
			}
		}
		return false;
	}

	/** Dump out (default 'mysql') the structure and all of the data. Includes both the structure and data by default, as well as drops the table if needed before creating it..
		expects: format=string, data=boolean
		returns: string */
	public function _dump($format = 'mysql', $data = true, $reset = true) {
		// TODO: support CSV and XML
		$table = $this->_get_table();
		if ($format === 'mysql') {
			// generate the table structure
			$structure = array();
			// add a reset if requested
			if ($reset) {
				$structure[] = "DROP TABLE IF EXISTS `$table`;";
			}
			$structure[] = "CREATE TABLE `$table` (";
			$props = $this->_get_props();
			$cols = array();
			foreach ($props as $name => $prop) {
				$cols[] = "    " . $this->prop_sql($name, $prop);
			}
			$structure[] = implode(",\n", $cols);
			$structure[] = ") ENGINE=INNODB;";
			$retval = implode("\n", $structure);
			// include the data if requested
			if ($data) {
				$data = array();
				foreach ($this->_request() as $row) {
					 // TODO
					 throw new Exception("Not yet supported.");
				}
				$retval .= "\n-- data for table $table --\n";
				$retval .= implode("\n", $data);
			}
		} else {
			throw new Exception("Unsupported format: '$format'.");
		}
		return $retval;
	}

	private function prop_sql($name, $prop) {
		$sql = "`$name` " .  strtoupper($prop['type']);
		if (isset($prop['flags'])) {
			if (in_array('NOT NULL', $prop['flags'])) {
				$sql .= ' NOT NULL';
			}
			if (in_array('AUTO_INCREMENT', $prop['flags'])) {
				$sql .= ' AUTO_INCREMENT';
			}
		}
		if (isset($prop['default'])) {
			if ($prop['default'] === null) {
				$sql .= " DEFAULT null";
			} else {
				if ($this->_is_datetime($prop) || $this->_is_string($prop)) {
					$sql .= " DEFAULT '" . $prop['default'] . "'";
				} else {
					$sql .= " DEFAULT " . $prop['default'];
				}
			}
		}
		if (isset($prop['foreign_key'])) {
			$fk = $prop['foreign_key'];
			$sql .= " FOREIGN KEY (`$name`) REFERENCES `" . $fk['table'] . "` (`" . $fk['prop'] . "`)";
			// tack on our flags if we have any
			if (isset($fk['flags'])) {
				foreach ($fk['flags'] as $flag) {
					if (preg_match('/^ON (UPDATE|DELETE) (RESTRICT|CASCADE|SET NULL|NO ACTION)$/i', $flag)) {
						$sql .= strtoupper($flag);
					} else {
						throw new Exception("Unrecognized foreign key flag for $name: $flag.");
					}
				}
			}
		} else if (isset($prop['primary_key']) && $prop['primary_key']) {
			$sql .= ' PRIMARY KEY';
		}
		if (isset($prop['unique'])) {
			$sql .= ",\n     UNIQUE (`$name`)";
		}
		if (isset($prop['index'])) {
			$sql .= ",\n    INDEX (`$name`)";
		}
		return $sql;
	}

	/** Returns information about the object type, optionally including additional descriptive information.
		expects: verbose=boolean
		returns: object */
	public function _describe($verbose = false) {
		$props = array(
			'properties' => array(),
			'key' => null,
			'indexes' => array()
		);
		if ($verbose) {
			// record any foreign key constraints as related data
			$props['related_data'] = array();
		}
		foreach ($this->_get_props() as $name => $args) {
			$desc = '';
			if ($verbose) {
				if (isset($args['nice_name'])) {
					$desc .= $args['nice_name'];
				}
			}
			if (isset($args['description']) && strlen($args['description'])) {
				if (strlen($desc)) {
					$desc .= ' - ';
				}
				$desc .= $args['description'];
			}
			if ($verbose) {
				// show any type and dimensional info
				$verb_desc = array();
				$type_info = $this->_parse_type($args['type']);
				if ($type_info['type_arg'] !== null) {
					if ($this->_is_string($type_info['type'])) {
						$verb_desc[] = 'max length: ' . $type_info['type_arg'];
					} else if ($this->_is_float($type_info['type'])) {
						$parts = explode(',', $type_info['type_arg']);
						$max_value = pow(10, $parts[1] - $parts[2]);
						$verb_desc[] = 'max value/decimal points: ' . $max_value . '/' . $parts[2];
					} else if ($this->_is_integer($type_info['type'])) {
						$verb_desc[] = 'max value: ' . $type_info['type_arg'];
					} else {
						$verb_desc[] = $type_info['type'] . ': ' . $type_info['type_arg'];
					}
				}
				// show our default value
				if (isset($args['default'])) {
					if ($args['default'] === '') {
						$args['default'] = '[blank]';
					} else if ($args['default'] === null) {
						$args['default'] = '[none]';
					}
					$verb_desc[] = 'default: ' . $args['default'];
				}
				// tack on any verbose info collected
				if (count($verb_desc)) {
					if (strlen($desc)) {
						$desc .= ' ';
					}
					$desc .= '(' . join(', ', $verb_desc) . ')';
				}
				// keep track of related data in foreign key constraints
				if (isset($args['foreign_key']) && $args['foreign_key']) {
					$props['related_data'][] = $args['foreign_key']['table'] . '.' . $args['foreign_key']['prop'];
				}
			}
			// record the property
			$props['properties'][$name] = $desc;
			// pick out the primary key for the data
			if (isset($args['primary_key']) && $args['primary_key']) {
				$props['key'] = $name;
			}
			// index any anything with an index, foreign key or is unique
			$is_key = false;
			if (isset($args['unique'])) {
				$is_key |= $args['unique'];
			}
			if (isset($args['index'])) {
				$is_key |= $args['index'];
			}
			if (isset($args['foriegn_key'])) {
				$is_key |= $args['index'];
			}
			if ($is_key) {
				$props['indexes'][] = $name;
			}
		}
		return $props;
	}

	/** Insert a row into the database using the supplied properties. Returns the ID of the created row.
		expects: props=object, auto_escape=boolean
		returns: number */
	public function _create($props, $auto_escape = true) {
		if (! is_array($props)) {
			throw new Exception("Invalid properties to create object with $props.");
		} else if (count($props) == 0) {
			throw new Exception("Unable to create an empty object.");
		} else {
			$errors = $this->_validate_props($props);
		}
		if (count($errors)) {
			$error_msgs = array();
			foreach ($errors as $error_list) {
				$error_msgs[] = join(' ', $error_list);
			}
			throw new Exception("Unable to create row. " . join(' ', $error_msgs));
		}
		$prop_names = array_keys($props);
		$keys = array();
		$values = array();
		$my_props = $this->_get_props();
		foreach ($props as $name => $value) {
			$prop = $my_props[$name];
			$keys[] = '`' . $name . '`';
			if ($value === null) {
				$values[] = 'NULL';
			} else if ($this->_is_string($prop['type'])) {
				if (preg_match("/^'.*'$/", $value)) {
					$values[] = $value;
				} else {
					if ($auto_escape) {
						$values[] = "'" . $this->db->escape($value) . "'";
					} else {
						$values[] = "'" . $value . "'";
					}
				}
			} else if ($this->_is_datetime($prop['type'])) {
				// dates should always be safe to escape
				if (preg_match("/^'.*'$/", $value)) {
					$values[] = $this->db->escape($value);
				} else {
					$values[] = "'" . $this->db->escape($value) . "'";
				}
			} else {
				// auto-escape all other types by default
				$values[] = $this->db->escape($value);
			}
		}
		$sql = 'INSERT INTO `' . $this->_get_table() . '` (' . join(', ', $keys) . ') VALUES (' . join(', ', $values) . ')';
		$this->db->query($sql);
		$last_id = $this->db->get_last_id();
		return $last_id;
	}

	/** Remove the specified object. Returns information about the removed data.
		expects: obj=object
		returns: object */
	public function _remove($index) {
		$obj = $this->_get($index);
		$pkey_name = $this->_get_primary_key();
		$pkey_value = $obj[$pkey_name];
		$sql = "DELETE FROM `" . $this->_get_table() . "` WHERE `$pkey_name` = ";
		// add quotes if needed on pkey
		$my_props = $this->_get_props();
		$pkey_type = $my_props[$pkey_name]['type'];
		if ($this->_is_string($pkey_type)) {
			$sql .= "'" . $this->db->escape($pkey_value) . "'";
		} else {
			$sql .= $this->db->escape($pkey_value);
		}
		$this->db->query($sql);
		return $obj;
	}

	/** Update the properties listed for the specified objects. Returns the updated object information.
		expects: obj=object, props=object, auto_escape=boolean
		returns: object */
	public function _update($where, $props, $auto_escape = true) {
		if (! is_array($props)) {
			throw new Exception("Invalid properties to update: $props.");
		} else if (count($props) == 0) {
			throw new Exception("You must supply at least one property to update.");
		} else {
			$errors = $this->_validate_props($props);
		}
		if (count($errors)) {
			$error_msgs = array();
			foreach ($errors as $error_list) {
				$error_msgs[] = join(' ', $error_list);
			}
			throw new Exception("Unable to perform update. " . join(' ', $error_msgs));
		}
		$prop_names = array_keys($props);
		$pairs = array();
		$my_props = $this->_get_props();
		foreach ($props as $name => $value) {
			$prop = $my_props[$name];
			if ($value === null) {
				$pairs[] = $name . ' = NULL';
			} else if ($this->_is_string($prop['type'])) {
				if (preg_match("/^'.*'$/", $value)) {
					// quoted? assume it's escaped
					$pairs[] = $name . ' = ' . $value;
				} else {
					if ($auto_escape) {
						$pairs[] = $name . " = '" . $this->db->escape($value) . "'";
					} else {
						$pairs[] = $name . " = '" . $value . "'";
					}
				}
			} else if ($this->_is_datetime($prop['type'])) {
				// dates should always be safe to force escape
				if (preg_match("/^'.*'$/", $value)) {
					$pairs[] = $name . " = " . $this->db->escape($value);
				} else {
					$pairs[] = $name . " = '" . $this->db->escape($value) . "'";
				}
			} else {
				// other types should always be safe to force escape
				$pairs[] = $name . ' = ' . $this->db->escape($value);
			}
		}
		$sql = 'UPDATE `' . $this->_get_table() . '` SET ' . join(', ', $pairs) . ' WHERE ' . $this->_parse_where($where);
		$this->db->query($sql);
		return $this->_request($where);
	}

	/** Validates the specified properties listed. Returns a list of any errors found.
		expects: data=object
		returns: array */
	public function _validate_props($props) {
		$errors = array();
		foreach ($props as $key => $value) {
			$prop_errors = $this->_validate($key, $value);
			if (count($prop_errors)) {
				$errors[$key] = $prop_errors;
			}
		}
		return $errors;
	}

	private function _parse_type($type) {
		$parts = preg_split('/[\(\)]/', $type);
		if (count($parts) === 1) {
			$type = $type;
			$type_arg = null;
		} else {
			$type = $parts[0];
			$type_arg = $parts[1];
		}
		return array(
			'type' => $type,
			'type_arg' => $type_arg
		);
	}

	public function _is_integer($type) {
		$type_info = $this->_parse_type($type);
		return in_array(strtoupper($type_info['type']), array('TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'));
	}

	public function _is_float($type) {
		$type_info = $this->_parse_type($type);
		return in_array(strtoupper($type_info['type']), array('FLOAT', 'REAL', 'DOUBLE PRECISION', 'DECIMAL', 'NUMBER'));
	}

	public function _is_string($type) {
		$type_info = $this->_parse_type($type);
		return in_array(strtoupper($type_info['type']), array('VARCHAR', 'CHAR', 'BINARY', 'VARBINARY', 'BLOB', 'TEXT', 'SET'));
	}

	public function _is_datetime($type) {
		$type_info = $this->_parse_type($type);
		return in_array(strtoupper($type_info['type']), array('DATETIME'));
	}

	/** Validates the specified property. Returns a list of any errors found.
		expects: data=object
		returns: array */
	public function _validate($key, $value) {
		$table = $this->_get_table();
		$props = $this->_get_props();
		if (! isset($props[$key])) {
			throw new Exception("Unrecognized key '$key' for table '$table'.");
		}
		// what name do we use in error messages?
		$def = $props[$key];
		if (isset($def['nice_name'])) {
			$nice_name = $def['nice_name'];
		} else {
			$nice_name = $key;
		}
		// check value based on type
		if (isset($def['type'])) {
			$parts = preg_split('/[\(\)]/', $def['type']);
			if (count($parts) === 1) {
				$type = $def['type'];
				$type_arg = null;
			} else {
				$type = $parts[0];
				$type_arg = $parts[1];
			}
			$errors = array();
			if ($this->_is_integer($type)) {
				if (! preg_match('/^-?[0-9]+$/', $value)) {
					$errors[] = "The value '$value' for $nice_name must be an integer.";
				}
				// TODO: check sizes of ints
			} else if ($this->_is_float($type)) {
				if ($value == '' || ! preg_match('/^(-?[0-9]+)?(\.[0-9]+)?$/', $value)) {
					$errors[] = "The value '$value' for $nice_name must be a decimal number.";
				}
			} else if ($this->_is_datetime($type)) {
				if ($type_arg !== null) {
					 if (! preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/', $value)) {
					 	throw new Exception("The value for $nice_name must be a valid date and time (e.g. 2011-04-05 16:00:00.");
					 }
				}
			} else if ($this->_is_string($type)) {
				if ($type_arg !== null) {
					 if (strlen($value) > $type_arg) {
					 	throw new Exception("The value for $nice_name may not exceed $type_arg characters.");
					 }
				}
			}
		}
		// check value based on flags
		if (isset($def['flags'])) {
			if (in_array('NOT NULL', $def['flags']) && $value === null) {
				// allowable if we're auto_increment though!
				if (! in_array('AUTO_INCREMENT', $def['flags'])) {
					$errors[] = "The value for $nice_name may not be null.";
				}
			}
		}
		// we we have a validation RE?
		if (isset($def['validate_re'])) {
			if (! preg_match($def['validate_re'], $value)) {
				$errors[] = "The format for $nice_name is invalid.";
			}
		}
		return $errors;
	}

	/** Build a custom query to find data dynamically, including joining data from other tables. Returns all data by default.
		expects: where=array, props=array, joins=array, count=number, offset=number, args=array
		returns: object */
	public function _request($where = null, $props = null, $joins = null, $count = null, $offset = null, $args = null) {
		global $om;
		$args = $om->_get_args(array(
			'order_by' => null, // column to sort on
			'reverse' => false, // sort in descending order
			'keyed' => null // key data on specified key, defaulting to primary key if true (e.g. return {"3": {"id": 3, "name": "foo"}};)
		), $args);
		/*
		e.g
		$where = array(
			 'AND' => array(
				"id = 1",
				"name != ''",
				"OR" => array(
					"comment LIKE '%foo%'",
					"comment LIKE '%bar%'"
				)
			)
		);
		$props = array('*');
		$joins = array(
			'user on (example.user_id = user.id)'
		);
		*/
		$table = $this->_get_table();
		if ($props === null || $props === true) {
			$props = array('*');
		} else if (! is_array($props)) {
			$props = array($props);
		}
		$sql = 'SELECT ' . join(', ', $props) . ' FROM `' . $table . '`';
		if (is_array($joins)) {
			$pairs = array();
			foreach ($joins as $join) {
				$parts = explode(' ', $join, 2);
				if (count($parts) === 1) {
					// we weren't told what to join on
					$uc_join = strtoupper($parts[0]);
					$sql_frag = null;
					// but if it's a foreign key we can figure out the 'ON' part ourself
					foreach ($this->_get_props() as $name => $prop) {
						if (isset($prop['foreign_key'])) {
							// if we're joining on a foreign table we know what to do
							if (strtoupper($prop['foreign_key']['table']) === $uc_join) {
								$sql_frag = ' ON (`' . $table . '`.`' . $name . '` = `' . $join . '`.`' . $prop['foreign_key']['prop'] . '`)';
								break;
							}
						}
					}
					if ($sql_frag === null) {
						throw new Exception("Unable to join data from $join without additional information (e.g. $join on ($join.${table}_id = $table.id).");
					} else {
						$join .= $sql_frag;
					}
				}
				$pairs[] = $join;
			}
			$sql .= ' JOIN ' . join(' JOIN ', $pairs);
		} else if (strlen($joins)) {
			$sql .= ' JOIN ' . $joins;
		}
		if (count($where)) {
			$sql .= ' WHERE ' . $this->_parse_where($where);
		}
		// order result
		if ($args['order_by']) {
			$sql .= ' ORDER BY `' . $this->db->escape($args['order_by']) . '`';
		} else {
			$sql .= ' ORDER BY `' . $this->_get_primary_key() . '`';
		}
		if ($args['reverse']) {
			$sql .= ' DESC';
		}
        // limit by count/offset if given
        if ($count > 0) {
            $sql .= ' LIMIT ' . (int)$count;
            if ($offset !== null && $offset > 0) {
                $sql .= ' OFFSET ' . (int)$offset;
            }
        }
		if ($args['keyed'] === true) {
			$objs = $this->db->query($sql, 'array', $this->_get_primary_key());
		} else if (strlen($args['keyed'])) {
			$objs = $this->db->query($sql, 'array', $args['keyed']);
		} else {
			$objs = $this->db->query($sql, 'array');
		}
		return $objs;
	}

	protected function _parse_where($where) {
		$sql = '';
		if (is_array($where)) {
			if (count($where) == 1) {
				$where = array('AND' => $where);
			}
			foreach ($where as $bool => $items) {
				// we're either a boolean operation or a single key/value
				if (! in_array(strtoupper($bool), array('AND', 'OR'))) {
					$sql .= $bool . ' = ' . $items;
				} else {
					$sql .= '(';
					$first_item = true;
					$bool = strtoupper($bool);
					foreach ($items as $key => $item) {
						if (is_array($item)) {
							$sql .= $bool . ' ' . $this->_parse_where(array(
								$key => $item
							));
						} else {
							if (strtoupper($item) === 'NOT') {
								$sql .= '! ';
							} else if (in_array($bool, array('AND', 'OR'))) {
								if ($first_item) {
									$sql .= $item;
									$first_item = false;
								} else {
									$sql .= " $bool $item";
								}
							} else {
								throw new Exception("Unrecognized boolean operation: $bool.");
							}
						}
					}
					$sql .= ')';
				}
			}
		} else {
			if ($where !== null && strlen($where)) {
				$sql = $where;
			}
		}
		return $sql;
	}

	/** Return information about an object. If passed an valid object it will be returned back, as a quick caching method.
		expects: index=string, props=array, joins=array, args=object
		returns: object */
	public function _get($index, $props = null, $joins = null, $args = null) {
		global $om;
		$args = $om->_get_args(array(
			'auto_escape' => true
		), $args);
		$pkey = $this->_get_primary_key();
		if (is_array($index)) {
			// if this is an object already, return it; poor man's caching
			if ($this->_has_props($index)) {
				return $index;
			}
			// otherwise we must be querying on one or more indexes
			$where = array('AND' => array());
			foreach ($index as $key => $value) {
				if (! $this->_has_index($key)) {
					throw new Exception("Invalid index name: '$key'.");
				}
				if (preg_match("/^'.*'$/", $value)) {
					$where['AND'][] = $key . ' = ' . $value;
				} else {
					if ($args['auto_escape']) {
						$where['AND'][] = $key . " = '" . $this->db->escape($value) . "'";
					} else {
						$where['AND'][] = $key . ' = ' . $value;
					}
				}
			}
			$where = $this->_parse_where($where);
		} else {
			$value = $index;
			$my_props = $this->_get_props();
			$pairs = array();
			$indexes = $this->_get_indexes();
			$indexes[] = $pkey;
			// check each of our indexes for this value, since we don't know what it is
			$index_sql = array();
			foreach ($indexes as $prop) {
				if ($this->_is_string($my_props[$prop]['type'])) {
					if (preg_match("/^'.*'$/", $value) || $value === null) {
						// presume it to be escaped already by being quoted
						$index_sql[] = $prop . " = " . $value;
					} else {
						if ($args['auto_escape']) {
							$index_sql[] = $prop . " = '" . $this->db->escape($value) . "'";
						} else {
							$index_sql[] = $prop . " = '" . $value . "'";
						}
					}
				} else if ($this->_is_datetime($my_props[$prop]['type'])) {
					// dates should always be safe to force escape
					if (preg_match("/^'.*'$/", $value) || $value === null) {
						$index_sql[] = $prop . " = " . $this->db->escape($value);
					} else {
						$index_sql[] = $prop . " = '" . $this->db->escape($value) . "'";
					}
				} else if (is_numeric($value)) {
					// numbers should always be safe to force escape
					$index_sql[] = $prop . ' = ' . $this->db->escape($value);
				}
			}
			$where = join(' OR ', $index_sql);
		}
		$req_args = array('auto_escape' => false);
		/* // TODO: other _get args we might want to pass on
		foreach (array() as $req_arg) {
			$req_args[$req_arg] = $args[$req_arg];
		}
		*/
		$count = 2; // _get always expects to return one object, so if we find at least two avoid getting the rest
		$offset = 0;
		$objs = $this->_request($where, $props, $joins, $count, $offset, $req_args);
		if (count($objs) > 1) {
			throw new Exception("Multiple objects found where " . $where . '.');
		}
		if (count($objs) === 0) {
			throw new Exception("No data found where $where.");
		}
		return array_pop($objs);
	}
}

?>

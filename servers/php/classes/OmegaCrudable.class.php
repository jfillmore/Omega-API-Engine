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
			'flags' => array('AUTO_INCREMENT'),
			'foreign_key' => array(
				'table' => 'user',
				'prop' => 'id',
				'crudable' => false
			)
		),
		'subject' => array(
			'type' => 'VARCHAR(64)',
			'nice_name' => 'comment subject',
			'description' => 'Subject of comment.',
			'default' => null,
			'index' => true,
			'validate_re' => '/^[a-zA-Z0-9 \.!]$/',
			'flags' => array('AUTO_INCREMENT')
		),
		'comment' => array(
			'type' => 'VARCHAR(160)',
			'nice_name' => 'comment contents',
			'description' => 'Your comments about anything you want to tell me about.',
			'default' => null,
			'flags' => array('AUTO_INCREMENT')
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

	/** Dump out (default 'mysql') the structure and all of the data. Includes both the structure and data by default.
		expects: format=string, data=boolean
		returns: string */
	public function _dump($format = 'mysql', $data = true) {
		throw new Exception("TODO.");
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
			if (isset($args['description']) && strlen($args['description'])) {
				$desc .= $args['description'];
			}
			if ($verbose) {
				if (isset($args['default'])) {
					$desc .= ' Default: ' . $args['default'];
				}
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

	public function _create($props) {
		throw new Exception("TODO.");
		if (! is_array($props)) {
			throw new Exception("Invalid properties to create object with $props.");
		} else if (count($props) == 0) {
			throw new Exception("Unable to create an empty object.");
		} else {
			$errors = $this->_validate_props($props);
		}
		if (count($errors)) {
			throw new Exception("Unable to perform update. " . join(' ', $errors));
		}
		$prop_names = array_keys($props);
		$pairs = array();
		$my_props = $this->_get_props();
		foreach ($props as $name => $value) {
			$prop = $my_props[$name];
			if ($this->_is_string($prop['type'])) {
				if (preg_match("/^'.*'$/", $value)) {
					$pairs[] = $name . ' = ' . $value;
				} else {
					if ($auto_escape) {
						$pairs[] = $name . " = '" . $this->db->escape($value) . "'";
					} else {
						$pairs[] = $name . " = '" . $value . "'";
					}
				}
			} else {
				if ($auto_escape) {
					$pairs[] = $name . ' = ' . $this->db->escape($value);
				} else {
					$pairs[] = $name . ' = ' . $value;
				}
			}
		}
		$sql = 'UPDATE `' . $this->_get_table() . '` SET ' . join(', ', $pairs) . ' WHERE ' . $this->_parse_where($where);
		$this->db->query($sql);
		return $this->_request($where);
	}

	/** Remove the specified object. Returns information about the removed data.
		expects: obj=object
		returns: object. */
	public function _remove($index) {
		throw new Exception("TODO.");
	}

	/** Update the properties listed for the specified objects. Returns the updated object information.
		expects: obj=object, props=object, auto_escape=boolean
		returns: obj */
	public function _update($where, $props, $auto_escape = true) {
		if (! is_array($props)) {
			throw new Exception("Invalid properties to update: $props.");
		} else if (count($props) == 0) {
			throw new Exception("You must supply at least one property to update.");
		} else {
			$errors = $this->_validate_props($props);
		}
		if (count($errors)) {
			throw new Exception("Unable to perform update. " . join(' ', $errors));
		}
		$prop_names = array_keys($props);
		$pairs = array();
		$my_props = $this->_get_props();
		foreach ($props as $name => $value) {
			$prop = $my_props[$name];
			if ($this->_is_string($prop['type'])) {
				if (preg_match("/^'.*'$/", $value)) {
					$pairs[] = $name . ' = ' . $value;
				} else {
					if ($auto_escape) {
						$pairs[] = $name . " = '" . $this->db->escape($value) . "'";
					} else {
						$pairs[] = $name . " = '" . $value . "'";
					}
				}
			} else {
				if ($auto_escape) {
					$pairs[] = $name . ' = ' . $this->db->escape($value);
				} else {
					$pairs[] = $name . ' = ' . $value;
				}
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

	public function _is_integer($type) {
		$parts = preg_split('/[\(\)]/', $type);
		if (count($parts) === 1) {
			$type = $type;
			$type_arg = null;
		} else {
			$type = $parts[0];
			$type_arg = $parts[1];
		}
		return in_array(strtoupper($type), array('TINYINT', 'SMALLINT', 'MEDIUMINT', 'INT', 'BIGINT'));
	}

	public function _is_float($type) {
		$parts = preg_split('/[\(\)]/', $type);
		if (count($parts) === 1) {
			$type = $type;
			$type_arg = null;
		} else {
			$type = $parts[0];
			$type_arg = $parts[1];
		}
		return in_array(strtoupper($type), array('FLOAT', 'REAL', 'DOUBLE PRECISION', 'DECIMAL', 'NUMBER'));
	}

	public function _is_string($type) {
		$parts = preg_split('/[\(\)]/', $type);
		if (count($parts) === 1) {
			$type = $type;
			$type_arg = null;
		} else {
			$type = $parts[0];
			$type_arg = $parts[1];
		}
		return in_array(strtoupper($type), array('VARCHAR', 'CHAR', 'BINARY', 'VARBINARY', 'BLOB', 'TEXT', 'SET'));
	}

	/** Validates the specified property. Returns a list of any errors found.
		expects: data=object
		returns: array */
	public function _validate($key, $value) {
		$table = $this->_get_table();
		if (! in_array($key, array_keys($this->_get_props()))) {
			throw new Exception("Unrecognized key '$key' for table '$table'.");
		}
		// what name do we use in error messages?
		$props = $this->_get_props();
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
			}
			// TODO: check string lengths
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
			'sort_by' => null, // column to sort by
			'sort_desc' => false, // sort in descending order
			'order_by' => null, // column to sort on
			'keyed' => true // key data on primary key (e.g. return {"3": {"id": 3, "name": "foo"};)
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
			$props = array('`' . $this->_get_table() . '`.*');
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
			$sql .= ' ORDER BY ' . $this->db->escape($args['order_by']);
		}
        // limit by count/offset if given
        if ($count > 0) {
            $sql .= ' LIMIT ' . (int)$count;
            if ($offset !== null && $offset > 0) {
                $sql .= ' OFFSET ' . (int)$offset;
            }
        }
		if ($args['keyed']) {
			$objs = $this->db->query($sql, 'array', $this->_get_primary_key());
		} else {
			$objs = $this->db->query($sql, 'array');
		}
		return $objs;
	}

	protected function _parse_where($where) {
		$sql = '';
		if (is_array($where)) {
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

	/** Return information about an object. If passed an valid object it will be returned back. Automatically escapes data not surrounded in single quotes. Returns data organized by the primary key by default; otherwise an array will be returned. Arguments: auto_escape=true
		expects: index=string, $props=array, $joins=array, args=object
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
			$index = $this->db->escape($index);
			$my_props = $this->_get_props();
			$pairs = array();
			$indexes = $this->_get_indexes();
			$pkey_type = $my_props[$pkey]['type'];
			// we must be searching by primary key, auto-quote/escape as needed
			if ($this->_is_string($pkey_type) && ! preg_match("/^'.*'$/", $value)) {
				if ($args['auto_escape']) {
					$where = $pkey . " = '" . $this->db->escape($value) . "'";
				} else {
					$where = $pkey . " = '" . $value . "'";
				}
			} else {
				if ($args['auto_escape']) {
					$where = $pkey . ' = ' . $this->db->escape($index);
				} else {
					$where = $pkey . ' = ' . $index;
				}
			}
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

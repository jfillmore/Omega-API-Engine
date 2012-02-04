<?php

abstract class OmegaORM extends OmegaCrudable implements OmegaApi {
	/** Returns information about the object type, optionally including additional descriptive information.
		expects: verbose=boolean
		returns: object */
	public function describe($verbose = false) {
		return parent::_describe($verbose);
	}

	/** Insert a row into the database using the supplied properties. Returns the ID of the created row.
		expects: props=object, auto_escape=boolean
		returns: number */
	public function create($props, $auto_escape = true) {
		return parent::_create($props, $auto_escape);
	}

	/** Remove the specified object. Returns information about the removed data.
		expects: index=object
		returns: object */
	public function remove($index) {
		return parent::_remove($index);
	}

	/** Update the properties listed for the specified objects. Returns the updated object information.
		expects: where=object, props=object, auto_escape=boolean
		returns: object */
	public function update($where, $props, $auto_escape = true) {
		return parent::_update($where, $props, $auto_escape);
	}

	/** Validates the specified properties listed. Returns a list of any errors found.
		expects: props=object
		returns: array */
	public function validate_props($props) {
		return parent::_validate_props($props);
	}

	/** Validates the specified property. Returns a list of any errors found.
		expects: key=string, value=undefined
		returns: array */
	public function validate($key, $value) {
		return parent::_validate($key, $value);
	}

	/** Build a custom query to find data dynamically, including joining data from other tables. Returns all data by default.
		expects: where=array, props=array, joins=array, count=number, offset=number, args=array
		returns: object */
	public function request($where = null, $props = null, $joins = null, $count = null, $offset = null, $args = null) {
		return parent::_request($where, $props, $joins, $count, $offset, $args);
	}


	/** Return information about an object. If passed an valid object it will be returned back, as a quick caching method.
		expects: index=string, props=array, joins=array, args=object
		returns: object */
	public function get($index, $props = null, $joins = null, $args = null) {
		return parent::_get($index, $props, $joins, $args);
	}
}

?>

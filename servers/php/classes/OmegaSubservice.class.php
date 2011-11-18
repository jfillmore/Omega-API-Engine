<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


abstract class OmegaSubservice implements OmegaApi {
	private $config;

	public function __construct() {
		$this->config = $this->get_default_config();
		$this->_save_config();
	}

	/** Returns the name of the subservice.
		returns: string */
	public function get_subservice_name() {
		global $om;
		// the name of the subservice is everything but 'Omega' up front
		return substr(get_class($this), 5);
	}

	/** Get the default configuration object for this subservice.
		returns: object */
	public function get_default_config() {
		return array();
	}

	/** Validate the subservice configuration object. Throws an exception if there is an error.
		expects: config=object */
	public function validate_config($config) {
		if (! is_array($config)) {
			throw new Exception("Configuration object is not an array.");
		}
	}

	/** Clear all of the data for the subservice. */
	public function clear_data() {
		global $om;
		$shed = new OmegaFileShed($this->_localize('/'));
		$shed->clear_shed(true);
	}

	/** Returns a path to the data bin relative to the subservice.
		expects: bin=string
		returns: string */
	final public function _localize($bin) {
		global $om;
		return $om->service_name . '/subservices/' . get_class($this) . '/' . $bin;
	}

	/** Validates and updates the subservice configuration file.
		expects: config=object */
	final public function set_config($config) {
		try {
			$this->validate_config($config);
			$this->config = $config;
			$this->_save_config();
		} catch (Exception $e) {
			throw new Exception("Failed to set configuration file.");
		}
	}

	/** Returns the current configuration file for the subservice.
		returns: object */
	final public function get_config() {
		return $this->config;
	}

	/** Save the current configuration file. */
	final private function _save_config() {
		global $om;
		$om->shed->store($this->_localize('/'), 'config', $this->config);
	}
}

?>

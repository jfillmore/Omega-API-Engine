<?php

/* omega - PHP host service
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

/** Omega service management methods. */
class OmegaServiceManager implements OmegaApi {
	private $config = null;

	public function __construct() {
		global $om;
		// load our configuration file, if we have one
		try {
			$this->config = $om->shed->get($om->service_name . '/services', 'config');
		} catch (Exception $e) {
			throw new Exception("Failed to read Omega services file. Please ensure that the configuration has been run.");
		}
	}

	public function _save_config() {
		global $om;
		$om->shed->store($om->service_name . '/services', 'config', $this->config);
	}

	/** Get the current omega service manager configuration object.
		returns: object */
	public function get_config() {
		return $this->config;
	}

	/** Sets the omega service manager configuration object, provided it is valid.
		expects: config=object */
	public function _set_config($config) {
		$this->_validate_config($config);
		$this->config = $config;
		$this->_save_config();
	}

	/** Validates the omega service manager configuration object. Throws an exception if any problems are found.
		expects: config=object */
	public function _validate_config($config) {
		if (! is_array($config)) {
			throw new Exception("Invalid configuration object; it is not an array.");
		}
		if (! (count($config) == 1 && isset($config['services']))) {
			throw new Exception("Invalid configuration option; unrecognized parts.");
		}
		foreach ($config['services'] as $service => $enabled) {
			if (! in_array($service, $this->services)) {
				throw new Exception("Unrecognized service: $service.");
			}
			if (! is_bool($enabled)) {
				throw new Exception("Invalid value for 'enabled': '$enabled'.");
			}
		}
	}

	/** Lists the omega services.
		returns: array */
	public function list_services() {
		return array_keys($this->config['services']);
	}

	/** Lists the omega services that are enabled.
		returns: array */
	public function list_enabled() {
		$enabled_services = array();
		foreach ($this->config['services'] as $service => $enabled) {
			if ($enabled) {
				$enabled_services[] = $service;
			}
		}
		return $enabled_services;
	}

	/** Enables and activates a service, provided the configuration is valid.
		expects: service=string */
	public function enable($service) {
		global $om;
		// make sure this is currently disabled
		if (! $this->is_disabled($service)) {
			throw new Exception("The service '$service' has already been enabled.");
		}
		// enable it in the config
		$this->config['services'][$service] = true;
		$this->_save_config();
		$om->subservice->logger->log("Enabled service '$service'.");
	}

	/** Disables an active service.
		expects: service=string */
	public function disable($service) {
		global $om;
		// make sure this is currently enabled
		if (! $this->is_enabled($service)) {
			throw new Exception("The service '$service' has already been disabled.");
		}
		// disable it in the config
		$this->config['services'][$service] = false;
		$this->_save_config();
		$om->subservice->logger->log("Disabled service '$service'.");
	}

	/** Returns whether or not a particular service is currently enabled.
		expects: service=string
		returns: boolean */
	public function is_enabled($service) {
		global $om;
		// it must be active and not in the 'disabled' list
		return $this->is_present($service) && $this->config['services'][$service];
	}

	/** Returns whether or not a particular service is currently disabled.
		expects: service=string
		returns: boolean */
	public function is_disabled($service) {
		global $om;
		// it must be active and disabled
		return $this->is_present($service) && $this->config['services'][$service] == false;
	}

	/** Returns whether or not a particular service is currently present.
		expects: service=string
		returns: boolean */
	public function is_present($service) {
		global $om;
		// return whether or not it is currently present
		return in_array($service, array_keys($this->config['services']));
	}

	/** Creates a new omega service. The service name is the PHP class to instantiate to build the service API. The nickname is what the API is called by. The description is a short description of the service. The key is used to authenticate the service maintainer. The class_dirs are an array of directories to look for the service class and interface files in. If async is set to true then the service will be stateless, and thus asynchronous. The scope sets the service instance life-style (global, user, none).
		expects:
			service_name=string
			nickname=string
			description=string
			key=string
			class_dirs=array
			async=boolean
			scope=string
			location=string
			*/
	public function create($service_name, $nickname, $description, $key, $class_dirs, $async = true, $scope = 'global', $location) {
		global $om;
		// validate our input
		if (! preg_match(OmegaTest::word_re, $service_name)) {
			throw new Exception("Invalid service name: '$service_name'.");
		}
		if (! preg_match(OmegaTest::word_re, $nickname)) {
			throw new Exception("Invalid service nickname: '$nickname'.");
		}
		$key_len = strlen($key);
		if (! ($key_len >= 6 && $key_len <= 4096)) {
			throw new Exception("Invalid key length: '$key_len'.");
		}
		if (! is_array($class_dirs)) {
			throw new Exception("Invalid class_dirs array.");
		}
		// make sure the directories exist
		foreach ($class_dirs as $dir) {
			if (! is_dir($dir)) {
				throw new Exception("The class directory '$dir' is not a directory.");
			}
		}
		if (! in_array($scope, array('global', 'user', 'session', 'none'))) {
			throw new Exception("Invalid service scope: '$scope'.");
		}
		if (! preg_match(OmegaTest::file_path_re, $location)) {
			throw new Exception("Invalid service location: '$location'.");
		}
		// make sure the service name is available
		if (in_array($service_name, array_keys($this->config['services']))) {
			throw new Exception("The service name '$service_name' is already in use.");
		}
		// figure out where we'll store the data
		$data_dir = OmegaConstant::data_dir . "/$service_name";
		if (file_exists($data_dir) && ! is_dir($data_dir)) {
			throw new Exception("Unable to replace file '$data_dir' with a directory.");
		}
		$result = @mkdir($data_dir, 0777);
		if (! $result) {
			throw new Exception("Failed to create directory '$data_dir'.");
		}
		// build and save the new configuration
		try {
			$shed = new OmegaFileShed($data_dir);
			try {
				$config = array('omega' => array(
					'key' => $key,
					'nickname' => $nickname,
					'description' => $description,
					'async' => (bool)$async,
					'scope' => $scope,
					'location' => $location,
					'class_dirs' => $class_dirs
				));
				$shed->store('/', 'config', $config);
			} catch (Exception $e) {
				// if we failed then clean up after ourselves before aborting
				$shed->clear_shed();
				throw $e;
			}
		} catch (Exception $e) {
			@rmdir($data_dir);
			throw $e;
		}
		// and update our own configuration to add the service, initially disabled
		$this->config['services'][$service_name] = false;
		$this->_save_config();
	}

	/** Removes an omega service from the server, optionally deleting all of the internal data.
		expects: service_name=string, clear_data=boolean */
	public function remove($service_name, $clear_data=false) {
		// make sure the service is present
		if (! in_array($service_name, array_keys($this->config['services']))) {
			throw new Exception("No service by '$service_name' exists.");
		}
		// remove the data if requested
		if ($clear_data) {
			// figure out just where exactly that data is at
			$data_dir = OmegaConstant::data_dir . "/$service_name";
			if (! is_dir($data_dir)) {
				throw new Exception("Unable to remove service and clear data: '$data_dir' is not a directory as expected.");
			}
			// clear the shed
			$shed = new OmegaFileShed($data_dir);
			$shed->clear_shed(true);
		}
		// remove it
		unset($this->config['services'][$service_name]);
		// and save the config
		$this->_save_config();
	}

	/** Returns the configuration file for the specified service.
		expects: service=string
		*/
	public function get_service_config($service) {
		$shed = new OmegaFileShed(OmegaConstant::data_dir);
		return $shed->get($service, 'config');
	}			
}

?>

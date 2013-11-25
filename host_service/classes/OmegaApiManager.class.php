<?php

/* omega - PHP host api
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

/** Omega API management methods. */
class OmegaApiManager extends OmegaRESTful {
    private $config = null;

    public function _get_handlers() {
        return array(
            'get' => array(
                '/' => 'get_config',
                '/enabled' => 'list_enabled',
                '/:api/enabled' => 'is_enabled',
                '/:api/disabled' => 'is_disabled',
                '/:api/exists' => 'is_present'
            ),
            'post' => array(
                '/:api/enable' => 'enable',
                '/:api/disable' => 'disable',
                '/' => 'create'
            ),
            'put' => array(),
            'patch' => array(),
            'delete' => array(
                '/:api' => 'remove'
            )
        );
    }

    public function __construct() {
        $om = Omega::get();
        // load our configuration file, if we have one
        try {
            $this->config = $om->shed->get($om->api_name, 'apis');
        } catch (Exception $e) {
            throw new Exception("Failed to read Omega APIs file. Please ensure that the configuration has been run.");
        }
    }

    /** Lists the omega APIs.
        returns: array */
    public function list_apis() {
        return array_keys($this->config['apis']);
    }

    /** Lists the omega apis that are enabled.
        returns: array */
    public function list_enabled() {
        $enabled_apis = array();
        foreach ($this->config['apis'] as $api => $enabled) {
            if ($enabled) {
                $enabled_apis[] = $api;
            }
        }
        return $enabled_apis;
    }

    /** Enables and activates a api, provided the configuration is valid.
        expects: api=string */
    public function enable($api) {
        $om = Omega::get();
        // make sure this is currently disabled
        if (! $this->is_disabled($api)) {
            throw new Exception("The API '$api' has already been enabled.");
        }
        // enable it in the config
        $this->config['apis'][$api] = true;
        $this->_save_config();
        $om->logger->log("Enabled API '$api'.");
    }

    /** Disables an active API.
        expects: api=string */
    public function disable($api) {
        $om = Omega::get();
        // make sure this is currently enabled
        if (! $this->is_enabled($api)) {
            throw new Exception("The API '$api' has already been disabled.");
        }
        // disable it in the config
        $this->config['apis'][$api] = false;
        $this->_save_config();
        $om->logger->log("Disabled API '$api'.");
    }

    /** Returns whether or not a particular API is currently enabled.
        expects: api=string
        returns: boolean */
    public function is_enabled($api) {
        $om = Omega::get();
        // it must be active and not in the 'disabled' list
        return $this->is_present($api) && $this->config['apis'][$api];
    }

    /** Returns whether or not a particular API is currently disabled.
        expects: api=string
        returns: boolean */
    public function is_disabled($api) {
        $om = Omega::get();
        // it must be active and disabled
        return $this->is_present($api) && $this->config['apis'][$api] == false;
    }

    /** Returns whether or not a particular API is currently present.
        expects: api=string
        returns: boolean */
    public function is_present($api) {
        $om = Omega::get();
        // return whether or not it is currently present
        return in_array($api, array_keys($this->config['apis']));
    }

    /** Creates a new omega API. The API name is the PHP class to instantiate to build the API. The nickname is what the API is called by. The description is a short description of the API. The key is used to authenticate the API maintainer. The class_dirs are an array of directories to look for the API class and interface files in. If async is set to true then the API will be stateless, and thus asynchronous. The scope sets the API instance life-style (global, user, none).
        expects:
            api=string
            key=string
            class_dirs=array
            location=string
            admin_email=string
            description=string
            nickname=string
            async=boolean
            scope=string
            enabled=boolean
            production=boolean
            debug=boolean
            introspect=boolean
            */
    public function create($api, $key, $class_dirs, $location, $admin_email, $description = '', $nickname = null, $async = true, $scope = 'none', $enabled = true, $production = true, $debug = false, $introspect = true) {
        $om = Omega::get();
        // validate our input
        if (! preg_match(OmegaTest::word_re, $api)) {
            throw new Exception("Invalid API name: '$api'.");
        }
        if (! $nickname) {
            $nickname = strtolower($api);
        }
        if (! preg_match(OmegaTest::word_re, $nickname)) {
            throw new Exception("Invalid API nickname: '$nickname'.");
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
            throw new Exception("Invalid API scope: '$scope'.");
        }
        if (! preg_match(OmegaTest::file_path_re, $location)) {
            throw new Exception("Invalid API location: '$location'.");
        }
        if (! preg_match(OmegaTest::email_address_re, $admin_email)) {
            throw new Exception("Invalid admin email address: '$admin_email'.");
        }
        // make sure the API name is available
        if (in_array($api, array_keys($this->config['apis']))) {
            throw new Exception("The API name '$api' is already in use.");
        }
        // figure out where we'll store the data
        $data_dir = OmegaConstant::data_dir() . "/$api";
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
                    'admin' => array(
                        'email' => $admin_email
                    ),
                    'nickname' => $nickname,
                    'description' => (string)$description,
                    'production' => (bool)$production, // don't return backtraces by default in errors
                    'async' => (bool)$async,
                    'scope' => $scope,
                    'debug' => $debug, // return backtraces w/ data in them if true
                    'introspect' => $introspect, 
                    'location' => $location,
                    'class_dirs' => $class_dirs,
                    'logger' => array(
                        'verbosity' => array()
                    ),
                    'auth' => false
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
        // and update our own configuration to add the API, initially disabled
        $this->config['apis'][$api] = false;
        $this->_save_config();
        if ($enabled) {
            $this->enable($api);
        }
    }

    /** Removes an omega API from the server, optionally deleting all of the internal data.
        expects: api=string, clear_data=boolean */
    public function remove($api, $clear_data=false) {
        // make sure the API is present
        if (! in_array($api, array_keys($this->config['apis']))) {
            throw new Exception("No API by '$api' exists.");
        }
        // remove the data if requested
        if ($clear_data) {
            // figure out just where exactly that data is at
            $data_dir = OmegaConstant::data_dir() . "/$api";
            if (! is_dir($data_dir)) {
                throw new Exception("Unable to remove API and clear data: '$data_dir' is not a directory as expected.");
            }
            // clear the shed
            $shed = new OmegaFileShed($data_dir);
            $shed->clear_shed(true);
        }
        // remove it
        unset($this->config['apis'][$api]);
        // and save the config
        $this->_save_config();
    }

    public function _save_config() {
        $om = Omega::get();
        $om->shed->store($om->api_name, 'apis', $this->config);
    }

    /** Get the current omega API manager configuration object.
        returns: object */
    public function get_config() {
        return $this->config;
    }

    /** Sets the omega API manager configuration object, provided it is valid.
        expects: config=object */
    public function _set_config($config) {
        $this->_validate_config($config);
        $this->config = $config;
        $this->_save_config();
    }

    /** Validates the omega API manager configuration object. Throws an exception if any problems are found.
        expects: config=object */
    public function _validate_config($config) {
        if (! is_array($config)) {
            throw new Exception("Invalid configuration object; it is not an array.");
        }
        if (! (count($config) == 1 && isset($config['apis']))) {
            throw new Exception("Invalid configuration option; unrecognized parts.");
        }
        foreach ($config['apis'] as $api => $enabled) {
            if (! in_array($api, $this->apis)) {
                throw new Exception("Unrecognized API: $api.");
            }
            if (! is_bool($enabled)) {
                throw new Exception("Invalid value for 'enabled': '$enabled'.");
            }
        }
    }

}

?>

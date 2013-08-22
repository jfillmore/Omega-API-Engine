<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Manages (activate, deactivate, enable, disable, etc) sub-services (e.g. ACLs, logging) for an omega service. */
class OmegaSubserviceManager extends OmegaRESTful implements OmegaApi {
    private $subservices = array('authority', 'logger', 'editor', 'profiler');
    private $config = null;

    // the subservices
    public $authority = null; // secure API via ACLs & firewall
    public $logger = null; // log service information
    public $editor = null; // source code viewing and editing
    public $profiler = null; // memory usage and process stats
    public $monitor = null; // exception/api/log tracking and alerting

    public function __construct() {
        global $om;
        // load our configuration file, if we have one
        try {
            $this->config = $om->shed->get($om->service_name . '/subservices', 'config');
        } catch (Exception $e) {
            $this->config = array(
                'subservices' => array(),
            );
        }
        // instantiate all subservices by default
        foreach ($this->subservices as $subservice) {
            $class_name = "Omega" . ucfirst($subservice);
            $subservice_obj = new $class_name();
            $this->$subservice = $subservice_obj;
        }
    }

    public function _get_handlers() {
        return array(
            'GET' => array(
                '/' => 'find',
                '/enabled' => 'list_enabled',
            ),
            'POST' => array(
                '/:subservice/deactivate' => 'deactivate',
                '/:subservice/enabled' => 'set_enabled'
            )
        );
    }

    public function _save_config() {
        global $om;
        $om->shed->store($om->service_name . '/subservices', 'config', $this->config);
    }

    /** Get the current subservice manager configuration object.
        returns: object */
    public function get_config() {
        return $this->config;
    }

    /** Sets the subservice manager configuration object, provided it is valid.
        expects: config=object */
    public function _set_config($config) {
        $this->_validate_config($config);
        $this->config = $config;
        $this->_save_config();
    }

    /** Validates the subservice manager configuration object. Throws an exception if any problems are found.
        expects: config=object */
    public function _validate_config($config) {
        if (! is_array($config)) {
            throw new Exception("Invalid configuration object; it is not an array.");
        }
        if (! (count($config) == 1 && isset($config['subservices']))) {
            throw new Exception("Invalid configuration option; unrecognized parts.");
        }
        foreach ($config['subservices'] as $subservice => $enabled) {
            if (! in_array($subservice, $this->subservices)) {
                throw new Exception("Unrecognized subservice: $subservice.");
            }
            if (! is_bool($enabled)) {
                throw new Exception("Invalid value for 'enabled': '$enabled'.");
            }
        }
    }

    /** Lists the subservices provided by this omega server. May optionally be filtered by a name (e.g. 'auth%').
        expects: name=string, case_sensitive=boolean
        returns: object */
    public function find($name = '%', $case_sensitive = false) {
        global $om;
        $subservices = array();
        $name_re = preg_replace('/%/', '.*', $name);
        foreach ($this->subservices as $service) {
            if (preg_match('/' . $name_re . '/i', $service)) {
                $r_class = new ReflectionClass('Omega' . ucfirst($service));
                $doc_string = $r_class->getDocComment();
                if ($doc_string === false) {
                    $doc_string = ''; 
                } else {
                    // trim the '/** */' and any extra white space
                    $doc_string = trim(
                        substr($doc_string, 3, strlen($doc_string) - 5)
                    );  
                }
                $subservices[$service] = array(
                    'enabled' => $this->is_enabled($service),
                    'description' => $doc_string
                );
            }
        }
        return $subservices;
    }

    /** Lists the enabled subservices for this service.
        returns: array */
    public function list_enabled() {
        $enabled = array();
        foreach ($this->config['subservices'] as $subservice => $is_enabled) {
            if ($is_enabled && isset($this->$subservice)) {
                $enabled[] = $subservice;
            }
        }
        return $enabled;
    }

    /** Unsubscribe your service from a subservice. Any data created by that subservice will be retained so it may be reactivated later. The data stored by the subservice may be cleared with the 'clear_data' option. Returns the subservice configuration information.
        expects: subservice=string, clear_data=boolean
        returns: object */
    public function deactivate($subservice, $clear_data = false) {
        global $om;
        $subservice = strtolower($subservice);
        // clear the data if requested
        if ($clear_data) {
            // TODO: clear any local config files?
            $this->$subservice->clear_data();
            if (isset($this->logger)) {
                $this->logger->log("Cleared subservice data for $subservice.");
            }
        }
        // unregister the subservice
        unset($this->config['subservices'][$subservice]);
        // save our config
        $this->_save_config();
        return $this->get_config();
    }

    /** Sets a subservice to be either enabled or disabled. Defaults to true.
        expects: subservice=string, enabled=boolean */
    public function set_enabled($subservice, $enabled = true) {
        if ($enabled) {
            return $this->enable($subservice);
        } else {
            return $this->disable($subservice);
        }
    }

    /** Enables a subservice.
        expects: subservice=string */
    public function enable($subservice) {
        $subservice = strtolower($subservice);
        // make sure this is currently disabled
        if (! $this->is_disabled($subservice)) {
            throw new Exception("The subservice '$subservice' has already been enabled.");
        }
        // enable it in the config
        $this->config['subservices'][$subservice] = true;
        $this->_save_config();
        if ($this->is_enabled('logger')) {
            $this->logger->log("Enabled subservice '$subservice'.");
        }
        return $this->get_config();
    }

    /** Disables a subservice.
        expects: subservice=string */
    public function disable($subservice) {
        $subservice = strtolower($subservice);
        // make sure this is currently enabled
        if (! $this->is_enabled($subservice)) {
            throw new Exception("The subservice '$subservice' has already been disabled.");
        }
        // disable it in the config
        $this->config['subservices'][$subservice] = false;
        $this->_save_config();
        if ($this->is_enabled('logger')) {
            $this->logger->log("Disabled subservice '$subservice'.");
        }
        return $this->get_config();
    }

    /** Returns whether or not a particular subservice is currently enabled.
        expects: subservice=string
        returns: boolean */
    public function is_enabled($subservice) {
        return isset(
            $this->config['subservices'][$subservice]
        ) && $this->config['subservices'][$subservice];
    }

    /** Returns whether or not a particular subservice is currently disabled.
        expects: subservice=string
        returns: boolean */
    public function is_disabled($subservice) {
        return ! $this->is_enabled($subservice);
    }
}

?>

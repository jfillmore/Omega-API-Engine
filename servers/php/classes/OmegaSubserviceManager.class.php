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
        // instantiate only the enabled services
        foreach ($this->config['subservices'] as $subservice => $enabled) { 
            if (! $enabled) {
                // disabled? sorry not gonna load you
                continue;
            }
            // load up the subservice
            $class_name = "Omega" . ucfirst($subservice);
            $subservice_obj = new $class_name();
            $this->$subservice = $subservice_obj;
        }
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

    /** Lists the subservices provided by this omega server and whether or not they are active for this service. May optionally be filtered by a name (e.g. 'auth%').
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
                    'active' => $this->is_active($service),
                    'enabled' => $this->is_enabled($service),
                    'description' => $doc_string
                );
            }
        }
        return $subservices;
    }

    /** Lists the active subservices for this service.
        returns: array */
    public function list_active() {
        return array_keys($this->config['subservices']);
        $active = array();
        foreach ($this->config['subservices'] as $subservice => $is_enabled) {
            if ($this->$subservice !== null) {
                $active[] = $subservice;
            }
        }
        return $active;
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

    /** Returns the default configuration file for the specified subservice.
        expects: subservice=string
        returns: object */
    public function get_default_config($subservice) {
        $class_name = "Omega" . ucfirst($subservice);
        $subservice_obj = new $class_name();
        return $subservice_obj->get_default_config();
    }

    /** Subscribes your service to a subservice, which is disabled by default. It must be configured and enabled before it will operate.
        expects: subservice=string
        returns: object */
    public function activate($subservice, $config = null) {
        global $om;
        // make sure this is a valid subservice
        $subservice = strtolower($subservice);
        if (! in_array($subservice, $this->subservices)) {
            throw new Exception("No subservice by the name $subservice exists.");
        }
        // make sure it hasn't already been activated
        if ($this->is_active($subservice)) {
            throw new Exception("The subservice '$subservice' is already active.");
        }
        // instantiate it
        $class_name = "Omega" . ucfirst($subservice);
        $subservice_obj = new $class_name();
        // generate a default config if needed
        if ($config === null) {
            $config = $subservice_obj->get_default_config();
        }
        $subservice_obj->set_config($config);
        // add it to the config as a disabled subservice
        $this->config['subservices'][$subservice] = false;
        // save the service manager config
        $this->_save_config();
        if (isset($this->logger)) {
            $this->logger->log("Activated subservice '$subservice'.");
        }
        return $this->get_config();
    }

    /** Unsubscribe your service from a subservice. Any data created by that subservice will be retained so it may be reactivated later. The data stored by the subservice may be cleared with the 'clear_data' option. Returns the subservice configuration information.
        expects: subservice=string, clear_data=boolean
        returns: object */
    public function deactivate($subservice, $clear_data = false) {
        global $om;
        // make sure it is currently activated
        $subservice = strtolower($subservice);
        if (! $this->is_active($subservice)) {
            throw new Exception("The subservice '$subservice' is currently inactive.");
        }
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
        // kill the subservice
        unset($this->$subservice);
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

    /** Enables and activates a subservice.
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
        if (isset($this->logger)) {
            $this->logger->log("Enabled subservice '$subservice'.");
        }
        return $this->get_config();
    }

    /** Disables an active subservice.
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
        $this->logger->log("Disabled subservice '$subservice'.");
        return $this->get_config();
    }

    /** Returns whether or not a particular subservice is currently active.
        expects: subservice=string
        returns: boolean */
    public function is_active($subservice) {
        // return whether or not it is currently active
        return in_array($subservice, $this->list_active());
    }

    /** Returns whether or not a particular subservice is currently enabled.
        expects: subservice=string
        returns: boolean */
    public function is_enabled($subservice) {
        // it must be active and not in the 'disabled' list
        return $this->is_active($subservice) && $this->config['subservices'][$subservice];
    }

    /** Returns whether or not a particular subservice is currently disabled.
        expects: subservice=string
        returns: boolean */
    public function is_disabled($subservice) {
        // it must be active and disabled
        return $this->is_active($subservice) && $this->config['subservices'][$subservice] == false;
    }
}

?>

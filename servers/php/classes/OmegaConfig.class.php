<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Omega configuration object. */
class OmegaConfig extends OmegaRESTful implements OmegaApi {
    public $config;
    private $config_path;
    
    public function __construct($config_path) {
        if ($config_path == '') {
            throw new Exception("Invalid config path: $config_path");
        }
        if (is_file($config_path)) {
            $lines = file_get_contents($config_path);
            if ($lines === false) {
                throw new Exception("Failed to read configuration file at '$config_path'.");
            }
            $this->config = unserialize($lines);
            if ($this->config === false) {
                throw new Exception("Failed to parse configuration file at '$config_path'.");
            }
            $this->config_path = $config_path;
        } else {
            throw new Exception("No configuration file exists at '$config_path'.");
        }
    }

    /** Save the current configuration file. */
    public function _save_config() {
        $dna = serialize($this->config);
        if ($dna === null) {
            throw new Exception("Failed to serialize configuration data.");
        }
        if (! file_put_contents($this->config_path, $dna)) {
            throw new Exception("Failed to save configuration file to '" . $this->config_path . "'.");
        }
    }

    /** Returns whether or not a configuration value exists.
        expects: path=string
        returns: boolean */
    public function exists($path) {
        if (! is_array($path)) {
            $path = preg_split('/[\.\/]/', $path);
        }
        if (count($path) == 0) {
            throw new Exception("Failed to split path into separate parts. This should never happen.");
        } else {
            $last = array_pop($path);
            $branch = $this->get($path);
            return isset($branch[$last]);
        }
    }

    /** Gets a specific service configuration item (e.g. 'omega.key').
        expects: path=string
        returns: undefined */
    public function get($path = '') {
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        if ($path == '') {
            return $this->config;
        } else {
            $org_path = $path;
            $path = preg_split('/[\.\/]/', $path);
            $last = array_pop($path);
            $obj = $this->config;
            // walk through the parts of the path, and check that each part exists
            $walked = array();
            foreach ($path as $item) {
                if (isset($obj[$item])) {
                    $obj = &$obj[$item];
                    $walked[] = $item;
                } else {
                    throw new Exception("Invalid config path: '$item' not found in $org_path (paths differ at \"" . join('/', $walked) . "\").");
                }
            }
            if (isset($obj[$last])) {
                return $obj[$last];
            } else {
                throw new Exception("Invalid config path: '$last' not found in $org_path.");
            }
        }
    }

    /** Appends configuration keys to a branch of the configuration tree. Returns the updated config branch.
        expects: path=string, items=object
        returns: object */
    private function append($path, $items) {
        // TODO
    }

    /** Sets (and optionally creates) a named configuration value.
        expects: path=string, value=undefined, new=boolean */
    public function set($path, $value, $new = false) {
        if ($path == '') {
            throw new Exception("Invalid config path: '$path'.");
        }
        $path = preg_split('/[\.\/]/', $path);
        // make sure we don't create a new value unless requested    
        if (! $this->exists($path)) {
            if (! $new) {
                throw new Exception("Unable to set new configuration item '$path' without force.");
            }
        }
        // finally set it, and save ourself
        $last = array_pop($path);
        $obj =& $this->config;
        // walk through the parts of the path, and check that each part exists
        foreach ($path as $part) {
            if (isset($obj[$part])) {
                $obj =& $obj[$part];
            } else {
                throw new Exception("Invalid config name: '$part'.");
            }
        }
        $obj[$last] = $value;
        $this->_save_config();
        return $this->get($path);
    }

    // TODO: method to patch object so you can update multiple keys at once

    /** Remove a configuration item. Omega configuration items cannot be removed without force.
        expects: path=string, force=boolean */
    public function rem($path, $force = false) {
        if ($path == '') {
            throw new Exception("Invalid config path to remove: '$path'.");
        }
        $path = preg_split('/[\.\/]/', $path);
        if ($path[0] == 'omega' && ! $force) {
            throw new Exception("Unable to remove values from the omega configuration.");
        }
        $last = array_pop($path);
        $obj =& $this->config;
        // walk through the parts of the path, and check that each part exists
        foreach ($path as $part) {
            if (isset($obj[$part])) {
                $obj =& $obj[$part];
            } else {
                throw new Exception("Invalid config name: '$part'.");
            }
        }
        if (! is_array($obj)) {
            throw new Exception("Invalid config path: '" . implode('/', $path) . "'.");
        }
        if (! isset($obj[$last])) {
            throw new Exception("No configuration item named '$last' found in '" . implode('/', $path) . "'.");
        }
        unset($obj[$last]);
        $this->_save_config();
    }

    /** Set the base service configuration.
        expects:
            nickname=string
            key=string
            class_dirs=array
            async=boolean
            scope=string
            location=string */
    public function set_omega_config($nickname, $key, $class_dirs, $async = false, $scope = 'global', $location) {
        // validate our input
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
        if (! is_bool($async)) {
            throw new Exception("Invalid boolean value for 'async': '$async'.");
        }
        if (! in_array($scope, array('global', 'user', 'none'))) {
            throw new Exception("Invalid service scope: '$scope'.");
        }
        if (! preg_match(OmegaTest::file_path_re, $location)) {
            throw new Exception("Invalid service location: $location.");
        }
        // set and save the new values
        $this->set('omega', array(
            'key' => $key,
            'class_dirs' => $class_dirs,
            'async' => $async,
            'scope' => $scope,
            'cookie_name' => 'OMEGA_SESSION_ID',
            'location' => $location
        ));
    }
}

?>

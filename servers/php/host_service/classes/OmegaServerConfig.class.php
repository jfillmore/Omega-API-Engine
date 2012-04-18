<?php

/* omega - PHP host service
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

/** Omega server configuration options. */
class OmegaServerConfig extends OmegaRESTful implements OmegaApi {

    public function _get_handlers() {
        return array(
            'get' => array(
                '/:service/exists/*path' => 'exists',
                '/:service/*path' => 'get'
            ),
            'post' => array(
                '/:service/*path' => 'set'
            ),
            'delete' => array(
                '/:service/*path' => 'remove'
            )
        );
    }

    /** Returns configuration data for the specified service.
        expects: service=string, path=string
        returns: object */
    public function get($service, $path = '') {
        $shed = new OmegaFileShed(OmegaConstant::data_dir);
        $config = $shed->get($service, 'config');
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        if ($path == '') {
            return $config;
        } else {
            $path = preg_split('/[\.\/]/', $path);
            $last = array_pop($path);
            $obj = $config;
            // walk through the parts of the path, and check that each part exists
            foreach ($path as $item) {
                if (isset($obj[$item])) {
                    $obj = $obj[$item];
                } else {
                    throw new Exception("Invalid config path: '$item' not found in '" . join('.', $path) . "'.");
                }
            }
            if (isset($obj[$last])) {
                return $obj[$last];
            } else {
                throw new Exception("Invalid config path: '$last' not found in '" . join('.', $path) . "'.");
            }
        }
    }

    /** Update configuration information for a service.
        expects: service=string, path=string, value=undefined, new=boolean
        returns: undefined */
    public function set($service, $path, $value, $new = false) {
        $shed = new OmegaFileShed(OmegaConstant::data_dir);
        $config = $shed->get($service, 'config');
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        $path = preg_split('/[\.\/]/', $path);
        // make sure we don't create a new value unless requested    
        if (! $this->exists($service, $path)) {
            if (! $new) {
                throw new Exception("Unable to set new configuration item '$path' without force.");
            }
        }
        if ($path == '') {
            // replace the entire config
            $shed->store($service, 'config', $value);
            $full_path = $path;
        } else {
            $full_path = $path;
            $last = array_pop($path);
            $obj =& $config;
            // walk through the parts of the path, and check that each part exists
            foreach ($path as $part) {
                if (isset($obj[$part])) {
                    $obj =& $obj[$part];
                } else {
                    throw new Exception("Invalid config name: '$part'.");
                }
            }
            // and store our data where we ended up walking to
            $obj[$last] = $value;
            $shed->store($service, 'config', $config);
        }
        return $this->get($service, $full_path);
    }

    /** Returns whether or not a configuration value exists.
        expects: path=string
        returns: boolean */
    public function exists($service, $path) {
        if (! is_array($path)) {
            $path = preg_split('/[\.\/]/', $path);
        }
        if (count($path) == 0) {
            throw new Exception("Failed to split path into separate parts. This should never happen.");
        } else {
            $last = array_pop($path);
            $branch = $this->get($service, $path);
            return isset($branch[$last]);
        }
    }

    /** Remove a configuration item. Omega configuration items cannot be removed.
        expects: path=string */
    public function remove($path) {
        if ($path == '') {
            throw new Exception("Invalid config path to remove: '$path'.");
        }
        $path = preg_split('/[\.\/]/', $path);
        if ($path[0] == 'omega') {
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

}

?>

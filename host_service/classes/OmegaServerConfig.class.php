<?php

/* omega - PHP host API
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

/** Omega server configuration options. */
class OmegaServerConfig extends OmegaRESTful {

    public function _get_handlers() {
        return array(
            'get' => array(
                '/:api/exists/*path' => 'exists',
                '/:api/*path' => 'get'
            ),
            'post' => array(
                '/:api/*path' => 'set'
            ),
            'put' => array(
                '/:api/*path' => 'update'
            ),
            'delete' => array(
                '/:api/*path' => 'remove'
            )
        );
    }

    /** Returns configuration data for the specified API.
        expects: api=string, path=string
        returns: object */
    public function get($api, $path = '') {
        $shed = new OmegaFileShed(OmegaConstant::data_dir());
        $config = $shed->get($api, 'config');
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

    /** Update an existing configuation item.
    @param string $api API name
    @param string $path API config path (e.g. 'db/pass')
    @param unknown $value Value (string, int, array, etc) to be stored in the config.
    @return array Returns updated configuration info. */
    public function update($api, $path, $value) {
        return $this->set($api, $path, $value, false);
    }

    /** Update configuration information for an API.
        expects: api=string, path=string, value=undefined, new=boolean
        returns: undefined */
    public function set($api, $path, $value, $new = true) {
        $shed = new OmegaFileShed(OmegaConstant::data_dir());
        $config = $shed->get($api, 'config');
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        $path = preg_split('/[\.\/]/', $path);
        // make sure we don't create a new value unless requested    
        if (! $this->exists($api, $path)) {
            if (! $new) {
                throw new Exception("Unable to create new configuration item '" . join('/', $path) . "' without specifying that this is a new option.");
            }
        }
        if ($path == '') {
            // replace the entire config
            $shed->store($api, 'config', $value);
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
            $shed->store($api, 'config', $config);
        }
        return $this->get($api, $full_path);
    }

    /** Returns whether or not a configuration value exists.
        expects: path=string
        returns: boolean */
    public function exists($api, $path) {
        if (! is_array($path)) {
            $path = preg_split('/[\.\/]/', $path);
        }
        if (count($path) == 0) {
            throw new Exception("Failed to split path into separate parts. This should never happen.");
        } else {
            $last = array_pop($path);
            $branch = $this->get($api, $path);
            return isset($branch[$last]);
        }
    }

    /** Remove a configuration item. Omega configuration items cannot be removed without force. Returns the removed item.
        expects: api=string, path=string, force=boolean */
    public function remove($api, $path, $force) {
        $shed = new OmegaFileShed(OmegaConstant::data_dir());
        if ($path == '') {
            throw new Exception("Invalid config path to remove: '$path'.");
        }
        $path = preg_split('/[\.\/]/', $path);
        if ($path[0] == 'omega' && ! $force) {
            throw new Exception("Unable to remove values from the omega configuration.");
        }
        $last = array_pop($path);
        $config = $shed->get($api, 'config');
        $obj =& $config;
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
        $removed = $obj[$last];
        unset($obj[$last]);
        $shed->store($api, 'config', $config);
        return $removed;
    }

}

?>

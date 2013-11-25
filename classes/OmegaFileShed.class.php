<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Stores JSON objects by index within a folder. */
class OmegaFileShed {
    private $location;

    public function __construct($location) {
        // make sure the location exists, and save it
        $this->bind_location($location);
    }

    public function ls($dir = null, $type = null) {
        if ($dir === null) {
            $dir = $this->get_location();
        }
        $dir_handle = @opendir($dir);
        if ($dir_handle === false) {
            throw new Exception("Failed to open '$dir' to get folder contents.");
        }
        $result = array();
        while (($item = readdir($dir_handle)) !== false) {
            // ignore hidden files and folders
            if (substr($item, 0, 1) == '.') {
                continue;
            }
            $is_dir = is_dir("$dir/$item");
            $is_file = is_file("$dir/$item");
            if ($type == 'dirs') {
                if ($is_dir) $result[] = $item;
            } else if ($type == 'files') {
                if ($is_file) $result[] = $item;
            } else {
                $result[] = $item;
            }
        }
        return $result;
    }

    /** Binds the file data shed API to a new directory.
        expects: location=string */
    private function bind_location($location) {
        if (is_dir($location)) {
            $this->location = $location;
        } else {
            throw new Exception("The location '$location' does not exist.");
        }
    }

    /** Returns the directory the shed stores data in.
        returns: string */
    public function get_location() {
        return $this->location;
    }

    /** Checks whether or not the specified name is a validly formatted key name.
        expects: name=string
        returns: boolean */
    public function valid_key_name($name) {
        return preg_match(OmegaTest::file_name_re, $name);
    }

    /** Checks whether or not the specified name is a validly formatted bin name.
        expects: name=string
        returns: boolean */
    public function valid_bin_name($name) {
        return preg_match(OmegaTest::file_path_re, $name);
    }

    /** Returns object containing the contents of the shed.
        returns: object */
    public function get_shed() {
        $bins = array();
        foreach ($this->list_bins() as $bin) {
            $bins[$bin] = $this->get_bin($bin);
        }
        return $bins;
    }

    /** Remove all the files and directories from the location. Symolic links will NOT be followed.
        expects: rmdir_root=boolean */
    public function clear_shed($rmdir_root = false) {
        // dump each bin-- but in reverse order so we can get the deepest bins first
        foreach (array_reverse($this->list_bins('/')) as $bin) {    
            $this->dump_bin($bin);
        }
        // and any data in the root too
        if ($rmdir_root) {
            $this->dump_bin('/');
        } else {
            foreach ($this->list_keys('/') as $key) {
                $this->forget('/', $key);
            }
        }
    }

    /** Returns an object containing the contents of a particular bin.
        expects: bin=string
        returns: object */
    public function get_bin($bin) {
        $data = array();
        foreach ($this->list_keys($bin) as $key) {
            $data[$key] = $this->get($bin, $key);
        }
        return $data;
    }

    /** Deletes all of the data from the specified bin.
        expects: bin=string */
    public function dump_bin($bin) {
        foreach ($this->list_keys($bin) as $key) {
            $this->forget($bin, $key);
        }
        // remove the folder too
        $result = @rmdir($this->location . "/$bin");
        if ($result === false) {
            throw new Exception("Failed to remove directory '" . $this->location . "/$bin'.");
        }
    }

    /** and stores data in the specified bin.
        expects: bin=string, key=string, data=object, no_clobber=boolean */
    public function store_raw($bin, $key, $data, $no_clobber = false) {
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        // auto create the bin if needed
        if (! is_dir($this->location . "/$bin")) {
            if (! mkdir($this->location . "/$bin", 0777, true)) {
                throw new Exception("Failed to create new bin '$bin'.");
            }
        }
        // and save it
        if (file_exists($this->location . "/$bin/$key") && $no_clobber == true) {
            throw new Exception("Refusing to overwrite '$bin/$key'; data already exists and must not be clobbered.");
        }
        if (! file_put_contents($this->location . "/$bin/$key", $data)) {
            throw new Exception("Failed to save data to '$bin/$key'.");
        }
    }

    /** Encodes and stores data in the specified bin.
        expects: bin=string, key=string, data=object, no_clobber=boolean, encoding=string */
    public function store($bin, $key, $data, $no_clobber = false, $encoding = 'json') {
        // encode our data
        if ($encoding == 'json') {
            $dna = json_encode($data);
            if ($dna === false) {
                throw new Exception("Failed to encode data being stored in '$bin/$key'.");
            }
        } else if ($encoding == 'php') {
            $dna = serialize($data);
        } else {
            throw new Exception("Invalid encoding type: '$encoding'.");
        }
        return $this->store_raw($bin, $key, $dna, $no_clobber);
    }

    /** Returns whether or not anything exists for the specified bin/key.
        expects: bin=string, key=string
        returns: boolean */
    public function exists($bin, $key) {
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        return is_file($this->location . "/$bin/$key");
    }

    /** Retrieves the specified data from a bin. If the data was not found an exception will be thrown.
        expects: bin=string, key=string
        returns: undefined */
    public function get_raw($bin, $key) {
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        if (is_file($this->location . "/$bin/$key")) {
            $data = file_get_contents($this->location . "/$bin/$key");
            if ($data === false) {
                throw new Exception("Failed to read data from '$bin/$key'.");
            }
            return $data;
        } else {
            throw new Exception("No data exists at '$bin/$key'.");
        }
    }

    /** Retrieves and decodes the specified data from a bin. If the data was not found an exception will be thrown.
        expects: bin=string, key=string
        returns: undefined */
    public function get($bin, $key, $encoding = 'json') {
        $data_dna = $this->get_raw($bin, $key);
        if ($encoding == 'json') {
            $data = json_decode($data_dna, true);
            if ($data_dna === null && $data_dna !== 'null') {
                throw new Exception("Failed to decode data in '$bin/$key'.");
            }
        } else if ($encoding == 'php') {
            $data = unserialize($data_dna);
            if ($data_dna === false && $data_dna !== 'false') {
                throw new Exception("Failed to decode data in '$bin/$key'.");
            }
        } else {
            throw new Exception("Invalid encoding: '$encoding'.");
        }
        return $data;
    }

    /** Deletes data from the specified bin. If the data didn't exist an exception will NOT be thrown.
        expects: bin=string, key=string */
    public function forget($bin, $key) {
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        if (file_exists($this->location . "/$bin/$key")) {
            if (! @unlink($this->location . "/$bin/$key")) {
                throw new Exception("Failed to delete data '$bin/$key'.");
            }
        }
    }

    /** Locks data from being overwritten until unlocked.
        expects: bin=string, key=string, block=boolean */
    public function lock($bin, $key, $block = false) {
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        $data_path = $this->location . '/' . $bin . '/' . $key;
        if (! is_file($data_path)) {
            throw new Exception("Invalid no data is located at '$data_path'.");
        }
        $fh = fopen($data_path, 'r');
        if ($fh === false) {
            throw new Exception("Failed to open data at '$data_path'.");
        }
        if ($block) {
            $result = flock($fh, LOCK_EX);
        } else {
            $result = flock($fh, LOCK_EX | LOCK_NB);
        }
        if ($result === false) {
            throw new Exception("Failed to get a lock on '$data_path'.");
        }
    }

    /** Unlocks data from being written.
        expects: bin=string, key=string */
    public function unlock($bin, $key) {
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        $data_path = $this->location . '/' . $bin . '/' . $key;
        if (! is_file($data_path)) {
            throw new Exception("Invalid data specified by bin '$bin' and key '$key'.");
        }
        $fh = fopen($data_path, 'r');
        if ($fh === false) {
            throw new Exception("Failed to open data at '$data_path'.");
        }
        if (flock($fh, LOCK_UN) === false) {
            throw new Exception("Failed to unlock '$data_path'.");
        }
    }

    /** Lists the bins in the shed.
        expects: bin=string
        returns: array */
    public function list_bins($bin = '') {
        if ($bin != '' && ! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        try {
            $bins = array();
            foreach ($this->ls($this->location . '/' . $bin, 'dirs') as $dir) {
                $bins[] = "$bin/$dir";
                foreach ($this->list_bins("$bin/$dir") as $sub_bin) {
                    $bins[] = "$sub_bin";
                }
            }
        } catch (Exception $e) {
            $bins = array();
        }
        return $bins;
    }

    /** Lists the data keys within a particular bin.
        returns: array */
    public function list_keys($bin) {
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (is_dir($this->location . '/' . $bin)) {
            $keys = $this->ls($this->location . '/' . $bin, 'files');
            return $keys;
        } else {
            return array();
        }
    }
}


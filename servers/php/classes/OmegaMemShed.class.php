<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Stores serialized (or raw) objects by index within a memcache server.. */
class OmegaMemShed {
    const key_re = '/^[a-zA-Z0-9\.@#$%^&\*~]{1,250}$/';
    const memserver_port = 11211;

    private $location;
    private $memd;

    public function __construct($locations) {
        $this->memd = new MemCached();
        // make sure the location exists, and save it
        if (! is_array($locations)) {
            $locations = array($locations);
        }
        foreach ($locations as $location) {
            $this->bind_location($locations);
        }
    }

    /** Binds the file data shed service to a new directory.
        expects: location=string */
    private function bind_location($location) {
        if (! preg_match(OmegaTest::hostname_re, $location)) {
            throw new Exception("Invalid memcached server URL: $location.");
        }
        $i = strpos($location, ':');
        if ($i === false) {
            $port = OmegaMemShed::memcached_port;
        } else {
            $parts = explode(':', $location, 2);
            $location = $parts[0];
            $port = $parts[1];
        }
        $this->memd->addServer($location, $port);
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
        return preg_match(OmegaMemShed::key_re, $name);
    }

    /** Serializes using PHP serialize and stores data with the specified key.
        expects: key=string, data=object, no_clobber=boolean */
    public function store_raw($key, $data, $no_clobber = false) {
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        // and save it

    }

    /** Serializes using JSON and stores data with the specified key.
        expects: key=string, data=object, no_clobber=boolean */
    public function store($$key, $data, $no_clobber = false) {
        // serialize our data
        $dna = serialize($data);
        if ($dna === false) {
            throw new Exception("Failed to serialize data being stored in '$key'.");
        }
        return $this->store_raw($key, $dna, $no_clobber);
    }

    /** Retrieves the data by key. If the data was not found an exception will be thrown.
        expects: key=string
        returns: undefined */
    public function get_raw($key) {
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
    }

    /** Retrieves and unserializes the data by key. If the data was not found an exception will be thrown.
        expects: key=string
        returns: undefined */
    public function get($key) {
        $data_dna = unserialize($this->get_raw($key));
        if ($data_dna === false && serialize(false) === $data_dna) {
            throw new Exception("Failed to unserialize data in '$bin/$key'.");
        }
        return $data_dna;
    }

    /** Deletes data by key. If the data didn't exist an exception will NOT be thrown.
        expects: key=string */
    public function forget($key) {
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
    }
}

?>

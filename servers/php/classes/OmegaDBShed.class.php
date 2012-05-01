<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Stores serialized (or raw) objects within a database. */
class OmegaDBShed {
    private $db;
    private $table;
    private $block_delay = 2; // how many seconds to delay
    private $block_retries = 10; // how many retries to give up on locking data in block mode

    public function __construct($table, $hostname, $username, $password, $dbname, $type) {
        $this->db = new OmegaDatabase(
            $hostname,
            $username,
            $password,
            $dbname,
            $type
        );
        $this->table = $table;
    }

    public function ls($bin = '', $type = null) {
        global $om;
        // e.g. convert 'foo//bar/bob' to 'foo/bar/bob/'
        if ($bin != '') {
            $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        }
        $sql = "SELECT * FROM " . $this->table . " WHERE `bin` LIKE '$bin%'";
        $rows = $this->db->query($sql);
        $result = array();
        foreach ($rows as $row) {
            // is this a value in the requested bin or a sub-bin?
            if ($row['bin'] == $bin) {
                $item = $row['key'];
                $is_bin = false;
            } else {
                $item = substr($row['bin'], strlen($bin));
                $is_bin = true;
            }
            if ($type == 'bins') {
                if ($is_bin) $result[] = $item;
            } else if ($type == 'keys') {
                if (! $is_bin) $result[] = $item;
            } else {
                $result[] = $item;
            }
        }
        return $result;
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
    public function clear_shed() {
        $this->db->query("DELETE FROM `" . $this->table . "`");
    }

    /** Returns an object containing the contents of a particular bin.
        expects: bin=string, meta=boolean
        returns: object */
    public function get_bin($bin, $meta = false, $raw = false) {
        global $om;
        $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        $key = $this->db->escape($key);
        $sql = "SELECT * FROM `" . $this->table . "` WHERE `bin` = '$bin'";
        $rows = $this->db->query($sql);
        $data = array();
        foreach ($rows as $row) {
            if ($meta) {
                $data[$row['key']] = $row;
            } else {
                $data = $row['data'];
                if (! $raw) {
                    $data = $this->unserialize($data);
                }
                $data[$row['key']] = $data;
            }
        }
        return $data;
    }

    /** Deletes all of the data from the specified bin.
        expects: bin=string */
    public function dump_bin($bin = '') {
        global $om;
        if ($bin != '') {
            $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        }
        $this->db->query("DELETE FROM `" . $this->table . "` WHERE `bin` LIKE '$bin%'");
    }

    /** and stores data in the specified bin.
        expects: bin=string, key=string, data=object, no_clobber=boolean */
    public function store_raw($bin, $key, $data, $no_clobber = false) {
        global $om;
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        $key = $this->db->escape($key);
        $data = $this->db->escape($data);
        // see if we exist already
        $sql = "SELECT * FROM `" . $this->table . "` WHERE `bin` = '$bin' AND `key` = '$key'";
        $rows = $this->db->query($sql);
        $exists = count($rows) > 0;
        if ($exists) {
            if ($no_clobber) {
                throw new Exception("Refusing to overwrite '$key' in '$bin'; data already exists.");
            }
            if ($exists[0]['locked']) {
                throw new Exception("Unable to update '$bin/$key': data is currently locked.");
            }
        }
        if ($exists) {
            $sql = "UPDATE `" . $this->table . "` SET `data` = '$data', `modified` = NOW() WHERE `bin` = '$bin' AND `key` = '$key'";
        } else {
            $sql = "INSERT INTO `" . $this->table . "` (`bin`, `key`, `data`, `modified`) VALUES ('$bin', '$key', '$data', NOW())";
        }
        $this->db->query($sql);
    }

    /** Serializes and stores data in the specified bin.
        expects: bin=string, key=string, data=object, no_clobber=boolean */
    public function store($bin, $key, $data, $no_clobber = false) {
        // serialize our data
        $dna = serialize($data);
        if ($dna === false && $data !== false) {
            throw new Exception("Failed to serialize data being stored in '$bin/$key'.");
        }
        return $this->store_raw($bin, $key, $dna, $no_clobber);
    }

    /** Retrieves the specified data from a bin. If the data was not found an exception will be thrown.
        expects: bin=string, key=string, meta=boolean
        returns: undefined */
    public function get_raw($bin, $key, $meta = false) {
        global $om;
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        $key = $this->db->escape($key);
        $rows = $this->db->query("SELECT * FROM `" . $this->table . "` WHERE `bin` = '$bin' AND `key` = '$key'");
        if (count($rows)) {
            if ($meta) {
                return $rows[0];
            } else {
                return $rows[0]['data'];
            }
        } else {
            throw new Exception("No data exists for '$key' in '$bin'.");
        }
    }

    /** Returns whether or not anything exists for the specified bin/key.
        expects: bin=string, key=string
        returns: boolean */
    public function exists($bin, $key) {
        global $om;
        $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        $key = $this->db->escape($key);
        $sql = "SELECT COUNT(*) AS count FROM `" . $this->table . "` WHERE `bin` = '$bin' AND `key` = '$key'";
        $rows = $this->db->query($sql);
        return $rows[0]['count'] > 0;
    }

    /** Retrieves and unserializes the specified data from a bin. If the data was not found an exception will be thrown.
        expects: bin=string, key=string, meta=boolean
        returns: undefined */
    public function get($bin, $key, $meta = false) {
        $row = $this->get_raw($bin, $key, true);
        $row['data'] = $this->unserialize($row['data']);
        if ($meta) {
            return $row;
        } else {
            return $row['data'];
        }
    }

    private function unserialize($data) {
        $data_dna = unserialize($data);
        if ($data_dna === false && serialize(false) !== $data_dna) {
            throw new Exception("Failed to unserialize data.");
        }
        return $data_dna;
    }

    /** Deletes data from the specified bin. If the data didn't exist an exception will NOT be thrown.
        expects: bin=string, key=string */
    public function forget($bin, $key) {
        global $om;
        if (! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if (! $this->valid_key_name($key)) {
            throw new Exception("Invalid key name: '$key'.");
        }
        $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        $key = $this->db->escape($key);
        $this->db->query("DELETE FROM `" . $this->table . "` WHERE `bin` = '$bin' AND `key` = '$key'");
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
        $row = $this->get_raw($bin, $key, true);
        if ($row['locked']) {
            if ($block) {
                // wait until available; fail after too many tries
                $retries = 0;
                while ($retries < $this->block_retries && $row['locked']) {
                    sleep($this->block_delay);
                    $row = $this->get_raw($bin, $key, true);
                }
                // never got an unlocked row? QQ
                if ($row['locked']) {
                    throw new Exception("Unable to lock '$bin/$key'; data is already locked.");
                }
            } else {
                throw new Exception("Unable to lock '$bin/$key'; data is already locked.");
            }
        }
        // use the pre-cleaned values for the bin/key
        $bin = $row['bin'];
        $key = $row['key'];
        $this->db->query("UPDATE `" . $this->table . "` SET `locked` = 1 WHERE `bin` = '$bin' AND `key` = '$key'");
        $row['locked'] = 1;
        return $row;
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
        $row = $this->get_raw($bin, $key, true);
        if (! $row['locked']) {
            throw new Exception("Unable to unlock '$bin/$key'; data is already unlocked.");
        }
        $bin = $row['bin'];
        $key = $row['key'];
        $this->db->query("UPDATE `" . $this->table . "` SET `locked` = 0 WHERE `bin` = '$bin' AND `key` = '$key'");
        $row['locked'] = 0;
        return $row;
    }

    /** Lists the bins in the shed.
        expects: bin=string
        returns: array */
    public function list_bins($bin = '') {
        global $om;
        if ($bin != '' && ! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        if ($bin != '') {
            $bin = $this->db->escape($om->_pretty_path($bin) . '/');
        }
        $sql = "SELECT `bin` FROM `" . $this->table . "` WHERE `bin` LIKE '$bin%'";
        $rows = $this->db->query($sql);
        $bins = array();
        foreach ($rows as $row) {
            $bins[] = $row['bin'];
        }
        return $bins;
    }

    /** Lists the data keys within a particular bin.
        returns: array */
    public function list_keys($bin = '') {
        if ($bin != '' && ! $this->valid_bin_name($bin)) {
            throw new Exception("Invalid bin name: '$bin'.");
        }
        $keys = $this->ls($bin, 'keys');
        return $keys;
    }

    public function test() {
        global $om;
        $log = array();
        $this->clear_shed();
        $this->store('foo//bar', 'foo1', array(1, 3, 5));
        $this->store('foo', 'foo1', array(2, 4, 8));
        $this->store('foo', 'foo2', array('a', 'b', 'c'));
        $log[] = "Stored three values in bins " . join(', ', $this->list_bins());
        $this->dump_bin('foo/bar');
        $log[] = "Dumped bin 'foo/bar', remaining bins: " . join(', ', $this->list_bins());
        $this->forget('foo', 'foo1');
        $log[] = "Deleted 'foo/bar/bar1'. Bin 'foo/bar' contains: " . var_export($this->get_bin('foo///bar'), true);
        $this->store_raw('raw', 'milk', 'moo');
        $log[] = "Stored raw data in 'raw/milk': " . $this->get_raw('raw', 'milk');
        $this->lock('foo', 'foo2');
        $log[] = "Value of 'foo/foo2' is locked with data: " . var_export($this->get('foo', 'foo2', true), true);
        try {
            $this->store('foo', 'foo2', 'asdfkljasdf');
        } catch (Exception $e) {
            $log[] = "Successfully failed to overwrite locked value of 'foo/foo2'";
        }
        $this->unlock('foo', 'foo2');
        $this->store('foo', 'foo2', '0923409234');
        $log[] = "Unlocked and updated 'foo/foo2' with value: " . $this->get('foo', 'foo2');
        $this->clear_shed();
        return $log;
    }
}

?>

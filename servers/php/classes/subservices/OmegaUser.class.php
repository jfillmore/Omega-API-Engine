<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Information about the current API user. */
class OmegaUser implements OmegaApi {
    private $username;
    private $passwords;
    private $acls;
    private $prefs; // TODO
    private $dirty;

    public function __construct($username, $password, $acls = null, $prefs = null) {
        $this->set_username($username);
        $this->passwords = array();
        $this->add_password($password);
        $this->acls = array();
        if (is_array($acls)) {
            foreach ($acls as $acl) {
                $this->add_acl($acl);
            }
        } else {
            if ($acls != null) {
                $this->add_acl($acls);
            }
        }
        //$this->set_prefs($prefs); TODO
        $this->dirty = true;
        $this->save();
    }

    public function set_username($username) {
        global $om;
        // make sure we have a validly formatted username
        if (! $om->shed->valid_key_name($username)) {
            throw new Exception("Invalid username: '$username'.");
        }
        // make sure the username isn't in use
        if (in_array($username, $om->shed->list_keys($om->subservice->authority->_localize('users')))) {
            throw new Exception("The username '$username' is already in use.");
        }
        $this->dirty = true;
        $this->username = $username;
    }

    /** Returns the user's username.
        returns: string */
    public function get_username() {
        return $this->username;
    }

    /** Write the user data to storage. */
    public function save() {
        global $om;
        if ($this->dirty) {
            $om->shed->store($om->subservice->authority->_localize('users'), $this->get_username(), $this);
            $this->dirty = false; // clear our dirty bit... that sound dirty. literally and figuratively. oh boy.
        }
    }

    /** Whether or not the user data has changes not yet written to storage.
        returns: boolean */
    public function is_dirty() {
        return $this->dirty;
    }

    /** Adds an additional password for a user. A user may have up to 50 passwords, each of 1024 characters or less.
        expects: password=string */
    public function add_password($password) {
        if (count($this->passwords) >= 50) {
            throw new Exception("Don't you think that 50 passwords are enough for one person?");
        }
        if (strlen($password) < 1024) {
            // only add it if it isn't there already
            if (! in_array($password, $this->passwords)) {
                $this->dirty = true;
                $this->passwords[] = $password;
            }
        } else {
            throw new Exception("Invalid password length. Password must be less than 1024 characters.");
        }
    }

    /** Deletes the specified password for the user. Note that a user may be left without any passwords. No return value.
        expects: password=string */
    public function del_password($password) {
        if (isset($this->passwords[$password])) {
            $this->dirty = true;
            unset($this->passwords[$password]);
        }
    }

    /** Returns a list of the user's passwords.
        returns: array */
    public function get_passwords() {
        return $this->passwords;
    }

    /* Deletes this user information. */
    public function terminate() {
        global $om;
        $om->shed->forget($om->subservice->authority->_localize('users'), $this->get_username());
    }

    /* Adds and API ACL to the specified user. Returns the ACL ID. */
    public function add_acl($acl) {
        if (! preg_match('/^!?#?(\/\/|\/)[\?a-z_A-Z\*\.]+$/', $acl)) {
            throw new Exception("Invalid ACL format: '$acl'.");
        }
        // only add it if it isn't there already
        if (! in_array($acl, $this->acls)) {
            $this->acls[] = $acl;
            $this->dirty = true;
            return count($this->acls);
        }
    }

    /* Removes the specified ACL.
        expects: acl_id=number */
    public function del_acl($acl_id) {
        if (isset($this->acls[$acl_id])) {
            $this->dirty = true;
            unset($this->acls[$acl_id]);
        }
    }

    /* Retrieves the user's ACLS.
        returns: array */
    public function get_acls() {
        return $this->acls;
    }
}

?>

<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** User management and ACL control to limit API access. */
class OmegaAuth extends OmegaRESTful {
    public $user_dir; // path for the 'shed' storage
    public $user_dir_abs; // absolute path to where user files are
    public $authed_user = null; // who the currently authorized user is that we're running as
    public $authed_username; // the username of the logged in user
    public $enabled = false;

    public function __construct() {
        $om = Omega::get();
        $this->user_dir = $om->api_name . '/auth/';
        $this->user_dir_abs = OmegaConstant::data_dir() . '/' . $this->user_dir;
        $this->enabled = $om->config->get('omega/auth', false);
        if (! OmegaLib::mkdir($this->user_dir_abs, 0700, true)) {
            throw new Exception("Failed to create omega auth directory '$this->user_dir_ab'.");
        }
    }
    
    /** Authenticates as the specified user and verifies the user's API access.
        expects: credentials=object */
    public function authenticate($credentials) {
        $om = Omega::get();
        if (is_string($credentials)) {
            // new style is base64(md5(user:pass))
            // first see if it's the api user/pass
            $api_creds = base64_encode(md5(
                $om->api_nickname . ':' .  $om->config->get('omega/key')
            ));
            if ($credentials == $api_creds) {
                $this->authed_username = $om->api_nickname;
                return;
            }
            // otherwise maybe it's an API user
            $user_bin = $this->user_dir;
            $users = $om->shed->get_bin($user_bin);
            foreach ($users as $user) {
                $username = $user->get_username();
                foreach ($user->get_passwords() as $password) {
                    if (base64_encode(md5("$username:$password")) === $credentials) {
                        // woot, we have a match!
                        $this->authed_user = $user;
                        $this->authed_username = $username;
                        return;
                    }
                }
            }
            $om->response->header_num(401);
            throw new Exception("Invalid username or password.");
        } else if (is_array($credentials)) {
            // old style credentials are a get/post json encoded object
            if (isset($credentials['username']) && isset($credentials['password'])) {
                if ($credentials['username'] == $om->api_nickname
                    && $credentials['password'] == $om->config->get('omega/key')
                    ) {
                    // success, nothing more to do
                } else {
                    // get the user and see if the password matches
                    try {
                        $user = $om->shed->get($this->user_dir, $credentials['username']); 
                    } catch (Exception $e) {
                        // invalid user
                        $om->response->header_num(401);
                        throw new Exception("Invalid username or password.");
                    }
                    if (! in_array($credentials['password'], $user->get_passwords())) {
                        // invalid password
                        $om->response->header_num(401);
                        throw new Exception("Invalid username or password.");
                    }
                    $this->authed_user = $user;
                }
                $this->authed_username = $credentials['username'];
                try {
                    if (! $this->check_access($om->request->get_api(), $this->authed_username)) {
                        $om->response->header_num(401);
                        throw new Exception("Access to '" . $om->request->get_api() . "' denied.");
                    }
                } catch (Exception $e) {
                    // not allowed to access this part of the API? Sorry!
                    $om->response->header_num(401);
                    $om->response->set_result(false);
                    $om->response->set_reason($e->getMessage());
                    // print out the request headers
                    foreach ($om->response->headers as $header_name => $header_value) {
                        header($header_name . ': ' . $header_value);
                    }
                    echo $om->response->encode();
                    return;
                }    
            }
        } else {
            $om->response->header_num(401);
            throw new Exception("Missing username or password.");
        }
    }

    /** Returns a list of users.
        returns: array */
    public function list_users() {
        $om = Omega::get();
        return $om->shed->list_keys($this->user_dir);
    }

    /** Creates a new user with the specified password and initial ACLs.
        expects: username=string, password=string, acls=array */
    public function add_user($username, $password, $acls = null) {
        $om = Omega::get();
        // see if that username is already in use, verifying the username format at the same time
        $users = $om->shed->list_keys($this->user_dir);
        if (is_array($users) && in_array($username, $users)) {
            throw new Exception("The username '$username' is already in use.");
        }
        $user = new OmegaUser($username, $password, $acls);
    }

    /** Retreives information about a user.
        expects: username=string */
    public function get_user($username) {
        $om = Omega::get();
        try {
            return $om->shed->get($this->user_dir, $username);
        } catch (Exception $e) {
            throw new Exception("Invalid user: '$username'.");
        }
    }

    /** Deletes a user.
        expects: username=string */
    public function delete_user($username) {
        $om = Omega::get();
        try {
            $om->shed->forget($this->user_dir, $username);
        } catch (Exception $e) {
            throw new Exception("Invalid user: '$username'.");
        }
    }

    /** Change from one username to another.
        expects: username=string, new_username=string */
    public function rename_user($username, $new_username) {
        $om = Omega::get();
        try {
            $user = $om->shed->get($this->user_dir, $username);
        } catch (Exception $e) {
            throw new Exception("Invalid user: '$username'.");
        }
        try {
            // change the username and save the user data to the new file
            $user->set_username($new_username);
            $user->save();
        } catch (Exception $e) {
            throw new Exception("Failed to rename user to '$new_username'.");
        }
        // delete the old user data
        $om->shed->forget($this->user_dir, $username);
    }

    /** Adds an ACL to the specified user.
        expects: username=string, acl=string */
    public function add_acl($username, $acl) {
        $user = $this->get_user($username);
        $user->add_acl($acl);
        $user->save();
    }

    /** Deletes an ACL from the specified user.
        expects: username=string, acl_id=number */
    public function del_acl($username, $acl_id) {
        $user = $this->get_user($username);
        $user->del_acl($acl_id);
        $user->save();
    }

    /** Retreives a list of ACLs for a user.
        expects: username=string
        returns: array */
    public function get_acls($username) {
        $user = $this->get_user($username);
        return $user->get_acls();
    }

    /** Verifies access to an API for a user. Returns true if the user has been granted access.
        expects: api=string, username=string
        returns: boolean */
    public function check_access($api, $username) {
        $om = Omega::get();
        // if we're logged in as the api itself then we get access to everything
        if ($username == $om->api_nickname) {
            return true;
        }
        $grant_access = false; // assume false unless we can prove otherwise without hitting a deny rule
        foreach ($this->get_acls($username) as $acl_id => $acl) {
            // translate the ACL into a regular expression we can compare against $api
            // check to see if this is a deny rule
            if (substr($acl, 0, 1) == '!') {
                $deny_acl = true;
                $acl = substr($acl, 1);
            } else {
                $deny_acl = false;
            }
            // check to see if we're referring to omega in this ACL
            if (substr($acl, 0, 1) == '#') {
                $omega_acl = true;
                $acl = substr($acl, 1);
            } else {
                $omega_acl = false;
            }
            // see if the ACL even applies to this type of request
            if (substr($api, 0, 6) == 'omega.' && ! $omega_acl) {
                continue;
            }
            // figure out anchoring
            if (strlen($acl) > 2 && substr($acl, 0, 2) == '//') {
                $regex = '/[\.\/]';
                $acl = substr($acl, 2);
            } else if (strlen($acl) > 1 && substr($acl, 0, 1) == '/') {
                $regex = '/^';
                $acl = substr($acl, 1);
            } else {
                throw new Exception("Invalid ACL for $username: '$acl'.");
            }
            // escape any slashes and question marks in the ACL
            $regex .= preg_replace('/\//', '\/', preg_replace('/\?/', '\?', $acl));
            // and if the ACL contains an asterisk, change it to '.*'
            $regex = preg_replace('/\*/', '.*', $regex);
            // now we've got something like '^foo\/bar\/.*' or 'bar\/.*'
            $regex .= '/i'; // and add our trailing slash and specify case insensitivity
            $matches = preg_match($regex, $api);
            if ($matches) {
                // if this is a deny ACL then too bad... denied!
                if ($deny_acl) {
                    return false;
                } else {
                    $grant_access = true;
                }
            }
        }
        return $grant_access;
    }
}


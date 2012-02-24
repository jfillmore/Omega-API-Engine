<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** User management and ACL control to limit API access. */
class OmegaAuthority extends OmegaSubservice {
	public $authed_user = null; // who the currently authorized user is that we're running as
	public $authed_username; // the username of the logged in user

	/** Validate the authority subservice configuration object. Throws an exception listing any errors.
		expects: config=object */
	public function validate_config($config) {
		
	}

	/** Re-authenticates as the specified user, gaining the privileges of that user.
		expects: username=string */
	public function switch_user($username) {
		global $om;
		if ($username != $om->service_name) {
			try {
				$this->authed_user = $om->shed->get($this->_localize('users', $username));
			} catch (Exception $e) {
				$om->response->header_num(404);
				throw new Exception("The username '$username' does not exist.");
			}
		}
		$this->authed_username = $this->authed_user->get_username();
	}

	/** Authenticates as the specified user and verifies the user's API access.
		expects: credentials=object */
	public function authenticate($credentials) {
		global $om;
		// first see if there is a session ID we can use to get the user info
		try {
			$cookie_name = $om->config->get('omega.cookie_name');
		} catch (Exception $e) {
			$cookie_name = 'OMEGA_SESSION_ID';
		}
		if (isset($_COOKIE[$cookie_name])) {
			$session = $om->shed->get(
				$om->service_name . '/instances/sessions',
				$_COOKIE[$cookie_name]
			);
			$credentials = $session['creds'];
		}
		if (is_string($credentials)) {
			// new style are base64(md5(user:pass))
			// first see if it's the service user/pass
			$service_name = $om->config->get('omega.nickname');
			$service_creds = base64_encode(md5( $service_name . ':' .
				$om->config->get('omega.key')));
			if ($credentials == $service_creds) {
				$this->authed_username = $service_name;
				return;
			}
			// otherwise maybe it's an API user
			$user_bin = $this->_localize('users');
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
			throw new Exception("Invalid username or password. $service_creds $credentials");
		} else if (is_array($credentials)) {
			// old style credentials are a get/post json encoded object
			if (isset($credentials['username']) && isset($credentials['password'])) {
				if ($credentials['username'] == $om->config->get('omega.nickname') && $credentials['password'] == $om->config->get('omega.key')) {
					// success, nothing more to do
				} else {
					// get the user and see if the password matches
					try {
						$user = $om->shed->get($this->_localize('users'), $credentials['username']); 
					} catch (Exception $e) {
						// invalid user
						$om->response->header_num(401);
						throw new Exception("Invalid username or password.");
					}
					if (! in_array($credentials['password'], $user->get_passwords())) {
						// invalid password :)
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
					// and return the request using the same encoding as was used
					echo $om->response->encode($om->request->get_encoding());
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
		global $om;
		return $om->shed->list_keys($this->_localize('users'));
	}

	/** Creates a new user with the specified password and initial ACLs.
		expects: username=string, password=string, acls=array */
	public function add_user($username, $password, $acls = null) {
		global $om;
		// see if that username is already in use, verifying the username format at the same time
		$users = $om->shed->list_keys($this->_localize('users'));
		if (is_array($users) && in_array($username, $users)) {
			throw new Exception("The username '$username' is already in use.");
		}
		$user = new OmegaUser($username, $password, $acls);
	}

	/** Retreives information about a user.
		expects: username=string */
	public function _get_user($username) {
		global $om;
		try {
			return $om->shed->get($this->_localize('users'), $username);
		} catch (Exception $e) {
			throw new Exception("Invalid user: '$username'.");
		}
	}

	/** Deletes a user.
		expects: username=string */
	public function delete_user($username) {
		global $om;
		try {
			$om->shed->forget($this->_localize('users'), $username);
		} catch (Exception $e) {
			throw new Exception("Invalid user: '$username'.");
		}
	}

	/** Change from one username to another.
		expects: username=string, new_username=string */
	public function rename_user($username, $new_username) {
		global $om;
		try {
			$user = $om->shed->get($this->_localize('users'), $username);
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
		$om->shed->forget($this->_localize('users'), $username);
	}

	/** Adds an ACL to the specified user.
		expects: username=string, acl=string */
	public function add_acl($username, $acl) {
		$user = $this->_get_user($username);
		$user->add_acl($acl);
		$user->save();
	}

	/** Deletes an ACL from the specified user.
		expects: username=string, acl_id=number */
	public function del_acl($username, $acl_id) {
		$user = $this->_get_user($username);
		$user->del_acl($acl_id);
		$user->save();
	}

	/** Retreives a list of ACLs for a user.
		expects: username=string
		returns: array */
	public function get_acls($username) {
		$user = $this->_get_user($username);
		return $user->get_acls();
	}

	/** Verifies access to an API for a user. Returns true if the user has been granted access.
		expects: api=string, username=string
		returns: boolean */
	public function check_access($api, $username) {
		global $om;
		if (! preg_match($om->request->api_re, $api)) {
			throw new Exception("Invalid API: '$api'.");
		}
		// if we're logged in as the service itself then we get access to everything
		if ($username == $om->config->get('omega.nickname')) {
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

/* // TODO
- audit to show recent history, list of rights, mapping of rights to actual methods w/ docs
- method to delegate temp rights (e.g. a limited-use, limited-life user with certain ACLs)
- 

*/


?>

<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Omega exists as a global object ($om or $omega) and provides an API to assist omega services (e.g. provide subservices like ACLs and logging). */
class Omega extends OmegaRESTful implements OmegaApi {
	public $session;
	private $save_service_state; // whether or not to save the state of the service after each request
	private $session_id; // used when scope = 'session'
	private $restful; // whether or not the API is RESTful

	// service information
	public $api; // alias to $this->service
	public $service; // the current hosted service e.g. 'new Marian(...);'
	public $service_name; // the service's name, used to initialize the client service, e.g. 'Marian'
	
	// internal omega branches
	public $config; // configuration information about this service
	public $request; // the request that is being processed
	public $response; // the response that will be sent back
	public $shed; // the storage engine
	public $subservice; // subservices to help out

	public function __construct($service_name) {
		if (! preg_match(OmegaTest::word_re, $service_name)) {
			throw new Exception("Invalid service name format: '$service_name'.");
		}
		$this->service_name = $service_name;
		// make sure we have a directory to store the data in
		if (! is_dir(OmegaConstant::data_dir)) {
			if (! @mkdir(OmegaConstant::data_dir)) {
				throw new Exception("Failed to create service data storage directory '" . OmegaConstant::data_dir . "'.");
			}
		}
		// instantiate the storage engine
		$this->shed = new OmegaFileShed(OmegaConstant::data_dir);
		// set up the configuration file for this service
		try {
			$this->config = new OmegaConfig($this->shed->get_location() . '/' . $this->service_name . "/config");
		} catch (Exception $e) {
			throw new Exception("Unknown service: '$service_name'.");
		}
		// if we're an async service then don't save the state so we can execute requests simultaneously
		if ($this->config->get('omega.async') == true) {
			$this->save_service_state = false;
		} else {
			$this->save_service_state = true;
		}
	}

	public function _get_routes() {
		return  array(
			'api' => 'api',
			'request' => 'request',
			'response' => 'response',
			'subservice' => 'subservice',
			'config' => 'config'
		);
	}

	public function _get_handlers() {
		return array(
			'GET' => array(
			),
			'POST' => array(
				'restart_service' => 'restart_service'
			),
			'PUT' => array(
			),
			'DELETE' => array(
			)
		);
	}

	/** Returns whether or not the service API is restful.
		returns: boolean */
	public function is_restful() {
		return $this->restful;
	}

	/** Returns the name of the omega user. */
	public function whoami() {
		if ($this->subservice->is_enabled('authority')) {
			return $this->subservice->authority->authed_username;
		} else {
			return 'Guest';
		}
	}

	public function _init_service() {
		// make sure we've got the parameters we need
		$class_name = $this->service_name;
		$r_class = new ReflectionClass($class_name);
		$r_method = $r_class->getMethod('__construct');
		$params = $this->request->_get_method_params($r_method);
		$service = $r_class->newInstanceArgs($params);
		if ($service !== null) {
			$this->service = $service;
			$this->api = $this->service;
		} else {
			throw new Exception("Failed to initialize $class_name.");
		}
		if (@is_subclass_of($this->service, 'OmegaRESTful')) {
			$this->restful = true;
		} else {
			$this->restful = false;
		}
	}

	/** Just another day in the life of an omega server. */
	public function _do_the_right_thing() {
		// capture any crap that PHP leaks through (e.g. warnings on functions) or that the user intentionally leaks
		ob_start();
		// load the subservice manager first so the request and response can do subservice-dependant stuff
		$this->subservice = new OmegaSubserviceManager();
		// get a response ready
		$this->response = new OmegaResponse();
		// prepare to generate the request to yield a response
		$this->request = new OmegaRequest();
		if ($_SERVER['HTTP_CONTENT_TYPE'] === 'application/json') {
			$this->response->set_encoding('json');
		} else {
			// default our response encoding to be the same as what we used with the request
			$this->response->set_encoding($this->request->get_encoding());
		}
		$service_nickname = $this->config->get('omega.nickname');

		// if authentication is enabled then check access now
		if ($this->subservice->is_enabled('authority')) {
			$this->subservice->authority->authenticate($this->request->get_credentials());
		}

		// load in our service or start a new one if this is our first load of this service
		$class_name = $this->service_name;
		// make sure this service exists and is enabled
		$shed = new OmegaFileShed(OmegaConstant::data_dir);
		$os_server_config = $shed->get('OmegaServer/services', 'config');
		if (! isset($os_server_config['services'][$this->service_name]) || $os_server_config['services'][$this->service_name] == false) {
			throw new Exception("Unknown or unavailable service : '" . $this->service_name . "'.");
		}
		// load/create the service instance, if needed
		if ($this->request->get_api() == '?' || ($this->request->get_api() === $service_nickname && $this->request->is_query())) {
			$this->save_service_state = false;
			$this->service = null;
			$this->api = $this->service;
			$this->session = null;
		} else {
			$this->_load_session();
		}
		// try to answer the request
		if ($this->request->get_api() == $service_nickname && ! $this->request->is_query()) {
			// we're just initializing, so nothing to do really
			if ($this->request->get_encoding() == 'raw') {
				// perform a redirection unless we've already got a 'Location' header set from the service
				if (! in_array('Location', array_keys($this->response->headers))) {
					$this->response->header(
						'Location',
						$this->config->get("omega.location"),
						true
					);
				}
			}
			$this->response->set_result(true);
		} else {
			try {
				$this->response->set_data($this->request->_answer());
				$this->response->set_result(true);
				if ($this->subservice->is_enabled('logger') && isset($this->subservice->logger)) { // gotta also be sure the subservice is initialized too, otherwise an error on enabling will happen
					// don't log boring things like queries or APIs about logging
					if (! $this->request->is_query() && strpos($this->request->get_api(), 'omega.subservice.logger') === false) {
						$this->subservice->logger->commit_log(true);
					}
				}
			} catch (OmegaException $e) {
				// if we're picking up an omega exception include the data
				$data = array(
					'data' => $e->data,
					'backtrace' => $this->_clean_trace($e->getTrace())
				);
				/* // API framework stuff?
				array_pop($data['backtrace']);
				array_pop($data['backtrace']);
				array_pop($data['backtrace']);
				*/
				$this->response->set_result(false);
				if ($this->subservice->is_enabled('logger')) {
					$this->subservice->logger->log_data('api_error', $e->getMessage());
					$this->subservice->logger->log_data('api_trace', $data['backtrace']);
					if ($e->data !== null) {
						$this->subservice->logger->log_data('data', $e->data);
					}
					if ($e->comment !== null) {
						$this->subservice->logger->log_data('error_comment', $e->comment);
					}
				}
			} catch (Exception $e) {
				$data = array(
					'backtrace' => $this->_clean_trace($e->getTrace())
				);
				/* // API framework stuff?
				array_pop($data['backtrace']);
				array_pop($data['backtrace']);
				array_pop($data['backtrace']);
				*/
				$this->response->set_result(false);
			}
			if (! $this->response->get_result()) {
				$this->response->set_reason($e->getMessage());
				// pop off the last few items, as they are framework functions
				$this->response->set_data($data);
				if ($this->subservice->is_enabled('logger')) {
					$this->subservice->logger->log($e->getMessage(), false);
					$this->subservice->logger->commit_log(false);
				}
			}
		}
		// unlock and save the service instance if needed
		if ($this->save_service_state && $this->config->get('omega.scope') != 'none') {
			$this->_save_session(false);
		}
		// see if we spilled anywhere... if so, pick it up to ensure we have a clean stream
		$spillage = ob_get_contents();
		ob_end_clean();
		if (strlen($spillage) > 0) {
			$this->response->set_spillage($spillage);
		}
		// print out the request headers
		foreach ($this->response->headers as $header_name => $header_value) {
			header($header_name . ': ' . $header_value);
		}
		// if we have a session ID respond with that cookie
		if ($this->session_id !== null) {
			$cookie_path = $this->response->get_cookie_path();
			setcookie('OMEGA_SESSION_ID', $this->session_id, 0, $cookie_path);
		}
		// if we failed and still have a 2xx status code then you know the drill
		if ((! $this->response->get_result()) &&
			$this->response->is_2xx()) {
			$this->response->header_num(500);
		}
		// set our status code (e.g. 200, 404, etc)
		header($this->response->get_status());
		// and return the request with the requested encoding
		$response = $this->response->encode($this->response->get_encoding());
		echo $response;
	}
	
	public function _load_session() {
		$scope = $this->config->get('omega.scope');
		$this->session_id = null;
		if ($scope == 'global') {
			// global scope means every request is served by the same server
			try {
				$this->service = $this->shed->get($this->service_name . '/instances', 'global');
				$this->api = $this->service;
			} catch (Exception $e) {
				// failed to get it? start one up fresh
				$this->_init_service();
				// and save it
				$this->shed->store($this->service_name . '/instances', 'global', $this->service);
			}
			// if we can serve requests asyncronously then don't lock the instance file
			if ($this->save_service_state) {
				$this->shed->lock($this->service_name . '/instances', 'global');
			}
		} else if ($scope == 'user') {
			// user scope means each user has their own private service instance
			// only supported if the authority service is available
			if (! $this->subservice->is_enabled('authority')) {
				//throw new Exception("Unable to run service at the 'user' level without the authority service.");
				$username = 'nobody';
			} else {
				$username = $this->subservice->authority->authed_username;
			}
			try {
				$this->service = $this->shed->get($this->service_name . '/instances/users', $username);
				$this->api = $this->service;
			} catch (Exception $e) {
				// failed to get it? start one up fresh
				$this->_init_service();
				// and save it
				$this->shed->store($this->service_name . '/instances/users', $username, $this->service);
			}
			// if we can serve requests asyncronously then don't lock the instance file
			if ($this->save_service_state) {
				$this->shed->lock($this->service_name . '/instances/users', $username);
			}
		} else if ($scope == 'session') {
			// look for a OMEGA_SESSION_ID cookie to see if we have a session going-- if so, resume it
			if (isset($_COOKIE['OMEGA_SESSION_ID'])) {
				$session_id = $_COOKIE['OMEGA_SESSION_ID'];
			} else {
				$session_id = '';
			}
			// if the client is requesting the service itself then consider that a request for a new session
			// unless they're explicitly initializing the service-- if so, let it be done
			;
			if ($session_id != ''
				&& file_exists($this->shed->get_location() . '/' . $this->service_name . '/instances/sessions/' . $session_id)
				&& $this->request->get_api() != $this->config->get('omega.nickname')) {
				$this->session = $this->shed->get($this->service_name . '/instances/sessions', $session_id);
				$this->service = $this->session['service'];
				$this->api = $this->service;
				$this->session_id = $_COOKIE['OMEGA_SESSION_ID'];
			} else {
				$this->_create_session();
				$this->_init_service();
				$this->session['service'] = $this->service;
				$this->shed->store($this->service_name . '/instances/sessions', $this->session_id, $this->session);
			}
			if ($this->save_service_state) {
				$this->shed->lock($this->service_name . '/instances/sessions', $this->session_id);
			}
		} else if ($scope == 'none') {
			// each service is served fresh within 5 minutes or its free
			$this->_init_service();
		} else {
			throw new Exception("Invalid service scope '" . $scope . "'.");
		}
		if (@is_subclass_of($this->service, 'OmegaRESTful')) {
			$this->restful = true;
		} else {
			$this->restful = false;
		}
	}

	public function _save_session($files_locked = true) {
		// save our state
		if ($this->config->get('omega.scope') == 'global') {
			// unlock and save the service instance
			if (! $files_locked) {
				$this->shed->lock($this->service_name . '/instances', 'global');
			}
			$this->shed->store($this->service_name . '/instances', 'global', $this->service);
			if (! $files_locked) {
				$this->shed->unlock($this->service_name . '/instances', 'global');
			}
		} else if ($this->config->get('omega.scope') == 'user') {
			// only supported if the authority service is available
			if ($this->subservice->is_enabled('authority')) {
				throw new Exception("Unable to run service at the 'user' level without the authority service.");
			}
			$username = $this->subservice->authority->authed_username;
			if (! $files_locked) {
				$this->shed->lock($this->service_name . '/instances/users', $username);
			}
			$this->shed->store($this->service_name . '/instances/users', $username, $this->service);
			if (! $files_locked) {
				$this->shed->unlock($this->service_name . '/instances/users', $username);
			}
		} else if ($this->config->get('omega.scope') == 'session') {
			if (! $files_locked) {
				$this->shed->lock($this->service_name . '/instances/sessions', $this->session_id);
			}
			$this->session['service'] = $this->service;
			$this->shed->store($this->service_name . '/instances/sessions', $this->session_id, $this->session);
			if (! $files_locked) {
				$this->shed->unlock($this->service_name . '/instances/sessions', $this->session_id);
			}
		} else {
			throw new Exception("Invalid service scope '" . $this->config->get('omega.scope') . "'.");
		}
	}

	/** Camel-cases a word by splitting the word up into clusters of letters, capitalizing the first letter of each cluster.
		expects: word=string
		returns: string */
	public function _camel_case($word) {
		$return = '';
		foreach (preg_split('/[^a-zA-Z]+/', $word) as $hump) {
			$return .= ucfirst($hump);
		}
		return $return;
	}

	/** Flattens a camel-cased word (e.g. fooBar, FooBar) to a lower-case representation (e.g. foobar), optionally inserting an underscore before capital letters (e.g. foo_bar).
		expects: word=string, add_cap_cap
		returns: string */
	public function _flatten($str, $add_cap_gap = false) {
		// force the first character to be lower
		// QQ... lcfirst is only in php 5.3.0
		$str = strtolower(substr($str, 0, 1)) . substr($str, 1);
		// add the cap gap if requested
		if ($add_cap_gap) {
			$str = preg_replace('/([A-Z])/', '_$1', $str);
		}
		// condense spaces/underscores to a single underscore
		// and strip out anything else but alphanums and underscores
		return strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '', preg_replace('/( |_)+/', '_', $str)));
	}

	/** Clears the current server instance, causing it to be reinitialized from scratch upon the next request. */
	public function restart_service() {
		// delete the service instance
		if ($this->config->get('omega.scope') == 'global') {
			$this->shed->forget($this->service_name . '/instances', 'global');
		} else if ($this->config->get('omega.scope') == 'user') {
			if ($this->subservice->is_enabled('authority')) {
				$this->shed->forget($this->service_name . '/instances/users', $this->subservice->authority->authed_username);
			}
		} else if ($this->config->get('omega.scope') == 'session') {
			$this->shed->forget($this->service_name . '/instances/sessions', $this->session_id);
		}
		// unset the service so it can wind down nicely
		unset($this->service);
		// make a note that the service was abandoned so we don't save it
		$this->save_service_state = false;
	}

	/** 

	/** Executes a shell command, possibly writing $stdin to the command, returning the contents of stdout and stderr. Throws an exception if the return value is non-zero. THE COMMAND BEING EXECUTED WILL NOT BE ESCAPED. USE WITH CAUTION.
		expects: cmd=string, stdin=string, env=array
		returns: object */
	public function _exec($cmd, $stdin = null, $env = null) {
		$pipe_info = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
			);
		$pipes = array();
		if ($env == null) {
			$proc = proc_open($cmd, $pipe_info, $pipes, '/');
		} else {
			$proc = proc_open($cmd, $pipe_info, $pipes, '/', $env);
		}
		if (! is_resource($proc)) {
			throw new Exception("Failed to create shell.");
		}
		// write our input to the pipe
		if ($stdin != null) {
			fwrite($pipes[0], $stdin);
		}
		fclose($pipes[0]);
		// read the result
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		$ret_val = proc_close($proc);
		if ($ret_val != 0) {
			throw new Exception("Command execution failed with return value $ret_val. Read '$stdout' from stdout, '$stderr' from stderr.");
		}
		return array('stdout' => $stdout, 'stderr' => $stderr);
	}

	/** Executes a shell command as another user, passing the command to su via STDIN to avoid escaping. Defaults to using /bin/bash and does not use a login shell. Returning the contents of stdout and stderr. Throws an exception if the return value is non-zero.
		expects: user=string, cmd=string, env=array, shell=string, login_shel=boolean
		returns: object */
	public function _su($user, $cmd, $env = null, $shell = '/bin/bash', $login_shell = false) {
		if (! preg_match('/^[a-zA-Z\.\-]+$/', $user)) {
			throw new Exception("Invalid user name: '$user'.");
		}
		$pipe_info = array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
		);
		if ($login_shell) {
			$login_shell = '-';
		} else {
			$login_shell = '';
		}
		$pipes = array();
		if ($env == null) {
			$proc = proc_open("cat | su $login_shell '$user' -s '$shell'", $pipe_info, $pipes, '/');
		} else {
			$proc = proc_open("cat | su $login_shell '$user' -s '$shell'", $pipe_info, $pipes, '/', $env);
		}
		if (! is_resource($proc)) {
			throw new Exception("Failed to create shell for $user.");
		}
		// write our command to the pipe
		fwrite($pipes[0], $cmd);
		fclose($pipes[0]);
		// read the result
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		$ret_val = proc_close($proc);
		if ($ret_val != 0) {
			throw new Exception("Command execution failed with return value $ret_val. Read '$stdout' from stdout, '$stderr' from stderr.");
		}
		return array('stdout' => $stdout, 'stderr' => $stderr);
	}

	public function _get_tmp_file($label = 'omega_tmp_file') {
		if (! is_dir('/tmp')) {
			throw new Exception("Unable to locate '/tmp' directory to create temporary file.");
		}
		$pid = getmypid();
		$i = 0;
		$x = rand(0, 1337);
		while (file_exists("/tmp/.$label-$pid-$i-$x.tmp")) {
			if ($i++ > 20) {
				throw new Exception("Failed to create find available name for '$label' temporary file.");
			}
		}
		return "/tmp/.$label-$pid-$i-$x.tmp";
	}

	public function _clean_trace($st) {
		$stack = array();
		foreach ($st as $trace) {
			$line = '';
			if (isset($trace['file'])) {
				$line .= $trace['file'] . ' ';
			}
			if (isset($trace['line'])) {
				$line .= $trace['line'] . ' ';
			}
			if (isset($trace['class'])) {
				$line .= ' ' . $trace['class'] . $trace['type'];
			}
			// don't return the actual args by default for security reasons
			$line .= $trace['function'] . '(' . count($trace['args']) . ' ' . (count($trace['args']) === 1 ? 'arg' : 'args') . ')';
			$stack[] = $line;
		}
		return $stack;
	}

	public function _generate_session_id($length = 64) {
		$char_pool = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ',', '_', '-');
		$session_id = '';
		for ($i = 0; $i < $length; $i++) {
			$session_id .= $char_pool[rand(0, count($char_pool)-1)];
		}
		return $session_id;
	}

	/** Creates a service session, propagated via HTTP cookies.
		returns: object */
	public function _create_session() {
		$creds = $this->request->get_credentials();
		$session_id = $this->_generate_session_id();
		$this->session = array(
			'creds' => $creds,
			'start_time' => time(),
			'session_id' => $session_id
		);
		$this->session_id = $session_id;
	}

	/** Returns the session ID for the service if the scope is set to 'session'. */
	public function get_session_id() {
		if ($this->config->get('omega.scope') == 'session') {
			return $this->session_id;
		} else {
			return null;
		}
	}

	/** Export API information about the specified API branch, optionally recursing through sub-branches and provisiong verbose information.
		expects: branch=string, recurse=boolean, verbose=boolean
		returns: object */
	public function export_api($branch, $recurse = false, $verbose = false) {
		//return $this->_export_api($branch, $recurse, $verbose);
		// get a reference to the API branch so we 
		$branches = explode('/', $branch);
		// just one branch? it's the service or omega itself
		if (count($branches) == 1) {
			if ($branches[0] == 'omega') {
				$api_branch = $this;
			} else if ($branches[0] == $this->config->get('omega.nickname')) {
				$api_branch = $this->service_name;
			} else {
				throw new Exception("Unknown service name: '" . $branches[0] . "'.");
			}
		} else {
			$api_branch = $this->request->_get_branch_ref($branches);
		}
		return $this->request->_get_branch_info($api_branch, $recurse, $verbose);
	}

	/** Return the default arg if set, otherwise use corresponding value in args. */
	public function _get_args($defaults, $args) {
		if ($args === null) {
			$my_args = $defaults;
		} else if (is_array($defaults) && is_array($args)) {
			$my_args = array();
			foreach ($defaults as $name => $default) {
				if (isset($args[$name])) {
					$my_args[$name] = $args[$name];
				} else {
					$my_args[$name] = $default;
				}
			}
		} else {
			throw new Expeception('Either default arguments or given arguments are not an array or null.');
		}
		return $my_args;
	}

	/** Abort execution, dumping structure of supplied object. Arguments are passed to thrown OmegaException for notifications/etc.
		expects: obj=object, args=object */
	public function _die($obj, $args = null) {
		$args = $this->_get_args(array(
			'alert' => false
		), $args);
		$err = var_export($obj, true);
		throw new OmegaException($err, $obj, $args);
	}
}

?>

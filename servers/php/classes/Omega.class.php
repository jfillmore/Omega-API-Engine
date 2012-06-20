<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Omega exists as a global object ($om or $omega) and provides an API to assist omega services (e.g. provide subservices like ACLs and logging). */
class Omega extends OmegaLib {
    public $session;
    public $session_id; // used when scope = 'session', can be prefixed with $om->response->set_cookie_prefix()
    private $restful; // whether or not the API is RESTful
    private $output_stream = null;
    private $save_service_state; // whether or not to save the state of the service after each request
    private $production = true; // whether the API should be considered in producition mode for security purposes

    // service information
    public $api; // alias to $this->service
    public $service; // the current hosted service e.g. 'new Marian(...);'
    public $service_name; // the service's name, used to initialize the client service, e.g. 'Marian'
    public $service_nickname; // short name for the service
    
    // internal omega branches
    public $config; // configuration information about this service
    public $request; // the request that is being processed
    public $response; // the response that will be sent back
    public $shed; // the storage engine for config data
    public $sessions; // a separate storage engine (possibly overlapping 'shed') for session data
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
        // determine whether we are in production mode or not
        $production = true;
        try {
            // if false we'll allow some debugging info in our responses
            $production = (bool)$this->config->get('omega/production');
        } catch (Exception $e) {};
        $this->production = $production;
        // initialize our storage engine for sessions
        try {
            $engine = $this->config->get('omega/session/engine');
            $engine_args = $this->config->get('omega/session/engine_args');
        } catch (Exception $e) {
            // default to OmegaFileShed
            $engine = 'File';
            $engine_args = array('location' => OmegaConstant::data_dir);
        }
        $class_name = 'Omega' . ucfirst($engine) . 'Shed';
        $r_class = new ReflectionClass($class_name);
        $this->sessions = $r_class->newInstanceArgs(
            $this->_get_construct_args($r_class, $engine_args)
        );
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

    public function in_production() {
        return $this->production;
    }

    /** Method for testing proxy logic. */
    private function proxy_test($domain, $port, $uri, $method = 'GET') {
        $proxy = new OmegaProxy();
        $proxy->passthru($domain, $port, array(), $uri, $method);
    }

    /** Returns a reference to an output stream that can be written to for providing an API response. */
    public function _get_output_stream() {
        global $om;
        // not returning JSON data if we use this
        $result = fopen('php://temp/maxmemory:128', 'rw');
        if ($result === false || $result === null) {
            throw new Exception("Failed to create output stream.");
        }
        $this->output_stream = $result;
        return $this->output_stream;
    }

    /** Simple method to test get_output_stream functionality. */
    private function test_output_stream($file) {
        if (file_exists($file)) {
            $os = $this->_get_output_stream();
            $this->response->set_encoding('raw');
            $this->response->header('Content-Type', mime_content_type($file));
            $fh = fopen($file, 'r');
            fwrite($os, fread($fh, filesize($file)));
        } else {
            throw new Exception("File not found: $file.");
        }
    }

    public function test_popen() {
        $this->response->set_encoding('raw');
        $this->response->header('Content-Type', 'application/gzip', true);
        $this->response->header('Content-Disposition', 'attachment; filename="test.tar.gz"', true);
        $fh = popen('cd /var/www/comcure && tar -czf - README', 'r');
        return $fh;
    }

    /** Rewrites the arguments from associative to positional to work for the class constructor. */
    private function _get_construct_args($r_class, $args) {
        global $om;
        $missing_params = array();
        $params = array();
        $param_count = 0;
        $r_method = $r_class->getMethod('__construct');
        foreach ($r_method->getParameters() as $i => $r_param) {
            // make sure the parameter is available, if present
            $param_name = $r_param->getName();
            // is this in our args?
            if (isset($args[$param_name])) {
                $params[$param_count] = $args[$param_name];
            } else if (! $r_param->isOptional()) {
                // damn, you missed!
                $missing_params[] = $param_name;
            }
            $param_count++;
        }
        if (count($missing_params) > 0) {
            throw new Exception("Constructor for '" . $r_class->getShortName() . "' is missing the following parameters: " . implode(', ', $missing_params) . '.');
        }
        return $params;
    }

    /** Returns whether or not the output stream was initialized for use. */
    public function _has_output_stream() {
        return ($this->output_stream !== null);
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
        $params = $this->_get_construct_args($r_class, $this->request->get_api_params());
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
        $this->service_nickname = $this->config->get('omega.nickname');
        // load the subservice manager first so the request and response can do subservice-dependant stuff
        $this->subservice = new OmegaSubserviceManager();
        // get a response ready
        $this->response = new OmegaResponse();
        // prepare to generate the request to yield a response
        $this->request = new OmegaRequest();
        // determine what cookie the session will use, if present
        try {
            $this->response->set_cookie_name(
                $this->config->get('omega.cookie_name')
            );
        } catch (Exception $e) {
            $this->response->set_cookie_name(
                'OMEGA_SESSION_ID'
            );
        }
        if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
            $this->response->set_encoding('json');
        } else {
            // default our response encoding to be the same as what we used with the request
            $this->response->set_encoding($this->request->get_encoding());
        }

        try {
            $this->subservice->authority->authenticate(
                $this->request->get_credentials()
            );
        } catch (Exception $e) {
            // no authority enabled, then no auth needed
            if (! $this->subservice->is_enabled('authority')) {
                // restore our header back to OK
                $this->response->header_num(200);
            } else {
                $data = array(
                    'backtrace' => $this->_clean_trace($e->getTrace())
                );
                $spillage = ob_get_contents();
                ob_end_clean();
                $this->response->set_spillage($spillage);
                $this->response->set_data($data);
                $this->subservice->logger->log($data);
                $this->subservice->logger->commit_log(false);
                throw $e;
            }
        }

        // if the API is for omega/* but we're not authed as the service then abort
        if (preg_match('/^omega\W/', $this->request->get_api())) {
            if ($this->subservice->authority->authed_username !== $this->service_nickname) {
               $this->response->header_num(403);
               throw new Exception("Authentication required.");
            }
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
        if ($this->request->get_api() == '?' || ($this->request->get_api() === $this->service_nickname && $this->request->is_query())) {
            $this->save_service_state = false;
            $this->service = null;
            $this->api = $this->service;
            $this->session = null;
        } else {
            $this->_load_session();
        }
        // try to answer the request
        if ($this->request->get_api() == $this->service_nickname && ! $this->request->is_query()) {
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
            $user_error = false;
            try {
                $answer = $this->request->_answer();
                // check to see if we got the answer back in an output stream or if they just returned it
                if ($this->_has_output_stream()) {
                    $this->response->set_data($this->output_stream);
                } else {
                    $this->response->set_data($answer);
                }
                $this->response->set_result(true);
                if ($this->subservice->is_enabled('logger') && isset($this->subservice->logger)) { // gotta also be sure the subservice is initialized too, otherwise an error on enabling will happen
                    // don't log boring things like queries or APIs about logging
                    if (! $this->request->is_query() && strpos($this->request->get_api(), 'omega.subservice.logger') === false) {
                        $this->subservice->logger->commit_log(true);
                    }
                }
            } catch (OmegaException $e) {
                // if we're picking up an omega exception include the data
                $data = array();
                $bt = $this->_clean_trace($e->getTrace());
                // last 3 items are framework methods
                array_pop($bt);
                array_pop($bt);
                array_pop($bt);
                if (! $this->in_production()) {
                    $data['backtrace'] = $bt;
                    $data['error_data'] = $e->data;
                }
                $user_error = $e->user_error;
                $this->response->set_result(false);
                if ($this->subservice->is_enabled('logger')) {
                    $this->subservice->logger->log_data('api_error', $e->getMessage());
                    $this->subservice->logger->log_data('api_trace', $bt);
                    if ($e->data !== null) {
                        $this->subservice->logger->log_data('data', $e->data);
                    }
                    if ($e->comment !== null) {
                        $this->subservice->logger->log_data('error_comment', $e->comment);
                    }
                }
            } catch (Exception $e) {
                $data = array();
                // last 3 items are framework methods
                $bt = $this->_clean_trace($e->getTrace());
                array_pop($bt);
                array_pop($bt);
                array_pop($bt);
                if (! $this->in_production()) {
                    $data['backtrace'] = $bt;
                }
                $this->response->set_result(false);
            }
            if (! $this->response->get_result()) {
                // if it's a user error then don't show the real exception
                if ($user_error) {
                    // use a default error if not given
                    if ($user_error === true) {
                        $user_error = 'There was an error within the input.'; // hard to be better than that :/
                    }
                    $this->response->set_reason($user_error, true);
                } else {
                    $this->response->set_reason($e->getMessage());
                }
                if (! $this->in_production()) {
                    $data['system_error'] = $e->getMessage();
                }
                $this->response->set_data($data);
                if ($this->subservice->is_enabled('logger')) {
                    $this->subservice->logger->log($e->getMessage(), false);
                    $this->subservice->logger->log_data('api_trace', $bt);
                    $this->subservice->logger->commit_log(false);
                }
            }
        }
        // unlock and save the service instance if needed
        if ($this->save_service_state && $this->config->get('omega/scope') != 'none') {
            $this->_save_session(false);
        }
        // see if we spilled anywhere... if so, pick it up to ensure we have a clean stream
        $spillage = ob_get_contents();
        ob_end_clean();
        if (strlen($spillage) > 0 && ! $this->in_production()) {
            $this->response->set_spillage($spillage);
        }
        // encode the response that we'll send back
        $response = $this->response->encode($this->response->get_encoding());
        // print out the request headers
        foreach ($this->response->headers as $header_name => $header_value) {
            header($header_name . ': ' . $header_value);
        }
        // if we have a session ID respond with that cookie - except when ending our session
        if ($this->get_session_id() !== null && isset($this->service)) {
            $cookie_path = $this->response->get_cookie_path();
            setcookie(
                $this->response->get_cookie_name(),
                $this->get_session_id(),
                0,
                $cookie_path
            );
        }
        // if we failed and still have a 2xx status code then you know the drill
        if ((! $this->response->get_result()) &&
            $this->response->is_2xx()) {
            $this->response->header_num(500);
        }
        // set our status code (e.g. 200, 404, etc)
        header($this->response->get_status());
        // and finally print/return the response
        if (is_resource($response)) {
            fpassthru($response);
        } else {
            echo $response;
        }
    }
    
    public function _load_session() {
        $scope = $this->config->get('omega/scope');
        $this->session_id = null;
        if ($scope == 'global') {
            // global scope means every request is served by the same server
            try {
                $this->service = $this->sessions->get($this->service_name . '/instances', 'global');
                $this->api = $this->service;
            } catch (Exception $e) {
                // failed to get it? start one up fresh
                $this->_init_service();
                // and save it
                $this->sessions->store($this->service_name . '/instances', 'global', $this->service);
            }
            // if we can serve requests asyncronously then don't lock the instance file
            if ($this->save_service_state) {
                $this->sessions->lock($this->service_name . '/instances', 'global');
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
                $this->service = $this->sessions->get($this->service_name . '/instances/users', $username);
                $this->api = $this->service;
            } catch (Exception $e) {
                // failed to get it? start one up fresh
                $this->_init_service();
                // and save it
                $this->sessions->store($this->service_name . '/instances/users', $username, $this->service);
            }
            // if we can serve requests asyncronously then don't lock the instance file
            if ($this->save_service_state) {
                $this->sessions->lock($this->service_name . '/instances/users', $username);
            }
        } else if ($scope == 'session') {
            // look for a cookie to see if we have a session going-- if so, resume it
            if (isset($_COOKIE[$this->response->get_cookie_name()])) {
                $session_id = $_COOKIE[$this->response->get_cookie_name()];
            } else {
                $session_id = '';
            }
            // if the client is requesting the service itself then consider that a request for a new session
            // unless they're explicitly initializing the service-- if so, let it be done
            if ($session_id != ''
                && $this->sessions->exists($this->service_name . '/instances/sessions/', $session_id)
                && $this->request->get_api() != $this->config->get('omega/nickname')
                ) {
                $this->session = $this->sessions->get($this->service_name . '/instances/sessions', $session_id);
                $this->service = $this->session['service'];
                $this->api = $this->service;
                $this->session_id = $_COOKIE[$this->response->get_cookie_name()];
            } else {
                $this->_create_session();
                $this->_init_service();
                $this->session['service'] = $this->service;
                // add our session cookie prefix if given one
                $this->sessions->store(
                    $this->service_name . '/instances/sessions',
                    $this->get_session_id(),
                    $this->session
                );
            }
            if ($this->save_service_state) {
                $this->sessions->lock($this->service_name . '/instances/sessions', $this->get_session_id());
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
        $scope = $this->config->get('omega/scope');
        if ($scope == 'global') {
            // unlock and save the service instance
            if (! $files_locked) {
                $this->sessions->lock($this->service_name . '/instances', 'global');
            }
            $this->sessions->store($this->service_name . '/instances', 'global', $this->service);
            if (! $files_locked) {
                $this->sessions->unlock($this->service_name . '/instances', 'global');
            }
        } else if ($scope == 'user') {
            // only supported if the authority service is available
            if ($this->subservice->is_enabled('authority')) {
                throw new Exception("Unable to run service at the 'user' level without the authority service.");
            }
            $username = $this->subservice->authority->authed_username;
            if (! $files_locked) {
                $this->sessions->lock($this->service_name . '/instances/users', $username);
            }
            $this->sessions->store($this->service_name . '/instances/users', $username, $this->service);
            if (! $files_locked) {
                $this->sessions->unlock($this->service_name . '/instances/users', $username);
            }
        } else if ($scope == 'session') {
            if (! $files_locked) {
                $this->sessions->lock($this->service_name . '/instances/sessions', $this->get_session_id());
            }
            $this->session['service'] = $this->service;
            $this->sessions->store(
                $this->service_name . '/instances/sessions',
                $this->get_session_id(),
                $this->session
            );
            if (! $files_locked) {
                $this->sessions->unlock($this->service_name . '/instances/sessions', $this->get_session_id());
            }
        } else if ($scope === 'none') {
            // do nothing, as we don't care to save the state... technically this shouldn't be called
            // but just in case...
        } else {
            throw new Exception("Invalid service scope '$scope'.");
        }
    }

    /** Clears the current server instance, causing it to be reinitialized from scratch upon the next request. */
    public function restart_service() {
        // delete the service instance
        if ($this->config->get('omega.scope') == 'global') {
            $this->sessions->forget($this->service_name . '/instances', 'global');
        } else if ($this->config->get('omega.scope') == 'user') {
            if ($this->subservice->is_enabled('authority')) {
                $this->sessions->forget($this->service_name . '/instances/users', $this->subservice->authority->authed_username);
            }
        } else if ($this->config->get('omega.scope') == 'session') {
            $this->sessions->forget($this->service_name . '/instances/sessions', $this->session_id);
        }
        // unset the service so it can wind down nicely
        unset($this->service);
        // make a note that the service was abandoned so we don't save it
        $this->save_service_state = false;
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
            if ($this->in_production()) {
                $line .= $trace['function'] . '(' . count($trace['args']) . ' ' . (count($trace['args']) === 1 ? 'arg' : 'args') . ')';
            } else {
                $line .= $trace['function'] . '(' . json_encode($trace['args']) . ')';
            }
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
            return $this->response->get_cookie_prefix() . $this->session_id;
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
}

?>

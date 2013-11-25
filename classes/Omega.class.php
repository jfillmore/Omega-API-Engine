<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Omega exists as a singletone object (Omega::get()) and provides an API to assist omega apis (e.g. routing, auth, logging). */
class Omega extends OmegaRESTful {
    static private $instance = null; // so we don't depend on a global var
    public $finished = false;
    public $session;
    public $session_id; // used when scope = 'session', can be prefixed with $om->response->set_cookie_prefix()
    private $output_stream = null;
    private $save_api_state; // whether or not to save the state of the api after each request
    private $production = true; // whether the API should be considered in producition mode for security purposes
    private $debug = false; // if true, errors will contain backtraces regardless of production state
    private $wrote_headers = false; // have we written response headers already?

    // api information
    public $api; // the current hosted API tree
    public $api_name; // the api's class name, used to initialize the API, e.g. 'FooBar'
    public $api_nickname; // short name for the api
    
    // internal omega branches
    public $config; // configuration information about this api
    public $request; // the request that is being processed
    public $response; // the response that will be sent back
    public $shed; // the storage engine for config data
    public $sessions; // a separate storage engine (possibly overlapping 'shed') for session data
    public $logger; // 

    public function __construct($api_name) {
        // we shouldn't ever exist already...
        if (self::$instance !== null) {
            $om = self::get();
            throw new Exception("Omega has already been initialized for API " . $om->api_name . ".");
        }
        if (! preg_match(OmegaTest::word_re, $api_name)) {
            throw new Exception("Invalid API name format: '$api_name'.");
        }
        $this->api_name = $api_name;
        // make sure we have a directory to store the data in
        if (! is_dir(OmegaConstant::data_dir())) {
            if (! @mkdir(OmegaConstant::data_dir())) {
                throw new Exception("Failed to create API data storage directory '" . OmegaConstant::data_dir() . "'.");
            }
        }
        // instantiate the storage engine
        $this->shed = new OmegaFileShed(OmegaConstant::data_dir());
        // set up the configuration file for this api
        try {
            $this->config = new OmegaConfig(
                $this->shed->get_location() . '/'
                . $this->api_name . "/config"
            );
        } catch (Exception $e) {
            throw new Exception("Unknown API: '$api_name'.");
        }
        // determine whether we are in debug mode or not
        $debug = false;
        try {
            // if false we'll allow some debugging info in our responses
            $debug = (bool)$this->config->get('omega/debug');
        } catch (Exception $e) {};
        $this->debug = $debug;
        // determine whether we are in production mode or not
        $production = true;
        try {
            // if false we'll allow some debugging info in our responses
            $production = (bool)$this->config->get('omega/production');
        } catch (Exception $e) {};
        $this->production = $production;
        // add any user class dirs to the include path too
        set_include_path(
            get_include_path()
            . PATH_SEPARATOR
            . join(
                PATH_SEPARATOR,
                $this->config->get('omega/class_dirs')
            )
        );
        // initialize our storage engine for sessions
        try {
            $engine = $this->config->get('omega/session/engine');
            $engine_args = $this->config->get('omega/session/engine_args');
        } catch (Exception $e) {
            // default to OmegaFileShed
            $engine = 'File';
            $engine_args = array('location' => OmegaConstant::data_dir());
        }
        $class_name = 'Omega' . ucfirst($engine) . 'Shed';
        try {
            $r_class = new ReflectionClass($class_name);
            $this->sessions = $r_class->newInstanceArgs(
                $this->get_construct_args($r_class, $engine_args)
            );
        } catch (Exception $e) {
            throw new Exception("Failed to initialize session storage: " . $e->getMessage());
        }
        // if we're an async API then don't save the state so we can execute requests simultaneously
        $this->save_api_state = 
            ! (bool)(int)$this->config->get('omega/async');
        self::$instance = $this;
    }

    /** Returns the the Omega instance (as opposed to trusing 'omega' or 'om' to be usable as globals). */
    public static function get() {
        return self::$instance;
    }

    /** Returns link to the user API Obj. */
    public static function api() {
        $om = Omega::get();
        return $om->api;
    }

    public function _get_routes() {
        return  array(
            'api' => 'api',
            'request' => 'request',
            'response' => 'response',
            'config' => 'config'
        );
    }

    public function _get_handlers() {
        return array(
            'GET' => array(
            ),
            'POST' => array(
                'restart_api' => 'restart_api',
                'test_popen' => 'test_popen',
                'test_output_stream' => 'test_output_stream',
                'test_proxy' => 'test_proxy',
                'test_log' => 'test_log'
            ),
            'PUT' => array(
            ),
            'DELETE' => array(
            )
        );
    }

    public function in_debug() {
        return $this->debug;
    }

    public function in_production() {
        return $this->production;
    }

    /** Method for testing proxy logic. */
    public function test_proxy($host, $port = null, $ssl = true, $method = null, $uri = null, $data = null, $content_type = null) {
        $proxy = new OmegaProxy();
        $proxy->passthru($host, $port, $ssl, $method, $uri, $data, $content_type);
    }

    /** Returns a reference to an output stream that can be written to for providing an API response. */
    public function get_output_stream($write_headers = true) {
        $om = Omega::get();
        // end output buffering, since we're taking over
        $spillage = $this->flush_ob(false);
        // note that headers MUST be sent before writing to the output stream, but you could concievably want to delay just a bit longer
        if ($write_headers) {
            $this->write_headers();
        }
        // take note of anything that slipped out prematurely (e.g. warnings)
        if (strlen($spillage) > 0 && ($this->in_debug() || ! $this->in_production())) {
            $this->response->set_spillage($spillage);
        }
        $result = fopen('php://output', 'w');
        if ($result === false || $result === null) {
            throw new Exception("Failed to create output stream.");
        }
        $this->output_stream = $result;
        return $this->output_stream;
    }

    public function test_log($msg, $alert = false) {
        $om = Omega::get();
        return $this->log($msg, $alert);
    }

    /** Simple method to test get_output_stream functionality. */
    public function test_output_stream($file) {
        if (file_exists($file)) {
            $chunk_size = 32000;
            $size = filesize($file);
            $fh = fopen($file, 'r');
            if ($fh === false ) {
                throw new Exception("Failed to open '$file' to read.");
            }
            $chunks = floor($size / $chunk_size);
            $remainder = $size % $chunk_size;
            // send headers before getting the output stream
            $this->response->header('Content-Type', mime_content_type($file));
            // write chunks
            $os = $this->get_output_stream();
            $offset = 0;
            for ($i = 0; $i < $chunks; $i++) {
                //$written = fwrite($os, fread($fh, $chunk_size), $chunk_size); // works, but below seems cleaner
                $written = stream_copy_to_stream($fh, $os, $chunk_size, $offset);
                if ($written === false) {
                    throw new Exception("Failed to write $chunk_size (offset $i) bytes to stdout.");
                }
                $offset += $written;
            }
            // write leftovers
            //$written = fwrite($os, fread($fh, $remainder), $remainder); // works, but below seems cleaner
            $written = stream_copy_to_stream($fh, $os, $remainder, $offset);
            if ($written === false) {
                throw new Exception("Failed to write $chunk_size (offset $i) bytes to stdout.");
            }
            return $os;
        } else {
            throw new Exception("File not found: $file.");
        }
    }

    public function test_popen() {
        $this->response->header('Content-Type', 'text/plain', true);
        $this->response->header('Content-Disposition', 'attachment; filename="README"', true);
        $fh = popen('cat /var/www/comcure/README', 'r');
        return $fh;
    }

    /** Rewrites the arguments from associative to positional to work for the class constructor. */
    private function get_construct_args($r_class, $args) {
        $om = Omega::get();
        $missing_params = array();
        $params = array();
        $param_count = 0;
        try {
            $r_method = $r_class->getMethod('__construct');
        } catch (Exception $e) {
            // there may not be a constructor on it, which is just fine
            return $params;
        }
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
            $this->response->header_num(403);
            throw new Exception('Not authenticated.');
            // throw new Exception("Constructor for '" . $r_class->getShortName() . "' is missing the following parameters: " . implode(', ', $missing_params) . '.');
        }
        return $params;
    }

    /** Returns whether or not the output stream was initialized for use. */
    public function has_output_stream() {
        return ($this->output_stream !== null);
    }

    /** Returns the name of the omega user. */
    public function whoami() {
        if ($this->auth->enabled) {
            return $this->auth->authed_username;
        } else {
            return 'Guest';
        }
    }

    public function init_api() {
        // make sure we've got the parameters we need
        $class_name = $this->api_name;
        $r_class = new ReflectionClass($class_name);
        $params = $this->get_construct_args($r_class, $this->request->get_api_params());
        if (count($params)) {
            /* If we have parameters in our constructor it must be explicitally accessed
            otherwise our args might overlap the underlying API being asked for.
            It also means you can post the auth info to any URI. I don't know
            of any way to cleanly separate constructor args from API args without
            requiring a separate call to init */
            // we can post to our nickname or base location
            $api = $this->request->get_api();
            if (! ($api === $this->api_nickname || $api === '')) {
                throw new Exception("Not found: \"$api\".");
            }
        }
        $api_obj = $r_class->newInstanceArgs($params);
        if ($api_obj !== null) {
            $this->api = $api_obj;
        } else {
            throw new Exception("Failed to initialize $class_name.");
        }
        if (! @is_subclass_of($this->api, 'OmegaRESTful')) {
            throw new Exception("API does not extend OmegaRESTful; unable to serve API requests.");
        }
    }

    /** Shorthand for logging and optional aborting/alerting. */
    public function log($msg, $alert = false, $abort = false) {
        if (is_string($msg)) {
            $this->logger->log($msg);
        } else {
            $this->logger->log_data('Log Alert', $msg);
        }
        if ($alert) {
            $oe = new OmegaException("Log Alert", $msg, array('alert' => true));
        } else {
            $oe = new OmegaException("Log Alert", $msg);
        }
        if ($abort) {
            throw $oe;
        } else {
            return $msg;
        }
    }

    /** Just another day in the life of an omega server. */
    public function do_the_right_thing() {
        $this->finished = false;
        // capture any crap that PHP leaks through (e.g. warnings on functions) or that the user intentionally leaks
        ob_start();
        $this->api_nickname = $this->config->get('omega/nickname');
        // enable logging/auth 
        $this->logger = new OmegaLogger();
        $this->auth = new OmegaAuth();
        // get a response ready
        $this->response = new OmegaResponse();
        // prepare to generate the request to yield a response
        $this->request = new OmegaRequest();
        // determine what cookie the session will use, if present
        try {
            $this->response->set_cookie_name(
                $this->config->get('omega/cookie_name')
            );
        } catch (Exception $e) {
            $this->response->set_cookie_name('OMEGA_SESSION_ID');
        }
        try {
            $this->auth->authenticate(
                $this->request->get_credentials()
            );
        } catch (Exception $e) {
            // no authority enabled, then no auth needed
            if (! $this->auth->enabled) {
                // restore our header back to OK
                $this->response->header_num(200);
            } else {
                $spillage = $this->flush_ob(false);
                $data = array();
                if (strlen($spillage) > 0 && ($this->in_debug() || ! $this->in_production())) {
                    $this->response->set_spillage($spillage);
                    $this->log($spillage);
                }
                if ($this->in_debug() || ! $this->in_production()) {
                    $data['backtrace'] = $this->clean_trace($e->getTrace());
                }
                $this->response->set_data($data);
                $this->logger->log($data);
                $this->logger->commit_log(false);
                throw $e;
            }
        }

        // if the API is for omega/* but we're not authed as the api then abort
        if (preg_match('/^omega\W/', $this->request->get_api())) {
            if ($this->auth->authed_username !== $this->api_nickname) {
               $this->response->header_num(403);
               throw new Exception("Authentication required.");
            }
        }

        // load in our api or start a new one if this is our first load of this api
        $class_name = $this->api_name;
        // make sure this api exists and is enabled
        $shed = new OmegaFileShed(OmegaConstant::data_dir());
        $os_server_config = $shed->get('OmegaServer', 'apis');
        if (! isset($os_server_config['apis'][$this->api_name])
            || $os_server_config['apis'][$this->api_name] == false
            ) {
            throw new Exception("Unknown or unavailable API : '" . $this->api_name . "'.");
        }
        if ($this->request->is_query) {
            // only doing a querying about the API?
            $this->save_api_state = false;
        }
        $this->load_session();
        // try to answer the request
        if ($this->request->get_api() == $this->api_nickname
            && ! $this->request->is_query
            ) {
            // we're just initializing, so nothing to do really
            // constructors can provide a response value this way
            if ($this->response->is_forced()) {
                // additionally, APIs can override the response value via '$om->response->force(...);'
                $this->response->set_data($this->response->get_forced_response());
            }
            $this->response->set_result(true);
        } else {
            $user_error = false;
            try {
                $answer = $this->request->answer();
                $this->response->set_result(true);
                // check to see if we got the answer back in an output stream or if they just returned it
                if ($this->has_output_stream()) {
                    $this->response->set_data($this->output_stream);
                } else if ($this->response->is_forced()) {
                    // constructors can provide a response value this way
                    // additionally, APIs can override the response value via '$om->response->force(...);'
                    $this->response->set_data($this->response->get_forced_response());
                } else {
                    $this->response->set_data($answer);
                }
                // don't log boring things like queries or APIs about logging
                if (! $this->request->is_query
                    && strpos(
                        $this->request->get_api(),
                        'omega/logger'
                    ) === false
                    ) {
                    $this->logger->commit_log(true);
                }
            } catch (OmegaException $e) {
                $data = array();
                $bt = $this->clean_trace($e->getTrace());
                // last 3 items are framework methods
                //array_pop($bt);
                //array_pop($bt);
                //array_pop($bt);
                if ($this->in_debug() || ! $this->in_production()) {
                    $data['backtrace'] = $bt;
                    $data['error_data'] = $e->data;
                }
                $user_error = $e->user_error;
                $this->response->set_result(false);
                $this->logger->log_data('api_error', $e->getMessage());
                $this->logger->log_data('api_trace', $bt);
                if ($e->data !== null) {
                    $this->logger->log_data('data', $e->data);
                }
                if ($e->comment !== null) {
                    $this->logger->log_data('error_comment', $e->comment);
                }
            } catch (Exception $e) {
                $data = array();
                // last 3 items are framework methods
                $bt = $this->clean_trace($e->getTrace());
                //array_pop($bt);
                //array_pop($bt);
                //array_pop($bt);
                if ($this->in_debug() || ! $this->in_production()) {
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
                if ($this->in_debug() || ! $this->in_production()) {
                    $data['system_error'] = $e->getMessage();
                }
                $this->response->set_data($data);
                $this->logger->log($e->getMessage(), false);
                $this->logger->log_data('api_trace', $bt);
                $this->logger->commit_log(false);
            }
        }
        // unlock and save the api instance if needed
        if ($this->save_api_state
            && $this->config->get('omega/scope') != 'none'
            ) {
            $this->save_session(true, true);
        }
        // see if we spilled anywhere... if so, pick it up to ensure we have a clean stream
        $spillage = $this->flush_ob(false);
        if (strlen($spillage) > 0 && ($this->in_debug() || ! $this->in_production())) {
            $this->response->set_spillage($spillage);
            $this->log($spillage);
        }
        // encode the response that we'll send back
        $response = $this->response->encode();
        // print out the request headers, unless they were sent already
        if (! $this->wrote_headers) {
            $this->write_headers();
        }
        // and finally print/return the response, unless the output stream was hijacked
        if (! $this->has_output_stream()) {
            if (is_resource($response)) {
                fpassthru($response);
            } else {
                echo $response;
            }
        }
        $this->finished = true;
    }

    public function write_headers() {
        if ($this->wrote_headers) {
            throw new Exception("Headers already sent; unable to write headers twice.");
        }
        $this->wrote_headers = true;
        if ($this->response->get_result() === null) {
            // we were called by the API, so assume true for now
            $this->response->set_result(true);
        }
        foreach ($this->response->headers as $header_name => $header_value) {
            header($header_name . ': ' . $header_value);
        }
        // if we have a session ID respond with that cookie - except when ending our session
        if ($this->get_session_id() !== null && isset($this->api)) {
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
    }

    public function flush_ob($passthru = true) {
        $output = '';
        while (ob_list_handlers()) {
            if ($passthru) {
                ob_flush();
            } else {
                $output .= ob_get_contents();
                ob_end_clean();
            }
        }
        return $output;
    }
    
    public function load_session() {
        $scope = $this->config->get('omega/scope');
        $this->session_id = null;
        if ($scope == 'global') {
            // global scope means every request is served by the same server
            try {
                $this->api = $this->sessions->get($this->api_name . '/instances', 'global', 'php');
            } catch (Exception $e) {
                // failed to get it? start one up fresh
                $this->init_api();
                // and save it
                $this->sessions->store($this->api_name . '/instances', 'global', $this->api, false, 'php');
            }
            // if we can serve requests asyncronously then don't lock the instance file
            if ($this->save_api_state) {
                $this->sessions->lock($this->api_name . '/instances', 'global');
            }
        } else if ($scope == 'user') {
            // user scope means each user has their own private api instance
            // only supported if the authority api is available
            if (! $this->auth->enabled) {
                //throw new Exception("Unable to run api at the 'user' level without the authority api.");
                $username = 'nobody';
            } else {
                $username = $this->auth->authed_username;
            }
            try {
                $this->api = $this->sessions->get($this->api_name . '/instances/users', $username, 'php');
            } catch (Exception $e) {
                // failed to get it? start one up fresh
                $this->init_api();
                // and save it
                $this->sessions->store($this->api_name . '/instances/users', $username, $this->api, false, 'php');
            }
            // if we can serve requests asyncronously then don't lock the instance file
            if ($this->save_api_state) {
                $this->sessions->lock($this->api_name . '/instances/users', $username);
            }
        } else if ($scope == 'session') {
            // look for a cookie to see if we have a session going-- if so, resume it
            if (isset($_COOKIE[$this->response->get_cookie_name()])) {
                $session_id = $_COOKIE[$this->response->get_cookie_name()];
            } else {
                $session_id = '';
            }
            // if the client is requesting the api itself then consider that a request for a new session
            // unless they're explicitly initializing the api-- if so, let it be done
            if ($session_id != ''
                && $this->sessions->exists($this->api_name . '/instances/sessions/', $session_id)
                && $this->request->get_api() != $this->config->get('omega/nickname')
                ) {
                $this->session = $this->sessions->get(
                    $this->api_name . '/instances/sessions',
                    $session_id,
                    'php'
                );
                $this->api = $this->session['api'];
                $this->session_id = $_COOKIE[$this->response->get_cookie_name()];
            } else {
                $this->create_session();
                $this->init_api();
                $this->session['api'] = $this->api;
                // add our session cookie prefix if given one
                $this->sessions->store(
                    $this->api_name . '/instances/sessions',
                    $this->get_session_id(),
                    $this->session,
                    false,
                    'php'
                );
            }
            if ($this->save_api_state) {
                $this->sessions->lock(
                    $this->api_name . '/instances/sessions',
                    $this->get_session_id()
                );
            }
        } else if ($scope == 'none') {
            $this->init_api();
        } else {
            throw new Exception("Invalid API scope '" . $scope . "'.");
        }
    }

    public function save_session($locked = null, $release = false) {
        // save our state
        $scope = $this->config->get('omega/scope');
        if ($locked === null) {
            $locked = $this->save_api_state;
        }
        if ($scope == 'global') {
            // unlock and save the api instance
            if ($locked) {
                $this->sessions->unlock($this->api_name . '/instances', 'global');
            }
            $this->sessions->store($this->api_name . '/instances', 'global', $this->api, false, 'php');
            if ($locked && ! $release) {
                $this->sessions->lock($this->api_name . '/instances', 'global');
            }
        } else if ($scope == 'user') {
            // only supported if the authority api is available
            if ($this->auth->enabled) {
                throw new Exception("Unable to run API at the 'user' level without the authority service.");
            }
            $username = $this->auth->authed_username;
            if ($locked) {
                $this->sessions->unlock($this->api_name . '/instances/users', $username);
            }
            $this->sessions->store($this->api_name . '/instances/users', $username, $this->api, false, 'php');
            if ($locked && ! $release) {
                $this->sessions->lock($this->api_name . '/instances/users', $username);
            }
        } else if ($scope == 'session') {
            if ($locked) {
                $this->sessions->unlock($this->api_name . '/instances/sessions', $this->get_session_id());
            }
            $this->session['api'] = $this->api;
            $this->sessions->store(
                $this->api_name . '/instances/sessions',
                $this->get_session_id(),
                $this->session,
                false,
                'php'
            );
            if ($locked && ! $release) {
                $this->sessions->lock($this->api_name . '/instances/sessions', $this->get_session_id());
            }
        } else if ($scope === 'none') {
            // do nothing, as we don't care to save the state... technically this shouldn't be called
            // but just in case...
        } else {
            throw new Exception("Invalid API scope '$scope'.");
        }
    }

    /** Clears the current server instance, causing it to be reinitialized from scratch upon the next request. */
    public function restart_api() {
        // delete the api instance
        if ($this->config->get('omega/scope') == 'global') {
            $this->sessions->forget($this->api_name . '/instances', 'global');
        } else if ($this->config->get('omega/scope') == 'user') {
            if ($this->config->get('omega/auth')) {
                $this->sessions->forget($this->api_name . '/instances/users', $this->auth->authed_username);
            }
        } else if ($this->config->get('omega/scope') == 'session') {
            $this->sessions->forget($this->api_name . '/instances/sessions', $this->session_id);
        }
        // unset the api so it can wind down nicely
        unset($this->api);
        // make a note that the api was abandoned so we don't save it
        $this->save_api_state = false;
    }

    public function get_tmp_file($label = 'omega_tmp_file') {
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

    public function clean_trace($st) {
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
            if (! $this->in_debug() && $this->in_production()) {
                $line .= $trace['function'] . '(' . count($trace['args']) . ' ' . (count($trace['args']) === 1 ? 'arg' : 'args') . ')';
            } else {
                $line .= $trace['function'] . '(' . json_encode($trace['args']) . ')';
            }
            $stack[] = $line;
        }
        return $stack;
    }

    public function generate_session_id($length = 64) {
        $char_pool = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ',', '_', '-');
        $session_id = '';
        for ($i = 0; $i < $length; $i++) {
            $session_id .= $char_pool[rand(0, count($char_pool)-1)];
        }
        return $session_id;
    }

    /** Creates an api session, propagated via HTTP cookies.
        returns: object */
    public function create_session() {
        $creds = $this->request->get_credentials();
        $session_id = $this->generate_session_id();
        $this->session = array(
            'creds' => $creds,
            'start_time' => time(),
            'session_id' => $session_id
        );
        $this->session_id = $session_id;
    }

    /** Returns the session ID for the API if the scope is set to 'session'. */
    public function get_session_id() {
        if ($this->config->get('omega/scope') == 'session') {
            return $this->response->get_cookie_prefix() . $this->session_id;
        } else {
            return null;
        }
    }

    /** Export API information about the specified API branch, optionally recursing through sub-branches and provisiong verbose information.
        expects: branch=string, recurse=boolean, verbose=boolean
        returns: object */
    public function export_api($branch, $recurse = false, $verbose = false) {
        // get a reference to the API branch so we 
        $branches = explode('/', $branch);
        // just one branch? it's the API or omega itself
        if (count($branches) == 1) {
            if ($branches[0] == 'omega') {
                $api_branch = $this;
            } else if ($branches[0] == $this->config->get('omega/nickname')) {
                $api_branch = $this->api_name;
            } else {
                throw new Exception("Unknown API name: '" . $branches[0] . "'.");
            }
        } else {
            $api_branch = $this->request->get_branch_ref($branches);
        }
        return $this->request->get_branch_info($api_branch, $recurse, $verbose);
    }
}


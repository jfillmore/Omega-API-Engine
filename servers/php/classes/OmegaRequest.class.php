<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Information about the API request being issued to the omega server. */
class OmegaRequest extends OmegaRESTful implements OmegaApi {
    public $encodings = array('json', 'php', 'raw', 'html');
    private $encoding; // the encoding used for the data
    private $credentials = null; // the credentials, if available, that the user has supplied

    private $query_options; // options related to the query at hand
    private $type = 'command'; // whether the client is querying for information or running a command
    private $api = null; // the API being executed (e.g. 'omega.logger.log')
    private $api_method_name = null; // the name of the method we're executing (e.g. 'add_user')
    private $api_branch_name = null; // the name of the branch we're executing (e.g. 'fsm.charon')
    private $api_params = array(); // the parameters being passed to the API
    private $query_arg = false; // whether or not the parameters contain a ? too
    private $restful = true; // whether this request is restful or not; assume and prove otherwise
    private $stdin = null; // input read via stdin

    public function __construct() {
        global $om;
        // get the request encoding
        try {
            $this->set_encoding($this->get_omega_param('ENCODING'));
        } catch (Exception $e) {
            // raw = GET/POST data for args
            $this->set_encoding('raw');
        }

        // and collect up the parameters
        $this->collect_api_params();

        // look for any authentication information
        try {
            // decode them with the appropriate decoder
            $this->credentials = $this->decode($this->get_omega_param('CREDENTIALS'), $this->get_encoding());
        } catch (Exception $e) {
            // no credentials? no worries
        }

        // determine our API based on the URI
        // e.g. base_uri = '/foo/bar'
        $base_uri = $om->_pretty_path($om->config->get('omega.location'), true);
        // e.g. request_uri = '/foo/bar/a/b/cde'
        // document uri = nginx rewritten URL, request_uri = actual HTTP request
        // it's preferable to use the rewritten URL so APIs can be mounted at
        // locations other than in 'omega/location'
        if (isset($_SERVER['DOCUMENT_URI'])) {
            $request_uri = $_SERVER['DOCUMENT_URI'];
            // we are already URL decoded, so only chop ?... if there is stuff after
            if (preg_match('/\?.+/', $request_uri)) {
                $q_pos = strpos($request_uri, '?');
                if ($q_pos !== false) {
                    $request_uri = substr($request_uri, 0, $q_pos);
                }
            }
        } else {
            $request_uri = $_SERVER['REQUEST_URI'];
            // chop off '?...' from URI
            $q_pos = strpos($request_uri, '?');
            if ($q_pos !== false) {
                $request_uri = substr($request_uri, 0, $q_pos);
            }
            $request_uri = urldecode($request_uri);
        }
        $request_uri = $om->_pretty_path($request_uri, true);
        // determine the request type
        // TODO: have a better way of handling introspection than this hack
        if (substr($request_uri, -1) == '?') { // aka '?'
            $this->set_type('query');
        }
        if (isset($_REQUEST['OMEGA_API_PARAMS']) || isset($_REQUEST['OMEGA_ENCODING'])) {
            // if we're an old-style API call then replace periods with slashes
            $request_uri = str_replace('.', '/', $request_uri);
        }
        // compare where we're configured at against what was requested to determine the API
        $base_parts = explode('/', substr($base_uri, 1));
        $request_parts = explode('/', substr($request_uri, 1));
        if ($base_parts[0] === '') {
            $base_parts = array();
        }
        // the first part of the API should match the base URI, the rest are the API
        $api_parts = array();
        for ($i = 0; $i < count($request_parts); $i++) {
            $req_part = $request_parts[$i];
            if ($i < count($base_parts)) {
                // we should match the base URL each step here
                if ($req_part != $base_parts[$i]) {
                    $om->response->header_num(404);
                    throw new Exception("Unable to parse API from $request_uri: expected '" . $base_parts[$i] . "', found '$req_part'.");
                }
            } else {
                // otherwise, this is part of the API
                $api_parts[] = $req_part;
            }
        }
        // this API may not be a valid API -- we'll determine that at resolve time
        $this->set_api(join('/', $api_parts));

        // set our option defaults, and collect any that were sent by the user
        if ($this->get_type() == 'query') {
            $this->query_options = array(
                'hide_flags' => array(
                    'methods' => false,
                    'branches' => false
                ),
                'search_flags' => array(
                    'case-sensitive' => true
                ),
                'search' => null,
                'recurse' => false,
                'verbose' => false
            );
            $this->collect_options();
        }
    }

    public function _get_routes() {
        return array();
    }

    public function _get_handlers() {
        return array(
            'GET' => array(
                'test' => 'test'
            ),
            'POST' => array(
                'test' => 'test'
            ),
            'PUT' => array(
                'test' => 'test'
            ),
            'PATCH' => array(
                'test' => 'test'
            ),
            'DELETE' => array(
                'test' => 'test'
            )
        );
    }

    private function set_api($api) {
        global $om;
        // rewrite the API as needed to expand it
        $api = $this->translate_api($api);
        $parts = explode('/', $api);
        // just a bit of sanity checking
        if (count($parts) == 0) {
            throw new Exception("API '$api' somehow has no parts!");
        }
        // with a properly formatted API we can see if we should infer the service name or not
        $first = $parts[0];
        if ($first === '') {
            // only way first part is blank is if the API is '/', which we can assume to be '/service_nickname'
            $parts = array($om->service_nickname);
        } else {
            // the first part is generally expected to be the service nickname or 'omega'
            if (! (in_array($first, array('omega', $om->service_nickname)) || substr($first, -1) == '?')) {
                // just assume they gave us the service name to make API calls cleaner
                array_unshift($parts, $om->service_nickname);
            }
        }
        // save the names of everything
        $this->api = implode('/', $parts);
    }

    /* Add additional paramters to the API (e.g. when parsing routes). */
    public function _add_api_params($params) {
        $this->api_params = array_merge(
            $params,
            $this->api_params
        );
        return $this->api_params;
    }

    public function get_stdin() {
        return $this->stdin;
    }

    private function get_omega_param($param) {
        global $om;
        $param = 'OMEGA_' . strtoupper($param);
        // if we find what we're looking for here it's ghetto and old school (aka non-restful)
        if (isset($_POST[$param])) {
            $this->restful = false;
            return $_POST[$param];
        } else if (isset($_GET[$param])) {
            $this->restful = false;
            return $_GET[$param];
        }
        if ($param === 'OMEGA_ENCODING') {
            if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0) {
                return 'json';
            }
        } else if ($param === 'OMEGA_API_PARAMS') {
            if ($_SERVER['REQUEST_METHOD'] == 'GET') {
                // we'll always find our params here, but we need to fake decode them for now
                return json_encode($_GET);
            } else if ($this->get_encoding() === 'json' && $this->is_restful()) {
                $stdin = file_get_contents('php://input');
                $this->stdin = $stdin;
                return $stdin;
            }
        } else if ($param === 'OMEGA_CREDENTIALS') {
            // gotta encode the credentials cause the old version does too
            if (isset($_SERVER['HTTP_AUTHENTICATION'])) {
                $auth_header = $_SERVER['HTTP_AUTHENTICATION'];
                $auth_parts = explode(' ', $auth_header);
                if ($auth_parts[0] === 'Basic') {
                    // different format than the old version, but much easier to implement
                    return json_encode($auth_parts[1]);
                }
            } else {
                // if they have a session open it may contain creds alrady
                if (is_array($om->session)) {
                    return json_encode($om->session['creds']);
                }
            }
        }
        // we shouldn't ever get here... but just in case
        throw new Exception("Unable to determine value of '$param'.");
    }

    private function decode($data, $encoding = 'json') {
        $decoded_data = null;
        if ($data === null) {
            return null;
        }
        switch ($encoding) {
            case 'json':
                $decoded_data = json_decode($data, true);
                if ($decoded_data === NULL) {
                    throw new Exception("Unable to decode '$data' as '$encoding'.");
                }
                break;
            case 'php':
                $decoded_data = unserialize($data);
                // PHP is lame and returns false for both errors and when the serialize value is a 'false' boolean
                if ($decoded_data === false && serialize(false) === $data) {
                    throw new Exception("Unable to decode '$data' as '$encoding'.");
                }
                break;
            case 'raw':
                $decoded_data = $data;
                break;
        }
        return $decoded_data;
    }

    private function get_query_options() {
        return $this->query_options;
    }

    private function collect_api_params() {
        $encoding = $this->get_encoding();

        // collect the parameters using the proper encoding
        if ($encoding == 'json') {
            $param_dna = $this->get_omega_param('API_PARAMS');    
            // look at the param_dna and if it isn't an object or array then make into an array of one element
            $first_char = substr($param_dna, 0, 1);
            if ($param_dna == '"?"') {
                // if it was a question mark then consider it a string as the param name
                $param_dna = '{"?": null}';
            } else if ($first_char != '[' && $first_char != '{') {
                // stick it in an array as the first element if we have a loner
                $param_dna = '[' . $param_dna . ']';
            }
            $params = $this->decode($param_dna, $encoding);
        } else if ($encoding == 'raw') {
            // collect up named get/post vars
            $params = array();
            foreach ($_GET as $key => $value) {
                $params[$key] = $value;
            }
            // POST vars trump GET vars and overwrite
            foreach ($_POST as $key => $value) {
                $params[$key] = $value;
            }
        } else {
            throw new Exception("Unsupport parameter encoding: '$encoding'.");
        }
        $named_params = array();
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                throw new Exception("Positional parameters no longer supported.");
            } else {
                if ($key == '?') {
                    $this->query_arg = true;
                    $this->set_type('query');
                }
                $named_params[$key] = $value;
            }
        }
        $this->api_params = $named_params;
    }

    private function collect_options() {
        // only valid if we're a query
        if (! $this->is_query()) {
            return;
        }

        function get_flags($str, $flag_names) { 
            // first figure out all the short names
            $flag_short_names = array();
            foreach ($flag_names as $flag) { 
                $flag_short_names[$flag] = substr($flag, 0 , 1);
            }
            // flags can be written in short hand w/o commas if all first letters are used, unique, and don't form a valid flag name
            $parse_flags = true;
            $parseIteration = 0;
            $flags = preg_split('/[,;]/', $str);
            $valid_flags = array();
            while ($parse_flags) { 
                $parseIteration++;
                $parse_flags = false; // assume we'll get it right this time
                foreach ($flags as $flag) { 
                    $arrayKey = array_search($flag, $flag_short_names);
                    if ($arrayKey !== false) { 
                        $valid_flags[] = $arrayKey;
                    } else if (in_array($flag, $flag_names)) { 
                        $valid_flags[] = $flag;
                    } else {
                        // otherwise, see if we can resplit our tags by character and try it again
                        if ($parseIteration == 1 && count($flags) == 1) {
                            $flags = str_split($str);
                            $parse_flags = true;
                            break;
                        } else {
                            // and if that didn't work or there were more than one flags... give up
                            throw new Exception("Invalid hide flag: '$flag'.");
                        }
                    }
                }
            }
            return $valid_flags;
        }

        // for each parameter, look for and gather any flags
        $api_params = $this->get_api_params();
        foreach ($api_params as $name => $value) {
            /* parameters will look like:
                hide=methods,branches
                name=bob, name=bob*, name=*bob, name=*bob*, name=bob%recurse,compare-case, name=*bob%cr, name=*bob*%r,compare-case
                ? (optional, verbose output)
                etc.
            */
            if ($name == '?') {
                // nothing to worry' bout, just asking for info
            } else if ($name == 'name' || $name == 'n') {
                // we're doing a search... validate and store
                $matches = array();
                if (! preg_match('/^(\*?[a-z\*A-Z_0-9]*\*?)(%[a-z\-,;]+)?$/', $value, $matches)) {
                    throw new Exception("Invalid name to search for: '$value'.");
                }
                if (count($matches) == 1) {
                    // no search data? interpret this as a full application query, baby...
                    $this->query_options['search'] = '*';
                } else if (count($matches) >= 2) {
                    $this->query_options['search'] = $matches[1];
                }
                // and check for flags, which will be slot #3 if it exists
                if (count($matches) == 3) {
                    $this->query_options['search_flags'] = get_flags(substr($matches[2], 1) /* skip the '%' */, array('compare-case'));
                }
            } else if ($name == 'recurse' || $name == 'r') {
                $this->query_options['recurse'] = (bool)$value;
            } else if ($name == 'verbose' || $name == 'v') {
                $this->query_options['verbose'] = (bool)$value;
            } else if ($name == 'hide' || $name == 'h') {
                // get any hide query_options
                if (! preg_match('/^[a-zA-Z_0-9,]+$/', $value)) {
                    throw new Exception("Invalid hide flags format: '$value'.");
                }
                $this->query_options['hide_flags'] = get_flags($value, array('methods', 'branches'));
            } else {} // if we don't recognize it then don't touch it
        }
    }

    private function set_type($type) {
        if (in_array($type, array('command', 'query'))) {
            $this->type = $type;
        }
    }

    /** Returns whether the request being made is RESTful. */
    public function is_restful() {
        return $this->restful;
    }

    /** Return information about the request. Assists with debugging.
        expects: foo=undefined
        returns: object */
    public function test($foo = null) {
        global $om;
        return array(
            'get' => $_GET,
            'post' => $_POST,
            'stdin' => $this->stdin,
            'server' => $_SERVER,
            'cookies' => $_COOKIE,
            'encoding' => $this->get_encoding(),
            'production' => $om->in_production(),
            'api' => $this->get_api(),
            'api_params' => $this->get_api_params(),
            'foo' => $foo,
            'restful_server' => $om->is_restful(),
            'restful_client' => $this->is_restful()
        );
    }

    /** Translates an API into its standard, internal representation (e.g. 'omega/service/' > 'some_service.main').
        expects: api=string
        returns: string */
    public function translate_api($api) {
        global $om;
        if (substr($api, 0, 1) === '/') {
            $api = substr($api, 1);
        }
        // TODO: deprecate this now that / is the default
        if (! $this->is_restful()) {
            // convert all periods to slashes for ease of use & backwards compatability
            $api = str_replace('.', '/', $api);
        }
        // make sure we don't start with a slash either
        if (substr($api, 0, 1) == '/') {
            $api = substr($api, 1);
        }
        // if we're asking about 'omega/service' then replace it with the service nickname for ease of ACL expression
        $matches = null;
        if (preg_match('/^(omega\/service)\/?/', $api, $matches) ||
            preg_match('/^(omega\/api)\/?/', $api, $matches)) {
            $api = $om->service_nickname . substr($api, strlen($matches[1]));
        }
        return $api;
    }

    /** Returns an object reference to the specified API path.
        expects: branches=array,
        returns: object */
    public function _get_branch_ref($branches) {
        global $om;
        // validate the input
        if (! is_array($branches)) {
            throw new Exception("Invalid parameter: \$branches. Array expected but " . gettype($branches) . " supplied.");
        }
        if (! count($branches)) {
            return $om->service;
        }
        // figure out who we are talking to
        if ($branches[0] == 'omega') {
            $service = $om;
            $nickname = 'omega';
            $branches[0] = substr($branches[0], 1);
        } else if ($branches[0] == $om->service_nickname) {
            $service = $om->service;
            $nickname = $om->service_nickname;
        } else {
            throw new Exception("Unknown service name: '" . $branches[0] . "'.");
        }
        $obj_pointer = $service;
        $r_class = new ReflectionClass($obj_pointer);
        // skip past the first branch and continue on
        array_shift($branches);
        $break_early = false;
        foreach ($branches as $branch) {
            if (! isset($obj_pointer->$branch)) {
                if ($this->is_query() || ! $r_class->hasMethod( '___404')) {
                    $om->response->header_num(404);
                    throw new Exception("Invalid API branch: '$branch'.");
                } else {
                    $break_early = true;
                }
            } else {
                // make sure the sub-branch exists in the current branch
                $r_prop = $r_class->getProperty($branch);
                // and isn't hidden (/^_/) and that it implements OmegaApi
                $branch_r_class = new ReflectionClass($obj_pointer->$branch);
                if ($r_prop->isPrivate() || substr($branch, 0, 1) == '_' || ! $branch_r_class->implementsInterface('OmegaApi')) {
                    $om->response->header_num(404);
                    throw new Exception("Invalid API branch: '$branch'.");
                }
                // get a reflection class of the current object so we can delve into it the next time around
                $r_class = new ReflectionClass($obj_pointer->$branch);
            }
            // we may break early if we have a 404 function to use
            if ($break_early) {
                return $obj_pointer;
            }
            // and advance the pointer along
            $obj_pointer = $obj_pointer->$branch;
        }
        // success!
        return $obj_pointer;
    }

    /** Returns the possibly unauthenticated credentials the user has sent the omega server.
        returns: object */
    public function get_credentials() {
        return $this->credentials;
    }

    /** Returns information about how to query omega.
        returns: string */
    public function get_query_help() {
        $t = '    ';
        return "OMEGA API Query parameters:\n${t}name=*someWord*%FLAGS\t(Only show objects/modules matching 'someWord')\n${t}${t}e.g.:\n${t}${t}${t}name=getS*%compare-case\n${t}${t}${t}n=getService*%rc\n${t}${t}${t}n=*addon*\n${t}${t}flags: c|case-sensitive\n{$t}hide=FLAGS\t(Trim results by hiding information)\n${t}${t}e.g.:\n${t}${t}${t}hide=details,m\n${t}${t}${t}h=bd\n${t}${t}flags: m|methods, b|branches\n";
    }

    public function is_query() {
        return $this->type === 'query';
    }

    public function get_api() {
        return $this->api;
    }

    public function get_api_params() {
        return $this->api_params;
    }

    public function get_type() {
        return $this->type;
    }

    private function set_encoding($encoding) {
        if (! in_array($encoding, $this->encodings)) {
            throw new Exception("Invalid parameter encoding: $encoding.");
        }
        $this->encoding = $encoding;
    }

    public function get_encoding() {
        return $this->encoding;
    }

    /** The belly of the beast. Produces an answer to the current omega request. */
    public function _answer() {
        global $om;
        // figure out whether 're talking to the service or to omega
        $api = $this->get_api();
        if (substr($api, 0, 6) == 'omega/') {
            $service = $om;
            $nickname = 'omega';
        } else {
            $service = $om->service;
            $nickname = $om->service_nickname;
        }

        if ($this->query_arg && ($api == '?' || substr($api, 0, -2) == '.?')) {
            $data = array('query_help' => $this->get_query_help());
        }
        // if the client sends a ? then answer with service information
        if ($api == '?' || ($api == $nickname && $this->is_query())) {
            $r_service = new ReflectionClass($om->service_name);
            $doc_string = $r_service->getDocComment();
            if ($doc_string === false) {
                $doc_string == '';
            }
            $data = array();
            $data['name'] = $om->service_nickname;
            $data['desc'] = trim(substr($doc_string, 3, strlen($doc_string)-5));
            // and constructor information too
            if ($r_service->hasMethod('__construct')) {
                $data['info'] = $this->_get_method_info($r_service->getMethod('__construct'), true);
            } else {
                $data['info'] = array();
            }
            // show enabled subservices
            $data['subservices'] = $om->subservice->list_enabled();
            return $data;
        }

        // get a reference to the API branch
        if ($api === '') {
            $method = '';
            $branches = array();
            $api_branch = $om->service;
        } else {
            $api_parts = explode('/', $api);
            if (count($api_parts) == 1) {
                // just one branch? the user must be initializing the service, as some are required to do
                if ($api_parts[0] !== $nickname) {
                    $om->response->header_num(404);
                    throw new Exception("Unknown service name: '" . $api_parts[0] . "'.");
                }
                // initialize the service and call it good
                $om->_init_service();
                return;
            }
            // uninitialized service? QQ
            if ($nickname != 'omega' && $om->service == null) {
                $om->response->header_num(404);
                throw new Exception("The service " . $om->service_name . " has not yet been initialized.");
            }
            // initialize params-- RESTful routes might contain some too
            if ($om->is_restful() && $this->is_restful()) {
                // good ol' RESTful
                // use the routes to figure out what class file and method to call
                // the first part is $om or $om->service, which we already figured out
                $branches = $api_parts;
                array_shift($api_parts);
                $route = $service->_route($api_parts);
                $api_branch = $route['api_branch'];
                $method = trim($route['method'], '/');
            } else {
                // if we're NOT a restful API then the last part is the method, or using an old request style...
                $method = array_pop($api_parts);
                $branches = $api_parts;
                // traverse the API tree to get the API branch and class info
                $api_branch = $this->_get_branch_ref($branches);
            }
        }
        $r_class = new ReflectionObject($api_branch);

        // if the client is asking about a branch then return that info
        if ($method == '?') {
            $branch_info = $this->_get_branch_info(
                $api_branch,
                $this->query_options['recurse'],
                $this->query_options['verbose']
            );
            return $branch_info;
        }
        // ditch the API name out of the API path for errors
        $api_parts = explode('/', $api);
        array_shift($api_parts);
        $api = join('/', $api_parts);
        // make sure the method is exists-- if not then see if the user has a ___404 method
        if (! $r_class->hasMethod($method)) {
            if (! $this->is_query() && $r_class->hasMethod('___404')) {
                return call_user_func_array(
                    array($api_branch, '___404'),
                    array($branches, $method, $this->get_api_params())
                );
            } else {
                $om->response->header_num(404);
                throw new Exception("Not found: " . $_SERVER['REQUEST_METHOD'] . " $api");
            }
        }
        $r_method = $r_class->getMethod($method);
        // not public or hidden (/^_/)? pretend you don't exist
        if ($r_method->isPrivate() || substr($method, 0, 1) == '_') {
            if (! $this->is_query() && $r_class->hasMethod('___404')) {
                return call_user_func_array(
                    array($api_branch, '___404'),
                    array($branches, $method, $this->get_api_params())
                );
            } else {
                $om->response->header_num(404);
                throw new Exception("Not found: " . $_SERVER['REQUEST_METHOD'] . " $api");
            }
        }
        // asking about this method? return that info
        if ($this->query_arg) {
            return $this->_get_method_info($r_method, $this->query_options['verbose'], $route['route']);
        }

        // make sure we have all the parameters we need to execute the API call
        $params = $this->_get_method_params($r_method);
        return call_user_func_array(
            array($api_branch, $method),
            $params
        );
    }

    /** Ensures that we have the parameters necessary to execute the specified method. */
    public function _get_method_params($r_method) {
        global $om;
        $api_params = $this->get_api_params();
        // cause 'isset' trips out on sending NULL values, saying false
        $param_names = array_keys($api_params);
        $missing_params = array();
        $params = array();
        $param_count = 0;
        foreach ($r_method->getParameters() as $i => $r_param) {
            // make sure the parameter is available, if present
            $param_name = $r_param->getName();
            if (in_array($param_name, $param_names)) {
                $params[$param_count] = $api_params[$param_name];
            } else {
                // wasn't there either? maybe it is optional...
                if ($r_param->isOptional()) {
                    $params[$param_count] = $r_param->getDefaultValue();
                } else {
                    // damn, you missed!
                    $missing_params[] = $param_name;
                }
            }
            $param_count++;
        }
        if (count($missing_params) > 0) {
            $om->response->header_num(400);
            $errors = array();
            $doc = $this->_parse_doc_string($r_method);
            // return any docs we have on missing params too
            foreach ($missing_params as $missing) {
                $error = '* ' . $missing;
                if (@$doc['expects'][$missing]) {
                    $p_info = $doc['expects'][$missing];
                    if (is_array($p_info)) {
                        if ($p_info['type']) {
                            $error .= " (" . $p_info['type'] . ")";
                        }
                        if ($p_info['desc']) {
                            $error .= ": " . $p_info['desc'];
                        }
                    } else {
                        if ($p_info) {
                            $error .= " ($p_info)";
                        }
                    }
                }
                $errors[] = $error;
            }
            throw new Exception(
                '"' . $_SERVER['REQUEST_METHOD'] . ' '
                . $this->get_api() . "\" is missing the following parameters.\n"
                . join("\n", $errors)
            );
        }
        return $params;
    }

    /** Returns an object representing the parsed doc string. Throws an exception if the formatting is incorrect.
        expects: r_method=object
        returns: object */
    public function _parse_doc_string($r_method) {
        $doc_string = $r_method->getDocComment();
        $doc = array(
            'desc' => '',
            'expects' => array(),
            'type' => null,
            'returns' => array(
                'type' => '',
                'desc' => ''
            )
        );
        if ($doc_string !== false) {
            $doc = $this->_parse_doc_omega($r_method, $doc_string);
        }
        return $doc;
    }

    public function _parse_doc_omega($r_method, $doc_string) {
        global $om;
        $types = array('object', 'number', 'string', 'boolean', 'array', 'null', 'undefined');
        // first see if we're a PHP style docstring
        if (preg_match("/\n\s*\**\s*@\w+/", $doc_string)) {
            $parser = new OmegaDocParser($doc_string);
            $params = $parser->getParams();
            $tokens = $parser->getTokens();
            return array(
                'desc' => $parser->getDesc(),
                'expects' => $params,
                'tokens' => $tokens,
                'returns' => @$tokens['return'],
                'type' => 'phpdoc'
            );
        }
        // first split it up by line, omitting the /** and */ parts
        $lines = preg_split("/(\n\r|\n|\r)/", substr($doc_string, 3, strlen($doc_string) - 5));
        $desc = '';
        $values = array(
            'expects' => array(),
            'returns' => array('type' => null, 'desc' => null)
        );
        $indent = null;
        $current_section = 'description';
        foreach ($lines as $line) {
            // check to see if we're changing to a new section
            if (preg_match('/^\s*(expects|returns):(.*)/i', $line, $matches)) {
                $current_section = $matches[1];
                // and make the line be the rest of the data
                $line = $matches[2];
            }
            // if the line isn't all space, skip it
            if (! preg_match('/\S/', $line)) {
                continue;
            } else {
                // trim out any extra spaces around the rest of the line
                $line = trim($line);
                if ($current_section == 'description') {
                    if ($desc != '') {
                        $desc = ' '; // add padding if needed
                    }
                    $desc .= $line; // minus any padding spacing
                } else if ($current_section == 'returns') {
                    if ($values['returns']['type'] === null) { // because there can be only one
                        $parts = explode(' ', $line, 2);
                        if (count($parts)) {
                            $return_type = $parts[0];
                            $values['returns']['desc'] = @$parts[1];
                        } else {
                            $return_type = $parts[0];
                        }
                        if (! in_array($return_type, $types)) {
                            throw new Exception("Invalid return type of '$return_type' in doc string for method " . $r_method->getName() . '.');
                        } else {
                            $values['returns']['type'] = $return_type;
                        }
                    } else {
                        throw new Exception("The return type '" . $matches[2] . "' has already previously been defined as '" . $values['returns'] . "' in doc string for " . $r_method->getName() . "." );
                    }
                } else if ($current_section == 'expects') {
                    $pairs = preg_split('/(\s*[,;]\s*)/', $line);
                    foreach ($pairs as $pair) {
                        $parts = preg_split('/(\s*=\s*)/', $pair);
                        if (count($parts) != 2) {
                            throw new Exception("Invalid pair format for '$pair' in method '" . $r_method->getName() . "'.");
                        }
                        // make sure this method is actually expecting the parameter named
                        $is_valid_param = false;
                        foreach ($r_method->getParameters() as $r_param) {
                            if ($parts[0] == $r_param->getName()) {
                                $is_valid_param = true;
                            }
                        }
                        if (! $is_valid_param) {
                            throw new Exception("Parameter '" . $parts[0] . "' does not exist as valid parameter for the method '" . $r_method->getName() . "'.");
                        }
                        // and verify this is a recognized type
                        if (! in_array($parts[1], $types)) {
                            throw new Exception("Invalid return type of '" . $parts[1] . "' in doc string for method " . $r_method->getName() . '.');
                        }
                        $values['expects'][$parts[0]] = $parts[1];
                    }
                } else {    
                    // ugh, never should get here
                    throw new Exception("Invalid section '$current_section'.");
                }
            }
        }
        $doc = array(
            'desc' => $desc,
            'expects' => $values['expects'],
            'returns' => $values['returns'],
            'type' => 'omdoc'
        );
        return $doc;
    }

    /** Returns information (branches, methods, etc.) about a branch.
        expects: r_method=object, recurse=boolean, verbose=false
        returns: object */
    public function _get_branch_info($branch, $recurse = false, $verbose = false) {
        global $om;
        $r_class = new ReflectionClass($branch);
        $data = array();
        if ($this->query_options['search'] !== null) {
            // check for any flags in the search
            if ($this->query_options['search_flags']['case-sensitive']) {
                $search_flags = '';
            } else {
                $search_flags = 'i';
            }
        }
        $doc_string = $r_class->getDocComment();
        if ($doc_string === false) {
            $doc_string = '';
        } else {
            // trim the '/** */' and any extra white space
            $doc_string = trim(
                substr($doc_string, 3, strlen($doc_string) - 5)
            );
        }
        $data['desc'] = $doc_string;
        // include branch/route information unless requested otherwise
        if (! $this->query_options['hide_flags']['branches']) {
            if ($this->is_restful() && $om->is_restful()) {
                $data['routes'] = array();
                // new, RESTful style listing
                foreach ($branch->_sorted_routes() as $route => $target) {
                    // can we use it?
                    if ($route !== '@pre_route') {
                        if (is_string($target)) {
                            $target = $branch->$target;
                        }
                        try {
                            $prop_branch = new ReflectionClass($target);
                        } catch (Exception $e) {
                            continue;
                        }
                        if (! $prop_branch) {
                            continue;
                        }
                        $doc_string = $prop_branch->getDocComment();
                        if ($doc_string === false) {
                            $doc_string = '';
                        } else {
                            // trim the '/** */' and any extra white space
                            $doc_string = trim(
                                substr($doc_string, 3, strlen($doc_string) - 5)
                            );
                        }
                        if ($recurse) {
                            $data['routes'][$route] = $this->_get_branch_info(
                                $target,
                                $recurse,
                                $verbose
                            );
                        } else {
                            $data['routes'][$route] = $doc_string;
                        }
                    }
                }
            } else {
                $data['branches'] = array();
                // old, deprecated non-restful style to list
                foreach ($r_class->getProperties() as $r_prop) {
                    $prop_name = $r_prop->getName();
                    // make sure this is a public object
                    if (! $r_prop->isPublic() || substr($prop_name, 0, 1) == '_') {
                        continue;
                    }
                    // and that it implements OmegaApi
                    if (is_string($branch)) {
                        if ($recurse) {
                            throw new Exception("Unable to recurse through service until it has been initialized.");
                        }
                        continue;
                    }
                    // can we use it?
                    try {
                        $prop_branch = new ReflectionClass(@get_class($branch->$prop_name));
                    } catch (Exception $e) {
                        continue;
                    }
                    if (! $prop_branch) {
                        continue;
                    }
                    if ($prop_branch->implementsInterface('OmegaApi') === false) {
                        continue;
                    }
                    // if we're searching and the name doesn't match then skip this branch
                    if ($this->query_options['search'] !== null) {
                        if (! preg_match('/^' . $regex . "$/$flags", $prop_name)) {
                            continue;
                        }
                    }
                    // otherwise add this to our list of branches, assuming we can make a reflection class out of the prop
                    // only return accessible branches
                    if ($om->subservice->is_enabled('authority')) {
                        // if we're peeking at 'omega.service' then call it by the service name instead
                        if (substr($this->api, 0, 6) == 'omega.' && $prop_name == 'service') {
                            $accessible = $om->subservice->authority->check_access(
                                $om->service_nickname . '/?',
                                $om->whoami()
                            );
                        } else {
                            $accessible = $om->subservice->authority->check_access(
                                $this->api_branch_name . "/$prop_name/?",
                                $om->whoami()
                            );
                        }
                    } else {
                        $accessible = true;
                    }
                    if ($accessible) {
                        // and check to see if we can get a docstring
                        $doc_string = $prop_branch->getDocComment();
                        if ($doc_string === false) {
                            $doc_string = '';
                        } else {
                            // trim the '/** */' and any extra white space
                            $doc_string = trim(
                                substr($doc_string, 3, strlen($doc_string) - 5)
                            );
                        }
                        if ($recurse) {
                            $data['branches'][$prop_name] = $this->_get_branch_info(
                                $branch->$prop_name,
                                $recurse,
                                $verbose
                            );
                        } else {
                            $data['branches'][$prop_name] = $doc_string;
                        }
                    }
                }
            }
        }
        // include method information unless requested otherwise
        if (! $this->query_options['hide_flags']['methods']) {
            $data['methods'] = array();
            if ($this->is_restful() && $om->is_restful()) {
                // get a list of the handlers
                foreach ($branch->_sorted_handlers() as $method => $handlers) {
                    $method = strtoupper($method);
                    $data['methods'][$method] = array();
                    foreach ($handlers as $route => $target) {
                        try {
                            $r_method = $r_class->getMethod($target);
                        } catch (Exception $e) {
                            throw new Exception("Unable to locate method '$target' on class '" . $r_class->getName() . "': " . $e->getMessage());
                        }
                        $method_info = $this->_get_method_info($r_method, $verbose, $route);
                        if ($verbose) {
                            $data['methods'][$method][$route] = $method_info;
                        } else {
                            $data['methods'][$method][$route] = $method_info['desc'];
                        }
                    }
                }
            } else {
                foreach ($r_class->getMethods() as $r_method) {
                    $method_name = $r_method->getName();
                    // make sure it is a public method and accessible
                    if (substr($method_name, 0, 1) != '_' && $r_method->isPublic()) {
                        $method_info = $this->_get_method_info($r_method, $verbose);
                        if ($method_info['accessible']) {
                            if ($verbose) {
                                $data['methods'][$method_name] = $method_info;
                            } else {
                                $data['methods'][$method_name] = $method_info['desc'];
                            }
                        }
                    }
                }
                // if we have a 404 method, show it as '*'
                $method_name = '___404';
                if ($r_class->hasMethod($method_name)) {
                    $r_method = $r_class->getMethod($method_name);
                    $method_info = $this->_get_method_info($r_method, $verbose);
                    if ($verbose) {
                        $data['methods']['*'] = $method_info;
                    } else {
                        $data['methods']['*'] = $method_info['desc'];
                    }
                }
            }
        }
        return $data;
    }

    /** Returns information (name, desc, accessibility, parameter info, etc) about a method.
        expects: r_method=object, verbose=false, route=object
        returns: object */
    public function _get_method_info($r_method, $verbose = false, $route = null) {
        global $om;
        $name = $r_method->getName();
        $doc = $this->_parse_doc_string($r_method);
        $declaring_class = $r_method->getDeclaringClass();
        $stats = array(
            'name' => $name,
            'desc' => $doc['desc'],
            'returns' => $doc['returns']
        );
        if ($doc['type'] == 'phpdoc' && $verbose) {
            $stats['tokens'] = $doc['tokens'];
            // hide param/return info, as it's duplicated
            if (isset($stats['tokens']['return'])) unset($stats['tokens']['return']);
            if (isset($stats['tokens']['param'])) unset($stats['tokens']['param']);
            if (isset($stats['tokens']['example'])) {
                $stats['example'] = $stats['tokens']['example'];
                unset($stats['tokens']['example']);
            }
            if (! count($stats['tokens'])) {
                unset($stats['tokens']);
            }
        }
        // not really needed: $stats['branch'] = $declaring_class->getName();
        // if there is an authority then include accessibility information
        if ($om->subservice->is_enabled('authority')) {
            $api = $om->request->get_api();
            if ($name === '__construct') {
                $stats['accessible'] = $om->subservice->authority->check_access($r_method->getDeclaringClass()->getName(), $om->whoami());
            } else {
                $branches = explode('/', $api);
                array_pop($branches); // pop off the method to get our location
                $stats['accessible'] = $om->subservice->authority->check_access(implode('/', $branches) . '/' . $name, $om->whoami());
            }
        } else {
            $stats['accessible'] = true;
        }
        $stats['params'] = array();
        foreach ($r_method->getParameters() as $param_pos => $r_param) {
            $param_name = $r_param->getName();
            // inlude stats on each parameter, if requested
            if (! $verbose) {
                $stats['params'][] = $param_name;
            } else {
                //$param_pos = $r_param->getPosition(); // getPosition() only in php 5.2.3+
                $stats['params'][$param_pos] = array(
                    'name' => $param_name,
                    'optional' => $r_param->isOptional()
                );
                // if we have a value that is passed by reference throw an error
                if ($r_param->isPassedByReference()) {
                    throw new Exception("Parameter '$param' of '" . $r_class->getName() . "' is passed by reference, which is not supported by this framework.");
                }
                // figure out what kind of parameter type is expected
                if ($r_param->isArray()) {
                    $stats['params'][$param_pos]['type'] = 'Array';
                } else if ($r_param->getClass() != null) {
                    $stats['params'][$param_pos]['type'] = 'Object [' . $r_param->getClass()->getName() . ']';
                }
                // take note of whether or not this param is within the route (e.g. :domain)
                if ($route !== null) {
                    if (preg_match('/[:\*](\w+)/', $route, $matches)) {
                        array_shift($matches); // first part is just the route
                        $stats['params'][$param_pos]['url_parsed'] = (in_array($param_name, $matches));
                    }
                }
                // is type info available in the doc string?
                if (isset($doc['expects'][$param_name])) {
                    // merge in docstring info on the param
                    if ($doc['type'] == 'phpdoc') {
                        $stats['params'][$param_pos] = array_merge(
                            $stats['params'][$param_pos],
                            $doc['expects'][$param_name]
                        );
                    } else {
                        $stats['params'][$param_pos]['type'] = $doc['expects'][$param_name];
                    }
                }
                // if there is a default value set by the programmer, retrieve it
                if ($r_param->isDefaultValueAvailable()) {
                    $stats['params'][$param_pos]['default_value'] = $r_param->getDefaultValue();
                }
            }
        }
        return $stats;
    }
}

?>

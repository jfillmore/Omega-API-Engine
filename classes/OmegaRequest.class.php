<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Information about the API request being issued to the omega server. */
class OmegaRequest extends OmegaRESTful {
    private $credentials = null; // the credentials, if available, that the user has supplied
    private $query_options; // options related to the query at hand
    private $api = null; // the API being executed (e.g. 'foo/1/bar')
    private $api_params = array(); // the parameters being passed to the API
    private $stdin = null; // input read via stdin
    public $is_query = false; // true when using HTTP method 'OPTIONS' for API introspection

    public function __construct() {
        $om = Omega::get();
        // and collect up the parameters -- additional parameters may picked up in the URL
        $this->collect_api_params();
        // look for any authentication information
        try {
            $this->credentials = $this->get_omega_creds();
        } catch (Exception $e) {
            // no credentials? no worries
        }
        // determine our API based on the URI
        // e.g. base_uri = '/foo/bar'
        $base_uri = OmegaLib::pretty_path($om->config->get('omega/location'), true);
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
        $request_uri = OmegaLib::pretty_path($request_uri, true);
        // determine the request type
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->is_query = true;
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
        if ($this->is_query) {
            $this->query_options = array(
                'hide_flags' => array(
                    'endpoints' => false,
                    'routes' => false
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
                'test' => 'test',
                'api' => 'get_api'
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
        $om = Omega::get();
        // rewrite the API as needed to expand it
        $api = $this->translate_api($api);
        $parts = explode('/', $api);
        // just a bit of sanity checking
        if (count($parts) == 0) {
            throw new Exception("API '$api' somehow has no parts!");
        }
        // with a properly formatted API we can see if we should infer the api name or not
        $first = $parts[0];
        if ($first === '') {
            // only way first part is blank is if the API is '/', which we can assume to be '/api_nickname'
            $parts = array($om->api_nickname);
        } else {
            // the first part is generally expected to be the api nickname or 'omega'
            if (! in_array($first, array('omega', $om->api_nickname))) {
                // just assume they gave us the api name to make API calls cleaner
                array_unshift($parts, $om->api_nickname);
            }
        }
        // save the names of everything
        $this->api = implode('/', $parts);
    }

    /* Add additional paramters to the API (e.g. when parsing routes). */
    public function add_api_params($params) {
        $this->api_params = array_merge(
            $params,
            $this->api_params
        );
        return $this->api_params;
    }

    public function get_stdin() {
        return $this->stdin;
    }

    private function get_omega_creds() {
        $om = Omega::get();
        if (isset($_SERVER['HTTP_AUTHENTICATION'])) {
            $auth_header = $_SERVER['HTTP_AUTHENTICATION'];
            $auth_parts = explode(' ', $auth_header);
            if ($auth_parts[0] === 'Basic') {
                // different format than the old version, but much easier to implement
                return $auth_parts[1];
            }
        } else {
            // if they have a session open it may contain creds alrady
            if (is_array($om->session)) {
                return $om->session['creds'];
            }
            return array();
        }
    }

    private function get_query_options() {
        return $this->query_options;
    }

    private function collect_api_params() {
        // collect the parameters based on how they were sent
        if ($_SERVER['REQUEST_METHOD'] != 'GET' 
            && strpos($_SERVER['CONTENT_TYPE'], 'application/json') === 0
            ) {
            $input = file_get_contents('php://input');
            $params = json_decode($input, true);
            if ($params === null) {
                throw new Exception("Failed to decode API parameters as JSON data.");
            }
        } else {
            // collect up named get/post vars
            $params = array();
            foreach ($_GET as $key => $value) {
                $params[$key] = $value;
            }
            // POST vars trump GET vars and overwrite
            foreach ($_POST as $key => $value) {
                $params[$key] = $value;
            }
        }
        $this->api_params = $params;
    }

    private function collect_options() {
        // only valid if we're a query
        if (! $this->is_query) {
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
                hide=endpoints,routes
                name=bob, name=bob*, name=*bob, name=*bob*, name=bob%recurse,compare-case, name=*bob%cr, name=*bob*%r,compare-case
                ? (optional, verbose output)
                etc.
            */
            if ($name == 'name' || $name == 'n') {
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
                    $this->query_options['search_flags'] = get_flags(
                        substr($matches[2], 1) /* skip the '%' */,
                        array('compare-case')
                    );
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
                $this->query_options['hide_flags'] = get_flags(
                    $value,
                    array('endpoints', 'routes')
                );
            } else {} // if we don't recognize it then don't touch it
        }
    }

    /** Return information about the request. Assists with debugging.
        expects: foo=undefined
        returns: object */
    public function test($foo = null) {
        $om = Omega::get();
        return array(
            'get' => $_GET,
            'post' => $_POST,
            'stdin' => $this->stdin,
            'server' => $_SERVER,
            'cookies' => $_COOKIE,
            'production' => $om->in_production(),
            'api' => $this->get_api(),
            'api_params' => $this->get_api_params(),
            'foo' => $foo
        );
    }

    /** Translates an API into its standard, internal representation (e.g. 'omega/api/' > 'some_api/main').
        expects: api=string
        returns: string */
    public function translate_api($api) {
        $om = Omega::get();
        if (substr($api, 0, 1) === '/') {
            $api = substr($api, 1);
        }
        // make sure we don't start with a slash either
        if (substr($api, 0, 1) == '/') {
            $api = substr($api, 1);
        }
        // if we're asking about 'omega/api' then replace it with the api nickname for ease of ACL expression
        $matches = null;
        if (preg_match('/^(omega\/api)\/?/', $api, $matches) ||
            preg_match('/^(omega\/api)\/?/', $api, $matches)) {
            $api = $om->api_nickname . substr($api, strlen($matches[1]));
        }
        return $api;
    }

    /** Returns an object reference to the specified *literal* API path.
        expects: branches=array,
        returns: object */
    public function get_branch_ref($branches) {
        $om = Omega::get();
        $four04 = $om->config->get('omega/404_route', '___404');
        // validate the input
        if (! is_array($branches)) {
            throw new Exception("Invalid parameter: \$branches. Array expected but " . gettype($branches) . " supplied.");
        }
        if (! count($branches)) {
            return $om->api;
        }
        // figure out who we are talking to
        if ($branches[0] == 'omega') {
            $target = $om;
            $nickname = 'omega';
            $branches[0] = substr($branches[0], 1);
        } else if ($branches[0] == $om->api_nickname) {
            $target = $om->api;
            $nickname = $om->api_nickname;
        } else {
            throw new Exception("Unknown API name: '" . $branches[0] . "'.");
        }
        $obj_pointer = $target;
        $r_class = new ReflectionClass($obj_pointer);
        // skip past the first branch and continue on
        array_shift($branches);
        $break_early = false;
        foreach ($branches as $branch) {
            if (! isset($obj_pointer->$branch)) {
                if ($this->is_query || ! $r_class->hasMethod($four04)) {
                    $om->response->header_num(404);
                    throw new Exception("Invalid API branch: '$branch'.");
                } else {
                    $break_early = true;
                }
            } else {
                // make sure the sub-branch exists in the current branch
                $r_prop = $r_class->getProperty($branch);
                // and isn't hidden (/^_/) and that it
                $branch_r_class = new ReflectionClass($obj_pointer->$branch);
                if ($r_prop->isPrivate() || substr($branch, 0, 1) == '_') {
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

    public function get_api($method = false) {
        $api = $this->api;
        if ($method) {
            $api = $_SERVER['REQUEST_METHOD'] . ' ' . $api;
        }
        return $api;
    }

    public function get_api_params() {
        return $this->api_params;
    }

    /** The belly of the beast. Produces an answer to the current omega request. */
    public function answer() {
        $om = Omega::get();
        // figure out whether we're talking to the API or to omega
        $api = $this->get_api();
        if (substr($api, 0, 6) == 'omega/') {
            $target = $om;
            $nickname = 'omega';
        } else {
            $target = $om->api;
            $nickname = $om->api_nickname;
        }
        $four04 = $om->config->get('omega/404_route', '___404');
        if ($this->is_query && $api == '*') {
            // "If the Request-URI is an asterisk ("*"), the OPTIONS request is intended to apply to the server in general rather than to a specific resource." -- good enough?
            // show help info on query method
            return array('query_help' => '//TODO');
        }
        // but only if introspection is enabled in general on the api
        $introspect = $om->config->get('omega/introspect', false);
        if ($this->is_query && ! $introspect) {
            throw new Exception("API introspection is not enabled.");
        }
        // get a reference to the API branch
        $api_parts = explode('/', trim($api, '/'));
        array_shift($api_parts); // remove the API name from up front
        $api = join('/', $api_parts); // truncate the name for errors
        // use the routes to figure out what class file and method to call
        $route = $target->_route($api_parts);
        $api_branch = $route['api_branch'];
        $method = trim($route['target'], '/');
        $r_class = new ReflectionObject($api_branch);
        // make sure the method exists-- if not then see if the API has a 404 method
        try {
            $r_method = $r_class->getMethod($method);
            if ($r_method->isPrivate()) {
                throw new Exception("doesn't really matter");
            }
        } catch (Exception $e) {
            $om->response->header_num(404);
            if ($r_class->hasMethod($four04)) {
                return call_user_func_array(
                    array($api_branch, $four04),
                    array($method, $this->get_api_params())
                );
            } else {
                throw new Exception("Not found: " . $_SERVER['REQUEST_METHOD'] . " $api");
            }
        }
        // make sure we have all the parameters we need to execute the API call
        return call_user_func_array(
            array($api_branch, $method),
            $this->get_method_params($r_method, $route['params'])
        );
    }

    /** Ensures that we have the parameters necessary to execute the specified method. */
    public function get_method_params($r_method, $overrides = array()) {
        $om = Omega::get();
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
                if (in_array($param_name, $overrides)) {
                    $params[$param_count] = $overrides[$param_name];
                } else {
                    $params[$param_count] = $api_params[$param_name];
                }
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
            $doc = $this->parse_doc_string($r_method);
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
                '"' . $this->get_api(true) . "\" is missing the following parameters.\n"
                . join("\n", $errors)
            );
        }
        return $params;
    }

    /** Returns an object representing the parsed doc string. Throws an exception if the formatting is incorrect.
        expects: r_method=object
        returns: object */
    public function parse_doc_string($r_method) {
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
            $doc = $this->parse_doc_omega($r_method, $doc_string);
        }
        return $doc;
    }

    public function parse_doc_omega($r_method, $doc_string) {
        // TODO: REMOVE THIS SHITNIT!
        $om = Omega::get();
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

    /** Returns information (routes, endpoints, etc.) about a branch.
        expects: r_method=object, recurse=boolean, verbose=false
        returns: object */
    public function get_branch_info($branch, $recurse = false, $verbose = false) {
        $om = Omega::get();
        if (is_a($branch, 'ReflectionClass')) {
            $r_class = $branch;
        } else {
            $r_class = new ReflectionClass($branch);
        }
        if (! is_subclass_of($branch, 'OmegaRESTful')) {
            throw new Exception("API branch does not inherit OmegaRESTful.");
        }
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
        if (! $this->query_options['hide_flags']['routes']) {
            $data['routes'] = array();
            // RESTful style listing
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
                        $data['routes'][$route] = $this->get_branch_info(
                            $target,
                            $recurse,
                            $verbose
                        );
                    } else {
                        $data['routes'][$route] = $doc_string;
                    }
                }
            }
        }
        // include method information unless requested otherwise
        if (! $this->query_options['hide_flags']['endpoints']) {
            $data['endpoints'] = array();
            // get a list of the handlers
            foreach ($branch->_sorted_handlers() as $method => $handlers) {
                $method = strtoupper($method);
                $data['endpoints'][$method] = array();
                foreach ($handlers as $route => $target) {
                    try {
                        $r_method = $r_class->getMethod($target);
                        if (! $r_method) {
                            throw new Exception('Failed to get ReflectionMethod.');
                        }
                    } catch (Exception $e) {
                        throw new Exception("Unable to locate method '$target' on class '" . $r_class->getName() . "': " . $e->getMessage());
                    }
                    $method_info = $this->get_method_info($r_method, $verbose, $route);
                    if ($verbose) {
                        $data['endpoints'][$method][$route] = $method_info;
                    } else {
                        $data['endpoints'][$method][$route] = $method_info['desc'];
                    }
                }
            }
        }
        return $data;
    }

    /** Returns information (name, desc, accessibility, parameter info, etc) about a method.
        expects: r_method=object, verbose=false, route=object
        returns: object */
    public function get_method_info($r_method, $verbose = false, $route = null) {
        $om = Omega::get();
        $name = $r_method->getName();
        $doc = $this->parse_doc_string($r_method);
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
        if ($om->auth->enabled) {
            $api = $om->request->get_api();
            if ($name === '__construct') {
                $stats['accessible'] = $om->auth->check_access(
                    $r_method->getDeclaringClass()->getName(), $om->whoami()
                );
            } else {
                $branches = explode('/', $api);
                array_pop($branches); // pop off the method to get our location
                $stats['accessible'] = $om->auth->check_access(
                    implode('/', $branches) . '/' . $name, $om->whoami()
                );
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


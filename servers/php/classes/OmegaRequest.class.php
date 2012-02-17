<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Information about the API request being issued to the omega server. */
class OmegaRequest extends OmegaRESTful implements OmegaApi {
	public $api_re = '/^(\?|\w+([\.\/]\w+\/?)*([\/\.]\?)?)$/';
	public $encodings = array('json', 'php', 'raw');
	private $encoding; // the encoding used for the data
	private $credentials; // the credentials, if available, that the user has supplied

	private $query_options; // options related to the query at hand
	private $type = 'command'; // whether the client is querying for information or running a command
	private $api = null; // the API being executed (e.g. 'omega.logger.log')
	private $api_method_name = null; // the name of the method we're executing (e.g. 'add_user')
	private $api_branch_name = null; // the name of the branch we're executing (e.g. 'fsm.charon')
	private $api_params = array(); // the parameters being passed to the API
	private $query_arg = false; // whether or not the parameters contain a ? too
	private $restful = false; // whether this request is restful or not

	public function __construct() {
		global $om;
		// figure out the API in question for the request and validate it immediately
		if (isset($_GET['OMEGA_API'])) {
			// translate the request URI to an object and method
			$this->set_api(rawurldecode($_GET['OMEGA_API']));
		} else {
			// TODO not set? infer it from our request URL if we can 
			$this->set_api($om->config->get('omega.nickname') . '.main');
		}

		// get the request encoding
		try {
			$this->set_encoding($this->get_omega_param('ENCODING'));
		} catch (Exception $e) {
			// default to 'raw'
			$this->set_encoding('raw');
		}

		// and collect up the parameters
		$this->collect_api_params();

		// look for any authentication information
		try {
			// decode them with the appropriate decoder
			$this->credentials = $this->decode($this->get_omega_param('CREDENTIALS'), $this->get_encoding());
		} catch (Exception $e) {
			// no credentials? no worries, unless there is an authority around here
			global $om;
			if ($om->subservice->is_enabled('authority')) {
				$this->credentials = null;
			}
		}

		// determine the request type
		if (substr($this->get_api(), -1) == '?') {
			$this->set_type('query');
		}

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
			'DELETE' => array(
				'test' => 'test'
			)
		);
	}

	private function set_api($api) {
		// rewrite the API as needed to expand it
		$api = $this->translate_api($api);
		// make sure it looks like what we're expecting and break the path into parts
		if (! preg_match($this->api_re, $api)) {
			throw new Exception("Invalid API format: '$api'. ");
		}
		$branches = explode('/', $api);
		// just a bit of sanity checking
		if (count($branches) == 0) {
			throw new Exception("API '$api' somehow has no parts!");
		}
		// save the names of everything
		$this->api = $api;
	}

	private function get_omega_param($param) {
		global $om;
		$param = 'OMEGA_' . strtoupper($param);
		// HTTP headers are preferred, and take priority
		if ($param === 'OMEGA_ENCODING') {
			if ($_SERVER['HTTP_CONTENT_TYPE'] === 'application/json') {
				// woot, if content-encoding is set right we're in business
				$this->restful = true;
				return 'json';
			}
		} else if ($param === 'OMEGA_API_PARAMS') {
			if ($this->get_encoding() === 'json' && $this->is_restful()) {
				return file_get_contents('php://input');
			}
		}
		// otherwise, fall back to ghetto get/post vars
		if (isset($_POST[$param])) {
			return stripslashes($_POST[$param]);
		} else if (isset($_GET[$param])) {
			return stripslashes($_GET[$param]);
		} else {
			throw new Exception("The value '$param' is not present in the GET or POST data.");
		}
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
		// split the params into positional and named params, scanning for any hail maries as we go
		$positional_params = array();
		$named_params = array();
		foreach ($params as $key => $value) {
			if (is_int($key)) {
				$positional_params[$key] = $value;
			} else {
				if ($key == '?') {
					$this->query_arg = true;
					$this->set_type('query');
				}
				$named_params[$key] = $value;
			}
		}
		$this->api_params = array(
			'positional' => $positional_params,
			'named' => $named_params
		);
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
		foreach ($api_params['named'] as $name => $value) {
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
			'stdin' => file_get_contents('php://input'),
			'http_method' => $_SERVER['REQUEST_METHOD'],
			'http_request' => $_SERVER['REQUEST_URI'],
			'http_accept' => $_SERVER['HTTP_ACCEPT'],
			'http_content_type' => $_SERVER['HTTP_CONTENT_TYPE'],
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
		// validate the API
		if (! preg_match($this->api_re, $api)) {
			throw new Exception("Invalid API: '$api'.");
		}
		// convert all periods to slashes for ease of use & backwarsd compat
		// TODO: deprecate this
		$api = str_replace('.', '/', $api);
		// make sure we don't start with a slash either
		if (substr($api, 0, 1) == '/') {
			$api = substr($api, 1);
		}
		// if we're asking about 'omega.service' then replace it with the service nickname for ease of ACL expression
		if (substr($api, 0, 13) == 'omega/service' ||
			substr($api, 0, 9) == 'omega/api') {
			$api = $om->config->get('omega.nickname') . substr($api, 13);
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
		// figure out who we are talking to
		if ($branches[0] == 'omega') {
			$service = $om;
			$nickname = 'omega';
			$branches[0] = substr($branches[0], 1);
		} else if ($branches[0] == $om->config->get('omega.nickname')) {
			$service = $om->service;
			$nickname = $om->config->get('omega.nickname');
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
			$nickname = $om->config->get('omega.nickname');
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
			$data['name'] = $om->config->get('omega.nickname');
			$data['description'] = trim(substr($doc_string, 3, strlen($doc_string)-5));
			// and constructor information too
			$data['info'] = $this->_get_method_info($r_service->getMethod('__construct'), true);
			// show enabled subservices
			$data['subservices'] = $om->subservice->list_enabled();
			return $data;
		}

		// get a reference to the API branch
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
			array_shift($api_parts);
			$route = $service->_route($api_parts);
			$api_branch = $route['api_branch'];
			$method = trim($route['method'], '/');
			// update our API params, as we got some new ones
			$this->api_params['named'] = array_merge(
				$route['params'],
				$this->api_params['named']
			);
		} else {
			// if we're NOT a restful API then the last part is the method, or using an old request style...
			$method = array_pop($api_parts);
			$branches = $api_parts;
			// traverse the API tree to get the API branch and class info
			$api_branch = $this->_get_branch_ref($branches);
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
		// make sure the method is exists-- if not then see if the user has a ___404 method
		if (! $r_class->hasMethod($method)) {
			// TODO: make this a subservice for 404's
			if (! $this->is_query() && $r_class->hasMethod('___404')) {
				return call_user_func_array(
					array($api_branch, '___404'),
					array($branches, $method, $this->get_api_params())
				);
			} else {
				$om->response->header_num(404);
				throw new Exception("API method '$method' does not exist.");
			}
		}
		$r_method = $r_class->getMethod($method);
		// not public or hidden (/^_/)? pretend you don't exist
		if ($r_method->isPrivate() || substr($method, 0, 1) == '_') {
			// TODO: make this a subservice for 404's
			if (! $this->is_query() && $r_class->hasMethod('___404')) {
				return call_user_func_array(
					array($api_branch, '___404'),
					array($branches, $method, $this->get_api_params())
				);
			} else {
				$om->response->header_num(404);
				throw new Exception("API method '$method' does not exist.");
			}
		}
		// asking about this method? return that info
		if ($this->query_arg) {
			return $this->_get_method_info($r_method, $this->query_options['verbose']);
		}

		// TODO: figure out verbosity, dryrun, etc

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
		$missing_params = array();
		$params = array();
		$param_count = 0;
		$switched_to_named = false; // once we see a named parameter the rest must also be named
		foreach ($r_method->getParameters() as $i => $r_param) {
			// make sure the parameter is available, if present
			$param_name = $r_param->getName();
			// is this in our positionals?
			if (isset($api_params['positional'][$param_count])) {
				// if we've switched to named crap out, as positionals can't be used anymore
				if ($switched_to_named) {
					$om->response->header_num(400);
					throw new Exception("Positional argument " . ($param_count + 1) . " not allowed after named parameters have been used.");
				}
				$params[$param_count] = $api_params['positional'][$param_count];
			} else {
				// we didnt' have a positional, so see if we can fill it with something from the named parameters
				if (isset($api_params['named'][$param_name])) {
					$params[$param_count] = $api_params['named'][$param_name];
					// note that we used a named parameter
					$switched_to_named = true;
				} else {
					// wasn't there either? maybe it is optional...
					if ($r_param->isOptional()) {
						$params[$param_count] = $r_param->getDefaultValue();
					} else {
						// damn, you missed!
						$missing_params[] = $param_name;
					}
				}
			}
			$param_count++;
		}
		if (count($missing_params) > 0) {
			$om->response->header_num(400);
			throw new Exception("API '" . $this->get_api() . "' is missing the following parameters: " . implode(', ', $missing_params) . '.');
		}
		return $params;
	}

	/** Returns an object representing the parsed doc string. Throws an exception if the formatting is incorrect.
		expects: r_method=object
		returns: object */
	public function _parse_doc_string($r_method) {
		$doc_string = $r_method->getDocComment();
		if ($doc_string !== false ) {
			$types = array('object', 'number', 'string', 'boolean', 'array', 'null', 'undefined');
			// first split it up by line, omitting the /** and */ parts
			$lines = preg_split("/(\n\r|\n|\r)/", substr($doc_string, 3, strlen($doc_string)-5));
			$desc = '';
			$values = array(
				'expects' => array(),
				'returns' => null
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
						if ($values['returns'] == null) { // because there can be only one
							$return_type = $line;
							if (! in_array($return_type, $types)) {
								throw new Exception("Invalid return type of '$return_type' in doc string for method " . $r_method->getName() . '.');
							} else {
								$values['returns'] = $return_type;
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
				'description' => $desc,
				'expects' => $values['expects'],
				'returns' => $values['returns']
			);
			return $doc;
		} else {
			return array(
				'description' => '',
				'expects' => array(),
				'returns' => ''
			);
		}
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
		$data['description'] = $doc_string;
		// include branch/route information unless requested otherwise
		if (! $this->query_options['hide_flags']['branches']) {
			if ($this->is_restful() && $om->is_restful()) {
				$data['routes'] = array();
				// new, RESTful style listing
				foreach ($branch->_sorted_routes() as $route => $target) {
					// can we use it?
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
								$om->config->get('omega.nickname') . '/?',
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
						$method_info = $this->_get_method_info($r_method, $verbose);
						if ($verbose) {
							$data['methods'][$method][$route] = $method_info;
						} else {
							$data['methods'][$method][$route] = $method_info['doc']['description'];
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
								$data['methods'][$method_name] = $method_info['doc']['description'];
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
						$data['methods']['*'] = $method_info['doc']['description'];
					}
				}
			}
		}
		return $data;
	}

	/** Returns information (name, description, accessibility, parameter info, etc) about a method.
		expects: r_method=object, verbose=false
		returns: object */
	public function _get_method_info($r_method, $verbose = false) {
		global $om;
		$name = $r_method->getName();
		$doc = $this->_parse_doc_string($r_method);
		$declaring_class = $r_method->getDeclaringClass();
		$stats = array('name' => $name, 'doc' => $doc);
		$stats['branch'] = $declaring_class->getName();
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
		// inlude stats on each parameter, if requested
		if ($verbose) {
			$stats['params'] = array();
			foreach ($r_method->getParameters() as $r_param) {
				$param_name = $r_param->getName();
				$param_pos = $r_param->getPosition();
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

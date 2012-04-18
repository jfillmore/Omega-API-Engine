<?php

abstract class OmegaRESTful {
    /** ROUTES
    Route proxy requests to another OmegaRESTful obj's routes/handlers
    - The beginning and ending slashes are optional.
    - Routes can have variables like ':key' in them, which will be 
      automatically collected up and passed as additional arguments to the
      target handler. 
    - Route targets should be either a ref to the OmegaRESTful object to
      route to, or a string with the object's name
    // e.g.
    return $routes = array(
        'account' => $this->account,
        '/service' => 'service',
        'server/' => $this->server,
        'plans/shared' => $this->service_plan
    );
    */
    public function _get_routes() {
        return array();
    }

    /** HANDLERS
    Handlers associate HTTP methods and Request URIs to class methods.
    As with routes, the beginning and ending slashes are optional.
    // e.g. as "Service.class.php" from 'service' route above
    return $handlers = array(
        'get' => array(
            '/' => 'find',
            ':service' => 'get',
            '/search' => 'find',
            '/search/*path' => 'find'
        ),
        'put' => array(
            '/:service' => 'replace'
        ),
        'patch' => array(
            '/:service' => 'update'
        ),
        'post' => array(
            ':service' => 'provision'
        ),
        'delete' => array(
            '/:service' => 'cancel', // double-deletion system
            '/:service/purge' => 'purge'
        )
    );
    */
    public function _get_handlers() {
        return array();
    }

    /** Sorts routes so those containing variables (e.g. '/:var/') are processed last. */
    public function _sorted_routes($routes = null) {
        global $om;
        $literals = array();
        $vars = array();
        if ($routes === null) {
            $routes = $this->_get_routes();
        }
        foreach ($routes as $route => $target) {
            $start = substr($om->_pretty_path($route, true), 0, 2);
            if ($start == '/:') {
                $vars[$route] = $target;
            } else {
                $literals[$route] = $target;
            }
        }
        return array_merge($literals, $vars);
    }

    public function _sorted_handlers($handlers = null) {
        $sorted = array();
        if ($handlers === null) {
            $handlers = $this->_get_handlers();
        }
        foreach ($handlers as $method => $routes) {
            $sorted[$method] = $this->_sorted_routes($routes);
        }
        return $sorted;
    }

    /** Routes a path to the appropriate class object within the RESTful API. Returns the class object, method to run, and extra parameters collected from the API.
        expects: path=string, params=array
        returns: string */
    public function _route($path, $params = array()) {
        global $om;
        $debug = false;
        // if the path is an array, join it up on '/'
        if (is_array($path)) {
            $path = implode('/', $path);
        }
        $path = $om->_pretty_path($path, true);
        if ($debug) echo "Routing path '$path' on " . get_class($this) . ".\n";
        // gotta have a path do to any routing
        if (strlen($path)) {
            // try to resolve path as needed within the routes
            foreach ($this->_sorted_routes() as $route => $target) {
                // normalize our route name
                $route = $om->_pretty_path($route, true);
                // convert strings to the actual branch object
                if (is_string($target)) {
                    $target = $this->$target;
                }
                // check target class as OmegaRESTful
                if (! is_subclass_of($target, get_class())) {
                    throw new Exception("Route target '" . get_class($target) . "' for '$route' in '" . get_class($this) . "' is not a RESTful object.");
                }
                // if our route matches this path...
                if ($debug) echo "\nParse path on " . get_class($this) . ": $path => route $route\n";
                $parsed = $this->_parse_path($path, $route, true);
                if ($parsed) {
                    if ($debug) echo "Routing to " . get_class($target) . "\n";
                    // recursively route until the handler is found
                    return $target->_route(
                        $parsed['sub_path'],
                        array_merge($params, $parsed['params'])
                    );
                }
            }
        }
        if ($debug) echo "Path not found; checking handlers for $path\n";
        // resolve path against our own handlers if we're still here
        $method = $_SERVER['REQUEST_METHOD'];
        $handlers = $this->_sorted_handlers();
        $query = (substr($path, -2) === '/?');
        if ($query && ($method == 'GET' || $method == 'POST')) {
            return array(
                'api_branch' => $this,
                'method' => '?',
                'route' => '/?',
                'params' => $params
            );
        }
        foreach ($handlers as $h_method => $list) {
            $h_method = strtoupper($h_method);
            if ($h_method !== $method) {
                continue;
            }
            foreach ($list as $handler => $target) {
                if ($debug) echo "\nParse path on " . get_class($this) . ": $path => handler $handler\n";
                $parsed = $this->_parse_path($path, $handler);
                if ($parsed) {
                    //die('matched: '. var_export($parsed, true));
                    return array(
                        'api_branch' => $this,
                        'route' => $handler,
                        'method' => $target,
                        'params' => array_merge($params, $parsed['params'])
                    );
                }
            }
        }
        // this will trigger a 404 
        //die('404');
        return array(
            'api_branch' => $this,
            'method' => null,
            'route' => null,
            'params' => array()
        );
    }
    
    /** Returns false if path is not within route. Otherwise returns information about the path, including extracting any arguments.
        expects: path=string, route=string
        returns: object */
    private function _parse_path($path, $route, $partial_route = false) {
        global $om;
        $debug = false;
        /* e.g. paths:
            /service,
            /service/37,
            /account/157/domain/foobar.com
            /2/bar
            /3
        */
        /* e.g. routes/handlers:
            /service,
            /service/:service,
            /account/:account/domain/:domain
            /:foo/bar
            /:foobar
        */
        // split up the path and route and compare 'em
        $path_str = $om->_pretty_path($path, true);
        $path = explode('/', substr($path_str, 1));
        $route_str = $om->_pretty_path($route, true);
        $route = explode('/', substr($route_str, 1));
        // gather params as we go
        $params = array();
        $sub_path = $path;
        $has_wildcard = (substr($route[count($route) - 1], 0, 1) === '*'); // because wildcards can match empty strings we have to be a bit extra careful
        if ($debug) echo "Route $route_str has wildcard: $has_wildcard.\n";
        if (count($route) > count($path)) {
            // route too long? ain't ever gonna match unless it contains a *wildcard at the end
            if (! $has_wildcard) {
                if ($debug) echo ": Aborted short route: $path_str vs $route_str\n";
                return false;
            }
        }
        for ($i = 0; $i < count($path); $i++) {
            if ($debug) echo "+ i = $i, count(path) = " . count($path) . ".\n";
            $path_part = $path[$i];
            // prune routes that are too short, unless partial allowed or we're going to be matching a wildcard
            if ($i >= count($route) && ! $has_wildcard) {
                if ($partial_route) {
                    if ($debug) echo "- Using partial route $route_str.\n";
                    break;
                }
                if ($debug) echo "- Aborting: route $route_str too short.\n";
                return false;
            }
            $route_part = $route[$i];
            if ($debug) echo ": Matching $route_part against $path_part.\n";
            // are we a parameter?
            // TODO: support multiple /:words/:like-:this/
            $first_char = substr($route_part, 0, 1);
            if ($path_part != '' && $first_char === ':' && $path_part != '?') {
                $param_name = substr($route_part, 1);
                $params[$param_name] = $path_part;
                if ($debug) echo "+ Collected param '$param_name' as '$path_part'.\n";
            } else if ($first_char === '*' && $path_part != '?') {
                $param_name = substr($route_part, 1);
                // since we're * we eat up all remaining parts of the path
                $params[$param_name] = join('/', array_slice($path, $i));
                $has_wildcard = false; // note that we used our wildcard up
                if ($debug) echo "+ Collected param '$param_name' as '" . $params[$param_name] . "'.\n";
                $i = count($path);
                while (count($sub_path) > 1) {
                    array_shift($sub_path);
                }
            } else if ($route_part != $path_part) {
                // mismatched section of the route/path
                if ($debug) echo "- Route part '$route_part' mismatches path part '$path_part'.\n";
                return false;
            }
            if ($debug) echo "+ Route part '$route_part' matches path part: '$path_part'.\n";
            // matched this part, so clip it out
            array_shift($sub_path);
            // if we have a wildcard next and we're at the end of the line then use it now
            if ($debug) var_export($route);
            if ($debug) var_export($i);
            if ($i + 1 >= count($path) && $i > count($route) && substr($route[$i + 1], 0, 1) == '*') {
                $param_name = substr($route[$i + 1], 1);
                $params[$param_name] = '';
                if ($debug) echo "+ Collected empty parameter to populate wildcard '$param_name' in route.\n";
            }
        }
        if ($i < count($route) - 1) {
            if ($debug) echo "+ Aborting route '$route_str', too short to match '$path_str'.\n";
            return false;

        }
        $return = array('params' => $params);
        if ($partial_route) {
            $return['sub_path'] = $sub_path;
        }
        if ($debug) echo "returning $path_str with " . var_export($return, true);
        return $return;
    }
}

?>

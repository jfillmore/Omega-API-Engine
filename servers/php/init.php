<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

// always capture errors, as omega will pick 'em up.
ini_set('display_errors', true);
ini_set('error_reporting', E_ALL);

// snag our configuration file
require_once('config.php');

// initialize our two omega variables -- needed in '__autoload' for now
$omega = null;
$om = null;
$omega_dir = getcwd(); // the CWD gets reset when _on_shutdown gets called, so we need to capture this while we can

function _clean_trace($st) {
    $stack = array();
    // hide internal frames
    array_pop($st);
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
        $line .= $trace['function'] . '(';
        if (count($trace['args'])) {
            // don't return the actual args, but the types are nice
            $args = array();
            foreach ($trace['args'] as $arg) {
                $args[] = gettype($arg);
            }
            $line .= join(', ', $args);
        }
        $line .= ')';
        $stack[] = $line;
    }
    return $stack;
}

function _fail($ex, $spillage = null, $prodution = true) {
    header('Content-Type: application/json; charset=utf-8');
    $answer = array(
        'result' => false,
        'reason' => $ex->getMessage()
    );
    if (! $prodution) {
        if ($spillage !== null) {
            $answer['spillage'] = $spillage;
        }
        $answer['data'] = array(
            'backtrace' => _clean_trace($ex->getTrace())
        );
    }
    echo json_encode($answer);
    exit(1);
}

// check each dir for either a class or interface file
function scan_dir($class_name, $dir, $depth = 5) {
    if (is_dir($dir)) {
        if (file_exists($dir . "/$class_name.class.php")) {
            require_once($dir . "/$class_name.class.php");
            return true;
        }
        if (file_exists($dir . "/$class_name.interface.php")) {
            require_once($dir . "/$class_name.interface.php");
            return true;
        }
        $found = false;
        $dir_h = opendir($dir);
        if (! $dir_h) {
            return $found;
        }
        if ($depth > 0) {
            while (false !== ($item = readdir($dir_h))) {
                if (is_dir("$dir/$item")) {
                    if ($item == '.' || $item == '..') {
                        continue;
                    }
                    $found = scan_dir(
                        $class_name,
                        $dir . "/" . $item,
                        $depth - 1
                    );
                    if ($found) {
                        break;
                    }
                }
            }
        }
        return $found;
    }
}

// define __autoload to automatically look in the omega class dir, and service include directories if available
spl_autoload_register(function ($class_name) {
    // sadly, we *must* use globals here cause PHP kinda sucks
    global $om;
    global $omega_dir;
    $dirs = array(
        $omega_dir . '/classes/',
        $omega_dir . '/classes/subservices/'
    );
    // if omega has been loaded then add the service's class dirs too
    if ($om !== null) {
        foreach ($om->config->get('omega/class_dirs') as $class_dir) {
            array_push($dirs, $class_dir);
        }
    }
    $found = false;
    foreach ($dirs as $dir) {
        $found = scan_dir($class_name, $dir);
        if ($found) {
            break;
        }
    }
    if (! $found) {
        throw new Exception("Unable to locate class object '$class_name'.");
    }
});

// load the alert class before the shutdown handler so it can load proper in case of PHP parse errors
// see "http://dev.kohanaframework.org/issues/4191" for why
new OmegaAlert();
register_shutdown_function(function () {
    $om = Omega::get();
    if ($om && ! $om->finished && $om->in_production()) {
        $alert = new OmegaAlert(
            "Internal Server Error",
            "API execution failed due to a fatal error.",
            array(
                'last_error' => error_get_last(),
                'api' => $_SERVER['REQUEST_URI']
            )
        );
    }
});

// figure out who we're talking to
$service_name = getenv('OMEGA_SERVICE');
if ($service_name == '' || $service_name === false) {
    // no service name set? peek at the URI and figure it out from there then
    $uri = null;
    // document uri = nginx rewritten URL, request_uri = actual HTTP request
    // it's preferable to use the rewritten URL so APIs can be mounted at
    // locations other than in 'omega/location'
    if (isset($_SERVER['DOCUMENT_URI'])) {
        $uri = $_SERVER['DOCUMENT_URI'];
    } else {
        $uri = $_SERVER['REQUEST_URI'];
    }
    // the first part (e.g. /api, /foo/bar/api, should be the service location)
    $location = trim(preg_replace('/\/+/', '/', $uri), '/');
    // see if it matches any of our config files
    $shed = new OmegaFileShed(OmegaConstant::data_dir);
    $server_config = $shed->get('OmegaServer/services', 'config');
    foreach ($server_config['services'] as $name => $enabled) {
        if ($enabled) {
            $config = $shed->get($name, "config");
            $service_loc = trim(@$config['omega']['location'], '/');
            if (strpos($location, $service_loc) === 0) {
                $service_name = $name;
                break;
            }
        }
    }
    if ($service_name === false) {
        header('HTTP/1.0 404 Not Found');
        exit;
    }
}


// capture any crap that PHP leaks through (e.g. warnings on functions) or that the user intentionally leaks
ob_start();
$prodution = true; // always assume we're in production by default
try {
    // start Omega up
    $omega = new Omega($service_name);
    $om = $omega; // alias it to its short name too
    $prodution = $om->in_production();
} catch (Exception $e) {
    $spillage = ob_get_contents();
    // encode the response that we'll send back
    ob_end_clean();
    // no dice? This should never happen
    _fail($e, $spillage, $prodution);
}
// see if we spilled anywhere on start up... we really never should
$spillage = ob_get_contents();
ob_end_clean();
if ($spillage) {
    // be paranoid and die if we have any warnings or errors
    $om->response->header_num(500);
    _fail(new Exception('API Spillage'), $spillage, $prodution);
}

// and let it loose
try {
    $om->_do_the_right_thing();
} catch (Exception $e) {
    // make sure we throw a proper error
    if ($om->response->is_2xx()) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
    } else {
        header($om->response->get_status());
    }
    _fail($e, $om->response->get_spillage(), $prodution);
}

?>

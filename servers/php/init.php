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

// initialize our two omega variables
$omega = null;
$om = null;

function _clean_trace($st) {
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

// define __autoload to automatically look in the omega class dir, and service include directories if available
function __autoload($class_name) {
    global $om;
    $dirs = array(
        'classes/',
        'classes/subservices/'
    );
    // if omega has been loaded then add the service's class dirs too
    if ($om !== null) {
        foreach ($om->config->get('omega.class_dirs') as $class_dir) {
            array_push($dirs, $class_dir);
        }
    }
    // check each dir for either a class or interface file
    foreach ($dirs as $dir) {
        if (file_exists($dir . "/$class_name.class.php")) {
            require_once($dir . "/$class_name.class.php");
            return;
        }
        if (file_exists($dir . "/$class_name.interface.php")) {
            require_once($dir . "/$class_name.interface.php");
            return;
        }
    }
    // didn't find it? complain!
    throw new Exception( "Unable to locate class object '$class_name'." );
}

// figure out who we're talking to
$service_name = getenv('OMEGA_SERVICE');
if ($service_name == '' || $service_name === false) {
    // no service name set? give 'em a 404 and bugger out
    header('HTTP/1.0 404 Not Found');
    exit;
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

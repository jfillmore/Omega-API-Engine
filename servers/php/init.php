<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

try {
	// always capture errors, as omega will pick 'em up.
	ini_set('display_errors', true);
	ini_set('error_reporting', E_ALL);

	// snag our configuration file
	require_once('config.php');

	// initialize our two omega variables
	$omega = null;
	$om = null;

	function _fail($exception) {
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array(
			'result' => false,
			'reason' => $exception->getMessage(),
			'data' => array(
				'backtrace' => $exception->getTraceAsString()
			)
		));
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

	/* // removed due to being too problematic for now
	function _fatal_error($error_code, $message, $error_file, $error_line, $error_context) {
		_fail(new Exception("$message ($error_code) @ $error_file:$error_line."));
	}
	*/

	// figure out who we're talking to
	$service_name = getenv('OMEGA_SERVICE');
	if ($service_name == '' || $service_name === false) {
		// no service name set? give 'em a 404 and bugger out
		header('HTTP/1.0 404 Not Found');
		exit;
	}

	// start Omega up
	$omega = new Omega($service_name);
	$om = $omega; // alias it to its short name too
} catch (Exception $e) {
	// no dice? This should never happen
	_fail($e);
}

// and let it loose
try {
	$om->_do_the_right_thing();
} catch (Exception $e) {
	if ($om->request) {
		$encoding = $om->request->get_encoding();
	} else {
		$encoding = 'raw';
	}
	if ($encoding === 'json') {
		_fail($e);
	} else {
		// TODO: support XML
		echo $e->getMessage();
		exit(1);
	}
}

?>

<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Client for talking to an Omega Server. */
class OmegaClient {
	const version = '0.1';

	private $service_url;
	private $credentials;
	private $use_ssl = true;
	private $port;
	private $verbose;
	private $cookie_file = 'c_is_for_cookie-thats_good_enough_for_me';

	public function __construct($service_url, $credentials = null, $port = 5800, $verbose = false) {
		$this->set_service_url($service_url);
		$this->set_credentials($credentials);
		$this->set_port($port);
		$this->set_verbose($verbose);
	}

	public function __destruct() {
		// TODO if we have a token then discard it before terminating
	}

	private function get_protocol() {
		if ($this->use_ssl) {
			return 'https';
		} else {
			return 'http';
		}
	}

	private function get_service_url() {
		return $this->service_url;
	}

	public function set_credentials($value) {
		if (is_array($value)) {
			// make sure we have a user or pass
			if (! (isset($value['username']) && isset($value['password']))) {
				throw new Exception("Invalid credentials array. Keys of 'username' and 'password' expected, but were not found.");
			}
			$this->credentials = $value;
		} else { // otherwise assume we've got a token
			if ($value !== '') {
				$this->credentials = $value;
			}
		}
	}

	private function get_credentials() {
		return $this->credentials;
	}

	public function set_service_url($value) {
		if ($value != '') {
			$this->service_url = $value;
		} else {
			throw new Exception('Invalid API service URL: "' . $value . '".');
		}
	}

	private function set_port($value) {
		if ($value >= 0 && $value <= 65535) {
			$this->port = $value;
		} else {
			throw new Exception('Invalid API service port: "' . $value . '".');
		}
	}

	private function get_port() {
		return $this->port;
	}

	public function __wakeup() {
		$this->init_curl();
	}
	
	private function init_curl() {
		if (! function_exists('curl_init')) {
			throw new Exception('There appears to be no support for cURL in this PHP installation.');
		}
		$curl_handle = curl_init();
		if ($curl_handle === false) {
			throw new Exception('Failed to initialize cURL handle.');
		}
		if ($this->use_ssl) {
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
		}
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl_handle, CURLOPT_POST, 1);
		curl_setopt($curl_handle, CURLOPT_HEADER, 0);
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'OmegaClient v' . OmegaClient::version);
		curl_setopt($curl_handle, CURLOPT_COOKIEFILE, $this->cookie_file);
		curl_setopt($curl_handle, CURLOPT_COOKIESESSION, 1);
		if ($curl_handle == null) {
			throw new Exception("cURL not initialized; this should not happen.");
		}
		return $curl_handle;
	}

	public function set_verbose($verbose = false) {
		if ($verbose) {
			$this->verbose = true;
		} else {
			$this->verbose = false;
		}
	}

	private function get_meta() {
		global $om;
		$meta = array(
			'timestamp' => time(),
			'secure' => $this->use_ssl,
			'server' => $this->service_url  . ':' . $this->port,
		);
		if ($this->credentials === null) {
			$meta['credentials'] = null;
		} else if (is_array($this->credentials)) {
			$meta['credentials'] = 'user|' . $this->credentials['username'];
		} else {
			$meta['credentials'] = 'token|' . $this->credentials;
		}
		return $meta;
	}

	public function exec($api, $params = null, $args = null) {
		$curl_handle = $this->init_curl();
		// TODO: polish up to match python version :)
		if ($args === null) {
			$args = array(
				'full_response' => false,
				'raw_response' => false,
				'GET' => null,
				'POST' => null,
				'verbose' => $this->verbose
			);
		}
		// check and prep the data
		if ($api == '') {
			throw new Exception("Invalid service API: '$api'.");
		}
		$api = urlencode($api);
		if ($params === null) {
			$params = '{}';
		} else {
			$params = json_encode($params);
		}
		$data = array(
			'OMEGA_ENCODING=json',
			'OMEGA_API_PARAMS=' . urlencode(addslashes($params)), 
			'OMEGA_CREDENTIALS=' . urlencode(addslashes(json_encode($this->get_credentials())))
		);
		// initialize cURL
		curl_setopt($curl_handle, CURLOPT_URL, $this->get_protocol() . '://' . $this->get_service_url() . "/$api");
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, implode('&', $data));
		curl_setopt($curl_handle, CURLOPT_PORT, $this->get_port());
		// and fire away!
		$result = curl_exec($curl_handle);
		$result_info = curl_getinfo($curl_handle);
		if ($result_info['http_code'] != 200) {
			throw new Exception('Failed to access "' . $this->get_service_url() . '" with the HTTP error code ' . $result_info['http_code'] . '. The cURL error message was "' . curl_error($curl_handle) . '".');
		}
		// make sure we got back a meaningful result
		$content_type = substr($result_info['content_type'], 0, 16);
		if ($content_type == 'application/json') {
			$response = json_decode($result, true);
			if ($response === false || $response === null) {
				throw new Exception("Failed to decode Omega response. Returned data was '$result'.");
			}
			// check to see if our API call was successful
			if (isset($response['result']) && $response['result'] == false) {
				if (isset($response['reason'])) {
					if ($args['verbose']) {
						throw new OmegaException($response['reason'], array(
							'api' => $api,
							'response' => $response,
							'meta' => $this->get_meta()
						));
					} else {
						throw new Exception($response['reason']);
					}
				} else {
					$reason = 'API "' . $api . '" to "' . $this->get_service_url() . '" failed without an explanation.';
					if ($args['verbose']) {
						throw new OmegaException($reason, array(
							'api' => $api,
							'params' => $params,
							'response' => $response,
							'meta' => $this->get_meta()
						));
					}
				}
			}
			if (isset($response['data'])) {
				return $response['data'];
			} else {
				return;
			}
		} else {
			return $result;
		}
	}
}

?>

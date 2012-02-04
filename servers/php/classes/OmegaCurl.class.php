<?php

class OmegaCurl {
	private $curl_handle;
	private $server;
	private $cookie_file;
	private $use_ssl = false;
	private $http_auth = false;
	private $http_auth_info = null;
	private $agent = null;
	private $port = null; // null = 80/443 by default

	public function __construct($hostname, $port = null, $agent = 'cURL wrapper 0.1') {
		$this->set_hostname($hostname);
		$this->cookie_file = '/tmp/.cookies.' . uniqid();
		$this->set_port($port);
		$this->set_agent($agent);
	}

	public function set_agent($agent) {
		$this->agent = $agent;
	}

	public function __destruct() {
		$this->clear_cookies();
	}

	public function clear_cookies() {
		@unlink($this->cookie_file);
	}

	private function get_protocol() {
		if ($this->use_ssl) {
			return 'https://';
		} else {
			return 'http://';
		}
	}

	public function get_port() {
		if ($this->port === null) {
			if ($this->use_ssl) {
				return 443;
			} else {
				return 80;
			}
		} else {
			return $this->port;
		}
	}

	public function set_port($port) {
		if ($port >= 0 && $port <= 65535) {
			$this->port = $port;
		} else if ($port === null) {
			// null = 80/443 by default
			$this->port = null;
		}
	}

	public function set_ssl($enable = true) {
		if ($enable) {
			$this->use_ssl = true;
		} else {
			$this->use_ssl = false;
		}
		$this->init();
	}

	private function get_hostname() {
		return $this->hostname;
	}

	private function set_hostname($value) {
		if ($value != '') {
			$this->hostname = $value;
		} else {
			throw new Exception('Invalid hostname.');
		}
	}

	public function set_http_auth($username, $password) {
		$this->http_auth = true;
		$this->http_auth_info = "$username:$password";
	}

	public function clear_http_auth() {
		$this->http_auth = false;
		$this->http_auth_info = null;
	}

	public function init() {
		$this->curl_handle = curl_init();
		if ($this->curl_handle === false) {
			throw new Exception('Failed to initialize cURL handle.');
		}
		if ($this->use_ssl) {
			curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
		}
		if ($this->http_auth && $this->http_auth_info != null) {
			curl_setopt($this->curl_handle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($this->curl_handle, CURLOPT_USERPWD, $this->http_auth_info);
		}
		curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($this->curl_handle, CURLOPT_HEADER, 0);
		curl_setopt($this->curl_handle, CURLOPT_COOKIEFILE, $this->cookie_file);
		curl_setopt($this->curl_handle, CURLOPT_COOKIEJAR, $this->cookie_file);
		if ($this->agent !== null) {
			curl_setopt($this->curl_handle, CURLOPT_USERAGENT, $this->agent);
		}
	}

	private function write_body($body) {
		if ($body) {
			$fh = fopen('php://memory', 'rw');  
			fwrite($fh, $body);
			rewind($fh);  
			curl_setopt($this->curl_handle, CURLOPT_INFILE, $fh);  
			curl_setopt($this->curl_handle, CURLOPT_INFILESIZE, strlen($body));
		}
	}

	public function request($url, $params = '', $method = 'GET', $extended = false, $headers = null) {
		$this->init();
		if ($url == '') {
			throw new Exception("Invalid URL: '$url'.");
		}
		if ($this->curl_handle == null) {
			throw new Exception("cURL not initialized; this should not happen.");
		}
		$method = strtoupper($method);
		$url = $this->get_protocol() . $this->get_hostname() . "/$url";
		$content_length = strlen($params);
		if ($method === 'GET') {	
			if (is_array($params)) {
				$param_list = array();
				foreach ($params as $key => $value) {
					$param_list[] = urlencode($key) . '=' . urlencode($value);
				}
				$param_str = implode('&', $param_list);
			} else {
				$param_str = $params;
			}
			if ($param_str) {
				$url .= "?$param_str";
			};
			$content_length = null; // no need to send content length
		} else if ($method === 'POST_FORM') {
			curl_setopt($this->curl_handle, CURLOPT_POST, 1);
			$content_length = null; // no need to send content length
			curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $params);
		} else if ($method === 'POST') {
			curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $params);
		} else if ($method === 'PUT') {
			curl_setopt($this->curl_handle, CURLOPT_PUT, 1);
			$this->write_body($params);
		} else if ($method === 'DELETE') {
			curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
			$this->write_body($params);
		} else {
			curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, $method);
			$this->write_body($params);
		}
		curl_setopt($this->curl_handle, CURLOPT_URL, $url);
		curl_setopt($this->curl_handle, CURLOPT_PORT, $this->get_port());
		if (! $headers) {
			$headers = array();
		}
		if ($content_length !== null) {
			$headers[] = 'Content-Length: ' . $content_length;
		}
		if ($headers) {
			curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $headers);
		}
		$response = curl_exec($this->curl_handle);
		$meta = curl_getinfo($this->curl_handle);
		if ($extended) {
			return array(
				'response' => $response,
				'meta' => $meta
			);
		} else {
			if (! in_array($meta['http_code'], array(200, 201))) {
				throw new Exception("Failed to access '$url' with the HTTP error code " . $meta['http_code'] . '. cURL error message was "' . curl_error($this->curl_handle) . '".');
			}
			return $response;
		}
	}

	public function get($url, $params = '', $extended = false, $headers = null) {
		return $this->request($url, $params, 'GET', $extended, $headers);
	}
	
	public function post($url, $params = '', $extended = false, $headers = null) {
		return $this->request($url, $params, 'POST', $extended, $headers);
	}

	public function update($url, $params = '', $extended = false, $headers = null) {
		return $this->request($url, $params, 'UPDATE', $extended, $headers);
	}

	public function put($url, $params = '', $extended = false, $headers = null) {
		return $this->request($url, $params, 'PUT', $extended, $headers);
	}

	public function delete($url, $params = '', $extended = false, $headers = null) {
		return $this->request($url, $params, 'DELETE', $extended, $headers);
	}

	public function get_last_error() {
		return curl_error($this->curl_handle);
	}
}

?>
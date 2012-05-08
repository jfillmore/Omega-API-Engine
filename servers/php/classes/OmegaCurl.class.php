<?php

class OmegaCurl {
    private $curl_handle;
    private $server;
    private $cookie_file;
    private $http_auth = false;
    private $http_auth_info = null;
    private $agent = null;
    private $port = null; // null = 80/443 by default
    private $base_url = null; // e.g. example.com/foo
    private $return_output = true;
    private $return_binary = true;
    private $return_header = false;
    private $timeout = 10; // request timeout, in seconds
    public $num_requests;

    public function __construct($base_url = '', $port = null, $agent = 'cURL wrapper 0.2') {
        $this->set_base_url($base_url);
        $this->cookie_file = '/tmp/.cookies.' . uniqid();
        $this->set_port($port);
        $this->set_agent($agent);
        $this->num_requests = 0;
    }

    public function set_agent($agent) {
        $this->agent = $agent;
    }

    public function set_return_transfer($value) {
        $this->return_output = (bool)$value;
    }

    public function set_return_binary($value) {
        $this->return_binary = (bool)$value;
    }

    public function set_return_header($value) {
        $this->return_header = (bool)$value;
    }

    public function __destruct() {
        $this->clear_cookies();
    }

    public function clear_cookies() {
        @unlink($this->cookie_file);
    }

    public function get_port() {
        if ($this->port === null) {
            return 80;
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

    private function get_base_url() {
        return $this->base_url;
    }

    public function set_base_url($value) {
        $this->base_url = $value;
        return $this->base_url;
    }

    public function set_http_auth($username, $password = null) {
        $this->http_auth = true;
        if ($password !== null) {
            $this->http_auth_info = "$username:$password";
        } else {
            $this->http_auth_info = "$username";
        }
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
        curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, (int)$this->return_output);
        curl_setopt($this->curl_handle, CURLOPT_BINARYTRANSFER, (int)$this->return_binary);
        curl_setopt($this->curl_handle, CURLOPT_HEADER, (int)$this->return_header);
        if ($this->agent !== null) {
            curl_setopt($this->curl_handle, CURLOPT_USERAGENT, $this->agent);
        }
        if ($this->num_requests == 0) {
            curl_setopt($this->curl_handle, CURLOPT_COOKIEJAR, $this->cookie_file);
        } else {
            curl_setopt($this->curl_handle, CURLOPT_COOKIEFILE, $this->cookie_file);
            curl_setopt($this->curl_handle, CURLOPT_COOKIEJAR, $this->cookie_file);
        }
    }

    private function write_body($body) {
        $length = 0;
        if ($body) {
            $length = strlen($body);
            $fh = fopen('php://memory', 'rw');  
            fwrite($fh, $body);
            rewind($fh);  
            curl_setopt($this->curl_handle, CURLOPT_INFILE, $fh);  
            curl_setopt($this->curl_handle, CURLOPT_INFILESIZE, $length);
        }
        return $length;
    }

    public function request($url, $params = array(), $method = 'GET', $extended = false, $headers = array(), $cookies = array()) {
        $this->init();
        if ($url == '') {
            throw new Exception("Invalid URL: '$url'.");
        }
        if ($this->curl_handle == null) {
            throw new Exception("cURL not initialized; this should not happen.");
        }
        $method = strtoupper($method);
        $url = $this->get_base_url() . "/$url";
        // write our auth info
        if ($this->http_auth && $this->http_auth_info != null) {
            $headers[] = 'Authentication: Basic ' . base64_encode(md5($this->http_auth_info));
        }
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
            if (is_array($params)) {
                $param_list = array();
                foreach ($params as $key => $value) {
                    $param_list[] = urlencode($key) . '=' . urlencode($value);
                }
                $param_str = implode('&', $param_list);
            } else {
                $param_str = $params;
            }
            $content_length = strlen($param_str);
            curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $param_str);
        } else if ($method === 'PUT') {
            curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $params);
            $content_length = strlen($params);
            //$content_length = $this->write_body($params);
        } else if ($method === 'DELETE') {
            curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $params);
            $content_length = strlen($params);
        } else {
            curl_setopt($this->curl_handle, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $params);
            $content_length = strlen($params);
        }
        curl_setopt($this->curl_handle, CURLOPT_URL, $url);
        curl_setopt($this->curl_handle, CURLOPT_PORT, $this->get_port());
        curl_setopt($this->curl_handle, CURLOPT_TIMEOUT, $this->timeout);
        if ($content_length !== null) {
            $headers[] = 'Content-Length: ' . $content_length;
        }
        if ($headers) {
            curl_setopt($this->curl_handle, CURLOPT_HTTPHEADER, $headers);
        }
        if ($cookies) {
            if (is_array($cookies)) {
                $cookies = join('; ', $cookies);
            }
            curl_setopt($this->curl_handle, CURLOPT_COOKIE, $cookies);
        }
        $result = curl_exec($this->curl_handle);
        $meta = curl_getinfo($this->curl_handle);
        $this->num_requests++;
        if ($extended) {
            return array(
                'response' => $result,
                'meta' => $meta
            );
        } else {
            return $result;
            if ($result === false ||
                $meta['http_code'] < 200 || 
                $meta['http_code'] >= 300) {
                $data = array(
                    'meta' => $meta,
                    'response' => $result
                );
                throw new OmegaException(
                    "'$url' - HTTP " . $meta['http_code'] . '. ' . $reason,
                    $data
                );
            }
            return $result;
        }
    }

    public function get($url, $params = array(), $extended = false, $headers = null, $cookies = array()) {
        return $this->request($url, $params, 'GET', $extended, $headers, $cookies);
    }
    
    public function post($url, $params = array(), $extended = false, $headers = null, $cookies = array()) {
        return $this->request($url, $params, 'POST', $extended, $headers, $cookies);
    }

    public function patch($url, $params = array(), $extended = false, $headers = null, $cookies = array()) {
        return $this->request($url, $params, 'PATCH', $extended, $headers, $cookies);
    }

    public function put($url, $params = array(), $extended = false, $headers = null, $cookies = array()) {
        return $this->request($url, $params, 'PUT', $extended, $headers, $cookies);
    }

    public function delete($url, $params = array(), $extended = false, $headers = null, $cookies = array()) {
        return $this->request($url, $params, 'DELETE', $extended, $headers, $cookies);
    }

    public function get_last_error() {
        return curl_error($this->curl_handle);
    }
}

?>

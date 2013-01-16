<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Client for talking to an Omega Server. */
class OmegaClient {
    const version = '0.2';

    private $base_url;
    private $service_url; // e.g. https://
    private $uri_root; // e.g. /, /api (parsed from service_url)
    private $credentials;
    private $verbose;
    private $port;
    public $curl;

    public function __construct($service_url, $credentials = null, $port = 5800, $verbose = false) {
        global $om;
        $this->set_verbose($verbose);
        // extract the base URL and SSL 
        $matches = array();
        //                  proto:1/2    hostname:3       path:4
        if (! preg_match('/^(http(s):\/\/)?([a-z0-9-\.]+)\/?(.*)$/i',
            $service_url,
            $matches)
            ) {
            throw new Exception("Invalid URL: '$service_url'.");
        }
        if (! $matches[1]) {
            $matches[1] = 'https://'; // default to SSL
        }
        $this->base_url = $matches[1] . $matches[3];
        $this->uri_root = $om->_pretty_path(
            '/' . $matches[4] . '/'
        );
        $this->set_service_url($service_url);
        $this->port = $port;
        $this->init_curl();
        $this->set_credentials($credentials);
    }

    private function init_curl() {
        $this->curl = new OmegaCurl(
            $this->base_url . $this->uri_root,
            $this->port
        );
    }

    public function __wakeup() {
        $this->init_curl();
    }
    
    private function get_service_url() {
        return $this->service_url;
    }

    public function set_credentials($value) {
        if (is_array($value)) {
            // make sure we have a user or pass
            if (! (isset($value['username']) &&
                isset($value['password']))
                ) {
                throw new Exception("Invalid credentials array. Keys of 'username' and 'password' expected, but were not found.");
            }
            $this->curl->set_http_auth($value['username'], $value['password']);
        }
        $this->credentials = $value;
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
            'server' => $this->service_url,
            'port' => $this->port
        );
        /*
        if ($this->credentials === null) {
            $meta['credentials'] = null;
        } else if (is_array($this->credentials)) {
            $meta['credentials'] = 'user|' . $this->credentials['username'];
        } else {
            $meta['credentials'] = 'token|' . $this->credentials;
        }
        */
        return $meta;
    }

    public function get($url, $params = array(), $args = array()) {
        return $this->parse_result($this->curl->get(
            $url,
            $params,
            true,
            array('Content-Type: application/json')
        ), $args);
    }

    public function post($url, $params = array(), $args = array()) {
        return $this->parse_result($this->curl->post(
            $url,
            json_encode($params),
            true,
            array('Content-Type: application/json')
        ), $args);
    }

    public function patch($url, $params = array(), $args = array()) {
        return $this->parse_result($this->curl->patch(
            $url,
            json_encode($params),
            true,
            array('Content-Type: application/json')
        ), $args);
    }

    public function put($url, $params = array(), $args = array()) {
        return $this->parse_result($this->curl->put(
            $url,
            json_encode($params),
            true,
            array('Content-Type: application/json')
        ), $args);
    }

    public function delete($url, $params = array(), $args = array()) {
        return $this->parse_result($this->curl->delete(
            $url,
            json_encode($params),
            true,
            array('Content-Type: application/json')
        ), $args);
    }

    public function exec($api, $params = null, $args = array()) {
        global $om;
        // deprecated, non RESTful API
        $args = $om->_get_args(array(
            'full_response' => false,
            'verbose' => $this->verbose
        ), $args);
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
            'OMEGA_API_PARAMS=' . urlencode($params), 
            'OMEGA_CREDENTIALS=' . urlencode(json_encode($this->get_credentials()))
        );
        $result = $this->curl->get(
            $api,
            implode('&', $data),
            true
        );
        return $this->parse_result($result, $args);
    }

    private function parse_result($result_info, $args) {
        global $om;
        $args = $om->_get_args(array(
            'verbose' => $this->verbose
        ), $args);
        // make sure we got back a meaningful result
        $result = $result_info['response'];
        $meta = $result_info['meta'];
        $content_type = substr($meta['content_type'], 0, 16);
        if ($meta['http_code'] < 200 || 
            $meta['http_code'] >= 300) {
            $data = array(
                'meta' => $meta,
                'response' => $result
            );
            $url = array_shift(explode('?', $meta['url'], 2));
            // did we get a JSON response w/ a message back?
            if ($content_type == 'application/json') {
                // look for an error
                $response = $this->parse_json($result, $meta, true, true);
                // only show the reason in the error
                $reason = $response['reason'];
            } else {
                $reason = $result;
            }
            throw new OmegaException(
                "$reason (HTTP " . $meta['http_code'] . ')',
                $data
            );
        }
        if ($content_type == 'application/json') {
            $response = $this->parse_json($result, $meta);
            return $response['data'];
        } else {
            return $result;
        }
    }

    private function parse_json($json, $meta, $expect_error = false, $return_error = false) {
        $response = json_decode($json, true);
        if ($response === false || $response === null) {
            throw new OmegaException("Failed to decode Omega response.", array(
            'response' => $result,
            'meta' => $meta
        ));
        }
        // check to see if our API call was successful
        if (isset($response['result']) && $response['result'] == false) {
            if (! $expect_error) {
                throw new Exception("Unexpected error in API result when HTTP code was 2xx.");
            }
            if (! isset($response['reason'])) {
                $response['reason'] = 'API to "' . $this->get_service_url() . '" failed without an explanation.';
            }
            if ($return_error) {
                return $response;
            } else {
                throw new OmegaException($response['reason'], array(
                    'response' => $response,
                    'meta' => $this->get_meta()
                ));
            }
        } else {
            if ($expect_error) {
                throw new Exception("Error expected but API result indicates success.");
            }
        }
        if (! isset($response['data'])) {
            $response['data'] = null;
        }
        return $response;
    }
}

?>

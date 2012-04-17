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
    private $curl;
    private $verbose;
    private $port;
    private $cookie_file = 'c_is_for_cookie-thats_good_enough_for_me';

    public function __construct($service_url, $credentials = null, $port = 5800, $verbose = false) {
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
        $this->uri_root = '/' . $matches[4] . '/';
        $this->set_service_url($service_url);
        $this->curl = new OmegaCurl(
            $this->base_url,
            $port
        );
        $this->port = $port;
        $this->set_credentials($credentials);
        $this->curl->init();
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

    public function __wakeup() {
        $this->curl = new OmegaCurl(
            $this->base_url,
            $this->port
        );
        $this->curl->init();
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

    public function get($url, $params = '', $args = array()) {
        return $this->parse_result($this->curl->get(
            $this->uri_root . $url,
            $params,
            true,
            array()
        ), $args);
    }

    public function post($url, $params = '', $args = array()) {
        return $this->parse_result($this->curl->post(
            $this->uri_root . $url,
            json_encode($params),
            true,
            array('Content-Type: application/json')
        ), $args);
    }

    public function put($url, $params = '', $args = array()) {
        return $this->parse_result($this->curl->put(
            $this->uri_root . $url,
            json_encode($params),
            true,
            array('Content-Type: application/json')
        ), $args);
    }

    public function delete($url, $params = '', $args = array()) {
        return $this->parse_result($this->curl->delete(
            $this->uri_root . $url,
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
            $this->uri_root . $api,
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
                            'response' => $response,
                            'meta' => $this->get_meta()
                        ));
                    } else {
                        throw new OmegaException($response['reason'], array(
                            'response' => $response,
                            'meta' => $this->get_meta()
                        ));
                        throw new Exception($response['reason']);
                    }
                } else {
                    $reason = 'API to "' . $this->get_service_url() . '" failed without an explanation.';
                    throw new OmegaException($reason, array(
                        'params' => $params,
                        'response' => $response,
                        'meta' => $this->get_meta()
                    ));
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

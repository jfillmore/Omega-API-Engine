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
    private $credentials;
    private $curl;
    private $verbose;
    private $cookie_file = 'c_is_for_cookie-thats_good_enough_for_me';

    public function __construct($base_url, $credentials = null, $port = 5800, $verbose = false) {
        $this->curl = new OmegaCurl(
            $base_url,
            $port
        );
        $this->set_verbose($verbose);
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

    public function get($url, $params = '', $extended = false, $headers = null) {
        return $this->curl->get($url, $params, $extended, $headers);
    }

    public function exec($api, $params = null, $args = array()) {
        global $om;
        // deprecated, non RESTful API
        $curl_handle = $this->init_curl();
        $args = $om->_get_args(array(
            'full_response' => false,
            'verbose' => $this->verbose
        }, $args);
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
        $result = $this->curl->get($api, implode('&', $data), true);
        return $this->parse_result($result, $args);
    }

    private function parse_result($result, $args) {
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

?>

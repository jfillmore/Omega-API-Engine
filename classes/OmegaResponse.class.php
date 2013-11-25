<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/* Basic format of the omega response for JSON responses:
    result: boolean
    reason: string (required if result is 'false')
    data: mixed (expected if result is 'true', optional if result is false)
*/

/** Information about the API response that will be returned. */
class OmegaResponse extends OmegaRESTful {
    private $response; // the array to contain the reponse we convert to JSON
    private $cookie_path;
    private $cookie_name;
    private $cookie_prefix = '';
    private $force_val;
    private $force_response = false; // whether to override API end-point responses with force_val

    public $headers;
    public $default_headers;
    public $status_codes = array(
        // 1xx Informational
        '100' => 'Continue',
        '101' => 'Switching Protocols',
        '102' => 'Processing',
        // 2xx Success
        '200' => 'OK',
        '201' => 'Created',
        '202' => 'Accepted',
        '203' => 'Non-Authoritative Information',
        '204' => 'No Content',
        '205' => 'Reset Content',
        '206' => 'Partial Content',
        '207' => 'Multi-Status',
        '226' => 'IM Used',
        // 3xx Redirection
        '300' => 'Multiple Choices',
        '301' => 'Moved Permanently',
        '302' => 'Found',
        '303' => 'See Other',
        '304' => 'Not Modified',
        '305' => 'Use Proxy',
        '307' => 'Temporary Redirect',
        // 4xx Client Error
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '402' => 'Payment Required',
        '403' => 'Forbidden',
        '404' => 'Not Found',
        '405' => 'Method Not Allowed',
        '406' => 'Not Acceptable',
        '407' => 'Proxy Authentication Required',
        '408' => 'Request Timeout',
        '409' => 'Conflict',
        '410' => 'Gone',
        '411' => 'Length Required',
        '412' => 'Precondition Failed',
        '413' => 'Request Entity Too Large',
        '414' => 'Request-URI Too Long',
        '415' => 'Unsupported Media Type',
        '416' => 'Requested Range Not Satisfiable',
        '417' => 'Expectation Failed',
        '422' => 'Unprocessable Entity',
        '423' => 'Locked',
        '424' => 'Failed Dependency',
        '426' => 'Upgrade Required',
        // 5xx Server Error
        '500' => 'Internal Server Error',
        '501' => 'Not Implemented',
        '502' => 'Bad Gateway',
        '503' => 'Service Unavailable',
        '504' => 'Gateway Timeout',
        '505' => 'HTTP Version Not Supported',
        '507' => 'Insufficient Storage',
        '510' => 'Not Extended'
    );
    private $response_status = 200;

    public function __construct() {
        $om = Omega::get();
        $this->default_headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-cache'
        );
        $this->headers = $this->default_headers;
        // TODO: allow the cookie path to be set explicitly
        $cookie_path = $om->config->get('omega/location');
        $this->set_cookie_path($cookie_path);
    }
    
    /** Sets the path for the session cookies. Defaults to the configuration item "omega.location".
        expects: path=string */
    public function set_cookie_path($path) {
        if (preg_match('/^[a-zA-Z0-9_\/]+$/', $path)) {
            $this->cookie_path = $path;
        } else {
            throw new Exception("Invalid cookie path: $path.");
        }
    }

    /** Return the session cookie path.
        returns: string */
    public function get_cookie_path() {
        return $this->cookie_path;
    }

    /** Sets the name for the session cookies. Defaults to the configuration item "omega.cookie_name".
        expects: name=string */
    public function set_cookie_name($name) {
        if (strlen($name) && strlen($name) < 64) {
            $this->cookie_name = $name;
        } else {
            throw new Exception("Invalid cookie name: '$name'.");
        }
    }

    /** Return the session cookie name.
        returns: string */
    public function get_cookie_name() {
        return $this->cookie_name;
    }

    /** Sets the prefix for the session cookies. Defaults to nothing.
        expects: prefix=string */
    public function set_cookie_prefix($prefix) {
        if (strlen($prefix) && strlen($prefix) < 64) {
            $this->cookie_prefix = $prefix;
        } else {
            throw new Exception("Invalid cookie prefix: '$prefix'.");
        }
    }

    /** Return the session cookie prefix.
        returns: string */
    public function get_cookie_prefix() {
        return $this->cookie_prefix;
    }

    /** Sets the value for the session cookies. Defaults to using a randomly generated value for session cookies..
        expects: value=string */
    public function set_cookie_value($value) {
        $om = Omega::get();
        if (strlen($value) && strlen($value) < 256) {
            $om->session_id = $value;
        } else {
            throw new Exception("Invalid cookie value: '$value'.");
        }
    }

    /** Return the session cookie value.
        returns: string */
    public function get_cookie_value() {
        $om = Omega::get();
        return $om->get_session_id();
    }

    /** Adds or sets a header to the response.
        expects: header_name=string, header_value=string, force=boolean */
    public function header($header_name, $header_value, $force = false) {
        // if it isn't in our default headers we won't support it unless forced to
        if (in_array($header_name, array_keys($this->default_headers))) {
            $this->headers[$header_name] = $header_value;
        } else {
            if ($force) {
                $this->headers[$header_name] = $header_value;
            } else {
                throw new Exception("Unable to add non-default header '$header_name' without force.");
            }
        }
    }

    /** Set a header by HTTP response number (e.g. 404, 200). Returns the current headers.
        expects: num=number
        returns: array */
    public function header_num($num) {
        $om = Omega::get();
        if (! in_array($num, array_keys($this->status_codes))) {
            throw new Exception("Invalid HTTP return status code: $num.");
        }
        $this->response_status = $num;
        return $num;
    }

    /** Returns the HTTP response status code (e.g. 200, 404) for the request.
        returns: number */
    public function get_status_code() {
        return $this->response_status;
    }

    /** Returns whether the HTTP status code is in the 1xx range.
        returns: boolean */
    public function is_1xx() {
        return ($this->response_status >= 100 &&
            $this->response_status < 200);
    }

    /** Returns whether the HTTP status code is in the 2xx range.
        returns: boolean */
    public function is_2xx() {
        return ($this->response_status >= 200 &&
            $this->response_status < 300);
    }

    /** Returns whether the HTTP status code is in the 3xx range.
        returns: boolean */
    public function is_3xx() {
        return ($this->response_status >= 300 &&
            $this->response_status < 400);
    }

    /** Returns whether the HTTP status code is in the 4xx range.
        returns: boolean */
    public function is_4xx() {
        return ($this->response_status >= 400 &&
            $this->response_status < 500);
    }

    /** Returns whether the HTTP status code is in the 5xx range.
        returns: boolean */
    public function is_5xx() {
        return ($this->response_status >= 500 &&
            $this->response_status < 600);
    }

    /** Returns the HTTP response status (e.g. '404 Not Fount') for the request.
        returns: string */
    public function get_status() {
        $num = $this->response_status;
        $code = $this->status_codes[$num];
        return $_SERVER['SERVER_PROTOCOL'] . " $num $code";
    }

    /** Sets whether or not the request was successful.
        expects: successful=boolean */
    public function set_result($successful) {
        $this->response['result'] = (bool)$successful;
    }

    /** Returns whether the response is currently successful or not.
        returns: boolean */
    public function get_result() {
        return $this->response['result'];
    }

    /** Stores any information leaked by the service.
        expects: spillage=string */
    public function set_spillage($spillage) {
        if ($spillage != '') {
            $this->response['spillage'] = $spillage;
        }
    }

    /** Returns any captured information leaked by the service.
        returns: string */
    public function get_spillage() {
        if (isset($this->response['spillage'])) {
            return $this->response['spillage'];
        }
    }

    /** Set the reason why the request was not successful. May also optionally note that the error is a user-caused error.
        expects: reason=string, user_error=boolean */
    public function set_reason($reason, $user_error = false) {
        // anything goes on the reason
        $this->response['reason'] = $reason;
        if ($user_error) {
            $this->response['user_error'] = true;
        }
    }

    /** Clears out any data currently being sent in the response. */
    public function clear_data() {
        $this->response['data'] = null;
    }

    /** Set the response data. */
    public function set_data($data) {
        $this->response['data'] = $data;
    }

    /** Returns the response as an encoded string based on our response content-type header
        expects: encoding=string
        returns: string */
    public function encode() {
        $om = Omega::get();
        // first peek at our header to see if it's anything but json... if so, change to 'raw'
        $content_type = $this->headers['Content-Type'];
        $response = $this->response;
        if (strpos($content_type, 'application/json') !== false) {
            $response = json_encode($response);
            if ($response === null && $response !== 'null') {
                throw new Exception("Unable to encode JSON response data.");
            }
            return $response;
        } else {
            // otherwise we're returning whatever we've got
            if (isset($response['result']) && $response['result']) {
                if (isset($response['data'])) {
                    if (is_resource($response['data'])) {
                        if (ftell($response['data'])) {
                            // only try to rewind if needed
                            try {
                                @rewind($response['data']);
                            } catch (Exception $e) {}
                        }
                        // assume this is a file descriptor or stream and pass it through
                        return $response['data'];
                    } else {
                        // encode arrays and objects anyway, so we have a more friendly response
                        if (is_array($response['data']) || is_object($response['data'])) {
                            $response = json_encode($response);
                        } else {
                            $response = (string)$response['data'];
                        }
                    }
                } else {
                    $response = null;
                }
            } else {
                if (isset($response['reason'])) {
                    $response = (string)$response['reason'];
                } else {
                    $response = 'An unknown failure has occurred.';
                }
            }
        }
        return $response;
    }

    /** Override the response data to return, regardless of what the API end-point may return. */
    public function force($data) {
        $this->force_response = true;
        $this->force_val = $data;
    }

    public function get_forced_response() {
        if ($this->force_response) {
            return $this->force_val;
        } else {
            throw new Exception("The response value has not been forced.");
        }
    }

    public function is_forced() {
        return $this->force_response;
    }
}


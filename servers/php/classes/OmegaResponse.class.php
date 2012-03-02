<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/* Basic format of the omega response (unless 'raw' is used as the encoding)
    result: boolean
    reason: string (required if result is 'false')
    data: mixed (expected if result is 'true', optional if result is false)
*/

/** Information about the API response that will be returned. */
class OmegaResponse extends OmegaRESTful implements OmegaApi {
    private $response; // the array to contain the reponse we convert to JSON
    private $encoding;
    private $cookie_path;
    public $headers;
    public $default_headers;
    private $response_status = 200;
    private $status_codes = array(
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

    public function __construct() {
        global $om;
        $this->default_headers = array(
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-cache'
        );
        $this->headers = $this->default_headers;
        // allow the cookie path to be set explicitly
        if (isset($_GET['OMEGA_COOKIE_PATH'])) {
            $cookie_path = $_GET['OMEGA_COOKIE_PATH'];
        } else if (isset($_POST['OMEGA_COOKIE_PATH'])) {
            $cookie_path = $_POST['OMEGA_COOKIE_PATH'];
        } else {
            $cookie_path = $om->config->get('omega.location');
        }
        $this->set_cookie_path($cookie_path);
    }
    
    /** Sets the path for any session cookies. Defaults to the configuration item "omega.location".
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

    /** Set the type of encoding that will be used to serialize the response. Default 'json', set to 'raw' or 'html' to disable response encoding (e.g. to serve a file).
        expects: encoding=string 
        returns: string */
    public function set_encoding($encoding) {
        global $om;
        // make sure we understand the encoding
        if (! in_array($encoding, $om->request->encodings)) {
            throw new Exception("Invalid response encoding: '$encoding'.");
        }
        // set the content-type as needed
        if ($encoding == 'json') {
            $this->header('Content-Type', 'application/json; charset=utf-8');
        } else if ($encoding == 'xml') {
            $this->header('Content-Type', 'application/xml; charset=utf-8');
        } else if ($encoding == 'raw' || $encoding == 'html') {
            if ($encode == 'html') {
                $this->header('Content-Type', 'text/html; charset=utf-8');
            }
        } else {
            throw new Exception("Invalid response encoding: '$encoding'.");
        }
        $this->encoding = $encoding;
    }

    /** Returns the encoding that the response will be serialized with. Defaults to the same type of encoding as was used to make the request.
        returns: string */
    public function get_encoding() {
        return $this->encoding;
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
        if ($successful == true || $successful == false) {
            $this->response['result'] = $successful;
        } else {
            throw new Exception("The value '$successful' is not a valid value for 'result'.");
        }
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
        } else {
            throw new Exception("The spillage must not be blank, or it isn't spillage.");
        }
    }

    /** Returns any captured information leaked by the service.
        returns: string */
    public function get_spillage() {
        if (isset($this->response['spillage'])) {
            return $this->response['spillage'];
        }
    }

    /** Set the reason why the request was not successful.
        expects: reason=string */
    public function set_reason($reason) {
        // anything goes on the reason
        $this->response['reason'] = $reason;
    }

    /** Clears out any data currently being sent in the response. */
    public function clear_data() {
        $this->response['data'] = null;
    }

    /** Set the response data. */
    public function set_data($data) {
        if (isset($data)) { // no worries if there isn't anything set... we'll just politely do nothing
            $this->response['data'] = $data;
        }
    }

    /** Returns the response as an encoded string. 
        expects: encoding=string
        returns: string */
    public function encode($encoding) {
        global $om;
        if (! in_array($encoding, $om->request->encodings)) {
            throw new Exception("Invalid data encoding: $encoding.");
        }
        // we won't return anything unless the result is at least set
        if (isset($this->response['result'])) {
            // if the result is false then require a reason
            if (! isset($this->response['result'])) {
                throw new Exception("An internal application error occurred. A problem occurred but no reason was logged. An administrator has been automatically notified of this problem.");
            }
            // return an encoded version of ourself
            switch ($encoding) {
                case 'json':
                    $response = json_encode($this->response);
                    if ($response === NULL) {
                        throw new Exception("Unable to encode response as '$encoding' data.");
                    }
                    break;
                case 'php':
                    $response = serialize($this->response);
                    // PHP is lame and returns false for both errors and when the serialize value is a 'false' boolean
                    if ($response === false && serialize(false) === $this->response) {
                        throw new Exception("Unable to decode response as '$encoding' data.");
                    }
                    break;
                case 'raw':
                case 'html':
                    if (isset($this->response['result']) && $this->response['result']) {
                        if (isset($this->response['data'])) {
                            if (is_resource($this->response['data'])) {
                                // assume this is a file descriptor and just pass it through
                                $response = '';
                                fpassthru($this->response['data']);
                            } else {
                                // encode data anyway, so we don't just say 'Array'
                                if (is_array($this->response['data'])) {
                                    $this->response['data'] = json_encode($this->response['data']);
                                }
                                $response = (string)$this->response['data'];
                            }
                        } else {
                            $response = null;
                        }
                    } else {
                        if (isset($this->response['reason'])) {
                            $response = (string)$this->response['reason'];
                        } else {
                            $response = 'An unknown failure has occurred.';
                        }
                    }
                    break;
            }
            return $response;
        } else {
            throw new Exception("Can't encode the response until at least the return result has been set.");
        }
    }
}

?>

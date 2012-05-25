<?php

class OmegaProxy {
    private $curl;

    public function __construct() {
        $this->curl = new OmegaCurl();
        $this->curl->set_return_header(true);
    }

    public function passthru($hostname, $port = null, $headers = array(), $uri = null, $method = null) {
        global $om;
        $port = ($port === null ? $_SERVER['SERVER_PORT'] : $port);
        // set our request to use the same info as we were given
        $this->curl->set_port($port);
        $this->curl->set_base_url($hostname);
        $this->curl->set_return_transfer(false); // return the response directly back from the server
        if (isset($_SERVER['HTTP_AUTHENTICATION'])) {
            $this->curl->set_http_auth($_SERVER['HTTP_AUTHENTICATION']);
        }
        // rewrite cookies to use the hostname of the target server
        $proxy_host = preg_replace('/^(https?:\/\/)?/', '', $hostname);
        $cookies = array();
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = "$name=$value";
        }
        // rewrite form post data to JSON
        if ($method === null) {
            $method = $_SERVER['REQUEST_METHOD'];
        }
        $method = strtoupper($method);
        if ($method === 'POST' && count($_POST)) {
            $headers[] = 'Content-Type: application/json';
            $params = json_encode($_POST);
        } else {
            $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
            $params = ($method === 'GET'
                ? array() // no params to send via GET, they are in the URL already
                : $om->request->get_stdin()
            );
        }
        // end output buffering if needed
        if (count(ob_list_handlers())) {
            $spillage = ob_get_contents();
            if ($spillage) {
                throw new Exception("Unable to proxy to $hostname; API spillage: $spillage");
            }
            ob_end_clean();
        }
        // send the proxied request
        if ($uri === null) {
            $uri = $_SERVER['REQUEST_URI'];
        }
        $result = $this->curl->request(
            $uri,
            $params,
            $method,
            true,
            $headers,
            $cookies
        );
        // cURL will return our response for us
        exit();
        /* // no longer needed -- we should be fine w/o the header rewriting logic below
        $response = $result['response'];
        // parse headers and return the body
        $parts = explode("\r\n\r\n", $response, 2); 
        $headers = explode(chr(10), $parts[0]);
        if (count($parts) > 1) {
            $body = $parts[1];
        } else {
            $body = '';
        }
        // print headers/response
        $hostname = gethostname();
        foreach ($headers as $value) {
            $value = trim(str_replace($proxy_host, $hostname, $value));
            $skip_header = (stripos($value, 'Transfer-Encoding:') === 0 || stripos($value, 'Connection:') === 0);
            if (! $skip_header) {
                header($value);
            }
        }
        echo $body;
        exit();
        */
    }
}

?>

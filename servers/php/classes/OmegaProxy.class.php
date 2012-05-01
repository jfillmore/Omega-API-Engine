<?php

class OmegaProxy {
    private $curl;

    public function __construct() {
        $this->curl = new OmegaCurl();
        $this->curl->set_return_header(true);
    }

    public function passthru($hostname, $port = null, $headers = array()) {
        global $om;
        $port = ($port === null ? $_SERVER['SERVER_PORT'] : $port);
        // set our request to use the same info as we were given
        $this->curl->set_port($port);
        $this->curl->set_base_url($hostname);
        if (isset($_SERVER['HTTP_AUTHENTICATION'])) {
            $this->curl->set_http_auth($_SERVER['HTTP_AUTHENTICATION']);
        }
        // rewrite cookies to use the hostname of the target server
        $proxy_host = preg_replace('/^(https?:\/\/)?/', '', $hostname);
        $cookies = array();
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = "$name=$value; domain=.$proxy_host; path=/";
        }
        // send the proxied request
        $response = $this->curl->request(
            $_SERVER['REQUEST_URI'],
            $om->request->get_api_params(),
            $_SERVER['REQUEST_METHOD'],
            false,
            $headers,
            $cookies
        );
        // parse headers and return the body
        $parts = explode("\r\n\r\n", $response, 2); 
        $headers = explode(chr(10), $parts[0]);
        $body = $parts[1];
        foreach ($headers as $value) {
            header(trim(
                str_replace($proxy_host, $_SERVER['SERVER_NAME'], $value)
            ));
        }
        // end output buffering if needed
        if (count(ob_list_handlers())) {
            $spillage = ob_get_contents();
            ob_end_clean();
            if ($spillage) {
                throw new Exception("Unable to proxy to $hostname; API spillage: $spillage");
            }
        }
        echo $body;
        // exit manually
        exit();
    }
}

?>
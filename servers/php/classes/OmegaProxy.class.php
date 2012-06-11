<?php

class OmegaProxy {
    private $curl;

    public function __construct() {
        $this->curl = new OmegaCurl();
        $this->curl->set_return_header(true);
    }

    public function passthru($hostname, $port = null) {
        $port = ($port === null ? $_SERVER['SERVER_PORT'] : $port);
        // init SSL socket
        $context = stream_context_create();
        stream_context_set_option($context, 'ssl', 'verify_host', false);
        $sock = stream_socket_client(
            "ssl://$hostname:$port",
            $err,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );
        // start the request and main headers
        fputs($sock, join(' ', array(
            $_SERVER['REQUEST_METHOD'],
            $_SERVER['REQUEST_URI'],
            "HTTP/1.1\n")
        ));
        fputs($sock, "Host: $hostname\n");
        $cookies = array();
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = "$name=$value";
        }
        fputs($sock, "Cookie: " . join('; ', $cookies) . "\n");
        fputs($sock, "Connection: close\n");
        fputs($sock, "Accept-Encoding: chunked;q=1.0\n"); // somehow forcing identity does not work for nginx, so forcing chunked, but will test below
         // send our data
        if (strtoupper($_SERVER['REQUEST_METHOD']) != 'GET') {
            // $input = file_get_contents('php://input');
            global $om;
            $input = $om->request->get_stdin();
            // send with the same content type as we got our data as
            fputs($sock, "Content-Type: " . $_SERVER['CONTENT_TYPE'] . "\n");
            fputs($sock, "Content-Length: " . strlen($input) . "\n\n");
            fputs($sock, $input . "\n");
        } else {
            fputs($sock, "Content-Type: " . $_SERVER['CONTENT_TYPE'] . "\n");
        }
        fputs($sock, "\n");
        
        // write out the response headers
        $chunked = false;
        while (($hdr = trim(fgets($sock))) > '') {
            header($hdr);
            /* // this works, but is problematic
            // take note of chunked encoding, as it requires special handling
            if (preg_match('~^Transfer-Encoding: .*chunked~i', $hdr)) {
                $chunked = true;
            } else {
                header($hdr);
            }
            */
        }
        header('X-Relayed-Via: ' . gethostname());
        header('X-Relayed-To: ' . $hostname);
        /* // this works, but is problematic
        if ($chunked) {
            $block_size = 1000000; // ~ 1 MB
            while ($l = trim(fgets($sock))) {
                $l = base_convert(trim($l), 16, 10);
                if ($l == 0) {
                    break;
                }
                $numChunks = floor($l / $block_size);
                $remainder = $l % $block_size;
                for ($i = 0; $i < $numChunks; $i++) {
                    echo fread($sock, $block_size);    
                }  
                echo fread($sock, $remainder);
                fread($sock, 2); // to skip the chunk-terminating \r\n
            }
        } else {
            fpassthru($sock);
        }
        */
        // return the response
        fpassthru($sock);
        exit;
    }

    public function curl_passthru($hostname, $port = null) {
        global $om;
        $port = ($port === null ? $_SERVER['SERVER_PORT'] : $port);
        // set our request to use the same info as we were given
        $this->curl->set_port($port);
        $this->curl->set_base_url($hostname);
        if (isset($_SERVER['HTTP_AUTHENTICATION'])) {
            $this->curl->set_http_auth($_SERVER['HTTP_AUTHENTICATION']);
        }
        $cookies = array();
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = "$name=$value";
        }
        // rewrite form post data to JSON
        $method = $_SERVER['REQUEST_METHOD'];
        $headers = array();
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
        $proxy_host = preg_replace('/^(https?:\/\/)?/', '', $hostname);
        foreach ($headers as $value) {
            $value = trim(str_replace($proxy_host, $hostname, $value));
            $skip_header = (stripos($value, 'Transfer-Encoding:') === 0 || stripos($value, 'Connection:') === 0);
            if (! $skip_header) {
                header($value);
            }
        }
        echo $body;
        exit();
    }
}

?>

<?php

class OmegaProxy {
    private $curl;

    public function __construct() {
        $this->curl = new OmegaCurl();
        $this->curl->set_return_header(true);
    }

    public function passthru($hostname, $port = null, $ssl = true, $method = null, $uri = null, $data = null, $content_type = null) {
        global $om;
        $port = ($port === null ? $_SERVER['SERVER_PORT'] : $port);
        $timeout = 5;
        $om->_flush_ob(false);
        // init SSL socket
        if ($ssl) {
            $context = stream_context_create();
            stream_context_set_option($context, 'ssl', 'verify_host', false);
            $sock = stream_socket_client(
                "ssl://$hostname:$port",
                $err,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        } else {
            $sock = stream_socket_client(
                "tcp://$hostname:$port",
                $err,
                $errstr,
                $timeout
            );
        }
        if ($sock === false ) {
            throw new Exception("Failed to initialize socket to $hostname.");
        }
        // start the request and main headers
        $method = $method ? $method : $_SERVER['REQUEST_METHOD'];
        $uri = $uri ? $uri : $_SERVER['REQUEST_URI'];
        $content_type = $content_type ? $content_type : $_SERVER['CONTENT_TYPE'];
        $req = array();
        $req[] = join(' ', array(
            $method,
            $uri,
            "HTTP/1.1"
        ));
        $req[] = "Host: $hostname";
        // pass on any cookies
        $cookies = array();
        foreach ($_COOKIE as $name => $value) {
            $cookies[] = "$name=$value";
        }
        if ($cookies) {
            $req[] = "Cookie: " . join('; ', $cookies);
        }
        // any auth header too
        if (isset($_SERVER['HTTP_AUTHENTICATION'])) {
            $req[] = "Authentication: " . $_SERVER['HTTP_AUTHENTICATION'];
        }
        // other headers to ease things along
        $req[] = "Connection: close";
        $req[] = "Accept-Encoding: chunked;q=1.0"; // somehow forcing identity does not work for nginx, so forcing chunked, but will test below
        // send our data
        if (strtoupper($method) != 'GET') {
            // $input = $data ? $data : file_get_contents('php://input');
            $input = $data ? $data : $om->request->get_stdin();
            // send with the same content type as we got our data as
            $req[] = "Content-Type: " . $content_type;
            $req[] = "Content-Length: " . strlen($input) . "\n";
            $req[] = $input;
        } else {
            $req[] = "Content-Type: " . $content_type;
        }
        $req[] = "\n";
        fputs($sock, join("\n", $req));
        
        // write out the response headers
        $chunked = false;
        while (($hdr = trim(fgets($sock))) > '') {
            if (stripos($hdr, 'Connection:') === 0) {
                header($hdr);
            } else if (preg_match('~^Transfer-Encoding: .*chunked~i', $hdr)) {
                $chunked = true;
            } else {
                header($hdr);
            }
        }
        header('X-Relayed-Via: ' . gethostname());
        header('X-Relayed-To: ' . $hostname);

        // handle the response body
        if ($chunked) {
            $out = $om->_get_output_stream(false);
            // gotta dechunk the response ourselves
            $block_size = 32000;
            while ($l = trim(fgets($sock))) {
                $l = base_convert(trim($l), 16, 10);
                if ($l == 0) {
                    break;
                }
                $numChunks = floor($l / $block_size);
                $remainder = $l % $block_size;
                for ($i = 0; $i < $numChunks; $i++) {
                    /*
                    echo fread($sock, $block_size);    
                    */
                    $copied = stream_copy_to_stream($sock, $out, $block_size);
                    if ($copied === false) {
                        throw new Exception("Failed to copy proxy stream data.");
                    }
                }  
                if ($remainder) {
                    /*
                    echo fread($sock, $remainder);
                    */
                    $copied = stream_copy_to_stream($sock, $out, $remainder);
                    if ($copied === false) {
                        throw new Exception("Failed to copy proxy stream data.");
                    }
                }
                fread($sock, 2); // to skip the chunk-terminating \r\n
            }
        } else {
            fpassthru($sock);
        }
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
        $spillage = $om->_flush_ob(false);
        if ($spillage) {
            throw new Exception("Unable to proxy to $hostname; API spillage: $spillage");
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

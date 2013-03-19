<?php

class OmegaApiDoc {
    private $api_tree;
    private $api_index;

    public function __construct($api_tree) {
        /* // e.g.
            methods: (empty)
            desc: Hosts and manages omega services powered by this omega server.
            routes:
                /config:
                    routes: (empty)
                    methods:
                        POST:
                            /:service/*path:
                                accessible: True
                                returns:
                                    type: undefined
                                    desc: (none/null)
                                params:
                                    0
                                        type: string
                                        url_parsed: True
                                        optional: False
                                        name: service
                                name: set
                                desc: Update configuration information for a service.
                /manage:
                    routes: (empty)
                    methods:
                        PUT: (empty)
                    desc: Omega service management methods.
        */
        if (is_array($api_tree) && isset($api_tree['routes'])) {
            $this->api_tree = $api_tree;
        }
        $this->parse_api($api_tree);
    }

    public function parse_api($branch, $depth = '') {
        // gather up the methods
        foreach($branch['methods'] as $method => $api_list) {
            ksort($api_list);
            foreach ($api_list as $uri => $info) {
                $path = rtrim("$depth$uri", '/');
                $this->api_index[$path] = array(
                    'method' => $method,
                    'info' => $info,
                    'path_html' => "<div class=\"method\">$method</div> $path",
                    'path' => "$method $path"
                );
            }
        }
        // recurse into any sub-routes
        ksort($branch['routes']);
        foreach($branch['routes'] as $route => $sub_branch) {
            $this->parse_api($sub_branch, OmegaLib::pretty_path("$depth/$route"));
        }
    }

    public function to_json() {
        return json_encode($this->api_tree);
    }

    public function to_html() {
        $title = "DMS API";
        $api_html = $this->parse_html();
        $json_link = "<a href=\"api.json\" target=\"_blank\">JSON-formatted API list</a>";
        $json_link = "<div class=\"json_link\">$json_link</div>\n";
        $body = "<div id=\"api_nav\">{$api_html['api_nav']}</div>\n<div id=\"api_list\"><h2>$title</h2>{$api_html['api_list']}$json_link</div>\n";
        //$js = "<script type=\"text/javascript\" src=\"https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js\"></script>\n";
        //$js .= "<script type=\"text/javascript\" src=\"api.js\"></script>\n";
        $css = "<link rel=\"stylesheet\" type=\"text/css\" href=\"api.css\">\n";
        $html = "<!DOCTYPE html><html>\n<head><title>$title</title>$css</head>\n<body>$body</body>\n</html>";
        return $html;
    }

    public function parse_html() {
        $nav_html = array();
        $api_html = array();
        foreach ($this->api_index as $uri => $api) {
            $ahref = str_replace('/', '', $api['path']);
            $ahref = str_replace(' ', '_', $ahref);
            $html = "<div class=\"api_end_point\" id=\"$ahref\">\n  <h3>{$api['path_html']}</h3>\n";
            $html .= "\n" . '  <div class="api_desc">' . $api['info']['desc'] . '</div>';
            $html .= '  <div class="api_params">' . "\n";
            foreach ($api['info']['params'] as $param) {
                $html .= "   <div class=\"api_param\">";
                $html .= "\n        <div class=\"param_name\">{$param['name']}";
                if (@$param['url_parsed']) {
                    $html .= "<small>(In URI)</small>";
                } else {
                    // url parsed == no type
                    if (@$param['type']) {
                        $html .= "<small>{$param['type']}</small>";
                    }
                }
                $html .= "</div>\n"; // param name
                if (! @$param['desc']) {
                    $param['desc'] = '';
                }
                if ($param['optional']) {
                    $param['desc'] .= " <em>Default: " . json_encode($param['default_value']) . "</em>";
                }
                $html .= "\n      <div class=\"param_desc\">{$param['desc']}</div>";
                $html .= " \n </div>";
            }
            $html .= "\n  </div>"; // api_params
            $html .= "</div>\n\n"; // api_end_point
            $api_html[] = $html;
            $nav_html[] = "<div class=\"nav_item\"><a href=\"#$ahref\">{$api['path_html']}</a></div>";
        }
        return array(
            'api_nav' => join("\n", $nav_html),
            'api_list' => join("\n", $api_html)
        );
    }
}

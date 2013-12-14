<?php

class OmegaApiDoc {
    private $api_tree;
    private $api_index;
    private $routes;
    private $name;

    public function __construct($name, $api_tree) {
        $this->name = $name;
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

    public function parse_api($branch, $depth = '/') {
        // gather up the methods
        foreach($branch['methods'] as $method => $api_list) {
            ksort($api_list);
            foreach ($api_list as $uri => $info) {
                $path = OmegaLib::pretty_path("$depth$uri", true);
                $this->api_index[] = array(
                    'method' => $method,
                    'info' => $info,
                    'path_html' => "<div class=\"method\">$method</div> $path",
                    'path' => "$method $path"
                );
            }
        }
        // recurse into any sub-routes
        ksort($branch['routes']);
        $this->routes[$depth] = $branch['desc'];
        foreach($branch['routes'] as $route => $sub_branch) {
            $this->parse_api($sub_branch, OmegaLib::pretty_path("$depth/$route"));
        }
    }

    public function to_json() {
        return json_encode($this->api_tree);
    }

    public function to_html() {
        $title = $this->name . " API Documentation";
        $api_html = $this->parse_html();
        $json_link = "<a href=\"api.json\" target=\"_blank\">JSON-formatted API list</a>";
        $json_link = "<div class=\"json_link\">$json_link</div>\n";
        $api_docs = $this->parse_docs();
        $body = "<div id=\"api_nav\">{$api_html['api_nav']}</div>\n"
            . "<div id=\"api_list\"><div class=\"docs\">$api_docs</div>"
            . "{$api_html['api_list']}$json_link</div>\n";
        //$js = "<script type=\"text/javascript\" src=\"https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js\"></script>\n";
        //$js .= "<script type=\"text/javascript\" src=\"api.js\"></script>\n";
        $css = "<link rel=\"stylesheet\" type=\"text/css\" href=\"api.css\">\n";
        $html = "<!DOCTYPE html><html>\n<head><title>$title</title>$css</head>\n<body>$body</body>\n</html>";
        return $html;
    }

    private function parse_docs() {
        $html = '';
        foreach ($this->routes as $route => $desc) {
            if ($route == '/') {
                $route = $this->name;
            }
            $desc = $this->tab_format($desc);
            $html .= "<h3 class=\"branch_name\">$route</h3>"
                . "<p class=\"branch_desc\">$desc</p>";
        }
        return $html;
    }

    private function tab_format($text, $width = 4) {
        $lines = explode("\n", trim($text));
        // change preceeding spaces/tabs to HTML entities for each line -- fails on mixed tabs/spaces for now
        $formatted = array();
        foreach ($lines as $line) {
            $i = 0;
            while (substr($line, $i, 1) == "\t") {
                $i++;
            }
            $line = str_repeat('&nbsp;', $i * $width) . substr($line, $i);
            $i = 0;
            while (substr($line, $i, 1) == " ") {
                $i++;
            }
            $line = str_repeat('&nbsp;', $i) . substr($line, $i);
            $formatted[] = $line;
        }
        return join("\n<br/>", $formatted);
    }

    public function parse_html() {
        $nav_html = array();
        $api_html = array();
        foreach ($this->api_index as $api) {
            $ahref = str_replace('/', '', $api['path']);
            $ahref = str_replace(' ', '_', $ahref);
            $api_desc = str_replace("\n", '<br>', $api['info']['desc']);
            $html = "<div class=\"api_end_point\" id=\"$ahref\">\n  <h3>{$api['path_html']}</h3>\n";
            $html .= "\n" . '  <div class="api_desc">' . $api_desc . "</div>\n";
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
                $html .= "\n      <div class=\"param_desc\">"
                    . $this->tab_format($param['desc']) . "</div>";
                $html .= " \n </div>";
            }
            $html .= "\n  </div>"; // api_params
            if (@$api['info']['example']) {
                $html .= "  <div class=\"api_example\">"
                    . $this->tab_format($api['info']['example']) . "</div>\n";
            }
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

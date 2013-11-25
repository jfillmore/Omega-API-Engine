<?php

/* omega - PHP host API
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Hosts and manages omega APIs powered by this omega server. */
class OmegaServer extends OmegaRESTful {
    public $api_manager;
    public $config;

    public function _get_routes() {
        return array(
            '/config' => $this->config,
            '/api' => $this->api_manager
        );
    }

    public function __construct() {
        $om = Omega::get();
        // always make sure the omega server is secured
        $om->config->set('omega/auth', true, true);
        $this->config = new OmegaServerConfig();
        $this->api_manager = new OmegaApiManager();
    }
}

?>

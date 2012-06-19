<?php

/* omega - PHP host service
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Hosts and manages omega services powered by this omega server. */
class OmegaServer extends OmegaRESTful implements OmegaApi {
    public $service_manager;
    public $config;

    public function _get_routes() {
        return array(
            '/config' => $this->config,
            '/manage' => $this->service_manager
        );
    }

    public function __construct() {
        global $om;
        // make sure the authority and logger are enabled for the host service
        $fixed_setup = false;
        foreach (array('logger', 'authority') as $subservice) {
            if ($om->subservice->is_disabled($subservice)) {
                $om->subservice->enable($subservice);
                $fixed_setup = true;
            }
        }
        $this->config = new OmegaServerConfig();
        $this->service_manager = new OmegaServiceManager();
    }
}

?>

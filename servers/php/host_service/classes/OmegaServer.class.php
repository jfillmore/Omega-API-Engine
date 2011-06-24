<?php

/* omega - PHP host service
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Hosts and manages omega services powered by this omega server. */
class OmegaServer implements OmegaApi {
	public $service_manager;
	public $config;

	public function __construct() {
		global $om;
		// make sure the authority and logger are enabled
		$fixed_setup = false;
		foreach (array('logger', 'authority') as $subservice) {
			if (! $om->subservice->is_active($subservice)) {
				$om->subservice->activate($subservice);
				$fixed_setup = true;
			}
			if ($om->subservice->is_disabled($subservice)) {
				$om->subservice->enable($subservice);
				$fixed_setup = true;
			}
		}
		if ($fixed_setup) {
			throw new Exception("The OmegaServer was not setup properly. It has been automatically configured for secure use.");
		}
		// load up our configuration
		$this->config = new OmegaServerConfig();
		// and build the services branch
		$this->service_manager = new OmegaServiceManager();
	}
}

?>

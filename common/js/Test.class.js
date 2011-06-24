/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

(function (om) {
	om['Test'] = {
		hostname_re: /^([a-zA-Z0-9_-]+\.)*[a-zA-Z0-9-]+\.[a-zA-Z0-9\-]+$/,
		ip4_address_re: /^\d{1,3}(\.\d{1,3}){3}$/,
		email_address_re: /^[a-zA-Z0-9+._-]+@[a-zA-Z0-9+._\-]+$/,
		word_re: /^[a-zA-Z0-9_-]+$/
	};
	om.test = om.Test;
}(om));

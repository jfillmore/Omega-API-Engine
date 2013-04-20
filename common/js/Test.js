/* omega - web client
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

(function (om) {
    om.Test = om.doc({
        desc: 'A few useful regular expressions.',
        obj: {
            hostname_re: /^([a-zA-Z0-9_-]+\.)*[a-zA-Z0-9-]+(\.[a-zA-Z0-9\-]+)?$/,
            ip4_address_re: /^\d{1,3}(\.\d{1,3}){3}$/,
            email_address_re: /^[a-zA-Z0-9+._-]+@[a-zA-Z0-9+._\-]+$/,
            word_re: /^[a-zA-Z0-9_-]+$/
        }
    });
    om.test = om.Test;
}(om));

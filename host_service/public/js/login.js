/* omega - PHP host service
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

$(document).ready(function() {
    // log user in
    $('#login_box form').on('submit', function (ev) {
        var api = om.ApiClient({
            url: '/os_admin/'
        });
    });
    $('#login_box input:first').focus();
});

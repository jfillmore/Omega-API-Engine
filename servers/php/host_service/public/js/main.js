/* omega - PHP host service
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

$(document).ready(function() {
	var os,
		login,
		gui;
	
	login = om.bf.make.collect(
		$('body'),
		'o m e g a',
		'',
		{
			'username': {
				type: 'text',
				args: {
					default_val: '',
					caption: 'Username'
				}
			},
			'password': {
				type: 'password',
				args: {
					default_val: '',
					caption: 'Password'
				}
			}
		},
		{
			submit_caption: 'Log in',
			modal: true,
			on_submit: function (trigger_event, input) {
				var loading = om.bf.make.loading();
				os = om.OmegaClient({
					name: 'os',
					creds: input
				});
				os.exec_na(
					'?',
					[],
					// if the login succeeds then get things going
					function (response) {
						// and initialize the GUI
						gui = Gui(os);
						window.gui = gui;
						// clear away our loading box
						loading._remove();
					},
					// otherwise report the error
					function (response) {
						var conf;
						// prevent the login from disappearing
						trigger_event.preventDefault();
						conf = om.bf.make.confirm(
							login.$,
							'Login Failure',
							response,
							{
								modal: true,
								on_close: function () {
									// focus the password
									login.$.find('input[type=password]').focus();
								}
							}
						);
						conf._center_top(0.2, login.$);
						// clear away our loading box
						loading._remove();
					}
				);
			}
		}
	);
	login.$.find('.om_form_cancel').remove();
	login.$.addClass('login_box');
	login._center_top(0.1);
	login.$.find('input:first').focus();
});

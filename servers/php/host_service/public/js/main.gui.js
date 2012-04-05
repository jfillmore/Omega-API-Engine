/* omega - PHP host service
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

$(document).ready(function() {
	var app, App;

	App = function () {
		var app;
		app = om.bf.box($('body > .canvas'));
		app.content = om.bf.box($('body > .content'));
		app.path = '/omdocs/';
		app.client = om.OmegaClient({
			url: app.path
		});
		app.speed = 300;
		app.init = function () {
			app.nav = new App.Nav({
				app: app
			});
			app.router = new App.Router();
			Backbone.history.start({
				root: '/omdocs/',
				pushState: false
			});
		};
		return app;
	};

	App.Nav = Backbone.View.extend({
		el: $('body > .footer_nav'),
		events: {
			'click a.link': 'link_click',
		},
		link_click: function (click_ev) {
			var link;
			link = $(click_ev.target).attr('href');
			// intercept the click and use AJAX to update the page instead
			click_ev.preventDefault();
			click_ev.stopPropagation();
			link = link.substr(app.path.length);
			app.router.navigate(link, {
				trigger: true
			});
			return false;
		}
	});

	App.Router = Backbone.Router.extend({
		routes: {
			'*path': 'nav'
		},
		embedded: [
			'docs/libs/js'
		],
		nav: function (path) {
			this.select_link(path);
			if ($.inArray(path, this.embedded) >= 0) {
				app.content.$.html(
					'<iframe src="' + app.path + 'html/' + path + '.html"/>'
				);
			} else if (path) {
				this.load_template(path + '.html', app.content, {
					html_dir: app.path + 'html'
				});
			} else {
				// default to overview
				this.navigate('overview', {trigger: true});
			}
		},
		select_link: function (path) {
			app.nav.$('a.link.selected').toggleClass('selected');
			app.nav.$('a.link').each(function (num, link) {
				var link = $(link);
				if (link.attr('href') === app.path + path) {
					link.toggleClass('selected', true);
				}
			});
		},
		load_template: (function () {
			var templates; // internal template cache
			templates = {};

			return function (path, owner, args) {
				var on_fetch, loading;
				args = om.get_args({
					fresh: false,
					on_load: undefined,
					on_fail: undefined,
					insert: undefined,
					html_dir: 'html'
				}, args);
				on_fetch = function (tpl) {
					var template;
					// clear the current HTML and add our own
					om.bf.box(owner, {html: ''});
					template = om.bf.make.box(owner, {
						'class': 'html',
						html: _.template(tpl),
						insert: args.insert
					});
					om.get(args.on_load, template, path);
				};
				if (path in templates && ! args.fresh) {
					on_fetch(templates[path]);
				} else {
					loading = om.bf.make.loading(app);
					$.ajax({
						url: args.html_dir + '/' + path,
						success: function (template) {
							templates[path] = template;
							loading._remove();
							on_fetch(template);
						},
						error: function (xml_http_request, text_status, error_thrown) {
							loading._remove();
							om.error(text_status);
							//om.error(xml_http_request.responseText);
							om.get(args.on_fail, xml_http_request, text_status, error_thrown);
						}
					});
				}
			};
		})()
	});

	app = App();
	app.init();

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

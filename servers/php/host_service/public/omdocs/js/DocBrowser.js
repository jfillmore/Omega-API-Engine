/* omega - web client
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

$(document).bind('ready', function () {
	var app;

	DocBrowser = function (args) {
		var app;
		args = om.get_args({
			owner: $('#doc_browser')
		}, args);
		app = om.bf.box(args.owner);
		app.path = '/omdocs/';
		app.obj = om; // the obj to document
		app.nav = new DocBrowser.Nav(
			app.$.children('ul.nav'), app
		);
		app.docs = new DocBrowser.DocView({
			owner: app.$.children('div.documentation'),
			app: app,
			model: new DocBrowser.DocObj()
		});
		app.router = new DocBrowser.Router({
			app: app
		});
		app.init = function () {
			// load up our examples and start the app
			$.get(app.path + 'examples.html', function (examples) {
				var i, script, example, func;
				examples = $(examples);
				for (i = 0; i < examples.length; i++) {
					example = $(examples[i]);
					if (example.is('h3')) {
						func = example.text();
						app.examples[func] = 
							jQuery.trim(example.next().html());
						// skip over the next item, as it was our script
						i += 1;
					}
				}
				Backbone.history.start();
			});
		};
		app.examples = {};
		app.init();
		return app;
	};

	DocBrowser.DocObj = Backbone.Model.extend({
		defaults: {
			path: '',
			name: '',
			desc: '',
			desc_ext: '',
			params: {},
			is_func: false
		}
	});

	DocBrowser.ExampleCode = Backbone.Model.extend({
		defaults: {
			code: ''
		}
	});

	DocBrowser.Example = Backbone.View.extend({
		events: {
			'keydown textarea.example_code': 'keypress'
		},
		initialize: function (args) {
			_.bindAll(this, 'render');
			this.model.bind('change', this.render);
			this.code = this.$('> textarea.example_code');
			this.canvas = this.$('> div.canvas');
		},
		render: function () {
			var code;
			code = this.model.get('code') || '// no example available';
			this.code.html(code);
			this.canvas.html('');
			(function (canvas) {
				try {
					eval(code);
				} catch (e) {
					canvas.html('There was an error parsing the above code.');
				}
			}(this.canvas));
		},
		clear: function () {
			this.model.set({
				code: undefined
			});
		},
		keypress: function (key_ev) {
			var text, code;
			// make tabs fancy
			if (key_ev.keyCode === 9) {
				key_ev.preventDefault();
				key_ev.stopPropagation();
				text = $(key_ev.target);
				code = text.html();
			}
		}
	});

	DocBrowser.DocView = Backbone.View.extend({
		initialize: function (args) {
			_.bindAll(this, 'render');
			this.model.bind('change', this.render);
			this.setElement(args.owner);
			this.app = args.app;
			this.path = this.$el.children('.path');
			this.name = this.$el.children('.name');
			this.desc = this.$el.children('.desc');
			this.desc_ext = this.$el.children('.desc_ext');
			this.params = this.$el.children('.params');
			this.example = new DocBrowser.Example({
				el: this.app.$.children('.example'),
				model: new DocBrowser.ExampleCode
			});
		},
		render: function () {
			var path, params, param_list, is_func, parse_params;
			path = this.model.get('path') || '';
			is_func = this.model.get('is_func');
			this.path.html(path);
			this.name.hide();
			//this.name.html(this.model.get('name') || '');
			this.desc.html(this.model.get('desc') || '');
			this.desc_ext.html(this.model.get('desc_ext') || '');
			// show our parameters, if we have any
			this.params.html('<ul class="param_list"></ul>');
			param_list = this.params.children('ul');
			params = this.model.get('params') || {}; 
			parse_params = function (info, name) {
				var param;
				param = param_list.append(om.sprintf(
					'<li><span class="param_name">%s</span>' +
					'<span class="param_type">%s</span>' +
					'<span class="param_desc">%s</span>' +
					'<span class="param_desc_ext">%s</span>' +
					'<span class="param_default">%s</span></li>',
					name,
					info.type || '',
					(info.desc || '').replace(/\n/, '<br/>'),
					(info.desc_ext || '').replace(/\n/, '<br/>'),
					(info.default_val !== undefined ?
						'Default: ' + String(info.default_val) :
						''
					)
				));
				// recurse if needed
				if (info.type === 'object' && info.params) {
					param.append('<ul class="param_list"></ul>');
					param_list = param.children('ul');
					_.each(info.params, arguments.callee, this);
				}
			};
			_.each(params, parse_params, this);
			if (! om.empty(params)) {
				this.params.prepend('<h3>Arguments</h3>');
			}
			// if we have example code then show it
			if (this.app.examples[path]) {
				this.example.model.set({
					code: this.app.examples[path]
				});
			} else {
				this.example.clear();
			}
		},
	});

	DocBrowser.Nav = Backbone.View.extend({
		initialize: function (owner, app) {
			this.setElement(owner);
			this.app = app;
			this.render();
		},
		events: {
			'click a': 'click_link'
		},
		render: function () {
		},
		click_link: function (click_ev) {
			var href = $(click_ev.target).attr('href').substr(1);
			this.app.router.navigate(href, {trigger: true});
		}
	});

	DocBrowser.Router = Backbone.Router.extend({
		initialize: function (args) {
			this.app = args.app;
		},
		routes: {
			'*path': 'route_path'
		},
		route_path: function (path) {
			var parts, part, i, doc, target, name;
			if (path === '') {
				this.navigate('om', {trigger: true});
				return;
			}
			parts = path.split('.');
			// first part should be 'om'
			if (parts.length === 0) {
				throw new Error('Invalid path: ' + path);
			}
			if (parts[0] !== 'om') {
				throw new Error('Invalid path: ' + path);
			}
			// start by looking at 'om'
			target = this.app.obj;
			for (i = 1; i < parts.length; i++) {
				part = parts[i];
				if (target[part] !== undefined) {
					name = part;
					target = target[part];
				} else {
					throw new Error('Invalid path "' + path + '", failed at part "' + part + '".');
				}
			}
			// no docs? no dice
			if (! '_doc' in target) {
				throw new Error("No ._doc attribute found for " + path);
			}
			doc = target._doc;
			// select the link this route corresponds to
			this.select_nav(
				// figure out which link is clicked based on the href
				_.filter(this.app.nav.$('li.nav_item a'), function (link) {
					return $(link).attr('href') === '#' + path;
				}, this)
			);
			// figure out which obj to view docs for
			this.app.docs.model.set({
				path: path,
				name: name,
				desc: doc.desc,
				desc_ext: doc.desc_ext,
				params: doc.params,
				is_func: typeof(target) === 'function'
			});
		},
		select_nav: function (link) {
			link = $(link);
			this.app.nav.$('li.nav_item a.selected').
				toggleClass('selected', false);
			link.toggleClass('selected', true);
		}
	});

	app = DocBrowser();
});

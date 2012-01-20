/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
(function (om) {
	/** Auto-gui for interacting with and browsing through a service. */
	om.Diviner = function (owner, omega_client, args) {
		var diviner;

		if (args === undefined) {
			args = {};
		}
		if (args.gui_colors === undefined) {
			args.gui_colors = ['#5F0609', '#ab9322'];
		}
		if (args.gui_color_step === undefined) {
			args.gui_color_step = 4;
		}

		/* Structure
		   ========= */
		diviner = om.bf.make.win(owner, {
			dont_show: true,
			toolbar: ['min', 'close'],
			resizable: null,
			'class': 'om_diviner'
		});
		diviner.toolbar = diviner._toolbar;
		diviner.toolbar.$.toggleClass('toolbar', true);
		diviner.toolbar.$.append('<img class="browser" alt="@" src="/omega/images/diviner/browser.png" /><img class="switch_nav" alt="#" src="/omega/images/diviner/switch.png" /></div>');
		diviner.toolbar.nav_path = diviner.toolbar._add_box('nav_path');
		// and set the navigation to the middle
		diviner.nav = diviner._canvas._extend('middle', 'nav');
		diviner.nav.columns = [];
		diviner.hud = diviner._extend('bottom', 'hud');
		diviner.hud.divide = diviner.hud._add_box('divide');
		/* -- ideas --
			Overview
				Configuration
					add edit later
				Subservices [enabled|disabled, toggle]

			Logs
				manage filters
				show log by branch/api links in nav to auto-filter log entries
				toggle, ctrl+click to add, alt+click to remove
					show tooltip

			Security
				manage users
				toggle to filter nav based on user's rights
					use subservice logic to determine nav mods?

			Editor
				show edit links in nav, view source in editor
					(add edit later)

			Monitor
				Zabbix-like user system
				Tracks exceptions
					via API/branch

			Profiler
				view/refresh stats
				(tracking at some point?)
		*/
		diviner.hud.boxes = {count: 0};
		diviner.hud.menu = om.bf.make.menu(
			diviner.hud.$, {
				overview: {
					caption: 'Overview',
					on_select: function (select_event, option) {
						if (diviner.hud.boxes.overview) {
							diviner.hud.boxes.overview.$.show();
						} else {
							diviner.hud.boxes.overview = diviner.OverviewHud({
								on_close: function (close_event) {
									close_event.preventDefault();
									option._unselect();
								}
							});
							diviner.hud.boxes.count += 1;
						}
					},
					on_unselect: function (unselect_event, option) {
						diviner.hud.boxes.overview._remove();
						delete diviner.hud.boxes.overview;
						diviner.hud.boxes.count -= 1;
					}
				},
				logger: {
					caption: 'Logs',
					on_select: function (select_event, option) {
						// TODO: make sure the subservice is enabled
						if (diviner.hud.boxes.logger) {
							diviner.hud.boxes.logger.$.show();
						} else {
							diviner.hud.boxes.logger = diviner.LoggerHud({
								on_close: function (close_event) {
									close_event.preventDefault();
									option._unselect();
								}
							});
							diviner.hud.boxes.count += 1;
						}
					},
					on_unselect: function (unselect_event, option) {
						diviner.hud.boxes.logger._remove();
						delete diviner.hud.boxes.logger;
						diviner.hud.boxes.count -= 1;
					}
				},
				security: {
					caption: 'Security',
					on_select: function (select_event, option) {
						// TODO: make sure the subservice is enabled
					},
					on_unselect: function (unselect_event, option) {
					}
				},
				editor: {
					caption: 'Editor',
					on_select: function (select_event, option) {
						// TODO: make sure the subservice is enabled
						if (diviner.hud.boxes.editor) {
							diviner.hud.boxes.editor.$.show();
						} else {
							diviner.hud.boxes.editor = diviner.EditorHud({
								on_close: function (close_event) {
									close_event.preventDefault();
									option._unselect();
								}
							});
							diviner.hud.boxes.count += 1;
						}
					},
					on_unselect: function (unselect_event, option) {
						diviner.hud.boxes.editor._remove();
						delete diviner.hud.boxes.editor;
						diviner.hud.boxes.count -= 1;
					}
				}
			}, {
				multi_select: false,
				'class': 'hud_menu',
				options_orient: 'bottom'
			}
		);
		// move our menu options to the bottom
		diviner.hud.menu.canvas = diviner.hud.menu._extend('middle', 'canvas');
		// the sidebar will display subservice data
		diviner.sidebar = diviner._canvas._extend('right', 'sidebar');

		/* Methods
		   ======= */
		diviner.SubserviceToggle = function (owner, subservice, config) {
			var toggle;
			toggle = om.bf.make.box(owner, {
				'class': 'subservice_toggle'
			});
			toggle.options = toggle._extend('right');
			toggle.content = toggle._extend('middle');
			toggle.caption = toggle.content._add_box('caption');
			toggle.caption.$.html(om.ucfirst(subservice));
			toggle.description = toggle.content._add_box('description');
			toggle.description.$.html(config.description);
			toggle.pri_menu = om.bf.make.menu(toggle.options.$, {
				'inactive': {
					caption: 'Inactive',
					'class': 'inactive',
					on_select: function (select_event, option) {
						if (diviner.subservices[toggle.subservice].active) {
							toggle.set_active(select_event, false);
						}
					}
				},
				'active': {
					caption: 'Active',
					'class': 'active',
					on_select: function (select_event, option) {
						if (! diviner.subservices[toggle.subservice].active) {
							toggle.set_active(select_event, true);
						}
					}
				}
			}, {
				'class': 'pri_menu'
			});
			// make a secondary menu that only appears while subservice is activated
			toggle.sec_menu = om.bf.make.menu(
				toggle.options.$, {
					'disabled': {
						'class': 'disabled',
						caption: 'Disabled',
						on_select: function (select_event, option) {
							if (diviner.subservices[toggle.subservice].enabled) {
								toggle.set_enabled(select_event, false);
							}
						}
					},
					'enabled': {
						'class': 'enabled',
						caption: 'Enabled',
						on_select: function (select_event, option) {
							if (! diviner.subservices[toggle.subservice].enabled) {
								toggle.set_enabled(select_event, true);
							}
						}
					}
				}, {
					'class': 'sec_menu',
					dont_show: true
				}
			);
			
			/* methods */
			toggle.set_enabled = function (select_event, enabled) {
				var query, fields;
				if (enabled === undefined) {
					enabled = true;
				}
				select_event.preventDefault();
				query = om.bf.make.query(
					diviner.hud.$,
					(enabled ? 'Enable' : 'Disable') + ' sub-service?',
					'Are you sure you wish to ' + (enabled ? 'enable' : 'disable') + ' the <span class="highlight">' + toggle.subservice + '</span> sub-service?',
					{
						modal: true,
						form_fields: (enabled ? {} : fields),
						ok_caption: (enabled ? 'Enable' : 'Disable'),
						on_ok: function (click_event, input) {
							var loading, args, i;
							args = {'subservice': toggle.subservice};
							if (enabled) {
								args['config'] = null;
							} else {
								args['clear_data'] = input.clear_data;
							}
							loading = om.bf.make.loading(diviner.hud.$);
							diviner.client.exec(
								'omega.subservice.' + (enabled ? 'enable' : 'disable'),
								args,
								function (config) {
									diviner.subservices[toggle.subservice].enabled = enabled;
									diviner.$.toggleClass('ss_' + toggle.subservice, enabled);
									// clear any cached data on our list of subservices
									if (diviner.client.shed.get('branch_info', 'omega.subservice')) {
										diviner.client.shed.forget('branch_info', 'omega.subservice');
										// reload the column data for subservice info
										if (diviner.nav.columns.length > 2 && diviner.nav.columns[2].name === 'omega.subservice.' + toggle.subservice) {
											for (i = 0; i < diviner.nav.columns[0].branches.length; i++) {
												if (diviner.nav.columns[0].branches[i].name === 'subservice') {
													diviner.nav.columns[0].branches[i].select();
													break;
												}
											}
										} else if (diviner.nav.columns.length > 1 && diviner.nav.columns[1].name === 'omega.subservice') {
											diviner.nav.columns[1].reload_data(function () {
												// are we viewing another subservice? restore our selection
												if (diviner.nav.columns.length > 2) {
													for (i = 0; i < diviner.nav.columns[1].branches.length; i++) {
														if ('omega.subservice.' + diviner.nav.columns[1].branches[i].name === diviner.nav.columns[2].name) {
															diviner.nav.columns[1].branches[i].$.toggleClass('selected', true);
															diviner.nav.columns[1].branches[i].paint();
														}
													}
												}
											});
										}
									}
									// select ourself
									toggle.sec_menu._options[enabled ? 'enabled' : 'disabled'].$.toggleClass('om_selected', true);
									toggle.sec_menu._options[enabled ? 'disabled' : 'enabled'].$.toggleClass('om_selected', false);
									loading._remove();
								},
								function (err) {
									om.error(err);
									loading._remove();
								}
							);
						}
					}
				);
			};

			toggle.set_active = function (select_event, active) {
				var query, fields;
				if (active === undefined) {
					active = true;
				}
				fields = {
					clear_data: {
						type: 'checkbox',
						args: {
							caption: 'Delete data?',
							caption_orient: 'left',
							default_val: false
						}
					}
				};
				select_event.preventDefault();
				query = om.bf.make.query(
					diviner.hud.$,
					(active ? 'Activate' : 'Deactivate') + ' sub-service?',
					'Are you sure you wish to ' + (active ? 'activate' : 'deactivate') + ' the <span class="highlight">' + toggle.subservice + '</span> sub-service?',
					{
						modal: true,
						form_fields: (active ? {} : fields),
						ok_caption: (active ? 'Activate' : 'Deactivate'),
						on_ok: function (click_event, input) {
							var loading, args;
							args = {'subservice': toggle.subservice};
							if (active) {
								args['config'] = null;
							} else {
								args['clear_data'] = input.clear_data;
							}
							loading = om.bf.make.loading(diviner.hud.$);
							diviner.client.exec(
								'omega.subservice.' + (active ? 'activate' : 'deactivate'),
								args,
								function (config) {
									diviner.subservices[toggle.subservice].active = active;
									toggle.pri_menu._options[active ? 'active' : 'inactive'].$.toggleClass('om_selected', true);
									toggle.pri_menu._options[active ? 'inactive' : 'active'].$.toggleClass('om_selected', false);
									if (active) {
										toggle.sec_menu.$.show(diviner.gui_speed);
									} else {
										diviner.subservices[toggle.subservice].enabled = false;
										toggle.sec_menu.$.hide(diviner.gui_speed);
									}
									toggle.sec_menu._options[active ? 'enabled' : 'disabled'].$.toggleClass('om_selected', false);
									toggle.sec_menu._options[active ? 'disabled' : 'enabled'].$.toggleClass('om_selected', true);
									// select ourself
									loading._remove();
								},
								function (err) {
									om.error(err);
									loading._remove();
								}
							);
						}
					}
				);
			};

			toggle.init = function () {
				// if we're activated show that option as selected
				if (diviner.subservices[toggle.subservice].active) {
					toggle.pri_menu._options.active.$.toggleClass('om_selected', true);
					// enabled too? toggle the right radio button
					if (diviner.subservices[toggle.subservice].enabled) {
						toggle.sec_menu._options.enabled.$.toggleClass('om_selected', true);
					} else {
						toggle.sec_menu._options.disabled.$.toggleClass('om_selected', true);
					}
					toggle.sec_menu.$.show();
				} else {
					toggle.pri_menu._options.inactive.$.toggleClass('om_selected', true);
				}
			};

			/* init */
			toggle.subservice = subservice;
			toggle.init();
			return toggle;
		};

		diviner.HudBox = function (owner, title, args) {
			var hudbox;
			hudbox = om.bf.make.box(owner, {
				'class': 'hudbox'
			});
			if (args === undefined) {
				args = {};
			}
			if (args.on_close === undefined) {
				args.on_close = null;
			}
			hudbox.args = args;
			hudbox.header = hudbox._extend('top', 'header');
			hudbox.header.controls = hudbox.header._add_box('controls');
			hudbox.header.controls.$.html('<img class="close_hudbox" alt="close" src="/omega/images/diviner/close-icon.png" />');
			hudbox.header.title = hudbox.header._add_box('title');
			hudbox.header.title.$.html(title);
			/* methods */
			hudbox._box_remove = hudbox._remove;
			hudbox._remove = function () {
				hudbox.$.animate({
					width: '0px'
				}, diviner.gui_speed * 0.5, function () {
					var min_height;
					hudbox._box_remove();
					// if we were the last hudbox...
					if (diviner.hud.menu.canvas.$.children('.hudbox').length === 0) {
						// remember how high the hud was for restoral
						diviner.hud.last_height = diviner.hud.$.height();
						min_height = parseInt(diviner.hud.divide.$.outerHeight(), 10);
						min_height += parseInt(diviner.hud.menu._options_box.$.outerHeight(), 10);
						// shrink the hud to it's minimum height, also resizing the nav to match
						diviner.nav.$.css('bottom', min_height + 'px');
						// hide the hud canvas by sliding it to the bottom
						diviner.hud.$.animate({
							height: min_height + 'px'
						}, diviner.gui_speed, function () {
							diviner.hud.menu.canvas.$.hide();
							diviner.nav.$.trigger('resize');
						});
					}
				});
			};

			hudbox.init = function () {
				var hud_height;
				// if the hud is closed then set the hud to either 50% of the
				// diviner height or the last height we were dragged to
				if (diviner.hud.boxes.count === 0) {
					diviner.hud.menu.canvas.$.show();
					if (diviner.hud.last_height === undefined) {
						hud_height = parseInt(
							parseInt(diviner.$.height(), 10) * 0.5,
							10
						);
					} else {
						hud_height = diviner.hud.last_height;
					}
					diviner.hud.$.animate({
						height: hud_height + 'px'
					}, diviner.gui_speed, function () {
						diviner.nav.$.css('bottom', hud_height + 'px');
						diviner.nav.$.trigger('resize');
						diviner.hud.$.trigger('resize');
						if (typeof(hudbox.args.on_show) === 'function') {
							// update the column scrollbars
							//diviner.nav.$.find('.om_scroller').trigger('scroll.om');
							if (hudbox.args.on_show !== undefined) {
								hudbox.args.on_show();
							}
						}
					});
				} else {
					if (hudbox.args.on_show !== undefined) {
						hudbox.args.on_show();
					}
				}
			};

			hudbox.close = function () {
				hudbox.$.trigger('close');
			};

			/* init */
			// make our close box work
			hudbox.header.controls.$.find('img.close_hudbox').bind('click', function () {
				hudbox.close();
			});
			hudbox.$.bind('close', function (close_event) {
				if (typeof(hudbox.args.on_close) === 'function') {
					hudbox.args.on_close(close_event);
				}
				if (! close_event.isDefaultPrevented()) {
					hudbox.$.slideUp(diviner.gui_speed, function () {
						hudbox._remove();
					});
				}
				close_event.stopPropagation();
				close_event.preventDefault();
			});
			hudbox.init();
			return hudbox;
		};

		diviner.OverviewHud = function (args) {
			var overview, pos1, pos2;
			overview = diviner.HudBox(
				diviner.hud.menu.canvas.$,
				'Overview',
				args
			);
			overview.$.toggleClass('overview', true);
			// service configuration info
			overview.config = overview._add_box('config half_pane');
			overview.config.title = overview.config._extend('top', 'title');
			overview.config.title.$.html('Configuration');
			overview.config.canvas = overview.config._extend('middle', 'canvas');
			overview.config.canvas.data = overview.config.canvas._add_box('config_data');
			//overview.config.scroller = om.bf.make.scroller(overview.config.$, {
			//	target: overview.config.canvas.data.$,
			//	constraint: overview.config.canvas.$,
			//	orient: 'verticle',
			//	speed: diviner.gui_speed,
			//	verticle: true
			//});
			
			// subservice information
			overview.subservices = overview._add_box('subservices half_pane');
			overview.subservices.title = overview.subservices._extend('top', 'title');
			overview.subservices.title.$.html('Sub-services');
			overview.subservices.canvas = overview.subservices._extend('middle', 'canvas');
			overview.subservices.canvas.data = overview.subservices.canvas._add_box('subservice_data');
			//overview.subservices.scroller = om.bf.make.scroller(overview.subservices.$, {
			//	target: overview.subservices.canvas.data.$,
			//	constraint: overview.subservices.canvas.$,
			//	orient: 'verticle',
			//	speed: diviner.gui_speed,
			//	verticle: true
			//});
			// make our data boxes and scrollers use the max space available
			//pos1 = overview.config.canvas.$.position();
			//overview.config.scroller.$.css('top', pos1.top + 'px')
			//	.css('bottom', '2px')
			//	.css('right', '50%')
			//	.width(18);
			//overview.config.canvas.$.css('top', pos1.top + 'px')
			//	.css('left', pos1.left + 'px')
			//	.css('width', '50%')
			//	.css('bottom', '0px');
			//pos1 = overview.subservices.canvas.$.position();
			//overview.subservices.scroller.$.css('top', pos1.top + 'px')
			//	.css('bottom', '2px')
			//	.css('right', '2px')
			//	.width(18);
			//overview.subservices.canvas.$.css('top', pos1.top + 'px')
			//	.css('left', '50%')
			//	.css('width', '50%')
			//	.css('bottom', '0px');

			/* methods */
			overview.init = function () {
				var loading, subservice, menu;
				// sync our heights to the hud height
				//diviner.hud.$.unbind(
				//	'resize',
				//	overview.config.scroller._update_trackbar
				//);
				//diviner.hud.$.unbind(
				//	'resize',
				//	overview.subservices.scroller._update_trackbar
				//);
				//diviner.hud.$.bind(
				//	'resize',
				//	overview.config.scroller._update_trackbar
				//);
				//diviner.hud.$.bind(
				//	'resize',
				//	overview.subservices.scroller._update_trackbar
				//);
				//// load the config data
				loading = om.bf.make.loading(overview.$);
				diviner.client.fetch(
					'service',
					'config',
					'omega.config.get',
					{},
					function (config) {
						overview.config.canvas.data.$.html(om.vis.obj2html(config));
						overview.config.canvas.data.$.trigger('resize');
						loading._remove();
					},
					function (err) {
						om.error(err);
						loading._remove();
					}
				);
				// load the subservice data
				overview.subservices.canvas.data.$.html('');
				for (subservice in diviner.subservices) {
					if (diviner.subservices.hasOwnProperty(subservice)) {
						diviner.SubserviceToggle(overview.subservices.canvas.data.$, subservice, diviner.subservices[subservice]);
					}
				}
				/*
				overview.subservices.canvas.data['authority'] = diviner.SubserviceToggle(overview.subservices.canvas.data.$, 'authority');
				overview.subservices.canvas.data['logger'] = diviner.SubserviceToggle(overview.subservices.canvas.data.$, 'logger');
				*/
				overview.subservices.canvas.data.$.trigger('resize');
			};

			/* init */
			overview.init();
			return overview;
		};

		diviner.LoggerHud = function (args) {
			var logger = diviner.HudBox(
				diviner.hud.menu.canvas.$,
				'Logging',
				args
			);
			logger.$.toggleClass('logger', true);
			logger.log_years = logger._add_box('log_years');
			logger.log_months = logger._add_box('log_months');
			logger.log_data = logger._add_box('log_data');
			/* methods */
			logger.load_log_list = function (log_list) {
				var year;
				logger.log_years.$.html('');
				for (year in log_list) {
					if (log_list.hasOwnProperty(year)) {
						logger.log_years._add_box('log_year', {
							'html': year
						});
					}
				}
				logger.log_list = log_list;
				// select the first year by default
				logger.log_years.$.find('.log_year:last').click();
			};

			logger.select_year = function (select_ev) {
				var year_node, year, file, i;
				year_node = $(select_ev.target);
				year = year_node.text();
				// select ourselves
				year_node.toggleClass('selected', true).siblings('.log_year.selected').toggleClass('selected', false);
				// load the list of log files for this year
				logger.log_months.$.html('');
				for (i = 0; i < logger.log_list[year].length; i++) {
					file = logger.log_list[year][i];
					logger.log_months._add_box('log_month', {
						'html': file
					});
				}
				logger.year = year;
				// select the last month by default
				logger.log_months.$.find('.log_month:last').click();
			};

			logger.select_month = function (select_ev) {
				var month_node, month, file, loading;
				month_node = $(select_ev.target);
				file = month_node.text();
				month = parseInt(file.substr(0, 2), 10);
				// select ourselves
				month_node.toggleClass('selected', true).siblings('.log_month.selected').toggleClass('selected', false);
				logger.month = month;
				// load the logs for the month
				loading = om.bf.make.loading(logger.$);
				diviner.client.exec(
					'omega.subservice.logger.get_log_file_raw',
					{year: logger.year, month: month},
					function (log) {
						/* // for when we want to structure the data more
						var log_date, data, html = '';
						for (log_date in log_lines) {
							if (log_lines.hasOwnProperty(log_date)) {
								data = log_lines[log_date];
								html += '<div class="log_item"><div class="log_header">' + log_date + ', ' + data.api_user + ' - </div><div class="log_data"></div>';
							}
						}
						*/
						logger.log_data.$.html(log.replace(/\n/g, "<br/>"));
						loading._remove();
					},
					function (errmsg) {
						om.error(errmsg, {target: logger.$, modal: true});
						loading._remove();
					}
				);
			};

			logger.init = function () {
				var loading;
				// load the list of log files
				loading = om.bf.make.loading(logger.$);
				diviner.client.fetch(
					'service',
					'log_files',
					'omega.subservice.logger.list_log_files',
					{},
					function (log_list) {
						logger.load_log_list(log_list);
						loading._remove();
					},
					function (err) {
						om.error(err);
						loading._remove();
					}
				);
			};
			/* init */
			logger.log_years.$.delegate('.log_year', 'click dblclick', logger.select_year);
			logger.log_months.$.delegate('.log_month', 'click dblclick', logger.select_month);
			logger.log_list = {};
			logger.init();
			return logger;
		};

		diviner.EditorHud = function (args) {
			var editor = diviner.HudBox(
				diviner.hud.menu.canvas.$,
				'Code Editor',
				args
			);
			editor.$.toggleClass('editor', true);
			editor.title = editor._add_box('title', {dont_show: true});
			editor.code = editor._add_box('code', {
				html: 'To view the source to an API branch or method please click the <img class="eye_symbol" src="/omega/images/diviner/eye.png" alt="eye"/> symbol to the right of the API name above. NOTE: The "Editor" subservice must be enabled to view source code.'
			});
			/* methods */
			editor.init = function () {
			};
			/* init */
			editor.init();
			return editor;
		};

		diviner.TitleBar = function (owner, title) {
			var bar = om.bf.make.box(owner, {
				'class': 'title_bar'
			});
			bar._target = null;
			bar.title = title;
			bar.tip = bar._extend('right', 'tip');
			bar.tip.$.html('hide');
			bar.$.append(title);
			bar.target = function (value) {
				if (value === undefined) {
					return bar._target;
				}
				bar._target = value;
				return bar;
			};
			bar.$.bind('click', function () {
				if (bar._target) {
					if (bar.tip.$.html() === 'hide') {
						bar.tip.$.html('show');
						bar.$.toggleClass('target_hidden', false);
					} else {
						bar.tip.$.html('hide');
						bar.$.toggleClass('target_hidden', true);
					}
					bar._target.slideToggle(diviner.gui_speed, function () {
						bar._target.trigger('resize');
					});
				}
			});
			return bar;
		};

		diviner.ApiRunner = function (branch, method, method_info) {
			var api_runner, color;
			api_runner = om.bf.make.win(
				diviner.$, {
					toolbar: ['title', 'min', 'close'],
					title: '<div class="api_path">' + branch + '.<span class="last_part">' + method + '</span></div>',
					'class': 'api_runner',
					resizable: null,
					dont_show: true,
					on_min: function (click_event) {
						click_event.preventDefault();
						click_event.stopPropagation();
						api_runner.minimize();
					}
				}
			);
			api_runner.branch = branch;
			api_runner.method = method;
			api_runner.method_info = method_info;
			api_runner.api_info_title = diviner.TitleBar(api_runner._canvas.$, 'Description');
			api_runner.api_info = api_runner._canvas._add_box('api_info');
			api_runner.api_info_title.target(api_runner.api_info.$);
			api_runner.api_result_title = diviner.TitleBar(api_runner._canvas.$, 'Result');

			api_runner.api_result = api_runner._canvas._add_box('api_result');
			api_runner.api_result_title.target(api_runner.api_result.$);
			// TODO: options to go auto-refresh, re-run, etc
			api_runner.form = om.bf.make.form(
				api_runner._canvas.$
			);

			api_runner.form._add_submit('Run', function (click_event, button) {
				var params, error;
				try {
					params = api_runner.form._get_input();
				} catch (e) {
					error = om.bf.make.confirm(
						api_runner.$,
						"API Parameter Errors",
						"The following parameters contain invalid JSON:<br/><br/>" + e.message,
						{
							modal: true
						}
					);
					error._move_to(click_event.clientX - 10, click_event.clientY - 10);
					error._constrain_to();
					return;
				}
				api_runner.run(params);
			}, 13);

			api_runner.form._add_cancel('Close', function () {
				api_runner._remove();
			}, 27);

			/* methods */
			api_runner.minimize = function () {
				var pos, new_left, new_top, min_runners, min_height;
				// hide our content
				api_runner._canvas.$.hide();
				api_runner._toolbar._controls._min.hide();
				// figure out where we'll position ourself
				min_runners = diviner.$.children('.api_runner').has('.om_win_canvas:hidden');
				pos = api_runner.$.position();
				min_height = api_runner._toolbar.$.outerHeight() + 1;
				new_left = $(window).width() - api_runner.$.outerWidth();
				new_top = (min_runners.length * min_height) + 4;
				api_runner._sink();
				// and animate stretching out to our final position
				api_runner.$.toggleClass('minimized', true);
				api_runner.$.animate({
					left: new_left + 'px',
					top: new_top + 'px'
				}, diviner.gui_speed, function () {
					// now that we're in place, adjust to anchor to the right
					api_runner.$.css('right', '0px').css('left', 'auto');
				});
				// restore ourselves on the next click
				api_runner.$.one('click win_close.om', function (ev) {
					var runner, hole_top;
					// restore our window state information
					if (ev.type != 'win_close') {
						hole_top = parseInt(api_runner.$.css('top'), 10);
						api_runner.$.toggleClass('minimized', false);
						api_runner.$
							.css('left', pos.left + 'px')
							.css('right', 'auto')
							.css('top', pos.top + 'px');
						api_runner._toolbar._controls._min.show();
						api_runner._canvas.$.slideDown(diviner.gui_speed);
					} else {
						hole_top = parseInt($(ev.target).css('top'), 10);
					}
					// everything below where we clicked needs to slide on up
					// close any gaps we might have left
					diviner.$.children('.api_runner.minimized').each(
						function () {
							var runner, pos;
							runner = $(this);
							pos = runner.offset();
							if (pos.top > hole_top) {
								// slide on down
								runner.animate({
									top: '-=' + min_height
								}, diviner.gui_speed);
							}
						}
					);
				});
			};

			api_runner.run = function (params) {
				var loading, branch, method, parts, om_branch;
				branch = api_runner.branch;
				method = api_runner.method;
				parts = branch.split(/\./);
				if (parts[0] !== 'omega') {
					parts[0] = 'omega.service';
				}
				om_branch = parts.join('.');
				loading = om.bf.make.loading(api_runner._box_middle.$);
				diviner.client.exec(
					om_branch + '.' + method,
					params,
					function (response) {
						// show the response 
						api_runner.api_result.$.css('width', 'auto').css('height', 'auto');
						api_runner.api_result.$.html(om.vis.obj2html(response));
						api_runner.api_result.$.trigger('resize');
						loading._remove();
					},
					function (response) {
						var error;
						error = om.bf.make.confirm(
							api_runner.$,
							'Failure: <span class="error_api">' + branch + '.' + method + '</span>',
							response,
							{
								dont_show: true,
								modal: true,
								on_close: function (click_event) {
									api_runner.form.$.find('input,button').slice(0, 1).focus();
								}
							}
						);
						// move the box to the same place our api_runner is at
						error._center(api_runner.$)._constrain_to();
						error._show();
						error.$.find('button:first').focus();
						loading._remove();
					}
				);
			};

			api_runner.paint = function (colors) { 
				if (colors === undefined) {
					colors = diviner.color_fade.colors;
				}
			};

			api_runner.init = function () {
				var fields, html;
				// make sure we have information about this method if not given
				if (api_runner.method_info === undefined) {
					diviner.client.exec(
						branch + '.' + method,
						'?',
						function (info) {
							// re-run ourself now that we have the info
							api_runner.method_info = info;
							return api_runner.init();
						},
						function (err) {
							om.error(err);
							throw new Error(err);
						}
					);
				}
				// reset the form
				fields = diviner.client.get_fields(api_runner.method_info);
				api_runner.form._set_fields(fields);
				api_runner._raise();
				// load the API description
				html = '';
				if (method_info.doc !== undefined) {
					if (method_info.doc.description !== undefined) {
						html += '<div class="description">' + method_info.doc.description + '</div>';
					}
					if (method_info.doc.returns !== undefined) {
						html += '<div class="return">Returns: ' + method_info.doc.returns + '</div>';
					}
				}
				api_runner.api_info.$.html(html);
				api_runner.$.animate({
					opacity: 'toggle'
				}, diviner.gui_speed, function () {
					// focus our first argument or button
					api_runner.form.$.find('input, button').slice(0, 1).focus();
				});
			};

			api_runner.init();
			return api_runner;
		};

		diviner.browse = function (click_event) {
			// load the browser if not already loaded;
			if (diviner.browser !== undefined && diviner.browser.$ !== undefined) {
				diviner.browser._raise();
				diviner.browser.$.show();
			} else {
				diviner.browser = om.bf.make.browser(
					diviner.$, 
					diviner.client.url,
					{
						title: diviner.service_name
					}
				);
				diviner.browser._move_to(
					click_event.clientX + 15,
					click_event.clientY + 15
				);
			}
			diviner.browser._constrain_to();
		};

		diviner.switch_nav = function (click_event) {
			// get the last data, if available
			var alt = null, i;
			if (diviner._alt !== undefined) {
				alt = {
					columns: diviner._alt.columns,
					path: diviner._alt.path,
					color_fade: diviner._alt.color_fade
				};
			}
			// record what we're swapping away from
			diviner._alt = {
				columns: diviner.nav.columns,
				path: diviner.toolbar.nav_path.path,
				color_fade: diviner.color_fade
			};
			// detach our columns
			for (i = 0; i < diviner.nav.columns.length; i += 1) {
				diviner.nav.columns[i].$.detach();
			}
			// toggle from omega to the service, or vice-versa
			if (diviner.toolbar.nav_path.path[0] === 'omega') {
				// swap back to the service
				// reuse old columns if available
				if (alt !== null) {
					diviner.nav.columns = alt.columns;
					// re-attach the new columns
					for (i = 0; i < diviner.nav.columns.length; i += 1) {
						diviner.nav.$.append(diviner.nav.columns[i].$);
					}
					diviner.color_fade = alt.color_fade;
					diviner.set_path(alt.path.join('.'));
				} else {
					// this code should never run since we start on the service
					throw new Error("A contradiction within the universe has occurred.");
				}
			} else {
				// swap to omega
				if (alt !== null) {
					diviner.nav.columns = alt.columns;
					for (i = 0; i < diviner.nav.columns.length; i += 1) {
						diviner.nav.$.append(diviner.nav.columns[i].$);
					}
					diviner.color_fade = alt.color_fade;
					diviner.set_path(alt.path.join('.'));
				} else {
					// first time initialization on omega
					diviner.color_fade = om.cf.make.fade(
						['#113366', '#2E4400'],
						{steps: 4}
					);
					diviner.set_path('omega');
					diviner.nav.columns = [];
					diviner.toolbar.$.find('.api_branch:first').click();
				}
			}
			// and update the GUI column sizes
			diviner.nav.resize_cols(null, {speed: 0});
		};

		diviner.set_path = function (path) {
			var parts, i, j, branches;
			// clear the old path
			path = path.split(/\./);
			diviner.toolbar.nav_path.path = path;
			diviner.toolbar.nav_path.$.html('');
			// add in the branches
			branches = {};
			for (i = 0; i < path.length; i += 1) {
				(function () {
					var branch, num_cols;
					branch = om.bf.make.box(diviner.toolbar.nav_path.$, {
						'class': 'api_branch'
					});
					// figure out what color to make it based on the navigation
					// brighten the color up a bit
					branch.name = path[i];
					branch.depth = i;
					branch.color = om.cf.blend(
						color = diviner.color_fade.get_color(i),
						'#FFFFFF',
						{ratio: 0.40}
					);
					branch.$.css('color', branch.color);
					branch.$.html(branch.name);
					// make ourself clickable
					branch.$.bind('click', function () {
						// if we're (re)loading the first branch do it by hand
						if (branch.depth == 0) {
							// remove all the current columns and load in the first
							if (diviner.nav.columns.length) {
								diviner.nav.$.find('.column').fadeOut(diviner.gui_speed, function () {
									var col = diviner.nav.columns.pop();
									col._remove();
									if (diviner.nav.columns.length === 0) {
										diviner.load_branch(branch.name);
									}
								});
							} else {
								diviner.load_branch(branch.name);
							}
						} else {
							// click on the api branch in the corresponding column to reload it
							for (j = 0; j < diviner.nav.columns[branch.depth - 1].branches.length; j += 1) {
								if (diviner.nav.columns[branch.depth - 1].branches[j].name == branch.name) {
									diviner.nav.columns[branch.depth - 1].branches[j].select();
								}
							}
						}
					});
					// add in a separator if we're not to the end yet
					if (i < path.length - 1) {
						diviner.toolbar.nav_path.$.append(
							'<span class="separator">.</span>'
						);
					}
				}());
			}
		};

		diviner.logout_user = function (click_event) {
			// log out if we have a token
			if (diviner.client !== undefined) {
				diviner.client.exec_na('omega.authority.logout', [], function () {}, function () {});
				delete diviner.client;
			}
			// clear any API windows currently visible
			diviner.$.find('om_box.api_runner').remove();
			// clear the GUI
			diviner.init();
			// invite the user to log back in
			diviner.login_user();
		};

		diviner.login_user = function (username) {
			// set the Logout text to say 'Login' in case the users decides to close the window
			diviner.login = om.bf.make.collect(
				diviner.$,
				'User Login',
				'Please log in',
				{
					'username': {
						type: 'text',
						args: {
							default_val: username,
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
					'class': 'login_box',
					modal: true,
					submit_caption: 'Log In',
					on_submit: function (submit_event, input) {
						var loading, args;
						loading = om.bf.make.loading(diviner.login.$);
						args = {};
						// if we have a token then use that
						if (input.token === '') {
							args.creds = {token: input.token};
						} else {
							args.creds = {username: input.username, password: input.password};
						}
						//args.params = om.json.auto_complete(input.params, true);
						diviner.client = om.OmegaClient(args);
						diviner.client.exec(
							'#omega.get_session_id',
							null,
							function (session_id) {
								// and reset the GUI
								diviner.shed = om.DataShed(diviner.client); // initialize data shed space for ourself
								if (session_id !== null) {
									diviner.client.set_token(session_id);
								}
								diviner.init();
								loading._remove();
							},
							// otherwise report the error
							function (response) {
								var confirm = om.bf.make.confirm(
									diviner.login.$,
									'Login Failure',
									response,
									{
										on_close: function (click_event) {
											loading._remove();
											// focus the password
											diviner.login.$.find('input:last').focus();
										},
										modal: true
									}
								);
								// and prevent the login from disappearing
								submit_event.preventDefault();
							}
						);
					}
				}
			);
			diviner.login._cancel._remove();
			diviner.login._center_top(0.1, diviner.$);
			diviner.login.$.find('input:first').focus();
			return diviner.login;
		};

		diviner.load_branch = function (branch, on_load) {
			var parts, loading, om_branch;
			// convert the first part to 'omega.service' for services that have an implicit nickname
			parts = branch.split(/\./);
			if (parts[0] !== 'omega') {
				parts[0] = 'omega.service';
			}
			om_branch = parts.join('.');
			// fetch the branch info for the new column
			loading = om.bf.make.loading(diviner.nav.$);
			diviner.client.fetch(
				'branch_info',
				branch,
				om_branch + '.?',
				{'verbose': true},
				function (branch_info) {
					loading._remove();
					diviner.nav.columns.push(
						diviner.Column(branch, branch_info)
					);
					if (on_load !== undefined) {
						on_load(diviner.nav.columns[diviner.nav.columns.length - 1]);
					}
				},
				function (err) {
					var conf;
					conf = om.bf.make.confirm(diviner.$, 'Service Error', err, {modal: true});
					conf.$.toggleClass('api_error', true);
					conf._center_top(0.1)._constrain_to();
					loading._remove();
				}
			);
		};

		diviner.ApiNode = function (owner, name, info) {
			var node;
			node = om.bf.make.box(owner, {
				'class': 'api_node'
			});
			node.name = name;
			node.info = info;
			node.title = node._add_box('title');
			node.body = node._add_box('body');
			node.description = node.body._add_box('description');
			node.controls = om.bf.make.box(node.body.$, {
				'class': 'controls'
			});
			node.controls.view_src = node.controls._add_box('view_src', {
				html: 'Code',
				on_click: function (click_ev) {
					click_ev.preventDefault();
					click_ev.stopPropagation();
					node.view_src();
				}
			});
			/* methods */
			node.view_src = function () {
				var api_path, api, params, loading;
				// determine our API path based on whether we're a branch or method
				if (node.$.is('.api_method')) {
					api_path = node.branch + '.' + node.name;
					params = {'api_method': api_path};
					api = 'view_method_source';
				} else if (node.$.is('.api_branch')) {
					api_path = node.column.name + '.' + node.name;
					params = {'api_branch': api_path};
					api = 'view_branch_source';
				}
				// show the editor window and loading box
				diviner.hud.menu._options.editor._select();
				loading = om.bf.make.loading(diviner.hud.boxes.editor.$);
				// and load the source code up
				diviner.client.exec(
					'omega.subservice.editor.' + api,
					params,
					function (code) {
						var i;
						loading._remove();
						diviner.hud.boxes.editor.title.$.html(api_path).show();
						diviner.hud.boxes.editor.code.$.text(code.join('\n').replace(/\t/g, '    '));
						om.sh.highlight(diviner.hud.boxes.editor.code.$, 'php');
					},
					function (errmsg) {
						om.error(errmsg, {target: diviner.hud.boxes.editor.code.$});
						loading._remove();
					}
				);
			};
			node.init = function () {
				node.title.$.append(node.name.replace(/_/g, ' '));
				// TODO: clean up logic for showing description
				if (typeof(node.info) === 'string') {
					node.description.$.html(node.info);
				} else {
					node.description.$.html(node.info.doc.description);
				}
			};
			/* init */
			node.init();
			return node;
		};

		diviner.ApiMethod = function (column, name, method_info) {
			var method;
			method = diviner.ApiNode(column.node_box.nodes.$, name, method_info)
			method.$.toggleClass('api_method', true);
			method.branch = column.name;
			method.column = column;
			method.api_params = method._add_box('api_params');
			// move our params to be before the description
			method.description.$.before(method.api_params.$.detach());
			// add a control to view logger info
			method.controls.view_logs = method.controls._add_box('view_logs', {
				html: 'Logs',
				on_click: function (click_ev) {
					click_ev.preventDefault();
					click_ev.stopPropagation();
					method.view_logs();
				}
			});
			
			/* methods */
			method.view_logs = function () {
				var api_path, api, params, loading;
				api_path = method.branch + '.' + method.name;
				params = {'api_method': api_path};
				api = 'find';
				// show the logger window and loading box
				diviner.hud.menu._options.logger._select();
				loading = om.bf.make.loading(diviner.hud.boxes.logger.$);
				// and load the source code up
				diviner.client.exec(
					'omega.subservice.logger.' + api,
					params,
					function (code) {
						var i;
						loading._remove();
						diviner.hud.boxes.logger.title.$.html(api_path).show();
						diviner.hud.boxes.logger.code.$.text(code.join('\n').replace(/\t/g, '    '));
						om.sh.highlight(diviner.hud.boxes.logger.code.$, 'php');
					},
					function (errmsg) {
						om.error(errmsg, {target: diviner.hud.boxes.logger.code.$});
						loading._remove();
					}
				);
			};
			method.paint = function (colors) {
				if (colors === undefined) {
					colors = method.column.colors;
				}
				method.controls.$.children().css('background-color', om.cf.blend(
					colors.method_bg,
					'#000000',
					{ratio: 0.2}
				));
				method.$.css('background-color', om.cf.blend(
					colors.method_bg,
					'#000000',
					{ratio: 0.1}
				));
				method.$.css('border-color', om.cf.blend(
					colors.method_bg,
					'#000000',
					{ratio: 0.18}
				));
				method.title.$.css('background-color', colors.method_bg).
					css('border-top', '1px solid ' + colors.method_bg_light).
					css('border-left', '1px solid ' + colors.method_bg_light).
					css('border-bottom', '1px solid ' + colors.method_bg_dark).
					css('border-right', '1px solid ' + colors.method_bg_dark);
			};

			method.load = function (click_event) {
				var api_runner, color;
				// build a form to get the input to run the API
				api_runner = diviner.ApiRunner(
					method.branch,
					method.name,
					method.info
				);
				if (click_event) {
					// position and show just underneath the cursor
					api_runner._move_to(
						click_event.clientX - 20,
						click_event.clientY - 20
					);
				}
				// make sure we stay in the canvas area
				api_runner._constrain_to($(window), {
					auto_scroll: true,
					target: api_runner.api_result.$,
					target_only: true,
					with_resize: true
				});
				// match the title BG to the nav column, but slightly darker
				color = om.cf.blend(
					$(click_event.target).parents('.column').css('background-color'),
					'#000000',
					{ratio: 0.32}
				);
				api_runner._box_top.$.css('background-color', color);
				api_runner._box_top.$.find('img').css('background-color', om.cf.blend(
					color,
					'#000000',
					{ratio: 0.5}
				));
			};

			method.init = function () {
				var i, param;
				// add in our API params
				method.api_params.$.html('');
				for (i = 0; i < method.info.params.length; i += 1) {
					param = method.info.params[i].name;
					method.api_params[param] = method.api_params._add_box('api_param');
					method.api_params[param].$.html(
						param.replace(/_/g, ' ')
					);
					if (method.info.params[i].optional) {
						method.api_params[param].$.toggleClass('optional', true);
					}
				}
				method.paint();
			};

			/* init */
			// load an API runner if our title is clicked
			method.title.$.bind('click', function (click_event) {
				method.load(click_event);
			});
			method.init();
			return method;
		};

		diviner.ApiBranch = function (column, name, branch_info) {
			var branch;
			branch = diviner.ApiNode(column.node_box.nodes.$, name, branch_info);
			branch.$.toggleClass('api_branch', true);
			branch.column = column;

			/* methods */
			branch.paint = function (colors) {
				if (colors === undefined) {
					colors = branch.column.colors;
				}
				if (branch.$.is('.selected')) {
					branch.controls.$.children().css('background-color', om.cf.blend(
						colors.next_bg_dark,
						'#ffffff',
						{ratio: 0.1}
					));
					branch.$.
						css('background-color', colors.next_bg).
						css('border-color', om.cf.blend(
							colors.bg,
							'#ffffff',
							{ratio: 0.10}
						));
				} else {
					branch.$.
						css('background-color', 'inherit').
						css('border-color', colors.bg);
					branch.$.children('.title').css('border-bottom-color', om.cf.blend(
						colors.bg,
						'#FFFFFF',
						{ratio: 0.10}
					));
					branch.controls.$.children().css('background-color', om.cf.blend(
						colors.bg,
						'#ffffff',
						{ratio: 0.1}
					));
				}
			};
			branch.select = function (click_event) {
				var new_branch, column, num_discards, num_left, i, left, width, padding;
				new_branch = branch.column.name + '.' + branch.name;
				// update our title bar
				diviner.set_path(new_branch);
				// select ourself, unselect any others
				branch.$.
					toggleClass('selected', true).
					triggerHandler('paint.gui');
				branch.$.
					siblings('.api_branch.selected').
					removeClass('selected').
					triggerHandler('paint.gui');
				// shrink down the columns we need to prune away
				num_discards = diviner.nav.columns.length - branch.column.depth;
				num_left = num_discards;
				if (num_discards > 0) {
					for (i = branch.column.depth; i < diviner.nav.columns.length; i += 1) {
						column = diviner.nav.columns[i];
						// hide our internal nodes first, to simplify the show/hide
						column.$.children('.om_box').hide();
						// if we're removing more than one column then also slide left
						left = parseInt(column.$.css('left'), 10);
						if (i > branch.column.depth) {
							left -= (column.$.width() + parseInt(column.$.css('padding-left')) * (num_discards - 1));
						}
						column.$.animate(
							{'width': '0px', 'left': left + 'px'},
							diviner.gui_speed * 0.50,
							function () {
								var column;
								// remove ourself
								num_left -= 1;
								// and when all are done resize the leftovers
								if (num_left === 0) {
									while (diviner.nav.columns.length > branch.column.depth) {
										column = diviner.nav.columns.pop();
										column._remove();
									}
									diviner.load_branch(new_branch);
								}
							}
						);
					}
				} else {
					// and add the new branch
					diviner.load_branch(new_branch);
				}
			};

			branch.init = function () {
				branch.paint();
			};

			/* init */
			branch.$.bind('paint.gui', function (paint_ev) {
				branch.paint();
				paint_ev.preventDefault();
				paint_ev.stopPropagation();
			});
			branch.title.$.bind('click', function (click_event) {
				branch.select(click_event);
			});
			branch.init();
			return branch;
		};

		diviner.Column = function (name, branch_info) {
			var column;
			column = om.bf.make.box(diviner.nav.$, {
				'class': 'column',
				dont_show: true
			});
			column.depth = name.split('.').length;
			column.name = name;
			column.colors = {};
			column.branches = [];
			column.methods = [];
			column.info = branch_info;
			column.search = column._add_box('search');
			column.search.box = om.bf.make.input.text(
				column.search.$,
				'search'
			);
			column.search.box.last_query = '';
			column.node_box = column._add_box('node_box');
			column.node_box.nodes = column.node_box._add_box('nodes');
			//column.scroller = om.bf.make.scroller(column.$, {
			//	target: column.node_box.nodes.$,
			//	constraint: column.node_box.$,
			//	speed: diviner.gui_speed,
			//	orient: 'horizontal'
			//});

			/* methods */
			column.filter = function (query) {
				if (query === undefined) {
					return column.search.box._val();
				}
				// filter all the nodes in our column
				column.node_box.nodes.$.find('.api_node').each(function () {
					var api_node, title, desc, index, text;
					api_node = $(this);
					title = api_node.find('.title');
					desc = api_node.find('.description');
					// remove previous highlighting
					title.html(title.text());
					// TODO: option to search description
					if (query === null || query === '') {
						api_node.show();
					} else {
						text = title.text();
						index = text.toLowerCase().indexOf(
							query.toLowerCase()
						);
						// did it match?
						if (index > -1) {
							// highlight the match
							title.html(
								text.substr(0, index) + '<span class="highlight">'
								+ text.substr(index, query.length)
								+ '</span>' + text.substr(index + query.length)
							);
							api_node.show();
						} else {
							api_node.hide();
						}
					}
				});
				// scroll the search box to the top
				//column.scroller._scroll_top();
			};

			column.reload_data = function (on_reload) {
				var loading, parts, om_branch;
				// fetch the branch info for the new column
				loading = om.bf.make.loading(diviner.nav.$);
				loading.$.css('background-color', column.colors.bg);
				parts = column.name.split(/\./);
				if (parts[0] !== 'omega') {
					parts[0] = 'omega.service';
					om_branch = parts.join('.');
				} else {
					om_branch = column.name;
				}
				diviner.client.fetch(
					'branch_info',
					column.name,
					om_branch + '.?',
					{'verbose': true},
					function (branch_info) {
						var name;
						column.info = branch_info;
						// clear any previous data
						column.node_box.nodes.$.html('');
						column.branches = [];
						column.methods = [];
						// and add in what we just got
						for (name in column.info.branches) {
							column.branches.push(
								diviner.ApiBranch(
									column,
									name,
									column.info.branches[name]
								)
							);
						}
						for (name in column.info.methods) {
							column.methods.push(
								diviner.ApiMethod(
									column,
									name,
									column.info.methods[name]
								)
							);
						}
						loading._remove();
						if (on_reload !== undefined) {
							on_reload(branch_info);
						}
					},
					function (err) {
						var conf;
						conf = om.bf.make.confirm(
							diviner.$,
							'Service Error',
							err,
							{
								modal: true
							}
						);
						conf.$.toggleClass('api_error', true);
						conf._center_top(0.1)._constrain_to();
						loading._remove();
					}
				);
			};

			column.paint = function () {
				// color the column, search box, scroller, and branch titles
				column.$.css('background-color', column.colors.bg);
				column.search.box._value.css(
					'background-color',
					column.colors.search_bg
				);
				//column.scroller._track.$.css(
				//	'background-color',
				//	column.colors.bg_darker
				//);
				//column.scroller._track._bar.$.css(
				//	'background-color',
				//	column.colors.bg_dark
				//);
				column.$.css(
					'border-right-color',
					om.cf.blend(
						column.colors.bg,
						'#000000',
						{ratio: 0.10}
					)
				);
			};

			column.init = function () {
				var padding, prev_col;
				// define our colors
				column.colors.bg = diviner.color_fade.get_color(column.depth);
				column.colors.bg_dark = om.cf.blend(
					column.colors.bg,
					'#000000',
					{ratio: 0.28}
				);
				column.colors.bg_darker = om.cf.blend(
					column.colors.bg,
					'#000000',
					{ratio: 0.36}
				);
				column.colors.next_bg = diviner.color_fade.get_color(
					column.depth + 1
				);
				column.colors.search_bg = om.cf.blend(
					'#FFFFFF',
					column.colors.bg,
					{ratio: 0.50}
				);
				column.colors.next_bg_light = om.cf.blend(
					column.colors.next_bg,
					'#FFFFFF',
					{ratio: 0.05}
				);
				column.colors.next_bg_dark = om.cf.blend(
					column.colors.next_bg,
					'#000000',
					{ratio: 0.14}
				);
				column.colors.method_bg = om.cf.blend(
					column.colors.bg,
					'#000000',
					{ratio: 0.09}
				);
				column.colors.method_bg_light = om.cf.blend(
					column.colors.bg,
					'#FFFFFF',
					{ratio: 0.05}
				);
				column.colors.method_bg_dark = om.cf.blend(
					column.colors.bg,
					'#000000',
					{ratio: 0.14}
				);
				column.paint();
				// add branch/method nodes
				column.reload_data();
				// show ourself and focus our search box
				// align ourselves along the right edge
				// first column? just fade in
				padding = parseInt(column.$.css('padding-left'), 10);
				if (diviner.nav.columns.length === 0) {
					column.$.width('100%');
					column.$.css('left', 0 + 'px');
					column.$.fadeIn(diviner.gui_speed, function () {
						column
					});
				} else {
					column.$.width(0);
					prev_col = diviner.nav.columns[diviner.nav.columns.length - 1];
					column.$.css('left', (parseInt(prev_col.$.css('left'), 10) + prev_col.$.width() + padding) + 'px');
					column.$.show();
					column.node_box.$.fadeOut(0);
					diviner.nav.resize_cols(undefined, {on_resize: function () {
						column.node_box.$.fadeIn(diviner.gui_speed);
					}});
				}
				column.search.box._val('').focus();
			};

			/* init */
			// do a search when the contents of the box change
			column.search.box._value.bind('keyup', function (keyup_event) {
				var query;
				query = column.search.box._val();
				if (keyup_event.keyCode === 27) { // ESC
					// if there are contents in the search box then clear 'em
					query = '';
					column.search.box._val(query);
				} else if (keyup_event.keyCode === 13) { // \n
					// auto-click the first match
					column.node_box.nodes.$.find('.api_node:visible:first .title').click();
				} else if (keyup_event.keyCode === 190) { // .
					// trim off the period
					query = query.substr(0, query.length - 1);
					column.search.box._val(query);
					// auto-click the first match
					column.node_box.nodes.$.find('.api_node:visible:first .title').click();
				}
				if (query === column.search.box.last_query) {
					return;
				} else {
					column.search.box.last_query = query;
				}
				column.filter(query);
			});
			column.init();
			return column;
		};

		diviner._hide = function (speed) {
			if (speed === undefined) {
				speed = diviner.gui_speed;
			}
			diviner.$.fadeOut(speed);
		};

		diviner._show = function (speed) {
			if (speed !== undefined) {
				speed = diviner.gui_speed;
			}
			diviner.$.fadeIn(speed);
		};

		diviner.init = function () {
			// clear out any columns from the navigation
			var i, loading;
			loading = om.bf.make.loading(diviner.$);
			for (i in diviner.nav.columns) {
				diviner.nav.columns[i]._remove();
			}
			diviner.nav.columns = [];
			// clear any alt data
			if (diviner._alt !== undefined) {
				delete diviner._alt;
			}
			diviner.color_fade = om.cf.make.fade(
				args.gui_colors,
				{steps: args.gui_color_step}
			);
			// load the subservice data
			diviner.client.fetch(
				'service',
				'subservices',
				'omega.subservice.find',
				{},
				function (subservices) {
					diviner.subservices = subservices;
					for (subservice in subservices) {
						if (subservices.hasOwnProperty(subservice)) {
							if (subservices[subservice].enabled) {
								diviner.$.toggleClass('ss_' + subservice, true);
							}
						}
					}
					loading._remove();
				},
				function (err) {
					om.error(err);
					loading._remove();
				}
			);
			// and information on the service too
			diviner.shed.fetch(
				'service',
				'info',
				'omega.service',
				'?',
				function (service) {
					var collect, fields, param, has_params;
					diviner.set_path(service.name);
					diviner.service = service;
					fields = diviner.client.get_fields(service.info);
					has_params = false;
					for (param in fields) {
						if (fields.hasOwnProperty(param)) {
							has_params = true;
						}
					}
					// does the constructor require parameters? snag 'em, if so
					if (has_params) {
						// TODO: if initialization fails re-prompt
						collect = om.bf.make.collect(
							diviner.$,
							'Initialize ' + service.name,
							service.description,
							fields,
							{
								modal: true,
								on_submit: function (click_event, input) {
									diviner.client.exec(
										service.name,
										input,
										function () {
											diviner.toolbar.nav_path.$.find('.api_branch:first').click();
											collect._remove();
										},
										function (error) {
											om.error(error);
											collect._form._focus_first();
										}
									);
									click_event.preventDefault();
								},
								on_cancel: function () {
									diviner.$.triggerHandler('win_close.om');
									diviner._remove();
								}
							}
						);
						collect._center_top(0.1, diviner.$);
						collect._constrain_to();
					} else {
						diviner.toolbar.nav_path.$.find('.api_branch:first').click();
					}
				}
			);
		};

		// when resized check to see if our columns need to shrink
		diviner.nav.resize_cols = function (resize_event, args) {
			var columns = diviner.nav.$.find('.column'), col_count,
				available_width, new_widths, width, col_count, left, padding;
			if (args === undefined) {
				args = {};
			}
			if (args.speed === undefined) {
				args.speed = diviner.gui_speed;
			}
			// is this in response to a real resize event? if so, don't animate it
			if (resize_event !== undefined && resize_event !== null && resize_event['type'] !== undefined && resize_event.type === 'resize') {
				args.speed = 0;
			}
			// get the available width for the columns
			available_width = diviner._canvas.$.innerWidth();
			// subtract out the sidebar and column padding
			padding = columns.length ? parseInt(columns.css('padding-left'), 10) : 0;
			padding += columns.length ? parseInt(columns.css('border-right-width'), 10) : 0;
			available_width -= (columns.length * padding);
			/* linear distribution */
			left = 0;
			width = parseInt(available_width / columns.length, 10);
			// keep our width between 10 and the max size
			if (width < 10) {
				width = 10;
			}
			if (width > diviner.nav.max_column_size && diviner.nav.max_column_size !== null) {
				width = diviner.nav.max_column_size;
			}
			col_count = columns.length;
			columns.each(function () {
				var col, col_node_box;
				col = $(this);
				col_node_box = col.children('.node_box');
				col.animate({
					width: width + 'px',
					'left': left + 'px'
				}, args.speed, function () {
					// fake a scroll to ensure our scroller is visible if needed
					//col.find('.om_scroller').trigger('scroll.om');
					col_count -= 1;
					if (col_count === 0) {
						if (args.on_resize !== undefined) {
							args.on_resize();
						}
					}
				});
				left += width + padding;
			});
		};

		/* Events/Bindings
		   =============== */
		// make the toolbar buttons do stuff
		diviner.toolbar.$.find('img.switch_nav').bind('click', diviner.switch_nav);
		diviner.toolbar.$.find('img.browser').bind('click', diviner.browse);
		// resize the columns when the window resizes
		$(window).bind('resize', function () {
			diviner.nav.resize_cols(undefined, {speed: 0});
		});
		// allow the divider to adjust the HUD height
		diviner.hud.divide.$.bind('mousedown', function (mousedown) {
			var pos, doc, max_height, min_height, on_mouseup, onmouse_move;
			// if the hud canvas is hidden then don't do anything
			if (diviner.hud.boxes.count == 0) {
				return;
			}
			pos = {
				x: mousedown.clientX,
				y: mousedown.clientY
			};
			doc = $(document);
			min_height = diviner.hud.menu._options_box.$.outerHeight();
			min_height += diviner.hud.divide.$.outerHeight();
			max_height = diviner.$.innerHeight() - diviner._toolbar.$.outerHeight();
			mousedown.preventDefault();
			mousedown.stopPropagation();
			// unbind our events when we finish our drag
			on_mouseup = function (mouseup) {
				mouseup.preventDefault();
				mouseup.stopPropagation();
				doc.unbind('mouseup', on_mouseup)
					.unbind('mousemove', on_mousemove);
			};
			on_mousemove = function (move_event) {
				var diff, height;
				move_event.preventDefault();
				move_event.stopPropagation();
				// calculate how far we moved and our new position
				diff = {
					x: move_event.clientX - pos.x,
					y: move_event.clientY - pos.y
				};
				pos = {
					x: move_event.clientX,
					y: move_event.clientY
				};
				// adjust the hud height
				height = parseInt(diviner.hud.$.css('height'), 10);
				height -= diff.y;
				// don't get smaller than the min allowed height
				if (height > max_height) {
					height = max_height;
				} else if (height < min_height) {
					height = min_height;
				}
				// adjust the bottom of the nav to mach the height
				diviner.hud.$.height(height);
				diviner.nav.$.css('bottom', height + 'px');
				diviner.nav.$.trigger('resize');
				diviner.hud.$.trigger('resize');
			};
			doc.bind('mouseup', on_mouseup)
				.bind('mousemove', on_mousemove);
		});
		om.bf.imbue.free(diviner.nav);
		diviner.nav.$.bind('resize', function (resize_event) {
			// trigger resize events on our scrollers
			//diviner.nav.$.find('.om_scroller').trigger('scroll.om');
		});

		/* Initialization
		   ============== */
		diviner.nav.max_column_size = null;
		diviner.gui_speed = 200; // default to visual movements speed
		if (omega_client !== undefined) {
			diviner.client = omega_client;
			diviner.shed = om.DataShed(diviner.client); // initialize data shed space for ourself
			diviner.init();
		} else {
			// and present a login screen to the user
			diviner.login_user();
		}
		return diviner;
	};
}(om));

/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

/* notes: 
	grep -E '^\s+[a-zA-Z_]+:( {| function \()' BoxFactory.class.js

*/
(function (om) {
	om.BoxFactory = {
		/* Takes a jQuery DOM object and turns it into a box, storing the jQuery reference as ".$".
		Boxes have some basic functions to nest/move
		*/
		box: function (jquery_obj, args) {
			var box, type, part_type, i, arg;
			args = om.get_args({
				html: '',
				imbue: undefined // object from om.bf
				// on_* will be auto
			}, args, true);
			// allow own own boxes to be passed in
			if (typeof(jquery_obj) === 'object' && om.is_jquery(jquery_obj.$)) {
				jquery_obj = jquery_obj.$;
			}
			if (! om.is_jquery(jquery_obj)) {
				throw new Error("Invalid jquery object: '" + jquery_obj + "'; jQuery object expected.");
			}
			if (om.is_jquery(jquery_obj)) {
				jquery_obj
			}
			if (jquery_obj.length === 0) {
				throw new Error("Target '" + jquery_obj.selector + "' has no length; unable to box.");
			}
			// create the box, starting with our jquery reference
			box = {$: jquery_obj};
			box.$.toggleClass('om_box', true);

			/* Remove ourself via jQuery. */
			box._remove = function (args) {
				if ('$' in box) {
					box.$.remove();
					delete(box.$);
				}
			};

			/* Add another box inside. */
			box._add_box = function (name, args) {
				var i, classes, new_box;
				new_box = om.bf.make.box(box.$, args);
				if (name !== undefined && name !== null && name !== '') {
					classes = name.split(/ /);
					for (i = 0; i < classes.length; i += 1) {
						new_box.$.toggleClass(classes[i], true);
					}
				}
				return new_box;
			};

			/* Add an input object inside. */
			box._add_input = function (type, name, args) {
				if (type in om.bf.make.input) {
					return om.bf.make.input[type](box.$, name, args);
				} else {
					throw new Error("Invalid input type: '" + type + "'.");
				}
			};

			/* Change box opacity */
			box._opacity = function (fade_ratio) {
				if (fade_ratio === undefined) {
					return box.$.css('opacity');
				} else {
					if (fade_ratio >= 0 && fade_ratio <= 1) {
						box.$.css('filter', 'alpha(opacity=' + parseInt(fade_ratio * 100, 10) + ')');
						box.$.css('opacity', fade_ratio);
					} else {
						throw new Error("Opacity fade ratio must be between 1.0 and 0.0, not '" + String(fade_ratio) + "'.");
					}
					return box;
				}	
			};

			/* Create another box inside, in a specific layout position.
			Positions: (top, left, right, bottom */
			box._extend = function (direction, name, args) {
				var box_part, children;
				args = om.get_args({
					wrap: undefined,
					dont_show: false
				}, args, true);
				// if we've already extended in the direction then just return that direction
				if ('_box_' + direction in box) {
					return box['_box_' + direction];
				}
				// otherwise, create it
				box_part = box._add_box(name, args);
				box_part._owner = box;
				box_part._direction = direction;
				box_part.$.toggleClass('om_box_' + direction, true);

				// redefine _remove to remove ourself from our parent object
				box_part._remove = function () {
					box_part.$.remove();
					delete box['_' + box_part._direction];
				};

				// and figure out where to orient it based on the position we extended towards
				box_part.$.detach();
				if (args.wrap) {
					box.$.children(args.wrap).detach().appendTo(box_part.$);
				}
				if (direction === 'top') {
					// if we extended to the top then prepend, as top always comes first
					box.$.prepend(box_part.$);
				} else if (direction === 'left') {
					// if we're extending to the left then see if the top exists-- if so, insert after the top
					if (box._box_top !== undefined) {
						box._box_top.$.after(box_part.$);
					} else {
						// otherwise, insert at the very beginning
						box.$.prepend(box_part.$);
					}
				} else if (direction === 'right') {
					// extending to the right means after top and left
					if (box._box_left !== undefined) {
						box._box_left.$.after(box_part.$);
					} else if (box._box_top !== undefined) {
						box._box_top.$.after(box_part.$);
					} else {
						box.$.prepend(box_part.$);
					}
				} else if (direction === 'middle') {
					// the middle goes before any bottoms
					if (box._box_bottom !== undefined) {
						box._box_bottom.$.before(box_part.$);
					} else {
						box.$.append(box_part.$);
					}
				} else if (direction === 'bottom') {
					// bottom positioning is always at the end of the box
					box.$.append(box_part.$);
				} else {
					box_part.$.remove();
					throw new Error('Invalid box direction: "' + direction + '".');
				}
				// create a property based on the direction
				box['_box_' + direction] = box_part;
				// auto show unless asked not to
				if (args.dont_show !== true) {
					box_part.$.show();
				}
				return box_part;
			};

			/* Test, TODO: remove at some point */
			/*
			box._shift = function (from, to, clobber) {
				var from_obj, to_obj;
				if (clobber === undefined) {
					clobber = false;
				}
				if ('_box_' + from in box) {
					from_obj = box['_box_' + from];
				} else {
					throw new Error("Invalid 'from' direction: '" + from + "'.");
				}
				// make sure we have a valid location
				if (! (to in ['top', 'left', 'bottom', 'right'])) {
					throw new Error("Invalid 'from' direction: '" + from + "'.");
				}
				// clobber the new location if needed
				if ('_box_' + to in box && clobber) {
					to_obj = box['_box_' + from];
					to_obj._remove();
				}
				// move the box to its new position
				box['_box_' + to] = box['_box_' + from];
				box['_box_' + to].$.
					toggleClass('om_box_' + from, false).
					toggleClass('om_box_' + to, true);
				delete box['_box_' + from];
				return box;
			};
			*/

			// see if there is any post-processing to do
			if (args.imbue !== undefined && args.imbue !== null) {
				type = typeof args.imbue;
				if (type === 'function') {
					// if we got a function then use it
					args.imbue(box);
				} else if (type === 'string') {
					if (args.imbue in om.bf.imbue) {
						om.bf.imbue[args.imbue](box);
					} else {
						throw new Error('Invalid imbue function name: "' + args.imbue + '".');
					}
				} else if (type === 'object' && jQuery.isArray(args.imbue)) {
					// if we were given an array then run through each function in the array
					for (i = 0; i < args.imbue.length; i += 1) {
						part_type = typeof args.imbue[i];
						if (part_type === 'function') {
							args.imbue[i](box);
						} else if (type === 'string') {
							if (type in om.bf.imbue) {
								om.bf.imbue[args.imbue](box);
							} else {
								throw new Error('Invalid imbue function name: "' + args.imbue + '".');
							}
						} else {
							throw new Error('Unable to perform imbue with the type "' + part_type + '".');
						}
					}
				} else {
					throw new Error('Unable to perform imbue with the type "' + type + '".');
				}
			}
			// set our HTML if given
			if (args.html !== undefined) {
				box.$.html(args.html);
			}
			// add any events we got, e.g. on_click, on_dblclick, etc.
			for (arg in args) {
				if (args.hasOwnProperty(arg) && arg.match(/^on_/)) {
					box.$.bind(arg.substr(3), function (ev) {
						args[arg](ev, box);
					});
				}
			}
			// and finally, return the constructed box
			return box;
		},

		// various methods to show content/input
		imbue: {
			free: function (box) {
				// make free boxes know when they are focused
				box._focus = function (ev) {
					var old_focus;
					// make ourselves the focused box
					old_focus = box.$.siblings('.om_box_focused');
					if (old_focus.length) {
						old_focus.triggerHandler('unselect.om');
					} 
					box.$.toggleClass('om_box_focused', true);
				};
				box._focus_out = function (ev) {
					box.$.toggleClass('om_box_focused', false);
				};
				box.$.bind('select.om', box._focus);
				box.$.bind('unselect.om', box._focus_out);
				box.$.bind('click dblclick', function (click_event) {
					box.$.triggerHandler('select.om');
				});
				// if we're the first free sibling, auto-focus ourself
				if (! box.$.siblings('.om_box_free.om_box_focused').length) {
					box._focus();
				}
				// and make them hover smart
				box.$.bind('mouseenter mouseleave', function (mouse_event) {
					if (mouse_event.type === 'mouseenter') {
						box.$.toggleClass('om_box_under_cursor', true);
						box.$.triggerHandler('cursorin.om');
					} else {
						box.$.toggleClass('om_box_under_cursor', false);
						box.$.triggerHandler('cursorout.om');
					}
				});
		
				/*
				box._title = function (html) {
					// make sure the box is extended to 'top'
					if (html !== undefined) {
						if (box._box_top === undefined) {
							box._extend('top', 'title');
						}
						box._box_top.$.html(html);
						return box;
					} else {
						if (box._box_top !== undefined) {
							return box._box_top.$.html();
						} else {
							throw new Error('Box does not have a header.');
						}
					}
				};

				box._footer = function (html) {
					// make sure the box is extended to 'bottom'
					if (html !== undefined) {
						if (box._box_bottom === undefined) {
							box._extend('bottom', 'footer');
						}
						box._box_bottom.$.html(html);
						return box;
					} else {
						if (box._box_bottom !== undefined) {
							return box._box_bottom.$.html();
						} else {
							throw new Error('Box does not have a footer.');
						}
					}
				};
				*/

				box._resizable = function (anchor, args) {
					var on_start_move, on_loosen;
					args = om.get_args({
						toggle: true, // default to enabling resizing
						tether: 600,
						loosen: false,
						constraint: undefined,
						constraint_target: box.$, // what to measure when detecting constraints
						grow: 'se', // what direction to grow/shrink in
						target: box.$, // what to move when dragging
						on_start_resize: undefined,
						on_end_resize: undefined,
						on_resize: undefined
					}, args);
					// when the anchor is clicked and dragged the box will be moved along with it :)
					if (anchor === undefined || anchor === null) {
						// default to dragging by the bottom if it exists
						if (box._box_bottom !== undefined) {
							anchor = box._box_bottom.$;
						} else {
							anchor = box.$;
						}
					}
					on_start_move = function (start_move_event) {
						var start_width, start_height, box_pos, start,
							delta, last, on_move, doc, on_end_move;
						start_move_event.stopPropagation();
						start_move_event.preventDefault();
						// if we're a maximized object then we can't resize
						if (box.$.is('.om_box_fullscreen')) {
							return;
						}
						// we've started a click-down, so flag our box as moving
						box.$.toggleClass('om_box_resizing', true);
						om.get(args.on_start_resize, start_move_event, box);
						// record where the move started
						start_width = args.target.width();
						start_height = args.target.height();
						box_pos = args.target.position();
						// record where the move started, and how far into the box the cursor is, and what the last pos was
						start = {
							left: start_move_event.clientX,
							top: start_move_event.clientY
						};
						delta = {
							left: start.left - box_pos.left,
							top: start.top - box_pos.top
						};
						last = {
							left: start.left,
							top: start.top
						};
						// define our move events
						on_move = function (move_event) {
							// calculate where we've moved since the start
							var new_pos = {
									left: move_event.clientX - start.left,
									top: move_event.clientY - start.top
								},
								diff = {
									x: move_event.clientX - last.left,
									y: move_event.clientY - last.top
								},
								abs_diff = {
									x: Math.abs(diff.x),
									y: Math.abs(diff.y)
								},
								box_pos = box.$.position();
							// consider the event handled
							move_event.stopPropagation();
							move_event.preventDefault();
							// if we moved by a huge amount then discard the input (e.g. mouse improperly recorded at 0, 0)
							if (abs_diff.x + abs_diff.y > args.tether) {
								return;
							}
							// and resize ourselves accordingly... but only if we've moved at least 2 pixels from the start
							if (abs_diff.x > 2 || abs_diff.y > 2) {
								if (args.grow === 'ne') {
									box.$.css('top', box_pos.top + diff.y);
									args.target.width(start_width + new_pos.left);
									args.target.height(start_height - new_pos.top);
								} else if (args.grow === 'nw') {
									box.$.css('top', box_pos.top + diff.y);
									box.$.css('left', box_pos.left + diff.x);
									args.target.width(start_width - new_pos.left);
									args.target.height(start_height - new_pos.top);
								} else if (args.grow === 'sw') {
									box.$.css('left', box_pos.left + diff.x);
									args.target.width(start_width - new_pos.left);
									args.target.height(start_height + new_pos.top);
								} else if (args.grow === 'se') {
									args.target.width(start_width + new_pos.left);
									args.target.height(start_height + new_pos.top);
								}
								if (args.on_resize !== undefined) {
									args.on_resize(move_event, box);
								}
								// constrain ourselves if needed
								if (args.constraint !== undefined) {
									if (args.constraint_target === undefined) {
										box._constrain_to(args.constraint);
									} else {
										// tricksy! -- if we are too big then force a specific part of the box to shrink
										box._constrain_to(args.constraint, {
											target: args.constraint_target,
											target_only: true
										});
										/*
										// this logic can almost certainly be improved :P
										om.bf.box(
											args.constraint_target, 
											{imbue: 'free'}
										)._constrain_to(args.constraint, {
											target: box.$,
											target_only: true
										});
										*/
									}
								}
								args.target.stop(true, true);
								args.target.trigger('resize');
								last = {
									left: move_event.clientX,
									top: move_event.clientY
								};
							}
						};
						doc = $(document);
						on_end_move = function (end_move_event) {
							if (args.on_end_resize !== undefined) {
								args.on_end_resize(end_move_event, box);
							}
							// remove our hooks when we're done moving
							end_move_event.preventDefault();
							doc.unbind('mousemove.om', on_move);
							doc.unbind('mouseup.om', arguments.callee);
							// and remove the moving class
							box.$.toggleClass('om_box_resizing', false);
						};
						// and bind our move and stop events
						doc.bind('mousemove.om', on_move);
						doc.bind('mouseup.om', on_end_move);
					};
					on_loosen = function (dblclick_event) {
						if (args.loosen) {
							args.target
								.css('width', 'inherit')
								.css('height', 'inherit');
							// keeping the shape we just got, make the width a # again
							args.target
								.css('width', args.target.width() + 'px')
								.css('height', args.target.height() + 'px');
						}
					};
					if (args.toggle === false) {
						anchor.unbind('mousedown.om', on_start_move);
						anchor.unbind('dblclick.om', on_start_move);
						anchor.toggleClass('om_resize_anchor', false);
						args.target.toggleClass('om_resizable', false);
					} else {
						anchor.toggleClass('om_resize_anchor', true);
						args.target.toggleClass('om_resizeable', true);
						anchor.bind('mousedown.om', on_start_move);
						anchor.bind('dblclick.om', on_loosen);
					}
					return box;
				};

				box._draggable = function (anchor, args) {
					var on_start_move;
					args = om.get_args({
						constraint_auto_scroll: false,
						toggle: true,
						tether: 400,
						on_start_move: undefined,
						on_move: undefined,
						on_end_move: undefined,
						constraint: undefined
					}, args);
					// when the anchor is clicked and dragged the box will be moved along with it :)
					if (anchor === undefined || anchor === null) {
						// default to dragging by the top if it exists, otherwise the middle
						if (box._box_top !== undefined) {
							anchor = box._box_top.$;
						} else {
							anchor = box.$;
						}
					}
					if (args.toggle === false) {
						anchor.unbind('mousedown');
						anchor.toggleClass('om_drag_anchor', false);
						box.$.toggleClass('om_box_draggable', false);
					} else {
						on_start_move = function (start_move_event) {
							var box_pos, start, delta, last, on_move, doc,
								on_end_move;
							start_move_event.preventDefault();
							// focus ourselves if needed
							if (! box.$.is('.om_box_focused')) {
								box.$.triggerHandler('select.om');
							}
							// if we're a fullscreen object then we can't move
							if (box.$.is('.om_box_fullscreen')) {
								return;
							}
							// we've started a click-down, so flag our box as moving
							box.$.toggleClass('om_box_moving', true);
							if (args.on_start_move !== undefined) {
								args.on_start_move(start_move_event, box);
							}
							// record the position within the box
							box_pos = box.$.position();
							// record where the move started, and how far into the box the cursor is, and what the last pos was
							start = {
								left: start_move_event.clientX,
								top: start_move_event.clientY
							};
							delta = {
								left: start.left - box_pos.left,
								top: start.top - box_pos.top
							};
							last = {
								left: start.left,
								top: start.top
							};
							// define our move events
							on_move = function (move_event) {
								// calculate where we should move the box to
								var new_pos = {
										left: move_event.clientX - delta.left,
										top: move_event.clientY - delta.top
									},
								// and move ourselves accordingly... but only if we've moved at least 2 pixels from the start
									diff = {
										x: Math.abs(move_event.clientX - last.left),
										y: Math.abs(move_event.clientY - last.top)
									};
								// if we moved by a huge amount then discard the input (e.g. mouse improperly recorded at 0, 0)
								if (diff.x + diff.y > args.tether) {
									return;
								}
								// consider the event handled
								move_event.stopPropagation();
								move_event.preventDefault();
								if (diff.x > 2 || diff.y > 2) {
									// reposition the box accordingly
									box._move_to(new_pos.left, new_pos.top);
									if (args.on_move !== undefined) {
										args.on_move(move_event, box);
									}
									// make sure we stay within our constraints
									if (args.constraint !== undefined) {
										if (args.constraint_target === undefined) {
											box._constrain_to(
												args.constraint, {
													auto_scroll: args.constraint_auto_scroll
												}
											);
										} else {
											box._constrain_to(
												args.constraint, {
													target: args.constraint_target,
													target_only: args.constraint_target_only,
													auto_scroll: args.constraint_auto_scroll
												}
											);
										}
									}
									last = {
										left: move_event.clientX,
										top: move_event.clientY
									};
									// let the world know we are moving
									box.$.triggerHandler('om_box_move.om');
								}
							};
							doc = $(document);
							on_end_move = function (end_move_event) {
								if (args.on_end_move !== undefined) {
									args.on_end_move(end_move_event, box);
								}
								end_move_event.stopPropagation();
								end_move_event.preventDefault();
								// remove our hooks when we're done moving
								doc.unbind('mousemove', on_move);
								doc.unbind('mouseup', arguments.callee);
								// and remove the moving class
								box.$.toggleClass('om_box_moving', false);
								// let the world know we moved
								box.$.triggerHandler('om_box_moved.om');
							};
							// and bind our move and stop events
							doc.bind('mousemove', on_move);
							doc.bind('mouseup', on_end_move);
						};
						// get the party started
						// add classes to our objects to identify their purpose
						anchor.toggleClass('om_drag_anchor', true);
						box.$.toggleClass('om_box_draggable', true);
						// and bind our drag movement
						anchor.bind('mousedown', on_start_move);
					}
					// make the box able to respond to height
					box.$.bind('mousedown', function (click_event) {
						box._raise();
					});
					return box;
				};

				box._toggle_fullscreen = function (args) {
					var last;
					args = om.get_args({
						target: box.$
					}, args);
					// record the last width/height before we maximize
					box.$.toggleClass('om_box_fullscreen');
					if (args.target !== box.$) {
						args.target.toggleClass('om_box_maximized');
					}
					return box;
				};

				box._resize_to = function (target, args) {
					var target_pos, max, delta, box_width, box_height,
						box_target_delta, resized = false, no_def_view,
						has_view;
					args = om.get_args({
						auto_scroll: false,
						target: undefined,
						measure: 'position',
						margin: 0
					}, args);
					has_view = target[0].ownerDocument !== undefined;
					// get our target's location and dimensions
					if (args.measure === 'offset') {
						box.$.css('position', 'fixed');
						if (has_view) {
							target_pos = target.offset();
						} else {
							target_pos = {left: 0, top: 0};
						}
					} else if (args.measure === 'position') {
						box.$.css('position', 'absolute');
						if (has_view) {
							target_pos = target.position();
						} else {
							target_pos = {left: 0, top: 0};
						}
					} else {
						throw new Error("Invalid measurement function: '" + args.measure + "'.");
					}
					// move in position and change our widths
					box.$.css('left', (target_pos.left + args.margin) + 'px');
					box.$.css('top', (target_pos.top + args.margin) + 'px');
					if (has_view) {
						target_pos.width = target.outerWidth(true);
						target_pos.height = target.outerHeight(true);
					} else {
						target_pos.width = target.width();
						target_pos.height = target.height();
					}
					box_width = box.$.outerWidth(true);
					box_height = box.$.outerHeight(true);
					if (args.target !== undefined) {
						box_target_delta = {
							width: args.target.width() - (box_width - target_pos.width),
							height: args.target.height() - (box_height - target_pos.height)
						};
					}
					delta = {
						width: target_pos.width - (box_width - box.$.width()),
						height: target_pos.height - (box_height - box.$.height())
					};
					// are we fatter than the constraint width? if so, shrink the difference
					if (delta.width !== 0) {
						box.$.width(delta.width - (args.margin * 2));
						// shrink the target too, if needed
						if (args.target !== undefined) {
							args.target.width(box_target_delta.width);
						}
						resized = true;
						// recalculate our width after moving
						box_width = box.$.outerWidth(true);
					}
					if (delta.height !== 0) {
						box.$.height(delta.height - (args.margin * 2));
						resized = true;
						// shrink the target too, if needed
						if (args.target !== undefined) {
							args.target.height(box_target_delta.height);
						}
						// recalculate our height after moving
						box_height = box.$.outerHeight(true);
					}
					if (resized) {
						box.$.trigger('resize');
						if (args.target !== undefined) {
							args.target.trigger('resize');
						}
					}
					return box;
				};

				box._get_bounds = function () {
					var bounds = box.$.position();
					bounds.width = box.$.width();
					bounds.height = box.$.height();
					return bounds;
				};

				box._get_bounds_abs = function () {
					var bounds, tmp_bounds;
					bounds = box._get_bounds();
					// hijack!
					tmp_bounds = box.$.offset();
					bounds.top = tmp_bounds.top;
					bounds.left = tmp_bounds.left;
					return bounds;
				};

				box._growable = function (anchor, args) {
					var orig_bounds;
					args = om.get_args({
						event: 'dblclick',
						target: box.$
					}, args);
					if (anchor === undefined) {
						if (box._box_top === undefined) {
							anchor = box.$;
						} else {
							anchor = box._box_top.$;
						}
					}
					anchor.bind(args.event, function (grow_event) {
						var old_bounds, new_bounds, resized;
						old_bounds = box._get_bounds();
						box._resize_to(
							box.$.parent(),
							{target: args.target}
						);
						new_bounds = box._get_bounds(); 
						resized = (old_bounds.width !== new_bounds.width) || (old_bounds.height !== new_bounds.height);
						if (resized) {
							// not grown yet? remember where we started
							if (! box.$.is('.om_grown')) {
								orig_bounds = old_bounds;
								box.$.toggleClass('om_grown', true);
							}
						} else {
							// already full size? return back to where we started
							args.target.width(orig_bounds.width);
							args.target.height(orig_bounds.height);
							om.bf.box(args.target, {imbue: 'free'})._move_to(
								orig_bounds.left,
								orig_bounds.top
							);
							args.target.$.toggleClass('om_grown', false);
						}
					});
				};
				
				box._constrain_to = function (constraint, args) {
					// TODO: add arg to resize to fit, otherwise act as 'viewport')
					var box_pos, box_off, con, delta, box_width, box_height, resized;
					resized = false;
					args = om.get_args({
						auto_scroll: false,
						with_resize: false,
						target: undefined,
						target_only: false
					}, args);
					// default to contraining to the body
					if (constraint === undefined) {
						constraint = $(window);
					}
					// re-constrain on resize
					if (args.with_resize === true) {
						// bind once, as it'll keep re-adding itself each time around
						box.$.one('resize', function (resize_event) {
							box._constrain_to(constraint, args);
						});
						/*
						if (args.target !== undefined) {
							args.target.one('resize', function (resize_event) {
								box._constrain_to(constraint, args);
							});
						}
						*/
					}
					// just remember these calcs so I don't have to repeat myself
					// and perform them differently on the 'window' object, since it doesn't have CSS style
					if (constraint[0].ownerDocument !== undefined) {
						// gotta go by offset at the window level
						con = constraint.offset();
						con.width = constraint.innerWidth();
						con.height = constraint.innerHeight();
					} else {
						con = {left: 0, top: 0};
						con.width = constraint.width();
						con.height = constraint.height();
					}
					box_pos = box.$.position();
					box_off = box.$.offset();
					// add what it'll take to get it inside the top left corners
					delta = {
						left: Math.max(con.left - box_off.left, 0),
						top: Math.max(con.top - box_off.top, 0)
					};
					// too far top or left? scoot over
					if (delta.top > 0) {
						box_pos.top += delta.top;
						box.$.css('top', box_pos.top + 'px');
					}
					if (delta.left > 0) {
						box_pos.left += delta.left;
						box.$.css('left', box_pos.left + 'px');
					}
					// now check our right edge to be sure its not hanging over
					box_width = box.$.outerWidth(true);
					// are we fatter than the constraint width? if so, shrink the difference
					if (box_width > con.width) {
						if (args.target_only !== true) {
							box.$.width(con.width - (box_width - box.$.width()));
						}
						// shrink the target too, if needed
						if (args.target !== undefined) {
							args.target.width(args.target.width() - (box_width - con.width));
						}
						resized = true;
						// recalculate our width after moving
						box_width = box.$.outerWidth(true);
					}
					// and see if we're hanging over the right edge
					delta.right = Math.max(box_off.left + box_width - (con.left + con.width), 0);
					if (delta.right > 0) {
						box_pos.left -= delta.right;
						box.$.css('left', box_pos.left + 'px');
					}
					box_height = box.$.outerHeight(true);
					// are we taller than the constraint height? if so, shrink the difference
					if (box_height > con.height) {
						if (args.target_only !== true) {
							box.$.height(con.height - (box_height - box.$.height()));
						}
						// shrink the difference too
						if (args.target !== undefined) {
							args.target.height(args.target.height() - (box_height - con.height));
							
						}
						resized = true;
						// recalculate our height after moving
						box_height = box.$.outerHeight(true);
					}
					/*
					// causing more problems than it seems to be worth right now...
					if (args.auto_scroll) {
						// set overflow to scroll, since we we're presumably too big
						if (args.target === undefined) {
							box.$.css('overflow-y', 'scroll');
						} else {
							args.target.css('overflow-y', 'scroll');
						}
					} else {
						// remove auto-scrolling
						if (args.target === undefined) {
							box.$.css('overflow-y', 'inherit');
						} else {
							args.target.css('overflow-y', 'inherit');
						}
					}
					*/
					// and finally see if we're hanging over the bottom edge
					delta.bottom = Math.max(box_off.top + box_height - (con.top + con.height), 0);
					if (delta.bottom > 0) {
						box_pos.top -= delta.bottom;
						box.$.css('top', box_pos.top + 'px');
					}
					if (resized) {
						box.$.trigger('resize');
						if (args.target !== undefined) {
							args.target.trigger('resize');
						}
					}
					return box;
				};

				/*
				box._dodge_cursor = function (x, y, margin) {
					return; // TODO
					var mouse_size = 15,
						box_pos,
						cur_pos,
						delta;
					if (margin === undefined) {
						margin = 5;
					}
					box_pos = box.$.offset();
					box_pos.width = box.$.width();
					box_pos.height = box.$.height();
					cur_pos = {left: x - margin / 2, top: y - margin / 2};
					cur_pos.width = mouse_size + (margin * 2);
					cur_pos.height = mouse_size + (margin * 2);
					// is the cursor within the tooltip range?
					delta = {
						top: Math.max(cur_pos.top - box_pos.top, 0),
						left: Math.max(cur_pos.left - box_pos.left, 0),
						right: Math.max((cur_pos.left) - (box_pos.left + box_pos.width), 0),
						bottom: Math.max((cur_pos.top) - (box_pos.top + box_pos.height), 0)
					};
					// if our right edge and bottom are both overlapping, then we've gotta move the thing up (and might as well go a little left too, while we're at it)
					if (delta.bottom > 0 && delta.right > 0) {
						box_pos = box.$.position(); // change to using the position for the movement calc
						// assume we'll have enough room up top for the tooltip... if not, oh well, we tried
						box.$.css('top', box_pos.top - (delta.bottom + cur_pos.height) + 'px');
					}
				};
				*/
					
				box._move_to = function (x, y) {
					box.$.css('left', x + 'px').css('top', y + 'px');
					return box;
				};

				box._move_by = function (x, y) {
					var position = box.$.position();
					box.$.css('left', position.left + x + 'px');
					box.$.css('top', position.top + y + 'px');
					return box;
				};

				box._center = function (target) {
					var target_dims, bounds, cur_center, new_center;
					if (target === undefined) {
						target = $(window);
						target_dims = {
							width: target.width(),
							height: target.height()
						};
					} else {
						target_dims = {
							width: target.innerWidth(),
							height: target.innerHeight()
						};
					}
					bounds = box._get_bounds();
					cur_center = {
						left: (bounds.left * 2 + bounds.width) / 2,
						top: (bounds.top * 2 + bounds.height) / 2
					};
					new_center = {
						left: target_dims.width / 2,
						top: target_dims.height / 2
					};
					// and move everything around the new center
					return box._move_by(new_center.left - cur_center.left, new_center.top - cur_center.top);
				};

				box._center_top = function (ratio, target) {
					var target_pos, bounds;
					if (ratio < 0 || ratio > 1) {
						throw new Error('Invalid ratio: "' + ratio + '".');
					}
					if (target === undefined) {
						target = $(window);
						target_pos = {left: 0, top: 0};
					} else {
						target_pos = target.position();
					}
					bounds = box._get_bounds();
					// and move everything around the top center, dampened by the ratio
					return box._move_to(
						((target.width() / 2)) - (bounds.width / 2),
						((target.height() * ratio))
					);
				};

				/*
				box._normalize_heights = function () {
					// TODO: automatically recenter box heights lest they break z-index bounds?
				};
				*/

				box._get_top_box = function (args) {
					var top_box,
						boxes;
					args = om.get_args({
						filter: undefined
					}, args);
					if (args.filter) {
						boxes = box.$.
							parent().
							find(args.filter).
							filter('.om_box_free:visible');
					} else {
						boxes = box.$.
							parent().
							find('.om_box_free:visible');
					}
					boxes.each(function () {
						var box = $(this),
							z = box.css('z-index'),
							best_z;
						// ignore any 'auto' peers, should they exist
						if (z !== 'auto') {
							z = parseInt(z, 10);
							if (top_box === undefined) {
								top_box = box;
							} else {
								best_z = top_box.css('z-index');
								if (best_z !== 'auto' && z > parseInt(best_z, 10))  {
									top_box = box;
								}
							}
						}
					});
					// TODO if the top sibling's z-index is huge then normalize our heights
					return top_box;
				};

				box._get_top_sibling = function (args) {
					var top_sibling,
						siblings;
					args = om.get_args({
						filter: undefined
					}, args);
					// not in the DOM? return now, as we have no siblings
					if (box.$ === undefined) {	
						return;
					}
					if (args.filter) {
						siblings = box.$.siblings(args.filter).filter('.om_box_free:visible');
					} else {
						siblings = box.$.siblings('.om_box_free:visible');
					}
					siblings.each(function () {
						var sibling = $(this),
							z = sibling.css('z-index'),
							best_z;
						// ignore any 'auto' peers, should they exist
						if (z !== 'auto') {
							z = parseInt(z, 10);
							if (top_sibling === undefined) {
								top_sibling = sibling;
							} else {
								best_z = top_sibling.css('z-index');
								if (best_z !== 'auto' && z > parseInt(best_z, 10))  {
									top_sibling = sibling;
								}
							}
						}
					});
					// TODO if the top sibling's z-index is huge then normalize our heights
					return top_sibling;
				};

				box._get_bottom_sibling = function (args) {
					var bottom_sibling;
					args = om.get_args({
						filter: undefined
					}, args);
					if (args.filter) {
						siblings = box.$.siblings(args.filter).filter('.om_box_free:visible');
					} else {
						siblings = box.$.siblings('.om_box_free:visible');
					}
					siblings.each(function () {
						var sibling = $(this),
							z = sibling.css('z-index'),
							best_z;
						// ignore any 'auto' peers, should they exist
						if (z !== 'auto') {
							z = parseInt(z, 10);
							if (bottom_sibling === undefined) {
								bottom_sibling = sibling;
							} else {
								best_z = bottom_sibling.css('z-index');
								if (best_z !== 'auto' && z > parseInt(best_z, 10))  {
									bottom_sibling = sibling;
								}
							}
						}
					});
					return bottom_sibling;
				};

				box._sink = function (args) {
					var bottom_sibling, top_sibling;
					args = om.get_args({
						no_refocus: false
					}, args);
					bottom_sibling = box._get_bottom_sibling();
					if (bottom_sibling !== undefined) {
						box.$.css('z-index', parseInt(bottom_sibling.css('z-index'), 10) - 1);
						if (! args.no_refocus) {
							// make sure we're not focused anymore
							top_sibling = box._get_top_sibling();
							if (top_sibling) {
								// the highest sibbling takes the focus
								top_sibling.triggerHandler('unselect.om');
							}
						}
					}
					return box;
				};

				box._raise = function (args) {
					var top_box;
					args = om.get_args({
						deep: false,
						no_focus: false
					}, args);
					if (args.deep) {
						top_box = box._get_top_sibling();
					} else {
						top_box = box._get_top_box();
					}
					if (top_box !== undefined) {
						box.$.css('z-index', parseInt(top_box.css('z-index'), 10) + 1);
					}
					if (! args.no_focus) {
						box.$.triggerHandler('select.om');
					}
					return box;
				};
				
				// TODO: and maybe move all modal logic in here?
				box.$.toggleClass('om_box_free', true);
			}
		},

		make: function () {
			// TODO: generic function for making nested objects
		}
	};

	jQuery.extend(
		om.BoxFactory.make,
		{
			/* Create a box from scratch. */
			box: function (owner, args) {
				var html, target, box, jq, arg, box_args;
				if (owner === undefined || owner === null) {
					owner = $('body');
				}
				args = om.get_args({
					'classes': [],
					'class': undefined,
					type: 'div',
					imbue: undefined,
					html: undefined,
					insert: 'append'
				}, args, true);
				if (typeof(args.classes) === 'string') {
					args.classes = args.classes.split(' ');
				}
				if (! (args['class'] === undefined || args['class'] === null)) { // IE sucks
					args.classes.push(args['class']);
				}
				html = om.assemble(args.type, {
					'class': args.classes,
					style: "display: none"
				});
				// determine if the owner is a box or a jquery
				if (owner.$ !== undefined && owner.$.jquery !== undefined && owner.$.length !== undefined && owner.$.length > 0) {
					target = owner.$;
				} else if (om.is_jquery(owner)) {
					target = owner;
				} else {
					throw new Error("Invalid box or jquery object: '" + owner + "'.");
				}
				if (args.insert === 'append') {
					jq = $(html).appendTo(target);
				} else if (args.insert === 'prepend') {
					jq = $(html).prependTo(target);
				} else if (args.insert === 'before') {
					jq = $(html).insertBefore(target);
				} else if (args.insert === 'after') {
					jq = $(html).insertAfter(target);
				}
				// pass any remaining 'on_*' args on through
				box_args = {imbue: args.imbue, html: args.html};
				for (arg in args) {
					if (args.hasOwnProperty(arg) && arg.substr(0, 3) === 'on_') {
						box_args[arg] = args[arg];
					}
				}
				box = om.bf.box(jq, box_args);
				box._args = args;
				if (args.dont_show !== true) {
					box.$.show();
				}
				return box;
			},

			/* Generic window object. */
			win: function (owner, args) {
				var win, i;
				args = om.get_args({
					'class': undefined,
					classes: [],
					draggable: true,
					dont_show: false,
					icon: undefined,
					icon_orient: 'left',
					insert: undefined,
					on_min: undefined,
					on_close: undefined,
					on_fullscreen: undefined,
					on_min: undefined,
					on_start_move: undefined,
					on_move: undefined,
					on_end_move: undefined,
					on_start_resize: undefined,
					on_resize: undefined,
					on_end_resize: undefined,
					resizable: undefined,
					resize_handle: undefined,
					title: '',
					toolbar: ['title', 'grow', 'min', 'max', 'close']
				}, args);
				if (owner === undefined) {
					owner = $('body');
				}
				args.classes.push('om_win');
				if (args['class'] !== undefined) {
					args.classes.push(args['class']);
				}
				win = om.bf.make.box(owner, {
					imbue: 'free',
					'classes': args.classes,
					dont_show: true,
					insert: args.insert
				});
				win._canvas = win._extend('middle', 'om_win_canvas');
				win._args = args;
				// init
				win._add_resize_handle = function () {
					win._footer = win._extend('bottom', 'om_win_footer');
					win._footer._controls = win._footer._add_box('om_win_controls');
					win._footer._controls.$.html('<img class="om_resize_handle" src="/omega/images/resize.png" alt="+" /></div>');
					return win._footer._controls.$.find('img.om_resize_handle');
				};
				win._init = function () {
					var box_toggle, i;
					if (args.toolbar !== null) {
						win._toolbar = win._extend('top', 'om_win_toolbar');
						// toolbar icon
						if (args.icon !== undefined) {
							if (args.icon_orient === 'inline') {
								win._toolbar._icon = win._toolbar._add_box('om_icon');
								win._toolbar._icon.$.css('display', 'inline');
							} else {
								win._toolbar._icon = win._toolbar._extend('left', 'om_icon');
							}
							win._toolbar._icon.$.html('<img src="' + args.icon + '" alt="icon" />');
						}
						for (i = 0; i < args.toolbar.length; i += 1) {
							// toolbar title
							if (args.toolbar[i] === 'title') {
								win._toolbar._title = win._toolbar._extend('middle', 'om_win_title');
								win._toolbar._title.$.html(args.title);
							} else if (args.toolbar[i] === 'min') {
								// min on click
								win._toolbar._controls = win._toolbar._extend('right', 'om_win_controls');
								win._toolbar._controls.$.append('<img class="om_win_minimize" src="/omega/images/diviner/minimize-icon.png" alt="hide" />');
								win._toolbar._controls._min = win._toolbar._controls.$.find('img.om_win_minimize');
								win.$.bind(
									'win_minimize.om',
									function (click_event) {
										if (typeof win._args.on_min === 'function') {
											win._args.on_min(click_event);
										}
										if (! click_event.isDefaultPrevented()) {
											win.$.hide();
										}
										click_event.stopPropagation();
										click_event.preventDefault();
									}
								);
								win._toolbar._controls._min.bind(
									'click dblclick',
									function (click_event) {
										win.$.trigger('win_minimize.om');
										click_event.stopPropagation();
										click_event.preventDefault();
									}
								);
							} else if (args.toolbar[i] === 'max') {
								// max on click
								win._toolbar._controls = win._toolbar._extend('right', 'om_win_controls');
								win._toolbar._controls.$.append('<img class="om_win_maximize" src="/omega/images/diviner/maximize-icon.png" alt="max" />');
								box_toggle = win._toggle_fullscreen;
								// hijack the fullscreen button to implement an on_fullscreen event
								win._toolbar._controls._max = win._toolbar._controls.$.find('img.om_win_maximize');
								win.$.bind(
									'win_maximize.om',
									function (click_event) {
										if (typeof win._args.on_fullscreen === 'function') {
											win._args.on_fullscreen(click_event);
										}
										if (! click_event.isDefaultPrevented()) {
											box_toggle({target: win._canvas.$});
										}
										click_event.stopPropagation();
										click_event.preventDefault();
									}
								);
								win._toolbar._controls._max.bind(
									'click dblclick',
									function (click_event) {
										win.$.trigger('win_maximize.om');
										click_event.stopPropagation();
										click_event.preventDefault();
									}
								);
							} else if (args.toolbar[i] === 'close') {
								// close on click
								win._toolbar._controls = win._toolbar._extend('right', 'om_win_controls');
								win._toolbar._controls.$.append('<img class="om_win_close" src="/omega/images/diviner/close-icon.png" alt="close" />');
								win._toolbar._controls._close = win._toolbar._controls.$.find('img.om_win_close');
								win.$.bind(
									'win_close.om',
									function (click_event) {
										if (typeof win._args.on_close === 'function') {
											win._args.on_close(click_event);
										}
										if (! click_event.isDefaultPrevented()) {
											win._remove();
										}
										click_event.stopPropagation();
										click_event.preventDefault();
									}
								);
								win._toolbar._controls._close.bind(
									'click dblclick',
									function (click_event) {
										win.$.trigger('win_close.om');
										click_event.stopPropagation();
										click_event.preventDefault();
									}
								);
							}
						}
						if (args.resizable === undefined) {
							args.resizeable = {
								target: win._canvas.$,
								loosen: true,
								constraint_target: win._canvas.$,
								on_start_resize: args.on_start_resize,
								on_resize: args.on_resize,
								on_end_resize: args.on_end_resize
							};
						}
						if (args.resizable !== null) {
							if (args.resize_handle === undefined) {
								args.resize_handle = win._add_resize_handle();
							}
							win._resizable(args.resize_handle, args.resizable);
						}
						if (args.draggable === undefined) {
							args.draggable = {
								constraint: $(window),
								constraint_target: win._canvas.$,
								on_start_move: args.on_start_move,
								on_move: args.on_move,
								on_end_move: args.on_end_move
							};
						}
						if (args.draggable !== null) {
							win._draggable(win._toolbar.$, args.draggable);
						}
					}
					win._center_top(0.2, owner.$);
					if (! args.dont_show) {
						win.$.show();
					}
				};

				win._init();
				return win;
			},

			/* Generic menu object. Contains list of options w/ events. */
			menu: function (owner, options, args) {
				var menu;
				args = om.get_args({
					dont_show: false,
					equal_option_widths: false,
					multi_select: false,
					name: '', // name of menu, also set as class
					options_inline: false, // defaults to true if options_orient is 'top' or 'bottom' 
					options_orient: undefined, // whether or not to orient menu options towards a particular box position
					peer_group: undefined // a jQuery ref to a DOM object to find menu option peers in (e.g. for nested single-select menus)
				}, args, true);
				/* options format:
				option = {
					name: {option_args},
					...
				} */
				// create the menu
				menu = om.bf.make.box(owner, args);
				menu.$.toggleClass('om_menu', true);
				menu._args = args;
				if (args.name) {
					menu.$.toggleClass(args.name, true);
				}
				if (args.options_orient) {
					menu._options_box = menu._add_box('om_menu_options');
				} else {
					menu._options_box = menu._extend(
						args.options_orient,
						'om_menu_options'
					);
				}
				menu._options = {};

				// add some functions
				menu._select_first = function () {
					var option_name;
					for (option_name in menu._options) {
						if (menu._options.hasOwnProperty(option_name)) {
							if (menu._options[option_name].$.is(':visible')) {
								menu._options[option_name]._select();
							}
							return menu._options[option_name];
						}
					}
				};
				menu._click_first = menu._select_first;

				menu._unselect_all = function () {
					var name;
					for (name in menu._options) {
						if (menu._options.hasOwnProperty(name)) {
							if (menu._options[name].$.is('.om_selected')) {
								menu._options[name].$.trigger('unselect.om');
							}
						}
					}
				};

				menu._clear_options = function () {
					var option_name;
					for (option_name in menu._options) {
						if (menu._options.hasOwnProperty(option_name)) {
							menu._options[option_name]._remove();
							delete menu._options[option_name];
						}
					}
					return menu;
				};

				menu._remove_option = function (name) {
					if (name in menu._options) {
						menu._options[name]._remove();
						delete menu._options[name];
					} else {
						throw new Error("No menu option with the name '" + name + '" exists.');
					}
				};

				menu._rename_option = function (name, new_name, new_caption) {
					if (name in menu._options) {
						if (new_name in menu._options) {
							throw new Error('A menu option with the name "' + new_name + '" already exists.');
						}
						menu._options[new_name] = menu._options[name];
						delete menu._options[name];
						if (new_caption !== undefined) {
							menu._options[new_name]._args.caption = new_caption;
							menu._options[new_name]._caption.$.html(new_caption);
						}
					} else {
						throw new Error("No menu option with the name '" + name + '" exists.');
					}
				};

				menu._set_options = function (options) {
					menu._clear_options();
					menu._add_options(options);
					return menu;
				};

				menu._add_options = function (options) {
					var name;
					for (name in options) {
						if (options.hasOwnProperty(name)) {
							menu._add_option(name, options[name]);
						}
					}
					return menu;
				};

				menu._add_option = function (name, args) {
					var option, img_html;
					args = om.get_args({
						caption: name,
						'class': undefined,
						classes: [],
						icon: undefined,
						icon_orient: 'left', // left, top, bottom, right, inline
						on_select: undefined, // before the select occurs, can cancel selection
						on_selected: undefined, // after the selection occurs
						on_unselect: undefined
					}, args);
					if (name === undefined) {
						throw new Error("Unable to add an option without a name.");
					}
					args.classes.push('om_menu_option');
					if (args['class']) {
						args.classes.push(args['class']);
					}
					option = om.bf.make.box(
						menu._options_box.$,
						{classes: args.classes}
					);
					// make sure the menu doesn't have an option with this name already
					if (name in menu._options) {
						throw new Error("The option '" + name + "' already exists in menu.");
					}
					option._args = args;
					option._name = name;
					option._menu = menu;
					option._cache = undefined;
					// set the caption
					option._caption = option._extend('middle', 'om_menu_option_caption');
					option._caption.$.html(args.caption);
					// add the icon, if available
					if (args.icon !== undefined) {
						img_html = '<img class="om_menu_option_icon" src="' + args.icon + '" alt="icon"/>';
						if (args.icon_orient === undefined) {
							args.icon_orient = 'left';
						}
						if (menu._args.options_inline) {
							if (args.icon_orient === 'left' || args.icon_orient === 'inline') {
								option._caption.$.prepend(img_html);
							} else if (args.icon_orient === 'top') {
								option._extend('top');
								option._box_top.$.html(img_html);
							} else if (args.icon_orient === 'right') {
								option._caption.$.append(img_html);
								
							} else if (args.icon_orient === 'bottom') {
								option._extend('bottom');
								option._box_bottom.$.html(img_html);
							} else {
								throw new Error("Invalid icon orientation for inline menu options: " + args.icon_orient + ".");
							}
						} else {
							if (args.icon_orient === 'inline') {
								option._caption.$.prepend(img_html);
							} else {
								option._icon = option._extend(args.icon_orient);
								option._icon.$.html(img_html);
							}
						}
					}
					option.$.bind('unselect.om', function (unselect_event) {
						// trigger the unselect event, if present
						if (option._args.on_unselect !== undefined) {
							option._args.on_unselect(unselect_event, option);
						}
						if (! unselect_event.isDefaultPrevented()) {
							option.$.toggleClass('om_selected', false);
						}
						unselect_event.preventDefault();
						unselect_event.stopPropagation();
					});
					option.$.bind('select.om', function (select_event) {
						var node = $(this), selected, last_selected, to_unselect;
						// determine selection behavior
						if (menu._args.multi_select) {
							selected = ! node.is('.om_selected');
							to_unselect = null;
						} else {
							// take note of our previously selected node(s)
							last_selected = node.siblings('.om_selected');
							// check peer group for menus that share the same scope
							if (menu._args.peer_group !== undefined) {
								last_selected.add(
									menu._args.peer_group.find('.om_selected')
								);
							}
							to_unselect = last_selected;
							selected = true;
						}
						// handle any events
						if (selected) {
							om.get(option._args.on_select, select_event, option);
							if (! select_event.isDefaultPrevented()) {
								if (to_unselect !== null) {
									to_unselect.trigger('unselect.om');
								}
								node.toggleClass('om_selected', selected);
							}
							om.get(option._args.on_selected, select_event, option);
						} else {
							option.$.trigger('unselect.om');
						}
						select_event.preventDefault();
						select_event.stopPropagation();
					});
					option._select = function (select_event) {
						option.$.trigger('select.om');
						if (select_event) {
							select_event.preventDefault();
							select_event.stopPropagation();
						}
					};
					option._unselect = function (select_event) {
						option.$.trigger('unselect.om');
						if (select_event) {
							select_event.preventDefault();
							select_event.stopPropagation();
						}
					};
					// make our option clickable
					option.$.bind('click dblclick', option._select);
					// fall inline, if needed
					if (menu._args.options_inline) {
						option.$.css('display', 'inline');
						if (option._caption !== undefined) {
							option._caption.$.css('display', 'inline');
						}
					}
					// add ourselves to the menu object
					menu._options[name] = option;
					// make everyone's width match the biggest
					if (menu._args.equal_option_widths) {
						menu._equalize_option_widths();
					}
					return option;
				};

				menu._equalize_option_widths = function () {
					var max_width, name, option, width;
					max_width = 0;
					// find the max width in our options
					for (name in menu._options) {
						if (menu._options.hasOwnProperty(name)) {
							option = menu._options[name];
							if (option.$.is(':visible')) {
								width = option.$.width();
								if (width > max_width) {
									max_width = width;
								}
							}
						}
					}
					// having found the max width, set it on all options
					for (name in menu._options) {
						if (menu._options.hasOwnProperty(name)) {
							option = menu._options[name];
							if (option.$.is(':visible')) {
								option.$.width(max_width);
							}
						}
					}
					return menu;
				};

				menu._init = function () {
					menu._set_options(options);
					return menu;
				};

				// load up the initial options into the menu
				menu._init();
				if (args.dont_show !== true && args.equal_option_widths) {
					menu._equalize_option_widths();
				}
				return menu;
			},

			/* Form container and methods to set/fetch data, as well as do basic layout. */
			form: function (owner, fields, args) {
				var form, classes, name, field;
				args = om.get_args({
					auto_break_length: null, // automatically insert a break after every X options
					breaker_args: { // arguments to use when creating break manager
						options_orient: 'top',
						options_inline: true,
						equalize_tab_widths: false,
						on_tab_change: undefined
					},
					break_type: undefined, // null, 'column', 'tab', 'page'
					dont_show: false
				}, args, true);
				/* // example of fields
				fields = {
					cost: {
						type: 'text',
						args: {
							default_val: '1',
							caption: 'Product price:',
							caption_orient: left,
							...
						},
				  },
					name: {
						type: 'input type',
						args: {
							'arg_name': 'arg_value'
						}
					};
				 };
				 ... */
				form = om.bf.make.box(owner, {
					dont_show: true,
					'classes': classes,
					insert: args.insert
				});
				form.$.toggleClass('om_form', true);
				form._args = args;
				form._canvas = form._extend('middle', 'om_form_fields', {wrap: '*'});
				form._fields = {};

				/* methods */
				// collect the user's input
				form._get_input = function (args) {
					var input = {}, name;
					args = om.get_args({
						trim: false,
						all: false
					}, args);
					for (name in form._fields) {
						if (form._fields.hasOwnProperty(name) && (args.all || form._fields[name]._type !== 'readonly')) {
							if (args.trim) {
								input[name] = String(
									form._fields[name]._val()
								).trim();
							} else {
								input[name] = form._fields[name]._val();
							}
						}
					}
					return input;
				};

				// return whether or not the form fields contain any errors
				form._has_errors = function () {
					var name;
					for (name in form._fields) {
						if (form._fields.hasOwnProperty(name)) {
							if (form._fields[name].$.is('.om_input_error')) {
								return true;
							}
						}
					}
					return false;
				};

				// return a list of any errors found based on auto validation
				form._get_errors = function (revalidate) {
					var name, errors, caption;
					errors = [];
					if (revalidate === undefined) {
						revalidate = false;
					}
					for (name in form._fields) {
						if (form._fields.hasOwnProperty(name)) {
							if (revalidate) {
								form._fields[name]._validate();
							}
							if (form._fields[name]._value.is('.om_input_error')) {
								if ('caption' in form._fields[name]._args) {
									caption = name;
								} else {
									caption = form._fields[name]._args.caption;
								}
								errors.push(
									caption + ': ' +
									form._fields[name]._error_tooltip._message
								);
							}
						}
					}
					return errors;
				};

				form._focus_first = function () {
					form.$.find('input, button').slice(0, 1).focus();
				};

				form._add_submit = function (caption, on_submit, key_bind) {
					if (form._box_bottom === undefined) {
						form._extend('bottom');
					}
					// make the form submittable with the keybind
					if (key_bind === undefined) {
						key_bind = 13; // enter
					}
					if (caption === undefined) {
						caption = 'Submit';
					}
					// add the submit button
					form._submit = om.bf.make.input.button(form._box_bottom.$, 'submit', {
						caption: caption,
						'class': 'om_form_submit',
						on_click: function (click_event) {
							// fire the users's event if given
							if (typeof on_submit === 'function') {
								on_submit(click_event, form._get_input(), form);
							}
						}
					});
					if (key_bind !== null) {
						form.$.bind('keydown', function (keydown_event) {
							if (keydown_event.keyCode === key_bind) {
								// user pressed enter, activate the submit button!
								keydown_event.preventDefault();
								keydown_event.stopPropagation();
								form._submit._value.click();
							}
						});
					}
					return form._submit;
				};

				form._add_cancel = function (caption, on_cancel, key_bind) {
					if (form._box_bottom === undefined) {
						form._extend('bottom');
					}
					if (caption === undefined) {
						caption = 'Cancel';
					}
					// make the form cancellable with the keybind
					if (key_bind === undefined) {
						key_bind = 27; // escape
					}
					form._cancel = om.bf.make.input.button(form._box_bottom.$, 'cancel', {
						caption: caption,
						'class': 'om_form_cancel',
						on_click: function (click_event) {
							// fire the user's on_cancel event if present
							if (typeof on_cancel === 'function') {
								on_cancel(click_event, form);
							}
							// if we did prevent the default then rebind ourselves if default is disabled too
							if (! click_event.isDefaultPrevented()) {
								// remove ourselves from the DOM
								form._remove();
							}
						}
					});
					if (key_bind !== null) {
						form.$.bind('keydown', function (keydown_event) {
							if (keydown_event.keyCode === key_bind) {
								// activate the cancel button!
								keydown_event.preventDefault();
								keydown_event.stopPropagation();
								form._cancel._value.click();
							}
						});
					}
				};

				form._clear_fields = function () {
					var name;
					for (name in form._fields) {
						if (form._fields.hasOwnProperty(name)) {
							form._fields[name]._remove();
							delete form._fields[name];
							// increment as we go so if we have any errors we stay as sane a number as we can
							form._field_count -= 1;
						}
					}
					// clear our breaker if we have one
					if (form._breaker) {
						form._breaker._reset();
					}
					return form;
				};

				form._reset_fields = function () {
					var name;
					for (name in form._fields) {
						if (form._fields.hasOwnProperty(name)) {
							form._fields[name]._val('');
						}
					}
				};

				form._remove_field = function (name) {
					if (form._fields.hasOwnProperty(name)) {
						form._fields[name]._remove();
						delete form._fields[name];
						form._field_count -= 1;
					} else {
						throw new Error('Form does not have a field by the name "' + name + '".');
					}
				};

				form._enable = function () {
					return form._set_enabled(true);
				};

				form._disable = function () {
					return form._set_enabled(false);
				};

				form._set_enabled = function (enabled) {
					var field;
					if (enabled === undefined) {
						enabled = true;
					}
					for (field in form._fields) {
						if (form._fields.hasOwnProperty(field)) {
							form._fields[field]._set_enabled(enabled);
						}
					}
					return form;
				};

				form._breakers = {
					breaker: function (args) {
						var breaker;
						breaker = {};
						breaker._reset = function () {};
						return breaker;
					},
					tab: function (args) {
						var breaker;
						breaker = form._breakers.breaker(args);
						breaker._init = function () {
							form._tabs = [];
							if (breaker._args.options_orient === undefined) {
								breaker._args.options_orient = 'top';
							}
							if (breaker._args.options_inline === undefined) {
								if (breaker._args.options_orient === 'top' || breaker._args.options_orient === 'bottom') {
									breaker._args.options_inline = true;
								} else {
									breaker._args.options_inline = false;
								}
							}
							form._tab_bar = form._canvas._extend(breaker._args.options_orient, 'om_form_tab_bar');
							form._tab_menu = om.bf.make.menu(form._tab_bar.$, {}, {
								options_inline: breaker._args.options_inline
							});
						};
						breaker._select_first = function () { 
							if (form._tabs) {
								form._tabs[0]._option.$.click();
							}
						};
						breaker._equalize_tab_widths = function () {
							var max_width, i, tab, width;
							max_width = 0;
							// find the max width in our options
							for (i = 0; i < form._tabs.length; i++) {
								tab = form._tabs[i]._option;
								if (tab.$.is(':visible')) {
									width = tab.$.width();
									if (width > max_width) {
										max_width = width;
									}
								}
							}
							// having found the max width, set it on all options
							for (i = 0; i < form._tabs.length; i++) {
								tab = form._tabs[i]._option;
								if (tab.$.is(':visible')) {
									tab.$.width(max_width);
								}
							}
						};

						breaker._reset = function () {
							var i;
							for (i = 0; i < form._tabs.length; i++) {
								form._tabs[i]._remove();
							}
							form._tabs = [];
						};

						breaker._add = function (name, args) {
							var tab;
							if (args === undefined) {
								args = {};
							}
							tab = form._canvas._add_box('om_form_tab');
							if (name) {
								tab.$.toggleClass(name, true);
							}
							tab._args = args;
							form._tabs.push(tab);
							if (form._tabs.length > 1) {
								tab.$.hide();
							}
							form._create_target = tab.$;
							tab._option = form._tab_menu._add_option(name, {
								caption: args.caption,
								'class': 'om_form_tab_option',
								on_select: function (select_event) {
									tab._option.select();
								}
							});
							tab._option.select = function (select_event) {
								form._tab_menu._options_box.$.children('.om_form_tab_option.om_selected').toggleClass('om_selected', false);
								tab._option.$.toggleClass('om_selected', true);
								form._canvas.$.find('.om_form_tab:visible').hide();
								tab.$.show();
								if (typeof(tab._args.on_select) === 'function') {
									tab._args.on_select();
								}
								if (typeof(breaker._args.on_tab_change) === 'function') {
									breaker._args.on_tab_change(tab);
								}
							};
							tab._option.$.html(args.caption ? args.caption : 'Page ' + form._tabs.length);
							if (breaker._args.equalize_tab_widths) {
								breaker._equalize_tab_widths();
							}
						};
						breaker._args = args;
						breaker._init();
						return breaker;
					},
					column: function (args) {
						var breaker;
						breaker = form._breakers.breaker(args);
						breaker._init = function () {
							form._columns = [];
						};
						breaker._select_first = function () { 
						};
						breaker._reset = function () {
							var i;
							for (i = 0; i < form._columns.length; i++) {
								form._columns[i]._remove();
							}
							form._columns = [];
						};
						breaker._add = function (args) {
							var col;
							if (args === undefined) {
								args = {};
							}
							col = form._canvas._add_box('om_form_column');
							if (args.width) {
								col.width(args.width);
							}
							form._columns.push(col);
							form._create_target = col.$;
						};
						breaker._args = args;
						breaker._init();
						return breaker;
					},
					page: function () {
						throw new Error('TODO');
					}
				};

				form._trim_empty = function (args) {
					var name, i, field, altered, new_fields;
					args = om.get_args({
						reorder: false,
						get_reorder_name: function (name, i) {
							// reorder by counting numbers by default
							return String(i + 1);
						},
						get_reorder_caption: function (field, i) {
							return String(i + 1) + ':';
						}
					}, args);
					altered = false;
					for (name in form._fields) {
						if (form._fields.hasOwnProperty(name) &&
							form._fields[name]._val() === '') {
							form._remove_field(name);
							altered = true;
						}
					}
					// reorder the names
					if (altered && args.reorder) {
						// yah, we might have only trimmed the bottom. Oh well.
						i = 0;
						new_fields = {};
						for (name in form._fields) {
							if (form._fields.hasOwnProperty(name)) {
								field = form._fields[name];
								field._name = args.get_reorder_name(field, i);
								field._caption.$.html(args.get_reorder_caption(field, i));
								new_fields[field._name] = field;
								delete form._fields[name];
								i += 1;
							}
						}
						form._fields = new_fields;
					}
					return form;
				};

				form._add_field = function (type, name, field_args) {
					var field, box_remove;
					if (field_args === undefined) {
						field_args = {};
					}
					if (type === undefined) {
						throw new Error("Missing form field type.");
					} else if (type === 'obj') {
						throw new Error("Unable to populate form with an object of type 'obj'.");
					}
					if (name === undefined && type !== 'break') {
						throw new Error("Missing form field name.");
					}
					if (name in form._fields) {
						throw new Error("Form already has a field by the name '" + name + "'.");
					}
					if (! (type in om.bf.make.input) && type !== 'break') {
						throw new Error("Invalid form input type: '" + type + "'.");
					}
					// auto break if needed
					if (form._auto_break_counter === form._auto_break_length) {
						form._auto_break_counter = 0;
						form._breaker._add(name, field_args);
					}
					// check to see if we have a break or field
					if (form._breaker && type === 'break') {
						form._breaker._add(name, field_args);
					} else {
						field = om.bf.make.input[type](form._create_target, name, field_args); 
						field._form = form;
						field._type = type;
						box_remove = field._remove;
						field._remove = function () {
							box_remove();
							delete form._fields[name];
						};
						form._fields[name] = field;
						form._field_count += 1;
						if (form._auto_break_length) {
							form._auto_break_counter += 1;
						}
					}
					return field;
				};

				form._load_data = function (data) {
					var item;
					for (item in data) {
						if (data.hasOwnProperty(item)) {
							if (form._fields.hasOwnProperty(item)) {
								form._fields[item]._val(data[item]);
							}
						}
					}
					return form;
				};

				form._add_fields = function (fields) {
					var name;
					// add each of the fields to the form
					for (name in fields) {
						if (fields.hasOwnProperty(name)) {
							field = fields[name];
							if (field.type === undefined) {
								throw new Error("Invalid field type.");
							}
							form._add_field(field.type, name, field.args);
						}
					}
					return form;
				};

				form._set_fields = function (fields) {
					form._clear_fields();
					form._add_fields(fields);
					return form;
				};

				form._init = function (fields) {
					if (form._break_type) {
						form._breaker = form._breakers[form._break_type](form._breaker_args);
					}
					form._set_fields(fields);
					if (form._breaker) {
						form._breaker._select_first();
					}
					return form;
				};

				form._auto_break_length = args.auto_break_length;
				if (form._auto_break_length) {
					form._auto_break_counter = 0;
				}
				form._break_type = args.break_type;
				form._breaker_args = args.breaker_args;
				form._breaker = null;
				form._field_count = 0;
				form._create_target = form._canvas.$;
				if (form._args.dont_show !== true) {
					form.$.show();
				}
				form._init(fields);
				return form;
			},

			/* Initial work at a scroll-bar duplication, but limited browser support for mousewheel. */
			scroller: function (owner, args) {
				var scroller;
				/* 	args = {
						target: $(...), // some jquery reference to scroll
						constraint: target.parent(), // the target's constraint
						orient: 'horizontal', // the direction the scroller is oriented
						verticle: true, // enable verticle scrolling
						horizontal: false, // enable horizontal scrolling
						speed: 0, // scroll bar animation speed
						multiplier: 1.0, // scrolling rate, in terms of target height/width,
						auto_hide: true // auto-hide the scroller if the target fits in the constraint
					};
				*/
				if (args === undefined) {
					args = {};
				}
				if (args.target === undefined || args.target.jquery === undefined) {
					throw new Error("Unable to create scroller; target is not a valid jQuery object reference.");
				}
				if (args.constraint === undefined) {
					args.constraint = args.target.parent();
				}
				if (args.orient === undefined) {
					args.orient = 'horizontal';
				}
				if (args.orient !== 'horizontal' && args.orient !== 'verticle') {
					throw new Error(
						"Invalid scroller orientation: '" +
						args.orient + "'. Valid orientations are 'horizontal' and 'verticle'."
					);
				}
				if (args.verticle === undefined) {
					args.verticle = true;
				}
				if (args.horizontal === undefined) {
					args.horizontal = false;
				}
				if (args.horizontal === args.verticle) {
					throw new Error("Unable to enable both verticle and horizontal scrolling at the same time.");
				}
				if (args.classes === undefined) {
					args.classes = [];
				}
				if (args.multiplier === undefined) {
					args.multiplier = 1.0;
				}
				if (args.auto_hide === undefined) {
					args.auto_hide = true;
				}
				/*
				if (args.no_progress === undefined) {
					args.no_progress = false;
				}
				if (args.no_links === undefined) {
					args.no_links = false;
				}
				*/
				args.classes.push('om_scroller');
				/* construction */
				scroller = om.bf.make.box(owner, {
					'classes': args.classes,
					insert: args.insert
				});
				/*
				scroller._header = scroller._extend('top', 'header');
				scroller._header._progress = scroller._header._add_box('progress');
				scroller._header._links = scroller._header._add_box('links');
				scroller._header._links._top = om.bf.make.input.link(
					scroller._header._links.$, 'top', {
						caption: 'Top',
						on_click: function (click_event) {
							scroller._scroll_top();
						}
					}
				);
				scroller._header._links._bottom = om.bf.make.input.link(
					scroller._header._links.$, 'bottom', {
						on_click: function (click_event) {
							scroller._scroll_bottom();
						}
					}
				);
				*/
				scroller._track = scroller._extend('middle', 'om_scroller_track');
				scroller._track._bar = scroller._track._extend('middle', 'om_scroller_bar');

				/* methods */
				scroller._on_scroll = function (wheel_ev) {
					var mag = 0;
					// figure out which direction we scrolled
					if (wheel_ev.wheelDelta > 0) {
						mag = -0.3;
					} else if (wheel_ev.wheelDelta < 0) {
						mag = 0.3;
					}
					scroller._scroll(mag);
				};

				scroller._update_trackbar = function (resize_ev) {
					var direction, measure, metrics, ratios, bar_length, offset,
						track_length, border_space;
					// when either the target or constraint resize we need
					// to adjust the trackbar size in the track
					metrics = scroller._get_metrics();
					ratios = scroller._get_ratios();
					// which direction do we scroll in?
					if (scroller._horizontal) {
						direction = 'left';
						measure = 'width';
					} else {
						direction = 'top';
						measure = 'height';
					}
					// do we fit in the constraint?
					if (metrics[measure].constraint >= metrics[measure].target && scroller._auto_hide) {
						// hide the scroller and make sure we're at 0/0 so we 
						// aren't hanging (e.g. after a resize)
						scroller.$.hide();
						return;
					} else {
						//  be sure we're visible
						scroller.$.fadeIn();
					}
					// set the bar length to relate the scroller length to target length
					if (scroller._orient === 'horizontal') {
						track_length = scroller._track.$.width()
							- parseInt(scroller._track.$.css('padding-left'), 10)
							- parseInt(scroller._track.$.css('padding-right'), 10);
					} else {
						track_length = scroller._track.$.height()
							- parseInt(scroller._track.$.css('padding-top'), 10)
							- parseInt(scroller._track.$.css('padding-bottom'), 10);
					}
					bar_length = parseInt(ratios[measure] * track_length, 10);
					// and make sure our position reflects where in the doc we're at
					offset = parseInt(scroller._target.css(direction), 10)
						/ (metrics[measure].constraint - metrics[measure].target);
					// TODO: add animation option
					// which direction do we grow/shrink in?
					border_space = 0;
					if (scroller._orient === 'horizontal') {
						border_space += parseInt(scroller._track._bar.$.css('border-left-width'), 10);
						border_space += parseInt(scroller._track._bar.$.css('border-right-width'), 10);
						scroller._track._bar.$.css(
							'width',
							bar_length + 'px'
						);
						scroller._track._bar.$.css(
							'left',
							parseInt(offset * (track_length - bar_length), 10) + 'px'
						);
					} else {
						border_space += parseInt(scroller._track._bar.$.css('border-top-width'), 10);
						border_space += parseInt(scroller._track._bar.$.css('border-bottom-width'), 10);
						scroller._track._bar.$.css(
							'height',
							bar_length + 'px'
						);
						scroller._track._bar.$.css(
							'top',
							Math.max(0, (parseInt(offset * (track_length - bar_length), 10)) - border_space) + 'px'
						);
					}
					//scroller._header._progress.$.text(parseInt(offset * 100, 10) + '%');
					return scroller;
				};

				scroller._scroll = function (mag) {
					var	direction, measure, metrics, delta, cur_pos, new_pos,
						min, max;
					// which direction do we scroll in?
					if (scroller._horizontal) {
						direction = 'left';
						measure = 'width';
					} else {
						direction = 'top';
						measure = 'height';
					}
					// adjust magnitudes for our multiplier
					mag = mag * scroller._multiplier;
					metrics = scroller._get_metrics();
					// figure out how far to scroll, translating pages to pixels
					delta = parseInt(mag * metrics[measure].constraint, 10);
					// cap the change to keep the target from going too far in any direction
					cur_pos = parseInt(scroller._target.css(direction), 10);
					new_pos = cur_pos - delta;
					min = - (metrics[measure].target - metrics[measure].constraint);
					max = 0;
					if (new_pos < min) {
						new_pos = min;
					}
					if (new_pos > max) {
						new_pos = max;
					}
					// and if we're moving, make it so
					if (new_pos != cur_pos) {
						// TODO: add animation option (e.g. fade out, move, fade in)
						scroller._target.css(direction, new_pos + 'px');
					}
					scroller._update_trackbar();
					return scroller;
				};

				scroller._scroll_top = function () {
					if (scroller._verticle) {
						scroller._target.css('top', '0px');
						scroller._update_trackbar();
					} else if (scroller._horizontal) {
						scroller._target.css('left', '0px');
						scroller._update_trackbar();
					}
					return scroller;
				};

				scroller._scroll_bottom = function () {
					var pos, new_pos;
					if (scroller._verticle) {
						pos = scroller._constraint.width();
						new_pos = pos - scroller._target.outerHeight();
						scroller._target.css('top', new_pos + 'px');
						scroller._update_trackbar();
					} else if (scroller._horizontal) {
						pos = scroller._constraint.height();
						new_pos = pos - scroller._target.outerWidth();
						scroller._target.css('left', new_pos + 'px');
						scroller._update_trackbar();
					}
					return scroller;
				};

				scroller._get_metrics = function () {
					return {
						width: {
							target: scroller._target.outerWidth(),
							constraint: scroller._constraint.innerWidth()
						},
						height: {
							target: scroller._target.outerHeight(),
							constraint: scroller._constraint.innerHeight()
						}
					};
				};

				scroller._get_ratios = function () {
					var metrics;
					metrics = scroller._get_metrics();
					return {
						width: Math.min(
							metrics.width.constraint / metrics.width.target,
							1.0
						),
						height: Math.min(
							metrics.height.constraint / metrics.height.target,
							1.0
						)
					};
				};
				
				scroller._init = function () {
					// make sure the handlers are only bound once
					scroller.$.unbind('scroll.om', scroller._on_scroll);
					scroller._target.unbind('mousewheel', scroller._on_scroll);
					scroller._target.unbind('resize', scroller._update_trackbar);
					scroller._constraint.unbind('resize', scroller._update_trackbar);
					scroller.$.bind('scroll.om', scroller._on_scroll);
					scroller._target.bind('mousewheel', scroller._on_scroll);
					scroller._target.bind('resize', scroller._update_trackbar);
					scroller._constraint.bind('resize', scroller._update_trackbar);
					// set our target to relative positioning so we can move it
					scroller._target.css('position', 'relative');
					// initialize it to 0%, lest we not already be there
					scroller._scroll_top();
					// set our orientation for CSS
					if (scroller._orient === 'verticle') {
						scroller.$.toggleClass('om_scroller_verticle', true);
						scroller.$.toggleClass('om_scroller_horizontal', false);
					} else {
						scroller.$.toggleClass('om_scroller_horizontal', true);
						scroller.$.toggleClass('om_scroller_verticle', false);
					}
					/*
					// show/hide the header info
					if (scroller._args.no_progress) {
						scroller._header._progress.$.hide();
					} else {
						scroller._header._progress.$.show();
					}
					if (scroller._args.no_links) {
						scroller._header._links.$.hide();
					} else {
						scroller._header._links.$.show();
					}
					*/
				};
				
				/* init */
				scroller._args = args;
				scroller._target = args.target;
				scroller._constraint = args.constraint;
				scroller._verticle = args.verticle;
				scroller._horizontal = args.horizontal;
				scroller._multiplier = args.multiplier;
				scroller._orient = args.orient;
				scroller._auto_hide = args.auto_hide;
				scroller._init();
				return scroller;
			},

			/* Common form objects with a bit more intelligence than the average object. */
			input: {

				/* Hint an input with a predefined value, which changes to the default value on focus (e.g. to give instructions that auto-clear on focus). */
				_hint: function (obj, hint, args) {
					if (args === undefined) {
						args = {};
					}
					// if we have a hint then auto-clear it when we focus the value
					if (hint !== undefined && typeof(hint) === typeof '') {
						// only hint objects w/o a value already
						if (obj._val() === '') {
							// hint the object
							obj._val(hint);
							// when focused clear the hint
							obj.$.delegate('.om_input_value', 'focusin focusout', function (focus_ev) {
								var value = obj._val();
								if (focus_ev.type === 'focusin') {
									// if the default text is shown then remove it
									if (value === hint) {
										if (args.default_val !== undefined) {
											obj._val(args.default_val);
										} else {
											obj._val('');
										}
									}
								} else if (focus_ev.type === 'focusout') {
									// left blank? re-hint the object
									if (value === '') {
										obj._val(hint);
									}
								}
							});
						}
					}
					return obj;
				},
				
				/* Generic object creation. Base object for input objects. */
				obj: function (owner, args) {
					var obj;
					args = om.get_args({
						caption: undefined,
						caption_orient: 'top',
						classes: [],
						dont_show: false,
						link_caption: true, // whether or not to link DOM events to thecaption to actual input object
						on_change: undefined, // what to do when the value changes
						on_click: undefined, // what to do when the input is clicked
						tooltip: undefined, // a tooltip to show on mouse-over
						validate: undefined
					}, args);
					/* args = {
					}; */
					obj = om.bf.make.box(owner, args);
					obj.$.toggleClass('om_input', true);
					// create a generic _val() function to get or set the value
					obj._args = args;
					obj._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return obj.$.find('.om_input_value').val();
						} else {
							return obj.$.find('.om_input_value').val(value);
						}
					};
					// add a validation function
					obj._validate = function (change_event) {
						var response;
						if (typeof(obj._args.validate) === 'function') {
							// run the validation
							response = om.get(obj._args.validate, obj._val(), obj);
							// remove any old errors if present
							if (obj._error_tooltip !== undefined) {
								obj._error_tooltip._remove();
								delete obj._error_tooltip;
							}
							if (response === true) {
								obj._value.toggleClass('om_input_error', false);
								return true;
							} else {
								obj._value.toggleClass('om_input_error', true);
								if (typeof response === typeof '') {
									// show the error as a tooltip if we got a string back
									obj._error_tooltip = om.bf.make.tooltip(obj.$, response);
								}
								return false;
							}
						}
					};
					// see where to put the caption, if we have one
					if (args.caption !== undefined) {
						if (args.caption_orient === undefined) {
							args.caption_orient = 'top';
						}
						if (! (args.caption_orient === 'top' ||
							args.caption_orient === 'right' ||
							args.caption_orient === 'bottom' ||
							args.caption_orient === 'left')) {
							throw new Error("Invalid caption orientation: '" + args.caption_orient + "'.");
						}
						if (args.link_caption === undefined) {
							args.link_caption = true;
						}
						// add the caption based on the orientation
						obj._caption = obj._extend(args.caption_orient, 'om_input_caption');
						obj._caption.$.html(args.caption);
						// and link it with the value if requested
						if (args.link_caption) {
							obj._caption.$.css('cursor', 'pointer');
							obj._caption.$.bind('click dblclick', function (click_event) {
								var value;
								obj._value.trigger('click');
								obj._value.trigger('change');
								if (! (obj._type === 'radio_button' || obj._type === 'checkbox')) {
									obj._value.focus();
								}
								click_event.stopPropagation();
								click_event.preventDefault();
							});
						}
					}
					// add in a click event if supplied
					obj.$.delegate('.om_input_value', 'click dblclick', function (click_event) {
						if (typeof(obj._args.on_click) === 'function') {
							obj._args.on_click(click_event, obj);
						}
					});
					// run our on_change method when the value is changed
					obj.$.delegate('.om_input_value', 'change', function (change_event) {
						// add in auto validation
						if (typeof(obj._validate) === 'function') {
							obj._validate(change_event);
						}
						if (typeof(obj._args.on_change) === 'function') {
							obj._args.on_change(change_event, obj);
						}
					});
					// add a tooltip if needed
					if (args.tooltip !== undefined && args.tooltip !== '') {
						obj._tooltip = om.bf.make.tooltip(obj.$, args.tooltip);
					}

					obj._enable = function () {
						return obj._set_enabled(true);
					};

					obj._disable = function () {
						return obj._set_enabled(false);
					};

					obj._set_enabled = function (enabled) {
						if (enabled === undefined) {
							enabled = true;
						}
						obj._value.prop('disabled', ! Boolean(enabled));
					};
					return obj;
				},

				/* Generic button object. Can be set to be single-clickable to prevent double clicks.*/
				button: function (owner, name, args) {
					var button;
					args = om.get_args({
						caption: undefined,
						classes: [],
						enabled: true,
						multi_click: true,
						on_click: undefined
					}, args, true);
					if (name === undefined) {
						name = '';
					}
					if (! args.caption) {
						args.caption = name;
					}
					// add the button to the DOM
					button = om.bf.make.input.obj(owner, args);
					button.$.toggleClass('om_button', true);
					button._name = name;
					if (button._name) {
						button.$.toggleClass(button._name);
					}
					button._type = 'button';
					button.$.html('<button>' + args.caption + '</button>');
					button._value = button.$.find('button:last');
					if (args.enabled === false) {
						button._value.prop('disabled', true);
					}
					button._value.toggleClass('om_input_value', true);
					if (args.on_click !== undefined) {
						// try disable ourselves right away to prevent double clicks
						if (args.multi_click) {
							button._value.one('click dblclick', function (click_event) {
								button._value.prop('enabled', false);
								args.on_click(click_event, button);
								// after having done our work we can re-bind/activate ourself
								button._value.one('click dblclick', arguments.callee);
								button._value.prop('enabled', true);
								click_event.preventDefault();
								click_event.stopPropagation();
							});
						} else {
							button._value.one('click dblclick', function (click_event) {
								button._value.prop('enabled', false);
								args.on_click(click_event, button);
								if (click_event.isDefaultPrevented()) {
									// re-bind our click
									button._value.one('click dblclick', arguments.callee);
								}
								click_event.preventDefault();
								click_event.stopPropagation();
							});
						}
					}
					button._enable = function () {
						return button._set_enabled(true);
					};

					button._disable = function () {
						return button._set_enabled(false);
					};

					button._set_enabled = function (enabled) {
						if (enabled === undefined) {
							enabled = true;
						}
						button._value.prop('disabled', ! Boolean(enabled));
					};
					return button;
				},

				/* HTTP link object. */
				link: function (owner, name, args) {
					var link;
					args = om.get_args({
						caption: undefined,
						href: 'javascript:',
						inline: false,
						target: undefined
					}, args, true);
					if (owner === undefined) {
						owner = $('body');
					}
					if (name === undefined) {
						name = '';
					}
					if (! args.caption) {
						args.caption = name;
					}
					link = om.bf.make.input.obj(owner, args);
					link.$.toggleClass('om_link', true);
					if (name) {
						link.$.toggleClass(name, true);
					}
					if (args.inline) {
						link.$.css('inline', true);
					}
					link._args = args;
					link._type = 'link';
					link._name = name;
					link.$.html('<a href="' + args.href + '">' +
						(args.caption ? args.caption : '' ) + '</a>');
					link._value = link.$.find('a:last');
					if (args.target) {
						link._value.prop('target', args.target);
					}
					link._value.toggleClass('om_input_value', true);
					return link;
				},

				/* Read-only (e.g. label) input. */
				readonly: function (owner, name, args) {
					var readonly;
					args = om.get_args({
						default_val: undefined
					}, args, true);
					if (name === undefined) {
						name = 'readonly';
					}
					readonly = om.bf.make.input.obj(owner, args);
					readonly._extend('middle', 'om_input_value');
					readonly._type = 'readonly';
					readonly._name = name;
					readonly._value = readonly.$.find('div.om_input_value');
					readonly._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return readonly._value.html();
						} else {
							return readonly._value.html(value);
						}
					};
					if (args.default_val) {
						readonly._val(args.default_val);
					}
					return readonly;
				},

				/* Text input form field. */
				text: function (owner, name, args) {
					var text;
					args = om.get_args({
						default_val: '',
						enabled: true,
						hint: undefined
					}, args, true);
					if (name === undefined) {
						name = 'text';
					}
					text = om.bf.make.input.obj(owner, args);
					text.$.append(om.assemble('input', {
						name: name,
						type: 'text',
						'class': 'om_input_value',
						value: args.default_val
					}));
					if (name) {
						text.$.toggleClass(name, true);
					}
					text._name = name;
					text._type = 'text';
					text._value = text.$.children('input.om_input_value:first');
					if (args.enabled === false) {
						text._value.prop('disabled', true);
					}
					text._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return text._value.val();
						} else {
							return text._value.val(value);
						}
					};
					if (args.hint) {
						om.bf.make.input._hint(text, args.hint, {
							default_val: args.default_val
						});
					}
					return text;
				},

				/* Password input form field. */
				password: function (owner, name, args) {
					var password;
					args = om.get_args({
						default_val: '',
						enabled: true
					}, args, true);
					if (name === undefined) {
						name = 'password';
					}
					password = om.bf.make.input.obj(owner, args);
					password.$.append(om.assemble('input', {
						name: name,
						type: 'password',
						'class': 'om_input_value',
						value: args.default_val
					}));
					password._name = name;
					password._type = 'password';
					password._value = password.$.children('input.om_input_value:first');
					if (args.enabled === false) {
						password._value.prop('disabled', true);
					}
					password._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return password._value.val();
						} else {
							return password._value.val(value);
						}
					};
					return password;
				},

				/* Checkbox input form field. */
				checkbox: function (owner, name, args) {
					var cb;
					args = om.get_args({
						default_val: false,
						enabled: true
					}, args, true);
					if (name === undefined) {
						name = 'checkbox';
					}
					cb = om.bf.make.input.obj(owner, args);
					cb.$.append(om.assemble('input', {
						name: name,
						type: 'checkbox',
						'class': 'om_input_value'
					}));
					cb._name = name;
					cb._type = 'checkbox';
					cb._value = cb.$.children('input.om_input_value:first');
					if (args.enabled === false) {
						cb._value.prop('disabled', true);
					}
					cb._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return cb._value.prop('checked');
						} else {
							return cb._value.prop('checked', value);
						}
					};
					if (args.default_val) {
						cb._val(true);
					}
					return cb;
				},

				/* Radio button form field. */
				radio_button: function (owner, name, args) {
					var rb;
					if (name === undefined) {
						name = 'radio_button';
					}
					args = om.get_args({
						default_val: false,
						name: name, // confusing, I know, but the RB name needs to be the same for multiple objs
						enabled: true
					}, args, true);
					rb = om.bf.make.input.obj(owner, args);
					rb.$.append(om.assemble('input', {
						name: args.name,
						type: 'radio',
						'class': 'om_input_value'
					}));
					rb._name = name;
					rb._type = 'radio_button';
					rb._value = rb.$.children('input.om_input_value:first');
					if (args.enabled === false) {
						rb._value.prop('disabled', true);
					}
					rb._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return rb._value.prop('checked');
						} else {
							return rb._value.prop('checked', value);
						}
					};
					if (args.default_val) {
						rb._val(true);
					}
					return rb;
				},

				/* Text area form field. */
				textarea: function (owner, name, args) {
					var textarea;
					args = om.get_args({
						default_val: '',
						enabled: true,
						hint: undefined
					}, args, true);
					if (name === undefined) {
						name = 'text';
					}
					textarea = om.bf.make.input.obj(owner, args);
					textarea.$.append(om.assemble('textarea', {
						name: name,
						'class': 'om_input_value',
						value: args.default_val
					}));
					textarea._type = 'textarea';
					textarea._value = textarea.$.children('textarea.om_input_value:first');
					textarea._name = name;
					if (args.enabled === false) {
						textarea._value.prop('disabled', true);
					}
					textarea._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return textarea._value.val();
						} else {
							return textarea._value.val(value);
						}
					};
					if (args.hint) {
						om.bf.make.input._hint(textarea, args.hint, {default_val: args.default_val});
					}
					return textarea;
				},

				/* Select form field. */
				select: function (owner, name, args) {
					var select;
					args = om.get_args({
						default_val: undefined, // e.g. value2
						enabled: true,
						options: {} // {value: "Option Name", value2: "Name 2"}
					}, args, true);
					if (name === undefined) {
						name = 'text';
					}
					select = om.bf.make.input.obj(owner, args);
					select.$.append(om.assemble('select', {
						name: name,
						'class': 'om_input_value'
					}));
					select._value = select.$.children('select.om_input_value:first');
					if (args.enabled === false) {
						select._value.prop('disabled', true);
					}
					select._type = 'select';
					select._name = name;
					select._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return select._value.val();
						} else {
							return select._value.val(value);
						}
					};
					/* Remove all the options. */
					select._clear_options = function () {
						select._value.html('');
					};
					/* Add an option, optionally with the specified value. */
					select._add_option = function (name, value) {
						if (value === undefined) {
							select._value.append(
								'<option>' + name + '</option>'
							);
						} else {
							select._value.append(
								'<option value="' + value + '">' + name + '</option>'
							);
						}
						return select;
					};
					/* Add in a set of options, replacing any existing ones. */
					select._set_options = function (options) {
						var i, key;
						if (jQuery.isArray(options)) {
							for (i = 0; i < options.length; i++) {
								select._add_option(options[i]);
							}
						} else {
							for (key in options) {
								if (options.hasOwnProperty(key)) {
									select._add_option(options[key], key);
								}
							}
						}
					};
					// add in our options, 
					select._set_options(args.options);
					// when we are typed in consider it a change
					select._value.bind('keyup', function(key_event) {
						select._value.trigger('change');
					});
					// handle our default val
					if (args.default_val !== null) {
						select._val(args.default_val);
					}
					return select;
				},

				/* File upload dialog object. */
				file: function (owner, name, args) {
					var file;
					args = om.get_args({
						enabled: true
					}, args, true);
					if (name === undefined) {
						name = 'text';
					}
					file = om.bf.make.input.obj(owner, args);
					file.$.append(om.assemble('input', {
						name: name,
						type: 'file',
						'class': 'om_input_value'
					}));
					file._type = 'file';
					file._value = file.$.children('file.om_input_value:first');
					if (args.enabled === false) {
						file._value.prop('disabled', true);
					}
					file._val = function (value) {
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							return file._value.val();
						} else {
							return file._value.val(value);
						}
					};
					file._name = name;
					return file;
				},

				/* JSON-aware text form field.
				Has a pop-up HUD to show an formatted copy of input. */
				json: function (owner, name, args) {
					var json;
					args = om.get_args({
						help: true,
						default_val: '',
						enabled: true
					}, args, true);
					if (name === undefined) {
						name = 'json';
					}
					json = om.bf.make.input.obj(owner, args);
					json.$.append(om.assemble('input', {
						name: name,
						type: 'text',
						'class': 'om_input_value'
					}));
					json._name = name;
					json._type = 'json';
					json._value = json.$.children('input.om_input_value:first');
					if (args.enabled === false) {
						json._value.prop('disabled', true);
					}
					json._val = function (value, auto_complete) {
						if (auto_complete === undefined) {
							auto_complete = true;
						}
						if (value === undefined) { // jquery 1.4.3 hack, QQ
							if (auto_complete) {
								value = om.json.auto_complete(json._value.val());
								if (value === '') {
									return null;
								} else {
									return om.json.decode(value);
								}
							} else {
								return om.json.decode(json._value.val());
							}
						} else {
							if (value === '') {
								return json._value.val('');
							} else {
								return json._value.val(om.json.encode(value));
							}
						}
					};
					json._val(args.default_val);
					if (args.help) {
						// create a param HUD to help the user
						json._help = json._add_box('om_input_help', {imbue: 'free', dont_show: true});
						// show the HUD next to the JSON input form field, or hide if the focus is out.
						json._value.bind('keyup focusin focusout', function (event) {
							var text, json_obj, obj_type, value_loc;
							// show the param HUD to the right of the API runner, but only show it if there is data in the input box
							if (event.type === 'keyup' || event.type === 'focusin') {
								// show the param HUD
								text = json._value.val();
								if (text !== '') {
									// try to auto-complete the JSON so it can be rendered
									text = om.json.auto_complete(text);
									try {
										json_obj = om.json.decode(text);
										obj_type = typeof(json_obj);
										if (jQuery.isArray(json_obj)) {
											obj_type = 'array';
										}
										json._help.$.html('<div class="header">(' + obj_type + ')</div>' + om.vis.obj2html(json_obj));
										//json._help.$.toggleClass('parse_error', false);
									} catch (e) {
										// parse error?
										json._help.$.html('(unable to parse <br/>input as JSON)');
										//json._help.$.toggleClass('parse_error', true);
									}
								} else {
									json._help.$.html('(Unknown)');
								}
								value_loc = json._value.position();
								// move to just below field's current location and match the width
								json._help._move_to(
									value_loc.left,
									value_loc.top + parseInt(json._value.outerHeight(), 10) + 3
								);
								json._help.$.width(json._value.innerWidth());
								json._help.$.show();
							} else if (event.type === 'focusout') {
								// clear and hide the param HUD
								json._help.$.html('');
								json._help.$.hide();
							} else {
								throw new Error("Invalid focus event type: '" + event.type + "'.");
							}
						});
					}
					return json;
				}
			},

			/* Tooltip GUI object. */
			tooltip: function (owner, message, args) {
				var tooltip;
				args = om.get_args({
					classes: [],
					offset: {x: 8, y: 8},
					speed: 0,
					target: owner
				}, args, true);
				args.classes.push('om_tooltip');
				if ('class' in args) {
					args.classes.push(args['class']);
				}
				tooltip = om.bf.make.box(owner, {
					imbue: 'free',
					classes: args.classes,
					dont_show: true,
					insert: args.insert
				});
				tooltip._args = args;
				tooltip._message = message;
				tooltip._offset = args.offset;
				if (message !== undefined) {
					tooltip.$.html(message);
				}
				tooltip._on_move = function (mouse_move) {
					// show the tooltip by the cursor
					tooltip._move_to(mouse_move.pageX + tooltip._offset.x, mouse_move.pageY + tooltip._offset.y);
					if (tooltip._args.on_move !== undefined) {
						tooltip._args.on_move(mouse_move, tooltip);
					}
					tooltip.$.show();
					// and move to be within any constraint we were given
					if (tooltip._args.constraint) {
						tooltip._constrain_to(tooltip._args.constraint);
					}
					mouse_move.stopPropagation();
				};
				tooltip._on_exit = function (mouse_event) {
					tooltip.$.hide();
					if (tooltip._args.on_exit !== undefined) {
						tooltip._args.on_exit(mouse_event, tooltip);
					}
					mouse_event.stopPropagation();
				};
				if (args.target) {
					args.target.bind('mousemove', tooltip._on_move);
					args.target.bind('mouseout', tooltip._on_exit);
				}
				if (args.delegate) {
					owner.delegate(args.delegate, 'mousemove', tooltip._on_move);
					owner.delegate(args.delegate, 'mouseout', tooltip._on_exit);
				}
				// rebind our _remove method so we can unbind our events from our args.target
				tooltip._box_remove = tooltip._remove;
				tooltip._remove = function () {
					if (tooltip._args.target) {
						tooltip._args.target.unbind('mousemove', tooltip._args.on_move);
						tooltip._args.target.unbind('mouseout', tooltip._args.on_exit);
					}
					if (tooltip._args.undelegate) {
						owner.undelegate(tooltip._args.delegate, 'mousemove', tooltip._args.on_move);
						owner.undelegate(tooltip._args.delegate, 'mouseout', tooltip._args.on_exit);
					}
					tooltip._box_remove();
				};
				return tooltip;
			},

			/* GUI object to cover/obscure other objects. Covers inside owner object. */
			blanket: function (owner, args) {
				var blanket;
				if (owner === undefined) {
					owner = $('body');
				}
				if (args === undefined) {
					args = {};
				}
				if (args.dont_show === undefined) {
					args.dont_show = false;
				}
				blanket = om.bf.make.box(owner, {
					imbue: 'free',
					dont_show: true,
					'class': 'om_blanket',
					insert: args.insert
				});
				if (args.options !== undefined) {
					blanket._opacity(args.opacity);
				}
				if (args.dont_show !== true) {
					blanket.$.show();
				}
				return blanket;
			},

			/* GUI object to cover/obscure other objects. Covers behind owner object. */
			skirt: function (owner, args) {
				var skirt;
				if (owner === undefined) {
					owner = $('body');
				}
				if (args === undefined) {
					args = {};
				}
				if (args.dont_show === undefined) {
					args.dont_show = false;
				}
				skirt = om.bf.make.box(owner, {
					imbue: 'free',
					dont_show: true,
					'class': 'om_skirt',
					insert: args.insert
				});
				if (args.options !== undefined) {
					skirt._opacity(args.opacity);
				}
				// move the skirt to be before the owner, instead of inside
				skirt.$ = skirt.$.detach();
				owner.before(skirt.$);
				if (args.dont_show !== true) {
					skirt.$.show();
				}
				return skirt;
			},

			/* Basic pop-up box to show a message. */
			message: function (owner, title, html, args) {
				var message, func;
				args = om.get_args({
					classes: [],
					dont_show: false,
					modal: false // automatically cover owning object with a skirt obj
				}, args);
				if (owner === undefined) {
					owner = $('body');
				}
				// create the box
				message = om.bf.make.box(owner, {
					imbue: 'free',
					dont_show: true,
					classes: args.classes,
					insert: args.insert
				});
				message.$.toggleClass('om_message', true);
				// add in a skirt if in modal mode
				if (args.modal === true) {
					message._blanket = om.bf.make.skirt(message.$, {
						imbue: 'free',
						dont_show: true,
						'class': 'om_blanket'
					});
					// hijack some functions so we can handle the possible skirt
					message._remove = function () {
						message._blanket.$.remove();
						message.$.remove();
						delete message.$;
					};
					message._show = function (speed) {
						message._blanket.$.show(speed);
						message.$.show(speed);
					};
					message._hide = function (speed) {
						message._blanket.$.hide(speed);
						message.$.hide(speed);
					};
					func = {
						skirt: {
							constrain_to: message._blanket._constrain_to,
							raise: message._blanket._raise
						},
						message: {
							constrain_to: message._constrain_to,
							raise: message._raise
						}
					};
					message._constrain_to = function (constrain_to, args) {
						func.message.constrain_to(constrain_to, args);
						func.skirt.constrain_to(constrain_to, args);
					};
					message._raise = function () {
						func.skirt.raise();
						func.message.raise();
					};
				} else {
					// add special show/hide functions so we never have to worry if this is modal
					message._show = function (speed) {
						message.$.show(speed);
					};
					message._hide = function (speed) {
						message.$.hide(speed);
					};
				}
				// set the HTML if available
				if (html !== undefined && html !== null) {
					message._extend('middle');
					message._box_middle.$.html(html);
				}
				// add in a title if one was given
				if (title !== undefined && title !== null) {
					message._extend('top', 'om_message_title');
					message._box_top.$.html(title);
					// default to dragging by the title
					message._draggable(message._box_top.$, {
						constraint: $(window)
					});
				} else {
					// no title? make the entire message draggable
					message._draggable(message.$, {
						constraint: $(window)
					});
				}
				message._center_top(0.2, owner);
				// and show it unless otherwise requested
				if (args.dont_show !== true) {
					message._show();
				}
				message._raise();
				return message;
			},

			/* Loading screen for covering GUI components. */
			loading: function (owner, args) {
				var loading;
				args = om.get_args({
					depth: 1, // each time _remove is called the depth is lowered by one; the loading box is removed when it hits 0
					on_complete: undefined, // callback on completion; args: loading obj
					resize: false // auto resize to fit owner using supplied arguments
				}, args);
				loading= om.bf.make.box(
					owner, {
						imbue: 'free',
						dont_show: true,
						'class': 'om_loading'
					}
				);
				loading._args = args;
				if (args.options !== undefined) {
					loading._opacity(args.opacity);
				}
				loading._depth = args.depth;
				if (args.resize) {
					loading._resize_to(owner, om.get(args.resize) || {});
				}
				// hi-jack remove to implement depth
				loading._box_remove = loading._remove;
				loading._remove = function () {
					// stall our removal until the depth is cleared
					loading._depth -= 1;
					if (loading._depth === 0) {
						om.get(args.on_complete, loading);
						loading._box_remove();
					}
				};
				if (args.dont_show !== true) {
					loading.$.show();
				}
				return loading;
			},

			/* Confirmation pop-up. */
			confirm: function (owner, title, html, args) {
				var conf;
				args = om.get_args({
					caption: 'Close',
					on_close: undefined, // callback for when pop-up is dismissed
					dont_show: false
				}, args, true);
				conf = om.bf.make.message(owner, title, html, args);
				conf.$.toggleClass('om_confirm', true);
				// add in a close button to the bottom of the box
				conf._extend('bottom');
				om.bf.make.input.button(conf._box_bottom.$, 'close', {
					caption: om.get(args.caption, conf),
					'class': 'om_confirm_close',
					on_click: function (click_event) {
						// and fire the users's on_close event if present
						om.get(args.on_close, click_event, conf);
						// if we did prevent the default then rebind ourselves if default is disabled too
						if (click_event.isDefaultPrevented()) {
							conf._box_bottom.$.find('.om_confirm_close').one('click dblclick', arguments.callee);
						} else {
							// remove ourselves from the DOM
							conf._remove();
						}
					}
				});
				if (args.dont_show !== true) {
					conf._show();
				} else {
					conf._hide();
				}
				if (! conf.$.is(':hidden')) {
					// auto-focus the first input, if there is one
					conf.$.find('input,button').slice(0, 1).focus();
				}
				return conf;
			},

			/* Data/form query pop-up object. */
			query: function (owner, title, html, args) {
				var query;
				args = om.get_args({
					cancel_caption: 'Cancel',
					form_fields: {}, // form fields to include
					form_args: {}, // form arguments
					ok_caption: 'Ok',
					on_ok: undefined, // callback for when Ok/submit button clicked
					on_cancel: undefined // callback for cancel/close
				}, args, true);
				query = om.bf.make.message(owner, title, html, args);
				query.$.toggleClass('om_query', true);
				query._args = args;
				query._form = om.bf.make.form(
					query._box_middle.$,
					args.form_fields,
					args.form_args
				);
				query._form.$.bind('keydown', function (keydown_event) {
					if (keydown_event.keyCode === 27) {
						// escape pressed, so close
						keydown_event.preventDefault();
						keydown_event.stopPropagation();
						query._cancel_button._value.click();
					} else if (keydown_event.keyCode === 13) {
						// user pressed enter, activate the submit button!
						keydown_event.preventDefault();
						keydown_event.stopPropagation();
						query._ok_button._value.click();
					}
				});
				// add in a close button to the bottom of the box
				query._extend('bottom');
				query._ok_button = om.bf.make.input.button(query._box_bottom.$, 'ok', {
					caption: query._args.ok_caption,
					multi_click: false,
					'class': 'om_query_ok',
					on_click: function (click_event) {
						// and fire the users's ok event if present, passing any data
						om.get(
							query._args.on_ok,
							click_event,
							query._form._get_input(),
							query
						);
						if (! click_event.isDefaultPrevented()) {
							query._remove();
						}
					}
				});
				query._cancel_button = om.bf.make.input.button(query._box_bottom.$, 'cancel', {
					caption: query._args.cancel_caption,
					'class': 'om_query_cancel',
					multi_click: false,
					on_click: function (click_event) {
						// fire the users's cancel event if present
						om.get(query._args.on_cancel, click_event, query);
						if (! click_event.isDefaultPrevented()) {
							query._remove();
						}
					}
				});
				query.$.toggleClass('om_query', true);
				if (args.dont_show !== true) {
					query._show();
				}
				if (! query.$.is(':hidden')) {
					// auto-focus the first input, if there is one
					query.$.find('input,button').slice(0, 1).focus();
				}
				return query;
			},

			/* Deprecated; replaced by 'query'. */
			collect: function (owner, title, html, fields, args) {
				var collect;
				args = om.get_args({
					form_fields: fields,
					on_submit: undefined // old name in collect obj
				}, args, true);
				if (! args.on_ok) {
					args.on_ok = args.on_submit; // new name in query obj
				}
				collect = om.bf.make.query(owner, title, html, args);
				collect.$.toggleClass('om_collect', true);
				return collect;
			},

			/* Browser window object. */
			browser: function (owner, url, args) {
				var browser;
				args = om.get_args({
					icon: "/omega/images/diviner/globe.png",
					title: undefined
				}, args);
				if (url === undefined) {
					throw new Error("Invalid browser URL.");
				}
				if (! args.title) {
					args.title = url;
				}
				browser = om.bf.make.win(
					owner, {
						title: args.title,
						icon: args.icon,
						toolbar: ['title', 'min', 'close']
					}
				);
				browser.$.toggleClass('om_browser', true);
				browser._canvas.$.html('<iframe src="' + url + '">Error: iframes not supported by this browser.</iframe>');
				// QQ: IE still has issues with objects
				//browser._canvas.$.html('<object data="' + url + '">Error: failed to load browser to "' + url + '".</object>');
				return browser;
			}
		}
	);
	// alias it to a short name
	om.bf = om.BoxFactory;
}(om));

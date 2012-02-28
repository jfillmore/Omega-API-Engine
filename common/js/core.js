/* omega - web client
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
var om = {};

/* The 'om' object serves as the base point for all other libraries
(e.g. om.BoxFactory/om.bf, om.ColorFactory/om.cf).
It also comtains various useful, generic functions. */
(function (om) {
	/* Iterate through the object and collect up parameters, using the
	default value provided in "my_args" if the value is not present in "args".
	Can optionally also merge extra arguments in "args" into result.
	See docs below. */
	om.get_args = function (my_args, args, merge) {
		var arg;
		for (arg in args) {
			if (args.hasOwnProperty(arg) && args[arg] !== undefined) {
				if (arg in my_args || merge) {
					my_args[arg] = args[arg];
				}
			}
		}
		return my_args;
	};

	/* Create a documented function. See docs below. */
	om.doc = function(args) {
		var doc;
		doc = om.get_args({
			desc: undefined,
			desc_ext: undefined,
			obj: undefined,
			params: undefined,
			prop_name: '_doc'
		}, args);
		if (typeof(doc.obj) === 'function' || typeof(doc.obj) === 'object') {
			doc.obj[doc.prop_name] = doc;
		}
		return doc.obj;
	};

	/* Document what we've already created of ourself. */
	om = om.doc({
		desc: 'Omega JavaScript library object.',
		desc_ext: 'Root object for holding common JavaScript methods, as well as additional special-purpose libraries',
		obj: om
	});

	om.doc = om.doc({
		desc: 'Creates and returns a documented function or object.',
		desc_ext: 'For example: var foo = om.doc({desc: "foo bar", obj: function () {}); alert(foo._doc.desc); // "foo bar"',
		obj: om.doc,
		params: {
			args: {
				desc: 'List of arguments to populate documentation.',
				type: 'object',
				params: {
					desc: {
						desc: 'Concise description of what the function does and returns.',
						type: 'string'
					},
					desc_ext: {
						desc: 'Extended description of the function and/or its parameters.',
						type: 'string'
					},
					method: {
						desc: 'The function object to document.',
						type: 'function'
					},
					params: {
						desc: 'List of parameters accepted by the function. Keys in the list are the parameter names.',
						desc_ext: 'The format for the list value is an object with the keys: "default_val", "desc", "desc_ext", "type", and "params" (for when "type" == "object"). Valid types are "undefined", "null", "string", "number", "array", "object", "function", and "boolean". If the parameters are dynamic the parameter: "*" should be set. If the parameter contains "params" then they object keys may be documented using the same format as "params".',
						type: 'object'
					},
					prop_name: {
						default_val: '_doc',
						desc: "The function's doc object property name.",
						type: 'string'
					}
				}
			}
		}
	});

	om.get_args = om.doc({
		desc: 'Iterate through the object and collect up arguments.',
		desc_ext: 'The default value provided in "my_args" is used if the value is not present in "args".  Can optionally also merge extra arguments in "args" into result; otherwise arguments not present in "my_args" will be filtered out.',
		obj: om.get_args,
		params: {
			my_args: {
				desc: 'The default arguments (e.g. {foo: "bar"}).',
				type: 'object'
			},
			args: {
				desc: 'Collection of passed arguments (e.g. {foo: "barn", a: 1}).',
				type: 'object'
			},
			merge: {
				desc: 'If true then items in "args" not in "my_args" will be merged into the result.',
				default_val: false,
				type: 'boolean'
			}
		}
	});

	om.get = om.doc({
		desc: 'Returns first non-function argument, executing any functions using the remaining arguments as parameters. Makes it easy to provide a call-back function for argument values.',
		params: {
			'*': {
				desc: 'Each argument is parsed until a non-function argument is countered, allowing argument values to be the return value of functions for which you also supply the paramters.' 
			}
		},
		obj: function (obj, obj1, obj2, objN) {
			var type, params, i, objs;
			// call function if given, and use supplied args.
			// if args are a function, call them for the args.
			// passes 'obj' to functions
			type = typeof(obj);
			objs = [];
			if (type === 'function') {
				// if we have extra arguments to the right pass 'em as params
				if (arguments.length > 2) {
					for (i = 2; i < arguments.length; i++) {
						objs.push(arguments[i]);
					}
				} else {
					objs = [];
				}
				// if our next argument is also a function then call it too
				if (typeof(obj1) === 'function') {
					params = [obj1.apply(this, objs)];
				} else {
					params = [obj1];
				}
				for (i = 0; i < objs.length; i++) {
					params.push(objs[i]);
				}
				return obj.apply(this, params);
			} else {
				return obj;
			}
		}
	});

	om.subtract = om.doc({
		desc: 'Subtract two numbers while properly maintaining the best precision possible.',
		desc_ext:
			'e.g.\njs> om.subtract(3.33, 1.10999); // = 2.22001\n' +
			'js> 3.33 - 1.10999 // = 2.2200100000000003',
		params: {
			f1: {
				desc: 'Floating point number.',
				type: 'number'
			},
			f2: {
				desc: 'Amount to subtract from first number.',
				type: 'number'
			}
		},
		obj: function (f1, f2) {
			var sig_digs, d1, d2;
			// determine how many significant digits we have and maintain that precision
			d1 = String(f1).match(/\.[0-9]+$/);
			d2 = String(f2).match(/\.[0-9]+$/);
			if (d1) {
				d1 = d1[0].length - 1;
			} else {
				d1 = 0;
			}
			if (d2) {
				d2 = d2[0].length - 1;
			} else {
				d2 = 0;
			}
			sig_digs = Math.max(d1, d2);
			return om.round(f1 - f2, {decimal: sig_digs});
		}
	});

	om.ucfirst = om.doc({
		desc: 'Convert the first character of the string to upper case.',
		params: {
			str: {
				desc: 'String to convert first character of.',
				type: 'string'
			}
		},
		obj: function (str) {
			return str.substr(0, 1).toUpperCase() + str.substr(1, str.length - 1);
		}
	});

	om.lcfirst = om.doc({
		desc: 'Lower-case first letter of string.',
		params: {
			str: {
				desc: 'String to convert first character of.',
				type: 'string'
			}
		},
		obj: function (str) {
			return str.substr(0, 1).toLowerCase() + str.substr(1, str.length - 1);
		}
	});

	om.flatten = om.doc({
		desc: 'Strip a string of extra spacing and non alpha-numerical characters, and/or add gaps between capital letters.',
		desc_ext: "e.g. 'foo_fooBar' => 'foo foo bar'.",
		params: {
			str: {
				desc: 'The string to modify.',
				type: 'string'
			},
			add_cap_gap: {
				desc: 'If true then capital letters will be lower-cased and have a space added before.',
				desc_ext: 'e.g. "fooBarBarn" => "foo bar barn"',
				type: 'boolean',
				default_val: false
			},
			spaces: {
				desc: "If true then underscores will be replaced with spaces.",
				type: 'boolean',
				default_val: false
			}
		},
		obj: function (str, add_cap_gap, spaces) {
			// lowercase the first char
			str = str.substr(0, 1).toLowerCase() + str.substr(1);
			// add the cap gap if requested
			if (add_cap_gap === true) {
				str = str.replace(/([A-Z])/g, '_$1');
			}
			// condense spaces/underscores to a single underscore
			// and strip out anything else but alphanums and underscores
			str = str.toLowerCase().replace(/( |_)+/g, '_');
			str = str.replace(/[^a-z0-9_]+/g, '');
			if (spaces) {
				str = str.replace(/_/g, ' ');
			}
			return str;
		}
	});

	om.is_jquery = om.doc({
		desc: 'Returns whether the object is a vailid jQuery object.',
		params: {
			obj: {
				desc: 'Object to examine whether it is a jQuery object or not.',
				type: 'object'
			}
		},
		obj: function (obj) {
			return typeof(obj) === 'object' && obj.length !== undefined && obj.jquery !== undefined;
		}
	});

	om.is_numeric = om.doc({
		desc: 'Returns whether or not a string is numerical.',
		parms: {
			str: {
				desc: 'String to determine if the value is numerical or not.',
				type: 'undefined'
			}
		},
		obj: function (str) {
			return (! isNaN(parseFloat(str))) && isFinite(str);
		}
	});

	/* Same as "om.is_numeric", but negated for the pedantic. */
	om.isnt_numeric = om.doc({
		desc: 'Returns whether or not the string argument is numeric.',
		params: {
			str: 'String to determine if the value is numerical or not.',
			type: 'undefined'
		},
		obj: function (str) {
			return ! om.is_numeric(str);
		}
	});

	om.empty = om.doc({
		desc: 'Returns whether or not an object or array is empty.',
		params: {
			obj: {
				desc: 'Object to examine.',
				type: 'object'
			}
		},
		obj: function (obj) {
			var item;
			for (item in obj) {
				if (obj.hasOwnProperty(item)) {
					return false;
				}
			}
			return true;
		}
	});

	om.plural = om.doc({
		desc: 'Returns whether or not an object or array has at least two items.',
		params: {
			obj: {
				desc: 'Object to check for multiple properties of.',
				type: 'object'
			}
		},
		obj: function (obj) {
			var item, count = 0;
			for (item in obj) {
				if (obj.hasOwnProperty(item)) {
					count += 1;
					if (count > 1) {
						return true;
					}
				}
			}
			return false;
		}
	});

	om.link_event = om.doc({
		desc: 'Cause events from one object to automatically trigger on the other object.',
		params: {
		},
		obj: function (event_type, from_obj, to_obj) {
			from_obj.bind(event_type, function (e) {
				to_obj.trigger(event_type);
				// let the link be processed up the DOM from here too
				e.preventDefault();
				e.stopPropagation();
			});
		}
	});

	om.assemble = om.doc({
		desc: 'Returns a string of an assembled HTML element.',
		params: {
		},
		obj: function (type, attributes, inner_html, leave_open) {
			// check the type
			if (! type.match(/^[a-zA-Z]+/)) {
				throw new Error('Invalid object type: "' + type + '".');
			}
			var value,
				name,
				html = '<' + type;
			// add in any attributes
			for (name in attributes) {
				if (attributes.hasOwnProperty(name)) {
					value = '';
					if (jQuery.isArray(attributes[name])) {
						value = attributes[name].join(' ');
					} else  {
						// null or undefined? empty string!
						if (attributes[name] === null || attributes[name] === undefined) {
							value = '';
						} else if (typeof attributes[name] === 'object') {
							throw new Error("Unable to assemble object '" + name + "' into attributes.");
						} else {
							value = String(attributes[name]);
						}
					}
					// automatically escape any quotes (e.g. in an <input/>)
					html += ' ' + name + '="' + value.replace(/"/, '\\"') + '"';
				}
			}
			html += '>';
			if (inner_html !== undefined) {
				html += inner_html;
			}
			if (leave_open === undefined) {
				leave_open = false;
			}
			if (! leave_open) {
				html += '</' + type + '>';
			}
			return html;
		}
	});

	om.set_cookie = om.doc({
		desc: 'Set a cookie with the specified value (which will be JSON encoded) & TTL.',
		params: {
			name: {
				desc: "Cookie name.",
				type: 'string'
			},
			value: {
				desc: 'Cookie value.'
			},
			ttl: {
				desc: "Time-to-live. Optional.",
				type: 'number'
			}
		},
		obj: function (name, value, ttl) {
			if (value === undefined || value === null) {
				value = '';
			}
			var expiration,
				cookie = name + "=" + om.json.encode(value).replace(/; /g, '\\; \\ ');
			if (typeof ttl === 'number') {
				expiration = new Date();
				expiration.setTime(expiration.getTime() + (ttl * 1000));
				cookie += ";expires=" + expiration.toUTCString();
			} else if (ttl !== undefined) {
				throw new Error('Invalid TTL (' + typeof ttl + '): ' + String(ttl) + '.');
			}
			document.cookie = cookie;
		}
	});

	om.get_cookies = om.doc({
		desc: 'Get a list of the cookies as an object.',
		params: {
		},
		obj: function () {
			var cookies = {},
				dough,
				index,
				value,
				cookie_name,
				i;
			if (document.cookie === '') {
				return [];
			}
			dough = document.cookie.split('; ');
			for (i = 0; i < dough.length; i += 1) {
				// find where the cookie name ends
				index = dough[i].indexOf('=');
				// save the cookie name
				cookie_name = dough[i].substr(0, index); 
				// make sure we're not at the end of our cookie
				if (index + 1 >= dough[i].length) {
					// and if we are, we've got an empty value
					cookies[cookie_name] = '';
				} else {
					// otherwise read up to the end of the dough part, unescaping any escaped '; ' sequences
					cookies[cookie_name] = dough[i].substr(index + 1).replace(/\; \ /g, '; ');
				}
			}
			return cookies;
		}
	});

	om.get_cookie = om.doc({
		desc: 'Returns a cookie by name, optionally decoding it as JSON.',
		params: {
			name: {
				desc: 'The name of the cookie.',
				type: 'string'
			},
			decode: {
				desc: 'Whether or not to decode the value as JSON.',
				type: 'boolean',
				default_val: false
			}
		},
		obj: function (name, decode) {
			var cookies = om.get_cookies();
			if (name in cookies) {
				if (decode) {
					return om.json.decode(cookies[name]);
				} else {
					return cookies[name];
				}
			}
		}
	});

	/*
	// TODO
	om.delete_cookie = function (name) { };
	// TODO
	om.delete_cookies = function (re) { };
	*/

	om.find_cookies = om.doc({
		desc: 'Returns an array of cookies matching with the given RE obj.',
		params: {
			re: {
				desc: 'Regular expression object to perform matching with.',
				type: 'object'
			},
			decode: {
				desc: 'Whether or not to decode the values as JSON.',
				type: 'boolean',
				default_val: 'false'
			}
		},
		obj: function (re, decode) {
			var matches = [],
				cookies = om.get_cookies(),
				name;
			for (name in cookies) {
				if (cookies.hasOwnProperty(name)) {
					if (re.test(name)) {
						if (decode) {
							matches.push(om.json.decode(cookies[name]));
						} else {
							matches.push(cookies[name]);
						}
					}
				}
			}
			return matches;
		}
	});

	om.round = om.doc({
		desc: 'Round numbers to some arbitrary precision or interval.',
		params: {
		},
		obj: function (num, args) {
			var mod, int_half, multiplier, i, to_add;
			/* args = {
				interval, // round to nearest 4th (e.g 5.9 -> 4, 6.1 -> 8) (default: unset)
				decimal, // rount to 10^n decimal (default: 0)
				min_dec // pad the decimal with 0's to ensure min length, returns string
			}; */
			if (args === undefined) {
				args = {};
			}
			if (args.interval !== undefined && args.decimal !== undefined) {
				throw new Error("Unable to use both the 'interval' and 'decimal' options.");
			}
			// do our rounding
			if (args.interval) {
				// round to the nearest interval
				mod = Math.abs(num) % args.interval;
				if (args.floor) {
					if (num > 0) {
						num -= mod;
					} else {
						num -= args.interval - mod;
					}
				} else if (args.ceiling && mod !== 0) {
					if (num > 0) {
						num += args.interval - mod;
					} else {
						num += mod;
					}
				} else {
					int_half = args.interval / 2;
					if (mod >= int_half) {
						if (num > 0) {
							num += args.interval - mod;
						} else {
							num -= args.interval - mod;
						}
					} else {
						if (num > 0 || args.ceiling) {
							num -= mod;
						} else {
							num += mod;
						}
					}
				}
			} else {
				// round, after adjusting to catch a decimal point
				multiplier = Math.pow(10, args.decimal ? args.decimal : 0);
				if (args.decimal) {
					num *= multiplier;
				}
				if (args.ceiling && num % 1.0) {
					// force it to round up
					num += 0.5;
				} else if (args.floor && num % 1.0) {
					// force it to round down
					num -= 0.5;
				}
				num = Math.round(num);
				if (args.decimal) {
					num /= multiplier;
				}
				if (args.min_dec !== undefined) {
					num = String(num);
					to_add = num.match(/\.(.*)$/);
					if (to_add !== null) {
						to_add = args.min_dec - to_add[1].length;
						for (i = 0; i < to_add; i++) {
							num += '0';
						}
					}
				}
			}
			return num;
		}
	});

	om.Error = om.doc({
		desc: 'Generic error handling.',
		params: {
		},
		obj: function (message, args) {
			// failed? throw a polite error to the user
			var error;
			args = om.get_args({
				bt: undefined, // backtrace from an error, if given
				modal: true,
				on_close: undefined, // callback for when error is closed
				retry: undefined, // if defined, a retry button will be given to re-run the given function & args
				title: 'Error',
				target: $('body')
			}, args);
			error = om.bf.make.confirm(
				args.target,
				args.title,
				message,
				{
					modal: args.modal,
					on_close: args.on_close
				}
			);
			if (args.bt !== undefined) {
				error.details = error._box_middle._add_box('om_error_backtrace');
				error.details.title = error.details._add_box('om_error_title');
				error.details.title.$.html('Details');
				error.details.bt = error.details._add_box('om_error_bt');
				error.details.bt.$.html(om.vis.obj2html(args.bt));
			}
			error.message = message;
			error._args = args;

			error._add_retry = function (func_args) {
				error._box_bottom._add_input(
					'button',
					'retry',
					{
						caption: 'Re-try',
						multi_click: true,
						on_click: function (button_click) {
							// dismiss ourselves and retry
							error._remove();
							if (func_args !== undefined) {
								func_args.callee.apply(this, func_args);
							}
						}
					}
				);
			};
			if (args.retry !== undefined) {
				error._add_retry(args.retry);
			}
			error._raise();
			return error;
		}
	});
	om.error = om.Error;

	return om;
}(om));

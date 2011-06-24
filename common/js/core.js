/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
var om = {};

(function (om) {
	// misc functions
	om.subtract = function (f1, f2) {
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
	};

	om.ucfirst = function (str) {
		return str.substr(0, 1).toUpperCase() + str.substr(1, str.length - 1);
	};

	om.lcfirst = function (str) {
		return str.substr(0, 1).toLowerCase() + str.substr(1, str.length - 1);
	};

	om.flatten = function (str, add_cap_gap) {
		// lowercase the first char
		str = str.substr(0, 1).toLowerCase() + str.substr(1);
		// add the cap gap if requested
		if (add_cap_gap === true) {
			str = str.replace(/([A-Z])/g, '_$1');
		}
		// condense spaces/underscores to a single underscore
		// and strip out anything else but alphanums and underscores
		return str.toLowerCase().replace(/( |_)+/g, '_').replace(/[^a-z0-9_]+/g, '');
	};

	om.is_jquery = function (obj) {
		return typeof(obj) === 'object' && obj.length !== undefined && obj.jquery !== undefined;
	};

	om.is_numeric = function (str) {
		return (! isNaN(parseFloat(str))) && isFinite(str);
	};

	om.isnt_numeric = function (str) {
		return ! om.is_numeric(str);
	};

	om.plural = function (obj) {
		var item;
		for (item in obj) {
			if (obj.hasOwnProperty(item)) {
				return true;
			}
		}
		return false;
	};

	// event magic
	om.link_event = function (event_type, from_obj, to_obj) {
		from_obj.bind(event_type, function (e) {
			to_obj.trigger(event_type, e);
			// let the link be processed up the DOM from here too
		});
	};

	om.reflect_event = function (event_type, from_obj, to_obj) {
		from_obj.bind(event_type, function (e) {
			to_obj.trigger(event_type, e);
			// don't let the event bubble back up in the DOM here
			event_type.preventDefault();
			event_type.stopPropagation();
		});
	};

	// a method to assemble HTML nodes dynamically
	om.assemble = function (type, attributes, inner_html, leave_open) {
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
	};

	// cookie fun
	om.set_cookie = function (name, value, ttl) {
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
	};

	om.get_cookies = function () {
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
	};

	om.get_cookie = function (name, decode) {
		var cookies = om.get_cookies();
		if (name in cookies) {
			if (decode) {
				return om.json.decode(cookies[name]);
			} else {
				return cookies[name];
			}
		}
	};

	om.delete_cookie = function (name) {
		// TODO
	};

	om.delete_cookies = function (re) {
		// TODO
	};

	om.find_cookies = function (re, decode) {
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
	};

	om.round = function (num, args) {
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
	};

	// error handling
	om.Error = function (message, args) {
		// failed? throw a polite error to the user
		var error;
		if (args === undefined) {
			args = {};
		}
		if (args.modal === undefined) {
			args.modal = true;
		}
		if (args.title === undefined) {
			args.title = 'Error';
		}
		if (args.target === undefined) {
			args.target = $('body');
		}
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
	};
	om.error = om.Error;

	return om;
}(om));

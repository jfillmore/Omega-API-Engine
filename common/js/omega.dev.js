/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
var om = {};

(function (om) {
	// misc functions
	om.get = function (val, args, obj1, obj2, objN) {
		// call function if given, and use supplied args.
		// if args are a function, call them for the args.
		// passes 'obj' to functions
		var type, params, i, objs;
		type = typeof(val);
		objs = [];
		if (type === 'function') {
			if (arguments.length > 2) {
				for (i = 2; i < arguments.length; i++) {
					objs.push(arguments[i]);
				}
			} else {
				objs = [];
			}
			if (typeof(args) === 'function') {
				params = [args.apply(this, objs)];
			} else {
				params = [args];
			}
			for (i = 0; i < objs.length; i++) {
				params.push(objs[i]);
			}
			return val.apply(this, params);
		} else {
			return val;
		}
	};
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
/**
sprintf() for JavaScript 0.7-beta1
http://www.diveintojavascript.com/projects/javascript-sprintf

Copyright (c) Alexandru Marasteanu <alexaholic [at) gmail (dot] com>
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of sprintf() for JavaScript nor the
      names of its contributors may be used to endorse or promote products
      derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL Alexandru Marasteanu BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


Changelog:
2011.02.18 - 0.7-beta1-om
  - integrated into omega!

2010.09.06 - 0.7-beta1
  - features: vsprintf, support for named placeholders
  - enhancements: format cache, reduced global namespace pollution

2010.05.22 - 0.6:
 - reverted to 0.4 and fixed the bug regarding the sign of the number 0
 Note:
 Thanks to Raphael Pigulla <raph (at] n3rd [dot) org> (http://www.n3rd.org/)
 who warned me about a bug in 0.5, I discovered that the last update was
 a regress. I appologize for that.

2010.05.09 - 0.5:
 - bug fix: 0 is now preceeded with a + sign
 - bug fix: the sign was not at the right position on padded results (Kamal Abdali)
 - switched from GPL to BSD license

2007.10.21 - 0.4:
 - unit test and patch (David Baird)

2007.09.17 - 0.3:
 - bug fix: no longer throws exception on empty paramenters (Hans Pufal)

2007.09.11 - 0.2:
 - feature: added argument swapping

2007.04.03 - 0.1:
 - initial release
**/

(function (om) {
	om.sprintf = (function () {
		var str_format;

		function get_type(variable) {
			return Object.prototype.toString.call(variable).slice(8, -1).toLowerCase();
		}
		function str_repeat(input, multiplier) {
			for (var output = []; multiplier > 0; output[--multiplier] = input) {/* do nothing */}
			return output.join('');
		}

		str_format = function() {
			if (!str_format.cache.hasOwnProperty(arguments[0])) {
				str_format.cache[arguments[0]] = str_format.parse(arguments[0]);
			}
			return str_format.format.call(null, str_format.cache[arguments[0]], arguments);
		};

		str_format.format = function(parse_tree, argv) {
			var cursor = 1, tree_length = parse_tree.length, node_type = '', arg, output = [], i, k, match, pad, pad_character, pad_length;
			for (i = 0; i < tree_length; i++) {
				node_type = get_type(parse_tree[i]);
				if (node_type === 'string') {
					output.push(parse_tree[i]);
				} else if (node_type === 'array') {
					match = parse_tree[i]; // convenience purposes only
					if (match[2]) { // keyword argument
						arg = argv[cursor];
						for (k = 0; k < match[2].length; k++) {
							if (!arg.hasOwnProperty(match[2][k])) {
								throw(om.sprintf('[sprintf] property "%s" does not exist', match[2][k]));
							}
							arg = arg[match[2][k]];
						}
					} else if (match[1]) { // positional argument (explicit)
						arg = argv[match[1]];
					} else { // positional argument (implicit)
						arg = argv[cursor++];
					}

					if (/[^s]/.test(match[8]) && (get_type(arg) != 'number')) {
						throw(om.sprintf('[sprintf] expecting number but found %s', get_type(arg)));
					}
					switch (match[8]) {
						case 'b': arg = arg.toString(2); break;
						case 'c': arg = String.fromCharCode(arg); break;
						case 'd': arg = parseInt(arg, 10); break;
						case 'e': arg = match[7] ? arg.toExponential(match[7]) : arg.toExponential(); break;
						case 'f': arg = match[7] ? parseFloat(arg).toFixed(match[7]) : parseFloat(arg); break;
						case 'o': arg = arg.toString(8); break;
						case 's': arg = ((arg = String(arg)) && match[7] ? arg.substring(0, match[7]) : arg); break;
						case 'u': arg = Math.abs(arg); break;
						case 'x': arg = arg.toString(16); break;
						case 'X': arg = arg.toString(16).toUpperCase(); break;
					}
					arg = (/[def]/.test(match[8]) && match[3] && arg >= 0 ? '+'+ arg : arg);
					pad_character = match[4] ? match[4] == '0' ? '0' : match[4].charAt(1) : ' ';
					pad_length = match[6] - String(arg).length;
					pad = match[6] ? str_repeat(pad_character, pad_length) : '';
					output.push(match[5] ? arg + pad : pad + arg);
				}
			}
			return output.join('');
		};

		str_format.cache = {};

		str_format.parse = function(fmt) {
			var _fmt = fmt, match = [], parse_tree = [], arg_names = 0;
			while (_fmt) {
				if ((match = /^[^\x25]+/.exec(_fmt)) !== null) {
					parse_tree.push(match[0]);
				} else if ((match = /^\x25{2}/.exec(_fmt)) !== null) {
					parse_tree.push('%');
				} else if ((match = /^\x25(?:([1-9]\d*)\$|\(([^\)]+)\))?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(_fmt)) !== null) {
					if (match[2]) {
						arg_names |= 1;
						var field_list = [], replacement_field = match[2], field_match = [];
						if ((field_match = /^([a-z_][a-z_\d]*)/i.exec(replacement_field)) !== null) {
							field_list.push(field_match[1]);
							while ((replacement_field = replacement_field.substring(field_match[0].length)) !== '') {
								if ((field_match = /^\.([a-z_][a-z_\d]*)/i.exec(replacement_field)) !== null) {
									field_list.push(field_match[1]);
								} else if ((field_match = /^\[(\d+)\]/.exec(replacement_field)) !== null) {
									field_list.push(field_match[1]);
								} else {
									throw('[sprintf] huh?');
								}
							}
						} else {
							throw('[sprintf] huh?');
						}
						match[2] = field_list;
					} else {
						arg_names |= 2;
					}
					if (arg_names === 3) {
						throw('[sprintf] mixing positional and named placeholders is not (yet) supported');
					}
					parse_tree.push(match);
				} else {
					throw('[sprintf] huh?');
				}
				_fmt = _fmt.substring(match[0].length);
			}
			return parse_tree;
		};
		return str_format;
	})();

	om.vsprintf = function(fmt, argv) {
		argv.unshift(fmt);
		return om.sprintf.apply(null, argv);
	};
}(om));
/*
    http://www.JSON.org/json2.js
    2009-09-29


	-- 2009-12-31: re-arranged by JFillmore
	-- 2010-04-25: added JSON auto-complete function


    Public Domain.

    NO WARRANTY EXPRESSED OR IMPLIED. USE AT YOUR OWN RISK.

    See http://www.JSON.org/js.html


    This code should be minified before deployment.
    See http://javascript.crockford.com/jsmin.html

    USE YOUR OWN COPY. IT IS EXTREMELY UNWISE TO LOAD CODE FROM SERVERS YOU DO
    NOT CONTROL.


    This file creates a global JSON object containing two methods: 
    and parse.

        om.JSON.encode(value, replacer, space)
            value       any JavaScript value, usually an object or array.

            replacer    an optional parameter that determines how object
                        values are stringified for objects. It can be a
                        function or an array of strings.

            space       an optional parameter that specifies the indentation
                        of nested structures. If it is omitted, the text will
                        be packed without extra whitespace. If it is a number,
                        it will specify the number of spaces to indent at each
                        level. If it is a string (such as '\t' or '&nbsp;'),
                        it contains the characters used to indent at each level.

            This method produces a JSON text from a JavaScript value.

            When an object value is found, if the object contains a decode
            method, its decode method will be called and the result will be
            stringified. A decode method does not serialize: it returns the
            value represented by the name/value pair that should be serialized,
            or undefined if nothing should be serialized. The decode method
            will be passed the key associated with the value, and this will be
            bound to the value

            For example, this would serialize Dates as ISO strings.

                Date.prototype.decode = function (key) {
                    function f(n) {
                        // Format integers to have at least two digits.
                        return n < 10 ? '0' + n : n;
                    }

                    return this.getUTCFullYear()   + '-' +
                         f(this.getUTCMonth() + 1) + '-' +
                         f(this.getUTCDate())      + 'T' +
                         f(this.getUTCHours())     + ':' +
                         f(this.getUTCMinutes())   + ':' +
                         f(this.getUTCSeconds())   + 'Z';
                };

            You can provide an optional replacer method. It will be passed the
            key and value of each member, with this bound to the containing
            object. The value that is returned from your method will be
            serialized. If your method returns undefined, then the member will
            be excluded from the serialization.

            If the replacer parameter is an array of strings, then it will be
            used to select the members to be serialized. It filters the results
            such that only members with keys listed in the replacer array are
            stringified.

            Values that do not have JSON representations, such as undefined or
            functions, will not be serialized. Such values in objects will be
            dropped; in arrays they will be replaced with null. You can use
            a replacer function to replace those with JSON values.
            om.JSON.encode(undefined) returns undefined.

            The optional space parameter produces a stringification of the
            value that is filled with line breaks and indentation to make it
            easier to read.

            If the space parameter is a non-empty string, then that string will
            be used for indentation. If the space parameter is a number, then
            the indentation will be that many spaces.

            Example:

            text = om.JSON.encode(['e', {pluribus: 'unum'}]);
            // text is '["e",{"pluribus":"unum"}]'


            text = om.JSON.encode(['e', {pluribus: 'unum'}], null, '\t');
            // text is '[\n\t"e",\n\t{\n\t\t"pluribus": "unum"\n\t}\n]'

            text = om.JSON.encode([new Date()], function (key, value) {
                return this[key] instanceof Date ?
                    'Date(' + this[key] + ')' : value;
            });
            // text is '["Date(---current time---)"]'


        om.JSON.decode(text, reviver)
            This method parses a JSON text to produce an object or array.
            It can throw a SyntaxError exception.

            The optional reviver parameter is a function that can filter and
            transform the results. It receives each of the keys and values,
            and its return value is used instead of the original value.
            If it returns what it received, then the structure is not modified.
            If it returns undefined then the member is deleted.

            Example:

            // Parse the text. Values that look like ISO date strings will
            // be converted to Date objects.

            myData = om.JSON.decode(text, function (key, value) {
                var a;
                if (typeof value === 'string') {
                    a =
/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2}(?:\.\d*)?)Z$/.exec(value);
                    if (a) {
                        return new Date(Date.UTC(+a[1], +a[2] - 1, +a[3], +a[4],
                            +a[5], +a[6]));
                    }
                }
                return value;
            });

            myData = om.JSON.decode('["Date(09/09/2001)"]', function (key, value) {
                var d;
                if (typeof value === 'string' &&
                        value.slice(0, 5) === 'Date(' &&
                        value.slice(-1) === ')') {
                    d = new Date(value.slice(5, -1));
                    if (d) {
                        return d;
                    }
                }
                return value;
            });


    This is a reference implementation. You are free to copy, modify, or
    redistribute.
*/

/*jslint evil: true, strict: false */

/*members "", "\b", "\t", "\n", "\f", "\r", "\"", JSON, "\\", apply,
    call, charCodeAt, getUTCDate, getUTCFullYear, getUTCHours,
    getUTCMinutes, getUTCMonth, getUTCSeconds, hasOwnProperty, join,
    lastIndex, length, parse, prototype, push, replace, slice, encode,
    test, decode, toString, valueOf
*/


// Create a JSON object only if one does not already exist. We create the
// methods in a closure to avoid creating global variables.

(function(om) {

om.JSON = {};

om.JSON.auto_complete = function(json, encode) {
	// trace through the JSON and track the depth so we can auto-complete it
	var i,
		queue = [],
		chr,
		next_expected = null;
	for (i = 0; i < json.length; i++) {
		chr = json[i];
		if (next_expected !== null && chr !== next_expected) {
			continue;
		}
		if (chr === '\\') {
			// escape char? skip the next char and go on
			continue;
		}
		if (chr === '{') {
			queue.push(chr);
			next_expected = null;
		} else if (chr === '[') {
			queue.push(chr);
			next_expected = null;
		} else if (chr === '"') {
			if (queue[queue.length-1] === '"') {
				// matched what we wanted? excellent-- mark it off
				if (next_expected === chr) {
					next_expected = null;
				}
				queue.pop();
			} else {
				queue.push(chr);
				next_expected = chr;
			}
		} else if (chr === '}') {
			if (queue[queue.length-1] === '{') {
				queue.pop();
			} else {
				throw new Error("JSON auto-complete parse error on character '" + chr + "' (#" + i + ").");
			}
		} else if (chr === ']') {
			if (queue[queue.length-1] === '[') {
				queue.pop();
			} else {
				throw new Error("JSON auto-complete parse error on character '" + chr + "' (#" + i + ").");
			}
		}
	}
	// take anything left in the queue and close it off
	for (i = queue.length - 1; i >= 0; i--) {
		chr = queue[i];
		if (chr === '{') {
			/*
			// look back and make sure we've got a key and value
			var back_chr;
			for (var j = json.length - 1; j >= 0; j--) {
				back_chr = json[j];
				// see if we find a ':' or a '{' first to see whats next
				if (back_chr == '"') {
					// complete the key value if needed
					// is the next sig char before this also a '"'? if so, we've got the value
					var have_key = false;
					for (var k = j - 1; k > 0; k--) {
						if (json[i] == '"') {
							have_key = true;
							break;
						} else if (json[i] == '[') {
							break;
						} else if (json[i] == '{') {
							break;
						}
					}
					if (! have_key) {
						json += ': null';
					}
				} else if (back_chr == ':') {
					// make sure the key is complete
					var fwd_char;
					var key_present = false;
					// end of the line? we know we need a key
					if (j == json.length - 1) {
						key_present = false;
					} else {
						for (var k = j + 1; k < json.length; k++) {
							fwd_char = json[k];
							if (fwd_char != ' ') {
								key_present = true;
								j = -1;
								break;
							}
						}
					}
					if (! key_present) {
						json += 'null';
						break;
					}
				}
			}
			*/
			json += '}';
		} else if (chr === '[') {
			json += ']';
		} else if (chr === '"') {
			json += '"';
		} else {
			throw new Error("Unrecognized token in JSON auto-complete: '" + chr + "'.");
		}
	}
	if (encode === true) {
		return om.JSON.encode(json);
	} else {
		return json;
	}
};

function f(n) {
	// Format integers to have at least two digits.
	return n < 10 ? '0' + n : n;
}

if (typeof Date.prototype.decode !== 'function') {

	Date.prototype.decode = function (key) {

		return isFinite(this.valueOf()) ?
			   this.getUTCFullYear()   + '-' +
			 f(this.getUTCMonth() + 1) + '-' +
			 f(this.getUTCDate())      + 'T' +
			 f(this.getUTCHours())     + ':' +
			 f(this.getUTCMinutes())   + ':' +
			 f(this.getUTCSeconds())   + 'Z' : null;
	};

	String.prototype.decode =
	Number.prototype.decode =
	Boolean.prototype.decode = function (key) {
		return this.valueOf();
	};
}

var cx = /[\u0000\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,
	escapable = /[\\\"\x00-\x1f\x7f-\x9f\u00ad\u0600-\u0604\u070f\u17b4\u17b5\u200c-\u200f\u2028-\u202f\u2060-\u206f\ufeff\ufff0-\uffff]/g,
	gap,
	indent,
	meta = {    // table of character substitutions
		'\b': '\\b',
		'\t': '\\t',
		'\n': '\\n',
		'\f': '\\f',
		'\r': '\\r',
		'"' : '\\"',
		'\\': '\\\\'
	},
	rep;


function quote(string) {

// If the string contains no control characters, no quote characters, and no
// backslash characters, then we can safely slap some quotes around it.
// Otherwise we must also replace the offending characters with safe escape
// sequences.

	escapable.lastIndex = 0;
	return escapable.test(string) ?
		'"' + string.replace(escapable, function (a) {
			var c = meta[a];
			return typeof c === 'string' ? c :
				'\\u' + ('0000' + a.charCodeAt(0).toString(16)).slice(-4);
		}) + '"' :
		'"' + string + '"';
}


function str(key, holder) {

// Produce a string from holder[key].

	var i,          // The loop counter.
		k,          // The member key.
		v,          // The member value.
		length,
		mind = gap,
		partial,
		value = holder[key];

// If the value has a decode method, call it to obtain a replacement value.

	if (value && typeof value === 'object' &&
			typeof value.decode === 'function') {
		value = value.decode(key);
	}

// If we were called with a replacer function, then call the replacer to
// obtain a replacement value.

	if (typeof rep === 'function') {
		value = rep.call(holder, key, value);
	}

// What happens next depends on the value's type.

	switch (typeof value) {
	case 'string':
		return quote(value);

	case 'number':

// JSON numbers must be finite. Encode non-finite numbers as null.

		return isFinite(value) ? String(value) : 'null';

	case 'boolean':
	case 'null':

// If the value is a boolean or null, convert it to a string. Note:
// typeof null does not produce 'null'. The case is included here in
// the remote chance that this gets fixed someday.

		return String(value);

// If the type is 'object', we might be dealing with an object or an array or
// null.

	case 'object':

// Due to a specification blunder in ECMAScript, typeof null is 'object',
// so watch out for that case.

		if (!value) {
			return 'null';
		}

// Make an array to hold the partial results of encode this object value.

		gap += indent;
		partial = [];

// Is the value an array?

		if (Object.prototype.toString.apply(value) === '[object Array]') {

// The value is an array. Stringify every element. Use null as a placeholder
// for non-JSON values.

			length = value.length;
			for (i = 0; i < length; i += 1) {
				partial[i] = str(i, value) || 'null';
			}

// Join all of the elements together, separated with commas, and wrap them in
// brackets.

			v = partial.length === 0 ? '[]' :
				gap ? '[\n' + gap +
						partial.join(',\n' + gap) + '\n' +
							mind + ']' :
					  '[' + partial.join(',') + ']';
			gap = mind;
			return v;
		}

// If the replacer is an array, use it to select the members to be stringified.

		if (rep && typeof rep === 'object') {
			length = rep.length;
			for (i = 0; i < length; i += 1) {
				k = rep[i];
				if (typeof k === 'string') {
					v = str(k, value);
					if (v) {
						partial.push(quote(k) + (gap ? ': ' : ':') + v);
					}
				}
			}
		} else {

// Otherwise, iterate through all of the keys in the object.

			for (k in value) {
				if (Object.hasOwnProperty.call(value, k)) {
					v = str(k, value);
					if (v) {
						partial.push(quote(k) + (gap ? ': ' : ':') + v);
					}
				}
			}
		}

// Join all of the member texts together, separated with commas,
// and wrap them in braces.

		v = partial.length === 0 ? '{}' :
			gap ? '{\n' + gap + partial.join(',\n' + gap) + '\n' +
					mind + '}' : '{' + partial.join(',') + '}';
		gap = mind;
		return v;
	}
}

// If the JSON object does not yet have a encode method, give it one.

if (typeof om.JSON.encode !== 'function') {
	om.JSON.encode = function (value, replacer, space) {

// The encode method takes a value and an optional replacer, and an optional
// space parameter, and returns a JSON text. The replacer can be a function
// that can replace values, or an array of strings that will select the keys.
// A default replacer method can be provided. Use of the space parameter can
// produce text that is more easily readable.

		var i;
		gap = '';
		indent = '';

// If the space parameter is a number, make an indent string containing that
// many spaces.

		if (typeof space === 'number') {
			for (i = 0; i < space; i += 1) {
				indent += ' ';
			}

// If the space parameter is a string, it will be used as the indent string.

		} else if (typeof space === 'string') {
			indent = space;
		}

// If there is a replacer, it must be a function or an array.
// Otherwise, throw an error.

		rep = replacer;
		if (replacer && typeof replacer !== 'function' &&
				(typeof replacer !== 'object' ||
				 typeof replacer.length !== 'number')) {
			throw new Error('om.JSON.encode');
		}

// Make a fake root object containing our value under the key of ''.
// Return the result of encode the value.

		return str('', {'': value});
	};
}


// If the JSON object does not yet have a parse method, give it one.

if (typeof om.JSON.decode !== 'function') {
	om.JSON.decode = function (text, reviver) {

// The parse method takes a text and an optional reviver function, and returns
// a JavaScript value if the text is a valid JSON text.

		var j;

		function walk(holder, key) {

// The walk method is used to recursively walk the resulting structure so
// that modifications can be made.

			var k, v, value = holder[key];
			if (value && typeof value === 'object') {
				for (k in value) {
					if (Object.hasOwnProperty.call(value, k)) {
						v = walk(value, k);
						if (v !== undefined) {
							value[k] = v;
						} else {
							delete value[k];
						}
					}
				}
			}
			return reviver.call(holder, key, value);
		}


// Parsing happens in four stages. In the first stage, we replace certain
// Unicode characters with escape sequences. JavaScript handles many characters
// incorrectly, either silently deleting them, or treating them as line endings.

		cx.lastIndex = 0;
		if (cx.test(text)) {
			text = text.replace(cx, function (a) {
				return '\\u' +
					('0000' + a.charCodeAt(0).toString(16)).slice(-4);
			});
		}

// In the second stage, we run the text against regular expressions that look
// for non-JSON patterns. We are especially concerned with '()' and 'new'
// because they can cause invocation, and '=' because it can cause mutation.
// But just to be safe, we want to reject all unexpected forms.

// We split the second stage into 4 regexp operations in order to work around
// crippling inefficiencies in IE's and Safari's regexp engines. First we
// replace the JSON backslash pairs with '@' (a non-JSON character). Second, we
// replace all simple value tokens with ']' characters. Third, we delete all
// open brackets that follow a colon or comma or that begin the text. Finally,
// we look to see that the remaining characters are only whitespace or ']' or
// ',' or ':' or '{' or '}'. If that is so, then the text is safe for eval.

		if (/^[\],:{}\s]*$/.
test(text.replace(/\\(?:["\\\/bfnrt]|u[0-9a-fA-F]{4})/g, '@').
replace(/"[^"\\\n\r]*"|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?/g, ']').
replace(/(?:^|:|,)(?:\s*\[)+/g, ''))) {

// In the third stage we use the eval function to compile the text into a
// JavaScript structure. The '{' operator is subject to a syntactic ambiguity
// in JavaScript: it can begin a block or an object literal. We wrap the text
// in parens to eliminate the ambiguity.

			j = eval('(' + text + ')');

// In the optional fourth stage, we recursively walk the new structure, passing
// each name/value pair to a reviver function for possible transformation.

			return typeof reviver === 'function' ?
				walk({'': j}, '') : j;
		}

// If the text is not JSON parseable, then a SyntaxError is thrown.

		throw new SyntaxError('om.JSON.decode');
	};
}

// create an alias
om.json = om.JSON;

}(om));
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
		box: function (jquery_obj, args) {
			var box, type, part_type, i, arg;
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
			if (args === undefined) {
				args = {};
			}
			// create the box, starting with our jquery reference
			box = {$: jquery_obj};
			box.$.toggleClass('om_box', true);

			// and add in our box logic
			box._remove = function () {
				var top_sibling;
				// TODO: make this cleanup dynamic based on imbuements
				if (box.$.is('.om_box_free')) {
					top_sibling = box._get_top_sibling();
					if (top_sibling) {
						box._focus_out();
						top_sibling.triggerHandler('focusin.om');
					}
				}
				box.$.remove();
				delete(box.$);
			};

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

			box._add_input = function (type, name, args) {
				if (type in om.bf.make.input) {
					return om.bf.make.input[type](box.$, name, args);
				} else {
					throw new Error("Invalid input type: '" + type + "'.");
				}
			};

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

			// allow the box to grow in any direction
			box._extend = function (direction, name, args) {
				var box_part, children;
				if (args === undefined) {
					args = {};
				}
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
				if (args.wrap !== undefined) {
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

			// let box parts shift directions, possibly overwriting the contents of another box
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

				box._dodge_cursor = function (x, y, margin) {
					return; // TODO
					/*
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
					*/
				};
					
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
			menu: function (owner, options, args) {
				var menu, option, option_args;
				/* options format:
				option = {
					name: {option_args},
					...
				} */
				/* args format:
				args = {
					name: '', // added as a class
					options_inline: true, // defaults to true if options_orient is 'top' or 'bottom' 
					options_orient: null, // whether or not to orient menu options towards a particular box position
					multi_select: false,
					peer_group: null, // a jQuery ref to a DOM object to find menu option peers in (e.g. for nested single-select menus)
					dont_show: false, // whether or not to show the menu by default
					equal_option_widths: false, // make all options as wide as widest
					class: 'foo',
					classes: ['foo', 'bar']
				} */
				if (! om.is_jquery(owner)) {
					throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
				}
				if (args === undefined) {
					args = {};
				}
				if (args.multi_select === undefined) {
					args.multi_select = false;
				}
				if (args.dont_show === undefined) {
					args.dont_show = false;
				}
				if (args.equal_option_widths === undefined) {
					args.equal_option_widths = false;
				}
				if (args.classes === undefined) {
					args.classes = [];
				}
				args.classes.push('om_menu');
				if ('class' in args) {
					args.classes.push(args['class']);
				}
				// create the menu
				menu = om.bf.make.box(owner, {
					dont_show: true,
					classes: args.classes,
					insert: args.insert
				});
				menu._args = args;
				if (args.name !== undefined) {
					menu.$.toggleClass(args.name, true);
				}
				if (args.options_orient === undefined) {
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
				if (args.dont_show !== true) {
					menu.$.show();
					if (args.equal_option_widths) {
						menu._equalize_option_widths();
					}
				}
				return menu;
			},
			/* automatically generates a form with the most intelligent objects available */
			form: function (owner, fields, args) {
				var form, classes, name, field;
				/* args = {
						break_type: null, // null, 'column', 'tab', 'page'
						breaker_args: { // arguments to use when creating break manager
							options_orient: 'top',
							options_inline: true,
							equalize_tab_widths: false,
							on_tab_change: function () {}
						},
				//      auto_break_length: null, // automatically insert a break after every X characters
				// };
				*/
				// fields = {
				//	cost: {
				//		type: 'text',
				//		args: {
				//			default_val: '1',
				//			caption: 'Product price:',
				//			caption_orient: left,
				//			...
				//		},
				//  },
				//	name: {
				//		type: 'input type',
				//		args: {
				//			'arg_name': 'arg_value'
				//		}
				//	};
				// };
				// ... */
				if (! om.is_jquery(owner)) {
					throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
				}
				if (args === undefined) {
					args = {};
				}
				classes = ['om_form'];
				if (args.classes !== undefined) {
					if (jQuery.isArray(args.classes)) {
						classes = classes.concat(args.classes);
					} else if (typeof args.classes === 'string') {
						classes = classes.concat(args.classes.split(' '));
					} else {
						throw new Error("Unrecognized type for 'classes' argument: '" + typeof args.classes + "'.");
					}
				}
				if (args['class'] !== undefined) { // IE sucks
					classes.push(args['class']);
				}
				if (args.breaker_args === undefined) {
					args.breaker_args = {};
				}
				if (args.auto_break_length !== undefined) {
					if (! args.auto_break_length > 0) {
						throw new Error("Form auto break length must be greater than 0.");
					}
				} else {
					args.auto_break_length = null;
				}
				form = om.bf.make.box(owner, {
					dont_show: true,
					'classes': classes,
					insert: args.insert
				});
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
			/* common form objects with a bit more intelligence than the average object */
			input: {
				// hint the input with a predefined value, which changes to the default value on focus (e.g. to give instructions that auto-clear on focus)
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
				obj: function (owner, args) {
					var obj;
					/* args = {
						caption_orient: top, // where to put the input object caption
						caption: '', // the label for the input object
						link_caption: true, // whether or not to link DOM events to thecaption to actual input object
						validate: function () {}, // called to verify input contents when they change, using tooltips to communicate any errors-- returns 'true' is good, otherwise return a string with the error message
						tooltip: '', // a tooltip to show on mouse-over
						default_val: '', // the default value of the input, called through _val() to be object specific
						on_change: function (change_event, obj) {} // what to do when the value changes
						on_click: function (click_event, obj) {} // what to do when the input is clicked
					}; */
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (args === undefined) {
						args = {};
					}
					if (args.caption_orient === undefined) {
						args.caption_orient = 'top';
					}
					if (args.on_change === undefined) {
						args.on_change = null;
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					args.classes.push('om_input');
					if ('class' in args) {
						args.classes.push(args['class']);
					}
					obj = om.bf.make.box(owner, { 
						'classes': args.classes,
						insert: args.insert
					});
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
							response = obj._args.validate(obj._val(), obj);
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
				button: function (owner, name, args) {
					var button;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = '';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.multi_click === undefined) {
						args.multi_click = true;
					}
					if (args.caption === undefined) {
						args.caption = name;
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					args.classes.push('om_button');
					if ('class' in args) {
						args.classes.push(args['class']);
					}
					if (name !== '' && name !== null) {
						args.classes.push(name);
					}
					// add the button to the DOM
					button = om.bf.make.input.obj(owner, {
						'classes': args.classes
					});
					button._name = name;
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
				link: function (owner, name, args) {
					var link;
					/* args = {
						href: "javascript:", // href attribute of link
						target: '', // target attribute of link
						on_click: function () {},
						caption: "", // text to go inside the link
						tooltip: '', // tooltip to show
						inline: false // show link inline
					} */
					if (owner === undefined) {
						owner = $('body');
					}
					if (name === undefined) {
						name = '';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.href === undefined) {
						args.href = 'javascript:';
					}
					if (args.target === undefined) {
						args.target = '';
					}
					if (args.caption === undefined) {
						args.caption = name;
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					args.classes.push('om_link');
					if ('class' in args) {
						args.classes.push(args['class']);
					}
					if (name !== '' && name !== null) {
						args.classes.push(name);
					}
					link = om.bf.make.input.obj(owner, args);
					if (args.inline) {
						link.$.css('inline', true);
					}
					link._args = args;
					link._type = 'link';
					link._name = name;
					link.$.html('<a href="' + args.href + '">' + args.caption + '</a>');
					link._value = link.$.find('a:last');
					if (args.target !== '') {
						link._value.prop('target', args.target);
					}
					link._value.toggleClass('om_input_value', true);
					return link;
				},
				readonly: function (owner, name, args) {
					var readonly;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'readonly';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.default_val === undefined) {
						args.default_val = '';
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.link_caption === undefined) {
						args.link_caption = false;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
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
				text: function (owner, name, args) {
					var text;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'text';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.default_val === undefined) {
						args.default_val = '';
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
					}
					text = om.bf.make.input.obj(owner, args);
					text.$.append(om.assemble('input', {
						name: name,
						type: 'text',
						'class': 'om_input_value',
						value: args.default_val
					}));
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
					if (args.hint !== undefined) {
						om.bf.make.input._hint(text, args.hint, {default_val: args.default_val});
					}
					return text;
				},
				password: function (owner, name, args) {
					var password;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'password';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.default_val === undefined) {
						args.default_val = '';
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
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
				checkbox: function (owner, name, args) {
					var cb;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'checkbox';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.default_val === undefined) {
						args.default_val = false;
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
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
				radio_button: function (owner, name, args) {
					var rb;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'radio_button';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.default_val === undefined) {
						args.default_val = false;
					}
					if (args.name === undefined) {
						args.name = 'radio_button';
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
					}
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
				textarea: function (owner, name, args) {
					var textarea;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'text';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.default_val === undefined) {
						args.default_val = '';
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
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
					if (args.hint !== undefined) {
						om.bf.make.input._hint(textarea, args.hint, {default_val: args.default_val});
					}
					return textarea;
				},
				select: function (owner, name, args) {
					var select, key, i;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'text';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.default_val === undefined) {
						args.default_val = null;
					}
					if (args.options === undefined) {
						args.options = {};
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
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
					select._clear_options = function () {
						select._value.html('');
					};
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
					// add in our options
					if (jQuery.isArray(args.options)) {
						for (i = 0; i < args.options.length; i++) {
							select._add_option(args.options[i]);
						}
					} else {
						for (key in args.options) {
							if (args.options.hasOwnProperty(key)) {
								select._add_option(args.options[key], key);
							}
						}
					}
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
				file: function (owner, name, args) {
					var file, key, i;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'text';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.options === undefined) {
						args.options = {};
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if ('class' in args) {
						args.classes.push(args['class']);
					}
					if (args.enabled === undefined) {
						args.enabled = true;
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
				json: function (owner, name, args) {
					var json;
					if (! om.is_jquery(owner)) {
						throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
					}
					if (name === undefined) {
						name = 'json';
					}
					if (args === undefined) {
						args = {};
					}
					if (args.help === undefined) {
						args.help = true;
					}
					if (args.classes === undefined) {
						args.classes = [];
					}
					if (args.default_val === undefined) {
						args.default_val = '';
					}
					if (args.enabled === undefined) {
						args.enabled = true;
					}
					if ('class' in args) {
						args.classes.push(args['class']);
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
			tooltip: function (owner, message, args) {
				var tooltip;
				if (! om.is_jquery(owner)) {
					throw new Error("Invalid jquery object: '" + owner + "'; jQuery object expected.");
				}
				if (args === undefined) {
					args = {};
				}
				if (args.speed === undefined) {
					args.speed = 0;
				}
				if (args.classes === undefined) {
					args.classes = [];
				}
				if (args.offset === undefined) {
					args.offset = {};
				}
				if (args.offset.x === undefined) {
					args.offset.x = 8;
				}
				if (args.offset.y === undefined) {
					args.offset.y = 8;
				}
				if (args.target === undefined) {
					args.target = owner;
				}
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
			message: function (owner, title, html, args) {
				var message, func;
				if (owner === undefined) {
					owner = $('body');
				}
				if (args === undefined) {
					args = {};
				}
				if (args.modal === undefined) {
					args.modal = false;
				}
				if (args.classes === undefined) {
					args.classes = [];
				}
				args.classes.push('om_message');
				if ('class' in args) {
					args.classes.push(args['class']);
				}
				// create the box
				message = om.bf.make.box(owner, {
					imbue: 'free',
					dont_show: true,
					classes: args.classes,
					insert: args.insert
				});
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
			loading: function (owner, args) {
				var loading = om.bf.make.box(
					owner, {
						imbue: 'free',
						dont_show: true,
						'class': 'om_loading'
					}
				);
				args = om.get_args({
					depth: 1,
					on_complete: undefined,
					resize: false
				}, args);
				loading._args = args;
				if (args.options !== undefined) {
					loading._opacity(args.opacity);
				}
				loading._depth = args.depth;
				// not sure I really need to resize?
				if (args.resize) {
					loading._resize_to(owner, om.get(args.resize) || {});
				}
				// hi-jack remove to implement depth
				loading._box_remove = loading._remove;
				loading._remove = function () {
					// stall our removal until the depth is cleared
					loading._depth -= 1;
					if (loading._depth === 0) {
						if (args.on_complete !== undefined) {
							args.on_complete();
						}
						loading._box_remove();
					}
				};
				if (args.dont_show !== true) {
					loading.$.show();
				}
				return loading;
			},
			confirm: function (owner, title, html, args) {
				var conf = om.bf.make.message(owner, title, html, args);
				if (args === undefined) {
					args = {};
				}
				conf.$.toggleClass('om_confirm', true);
				// add in a close button to the bottom of the box
				conf._extend('bottom');
				om.bf.make.input.button(conf._box_bottom.$, 'close', {
					caption: 'Close',
					'class': 'om_confirm_close',
					on_click: function (click_event) {
						// and fire the users's on_close event if present
						if (args.on_close !== undefined && typeof args.on_close === 'function') {
							args.on_close(click_event);
						}
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
				}
				if (! conf.$.is(':hidden')) {
					// auto-focus the first input, if there is one
					conf.$.find('input,button').slice(0, 1).focus();
				}
				return conf;
			},
			query: function (owner, title, html, args) {
				var query;
				if (args === undefined) {
					args = {};
				}
				if (args.ok_caption === undefined) {
					args.ok_caption = 'Ok';
				}
				if (args.cancel_caption === undefined) {
					args.cancel_caption = 'Cancel';
				}
				if (args.form_fields === undefined) {
					args.form_fields = {};
				}
				if (args.form_args === undefined) {
					args.form_args = {};
				}
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
						// close ourself
						query.args.on_close
						if (typeof query._args.on_cancel === 'function') {
							query._args.on_cancel(click_event, query);
						}
						if (! keydown_event.isDefaultPrevented()) {
							keydown_event.preventDefault();
							keydown_event.stopPropagation();
							query._remove();
						}
					}
				});
				query._form.$.bind('keydown', function (keydown_event) {
					if (keydown_event.keyCode === 13) {
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
						if (typeof query._args.on_ok === 'function') {
							query._args.on_ok(
								click_event,
								query._form._get_input(),
								query
							);
						}
						// if we did prevent the default then rebind ourselves if default is disabled too
						if (! click_event.isDefaultPrevented()) {
							// remove ourselves from the DOM
							query._remove();
						}
					}
				});
				query._cancel_button = om.bf.make.input.button(query._box_bottom.$, 'cancel', {
					caption: query._args.cancel_caption,
					'class': 'om_query_cancel',
					multi_click: false,
					on_click: function (click_event) {
						// and fire the users's cancel event if present
						if (typeof query._args.on_cancel === 'function') {
							query._args.on_cancel(click_event, query);
						}
						// if we did prevent the default then rebind ourselves if default is disabled too
						if (! click_event.isDefaultPrevented()) {
							// remove ourselves from the DOM
							query._remove();
						}
					}
				});
				if (query._args.dont_show !== true) {
					query._show();
					// auto-focus first input, if we have any
					query.$.find('input, select, textarea, button').slice(0, 1).focus();
				}
				return query;
			},
			collect: function (owner, title, html, fields, args) {
				var collect;
				if (args === undefined) {
					args = {};
				}
				collect = om.bf.make.message(owner, title, html, args);
				collect._form = om.bf.make.form(collect._box_middle.$, fields);
				collect._form._add_submit(args.submit_caption, function (click_event, input) {
					// and fire the users's on_submit event if present
					if (args.on_submit !== undefined && typeof args.on_submit === 'function') {
						args.on_submit(click_event, input);
					}
					// if we did prevent the default then rebind ourselves if default is disabled too
					if (! click_event.isDefaultPrevented()) {
						// remove ourselves from the DOM
						collect._remove();
					}
				});
				collect._form._add_cancel(args.cancel_caption, function (click_event) {
					// and fire the users's on_cancel event if present
					if (args.on_cancel !== undefined && typeof args.on_cancel === 'function') {
						args.on_cancel(click_event);
					}
					// if we did prevent the default then rebind ourselves if default is disabled too
					if (! click_event.isDefaultPrevented()) {
						// remove ourselves from the DOM
						collect._remove();
					}
				});
				collect.$.toggleClass('om_collect', true);
				if (args.dont_show !== true) {
					collect._show();
				}
				if (! collect.$.is(':hidden')) {
					// auto-focus the first input, if there is one
					collect.$.find('input,button').slice(0, 1).focus();
				}
				return collect;
			},
			browser: function (owner, url, args) {
				var browser;
				if (url === undefined) {
					throw new Error("Invalid browser URL.");
				}
				if (args === undefined) {
					args = {};
				}
				if (args.icon === undefined) {
					args.icon = "/omega/images/diviner/globe.png";
				}
				if (args.title === undefined) {
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
/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
(function (om) {
	om.OmegaClient = function (args) {
		var om_client = {}; 
		/* args = {
			name: '', // the name of the service, if known
			url: './', // the relative path to the omega service (default: ./)
			creds: null, // omega authentication information, object {username,password|token}
			on_fail: function () {}, // what to do if there is a protocol failure
			init_data: null // any service initialization params needed to talk to the service
		} */

		// parse our arguments
		if (args === undefined) {
			args = {};
		}
		om_client._args = args;
		om_client.service_name = args.name;
		if (args.url === undefined) {
			om_client.url = './';
		} else {
			om_client.url = args.url;
		}
		if (args.creds !== undefined) {
			if (args.creds.username !== undefined) {
				if (args.creds.password  === undefined) {
					args.creds.password = '';
				}
				om_client.creds = {
					username: args.creds.username,
					password: args.creds.password
				};
			} else {
				throw om.Error("Invalid credentials format");
			}
		}
		if (args.on_fail !== undefined) {
			om_client.on_fail = args.on_fail;
		} else {
			// default error handler
			om_client.on_fail = function (reason, api_args) {
				// failed? throw a polite error to the user
				var error = om.bf.make.confirm(
					$('body'),
					'Service Error',
					reason, {
						modal: true,
						on_click: function (click_event) {
							throw new Error(reason);
						}
					}
				);
				error.$.toggleClass('om_error', true);
				// let them retry
				error._box_bottom._add_input(
					'button',
					'retry',
					{
						caption: 'Re-try',
						multi_click: true,
						on_click: function (button_click) {
							error._remove();
							om_client.do_ajax.apply(this, api_args);
						}
					}
				);
				error._center_top(0.1)._constrain_to();
			};
		}

		om_client.set_token = function (token) {
			om_client.creds = {
				'token': token
			};
		};

		om_client.shed = om.DataShed(om_client);
		om_client.fetch_na = om_client.shed.fetch_na;
		om_client.fetch = om_client.shed.fetch;
		om_client.get_na = om_client.shed.get_na;
		om_client.get = om_client.shed.get;

		om_client.get_fields = function (method_info) {
			var i, fields, param, param_type, input_type, args;
			fields = {};
			for (i = 0; i < method_info.params.length; i += 1) {
				param = method_info.params[i].name;
				// initialize the args
				args = {};
				// get the type
				if (param in method_info.doc.expects) {
					param_type = method_info.doc.expects[param];
				} else {
					param_type = 'undefined';
				}
				args.caption = param.replace(/_/, ' ');
				if (param_type === 'boolean') {
					input_type = 'checkbox';
				} else {
					if (param_type === 'object' || param_type === 'array' || param_type === 'undefined') {
						input_type = 'json';
					} else if (param_type === 'number') {
						input_type = 'text';
					} else if (param_type === 'string') {
						input_type = 'text';
					} else {
						throw new Error("Unrecognized parameter type: '" + param_type + "'.");
					}
				}
				// check for null values-- force those types to JSON
				if (method_info.params[i].optional) {
					if (method_info.params[i].default_value !== undefined) {
						if (method_info.params[i].default_value === null) {
							input_type  = 'json';
						}
					}
				}
				args.caption += '<span class="api_param om_type_' + param_type + '">' + param_type + '</span>';
				// take note of optional parameters
				if (method_info.params[i].optional) {
					args.default_val = method_info.params[i].default_value;
					args.optional = true;
					args.caption += ' (optional)';
				}
				fields[param] = {type: input_type, args: args};
			}
			return fields;
		};

		om_client.init_service = function (args) {
			var init, loading;
			if (args === undefined) {
				args = {};
			}
			if (args.target === undefined) {
				args.target = $('body');
			}
			loading = om.bf.make.loading(args.target);
			om_client.exec(
				'?',
				null,
				function (service_info) {
					var has_params, collect, fields, param;
					has_params = false;
					fields = om_client.get_fields(service_info.info);
					for (param in fields) {
						if (fields.hasOwnProperty(param)) {
							has_params = true;
						}
					}
					if (args.title === undefined) {
						args.title = 'Initialize ' + service_info.name;
					}
					if (args.message === undefined) {
						args.message = service_info.description;
					}
					// does the constructor require parameters? snag 'em, if so
					if (has_params) {
						collect = om.bf.make.collect(
							args.target,
							args.title,
							args.message,
							fields,
							{
								modal: true,
								on_submit: function (click_event, input) {
									var loading;
									loading = om.bf.make.loading(collect._box_middle.$);
									click_event.preventDefault();
									om_client.exec(
										service_info.name,
										input,
										function () {
											collect._remove();
											loading._remove();
											if (args.on_init !== undefined) {
												args.on_init(service_info);
											}
										},
										function (error) {
											var msg = om.bf.make.confirm(
												collect.$,
												'Initialization failure',
												error,
												{modal: true}
											);
											loading._remove();
											msg._center_top(0.1, collect.$);
											msg._constrain_to();
										}
									);
								}
							}
						);
						loading._remove();
						collect.$.find('.om_button.cancel').remove();
						collect._center_top(0.1, args.target);
						collect._constrain_to();
					} else {
						loading._remove();
						if (args.on_init !== undefined) {
							args.on_init(service_info);
						}
					}
				},
				function (error_message) {
					loading._remove();
				}
			);
		};

		om_client.login_user = function (args) {
			var login;
			if (args === undefined) {
				args = {};
			}
			if (args.target === undefined) {
				args.target = $('body');
			}
			if (args.title === undefined) {
				args.title = 'Please Login';
			}
			if (args.message === undefined) {
				args.message = 'Please enter your username and password.';
			}
			if (args.username === undefined) {
				args.username = '';
			}
			login = om.bf.make.collect(
				args.target,
				args.title,
				args.message,
				{
					'username': {
						type: 'text',
						args: {
							default_val: args.username,
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
					caption: 'Log in',
					modal: true,
					on_submit: function (submit_event, input) {
						var loading, client, args;
						loading = om.bf.make.loading(login.$);
						args = {};
						// if we have a token then use that
						if (input.token === '') {
							args.creds = {token: input.token};
						} else {
							args.creds = {username: input.username, password: input.password};
						}
						client = om.OmegaClient(args);
						// and prevent the login from disappearing just yet
						submit_event.preventDefault();
						client.exec(
							'#omega.get_session_id',
							null,
							function (session_id) {
								if (session_id !== null) {
									om_client.set_token(session_id);
								}
								login._remove();
								loading._remove();
								if (login._args.on_login !== undefined) {
									login._args.on_login();
								}
							},
							// otherwise report the error
							function (response) {
								var error = om.bf.make.confirm(
									login.$,
									'Login Failure',
									response,
									{
										on_close: function (click_event) {
											// focus the password
											login._box_middle.$.find('input[type=password]').focus();
										},
										modal: true
									}
								);
								loading._remove();
								error._center_top(0.1, login.$);
								error._constrain_to();
							}
						);
					}
				}
			);
			login._args = args;
			login.$.find('.om_button.cancel').remove();
			login.$.addClass('login_box');
			login._center_top(0.1);
			login.$.find('input:first').focus();
		};

		om_client.exec = function (api, params, callback, fail_callback, args) {
			if (args === undefined) {
				args = {};
			}
			if (args.async === undefined) {
				args.async = true;
			}
			om_client.do_ajax(api, params, callback, fail_callback, args);
		};

		om_client.exec_na = function (api, params, callback, fail_callback, args) {
			if (args === undefined) {
				args = {};
			}
			if (args.async === undefined) {
				args.async = false;
			}
			om_client.do_ajax(api, params, callback, fail_callback, args);
		};

		om_client.do_ajax = function (api, params, callback, fail_callback, args) {
			var ajax, ajax_args;
			if (args === undefined) {
				args = {};
			}
			if (args.post === undefined) {
				args.post = [];
			}
			ajax_args = arguments;
			ajax = {
				_args: args,
				data: null
			};

			ajax.on_ajax_success = function (response, test_status, xml_http_request) {
				var json_response, spillage, message, error, response_encoding,
					response_charset, response_parts, cookies;
				response_parts = xml_http_request.getResponseHeader('Content-Type').split('; ');
				response_encoding = response_parts[0];
				if (response_parts.length > 1) {
					response_charset = response_parts[1];
				}
				if (response_encoding === 'application/json') {
					// if there was any spillage then note
					if (response.spillage !== undefined) {
						spillage = om.bf.make.confirm($('body'), 'API Spillage: ' + api, '<div class="om_spillage">' + response.spillage + '</div>');
						spillage._constrain_to();
					}
					// if this succeeded then execute any included callback code
					if (response.result !== undefined) {
						if (response.result === true) {
							if (typeof(callback) === 'function') {
								return callback(response.data);
							}
						} else {
							if (response.reason === undefined) {
								response.reason = "Failed to execute '" + api + "'; an unknown error has occurred.";
							}
							if (typeof(fail_callback) === 'function') {
								fail_callback(response.reason, ajax_args);
							} else {
								throw new Error(response.reason);
							}
						}
					} else {
						if (typeof(fail_callback) === 'function') {
							fail_callback("Failed to execute '" + api + "'; response object contains no result boolean.", ajax_args);
						} else {
							throw new Error("Failed to execute '" + api + "'; response object contains no result boolean.");
						}
					}
				} else {
					try {
						if (response_encoding === 'application/xml') {
							throw new Error("Unsupported response encoding: '" + response_encoding + "'." + response);
						} else if (response_encoding === 'text/html') {
							throw new Error("Unsupported response encoding: '" + response_encoding + "'." + response);
						} else {
							throw new Error("Unrecognized response encoding: '" + response_encoding + "'." + response);
						}
					} catch (e) {
						// report the error to the callback function if available
						if (typeof om_client.on_fail === 'function') {
							om_client.on_fail(e.message, ajax_args);
						} else {
							throw e;
						}
					}
				}
			};

			ajax.on_ajax_failure = function (xml_http_request, text_status, error_thrown) {
				var message, error;
				if (typeof(fail_callback) === 'function') {
					fail_callback({result: false, reason: xml_http_request.responseText});
				} else {
					message = 'An error has occurred within Omega. The following data was returned, but could not be interpretted:<br/><br/>' + xml_http_request.responseText;
					error = om.bf.make.confirm($('body'), 'Omega Error', message);
					error._constrain_to();
					throw new Error(message);
				}
			};

			// default to asyncronous mode
			if (args.async === undefined) {
				args.async = true;
			}
			// automatically assume no params if not present
			if (params === undefined || params === null) {
				params = {};
			}

			// figure out what data to send to the server
			ajax.data = [
				'OMEGA_API_PARAMS=' + om.JSON.encode(params).replace(/&/g, '%26').replace(/\+/g, '%2B'),
				'OMEGA_ENCODING=json'
			];
			// add in any extra post data
			ajax.data = ajax.data.concat(args.post);
			if (om_client.creds !== undefined) {
				ajax.data.push('OMEGA_CREDENTIALS=' + escape(om.JSON.encode(om_client.creds)));
			}
			// and fire off the API
			jQuery.ajax({
				async: args.async,
				type: 'POST',
				url: om_client.url + escape(api),
				data: ajax.data.join('&'),
				error: ajax.on_ajax_failure,
				success: ajax.on_ajax_success
			});
		};
		
		/*
		// if we don't have the name of the server then get that info up front
		if (om_client.service_name === undefined) {
			shed.fetch_na(
				'service',
				'info',
				'?',
				{},
				function (service_info) {
					om_client.service_name = service_info.name;
				},
				function () {} // do nothing on failure
			);
		}
		// get the description and constructor information, if possible
		if (om_client.service_name === undefined) {
			shed.fetch_na(
				'service',
				'info',
				'?',
				{},
				function (service_info) {
					om_client.service_desc = service_info.description;
					om_client.service_params = service_info.params;
				},
				function () {} // do nothing on failure
			);
		}
		// if we have init params then use 'em
		if (om_client.service_params !== undefined) {
			if (om_client.service_name === undefined) {
				throw om.Error("Unable to initialize the service with parameters without knowing its name.");
			}
			om_client.exec_na(
				om_client.service_name,
				om_client.service_params
			);
		}
		*/
		return om_client;
	};
}(om));

/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
(function (om) {
	om.ColorFactory = {};
	om.cf = om.ColorFactory;
	om.cf.get = function (color, args) {
		var hue, result, i, rgb_clr, min, max, delta, tmp;
		if (color === undefined) {
			throw new Error("Invalid color to get value of.");
		}
		if (args === undefined) {
			args = {};
		}
		if (args.format === undefined) {
			args.format = 'hex';
		}
		if (om.is_jquery(color)) {
			if (args.surface === undefined) {
				args.surface = 'color';
			}
			color = color.css(args.surface);
		}
		if (typeof color === 'string') {
			if (color.match(/^#?[a-f0-9]{6}$/i)) {
				// e.g. 0099FF, #996600
				// add the # if it isn't there
				if (color.substr(0, 1) !== '#') {
					color = '#' + color;
				}
				// no conversion needed
				if (args.format === 'hex') {
					return color;
				}
			} else if (color.substr(0, 3) === 'rgb') {
				if (color.substr(0, 4) === 'rgba') {
					// kill the alpha part if present
					color = color.replace(/, \d+\)/, ')');
				}
				// e.g. rgb(255, 41, 6)
				// no conversion needed
				if (args.format === 'rgb') {
					return color;
				}
				// get everything between "rgb(" and the ending ")"
				color = color.substr(4, color.length - 5).split(',');
				for (i = 0; i < color.length; i++) {
					color[i] = parseInt(color[i]).toString(16);
					if (color[i].length === 1) {
						color[i] = '0' + color[i];
					} else if (color[i].length > 2) {
						throw new Error("Color component '" + color[i] + "' is out of bounds.");
					}
				}
				color = '#' + color.join('');
			} else {
				throw new Error("Invalid color string: '" + color + "'.");
			}
		} else if (typeof color === 'object') {
			result = '#';
			if (color.r !== undefined && color.g !== undefined && color.b !== undefined) {
				// no conversion needed
				if (args.format === 'rgb_obj') {
					return color;
				}
				// make sure our colors are in order
				color = {
					r: color.r,
					g: color.g,
					b: color.b
				};
				for (hue in color) {
					if (color.hasOwnProperty(hue)) {
						color[hue] = color[hue].toString(16);
						if (color[hue].length === 1) {
							result += '0' + color[hue];
						} else {
							result += color[hue];
						}
					}
				}
			} else if (color.h !== undefined && color.s !== undefined && color.v !== undefined) {
				// no conversion needed
				if (args.format === 'hsv_obj') {
					return color;
				}
				if (color.s === 0) {
					color = String(parseInt(color.v * 255, 16));
					if (color.length === 1) {
						color = '0' + color;
					}
					color = '#' + color + color + color;
				} else {
					color.h = (color.h % 360) / 60;
					tmp = {
						i: Math.floor(color.h)
					};
					tmp.f = color.h - tmp.i;
					tmp.p = color.v * (1 - color.s);
					tmp.q = color.v * (1 - color.s * tmp.f);
					tmp.t = color.v * (1 - color.s * (1 - tmp.f));
					if (tmp.i === 0) {
						rgb_clr = {
							r: color.v,
							g: tmp.t,
							b: tmp.p
						};
					} else if (tmp.i === 1) {
						rgb_clr = {
							r: tmp.q,
							g: color.v,
							b: tmp.p
						};
					} else if (tmp.i === 2) {
						rgb_clr = {
							r: tmp.p,
							g: color.v,
							b: tmp.t
						};
					} else if (tmp.i === 3) {
						rgb_clr = {
							r: tmp.p,
							g: tmp.q,
							b: color.v
						};
					} else if (tmp.i === 4) {
						rgb_clr = {
							r: tmp.t,
							g: tmp.p,
							b: color.v
						};
					} else if (tmp.i === 5) {
						rgb_clr = {
							r: color.v,
							g: tmp.p,
							b: tmp.q
						};
					} else {
						throw new Error("Invalid color hue: '" + (color.h * 60) + "'.");
					}
					// now convert back to hex for further conversion
					result = '#';
					for (hue in rgb_clr) {
						if (rgb_clr.hasOwnProperty(hue)) {
							rgb_clr[hue] = parseInt(rgb_clr[hue] * 255, 10).toString(16);
							if (rgb_clr[hue].length === 1) {
								result += '0' + rgb_clr[hue];
							} else {
								result += rgb_clr[hue];
							}
						}
					}
				}
			} else {
				throw new Error("Unrecognized color object.");
			}
			color = result;
		} else {
			throw new Error("Unrecognized color type: " + color + '.');
		}

		// and return as the requested format - we're in hex by default
			// still nothin' to do
		if (args.format === 'rgb') {
			color = 'rgb(' + parseInt(color.substr(1, 2), 16) + ', '
				+ parseInt(color.substr(3, 2), 16) + ', '
				+ parseInt(color.substr(5, 2), 16) + ')';
		} else if (args.format === 'hsv_obj') {
			// convert to RBG [0,1] first
			rgb_clr = {
				'r': parseInt(color.substr(1, 2), 16) / 255,
				'g': parseInt(color.substr(3, 2), 16) / 255,
				'b': parseInt(color.substr(5, 2), 16) / 255
			};
			max = Math.max(rgb_clr.r, rgb_clr.g, rgb_clr.b);
			min = Math.min(rgb_clr.r, rgb_clr.g, rgb_clr.b);
			delta = max - min;
			color = {
				h: 0,
				s: 0,
				v: max
			};
			if (max !== 0) {
				color.s = delta / max;
			} else {
				return {s: 0, h: 360, v: color.v};
			}
			if (rgb_clr.r === max) {
				color.h = (rgb_clr.g - rgb_clr.b) / delta; // yellow/magenta range
			} else if (rgb_clr.g === max) {
				color.h = 2 + (rgb_clr.b - rgb_clr.r) / delta; // cyan/yellow range
			} else {
				color.h = 4 + (rgb_clr.r - rgb_clr.g) / delta; // yellow/magenta range
			}
			color.h *= 60;
			if (color.h < 0) {
				color.h += 360;
			}
			color.h = parseInt(color.h, 10);
		} else if (args.format === 'rgb_obj') {
			color = {
				'r': parseInt(color.substr(1, 2), 16),
				'g': parseInt(color.substr(3, 2), 16),
				'b': parseInt(color.substr(5, 2), 16)
			};
		} else if (args.format !== 'hex') {
			// we're in hex by default
			throw new Error("Invalid color format: '" + args.format + "'.");
		}
		return color;
	};

	om.cf.make = {
		fade: function (colors, args) {
			// validate our colors object
			var fade;
			/* args = {
				steps: 1, // how many steps between colors
				allow_oob: true // whether or not to allow out-of-bounds colors
			} */
			if (args === undefined) {
				args = {};
			}
			if (args.steps === undefined) {
				args.steps = 1;
			}
			if (args.allow_oob === undefined) {
				args.allow_oob = true;
			}
			
			fade = {
				_args: args,
				colors: []
			};

			fade.set_steps = function (steps) {
				if (typeof(steps) === 'number' && steps >= 0) {
					fade.steps = steps;
					fade.size = ((fade.colors.length - 1) * fade.steps) + fade.colors.length;
				} else {
					throw new Error("The number of steps must be 0 or greater.");
				}
			};

			fade.set_size = function (count) {
				// make sure this size can be used with this many colors
				if (count === fade.colors.length) {
					fade.set_steps(0);
				} else {
					// figure out how many steps we need to have the number of colors requested
					fade.set_steps(
						(count - fade.colors.length) / (fade.colors.length - 1)
					);
				}
			};

			fade.get_colors = function (args) {
				var colors = [],
					i;
				if (args === undefined) {
					args = {};
				}
				for (i = 0; i < fade.size; i += 1) {
					colors.push(fade.get_color(i), args);
				}
				return colors;
			};

			fade.set_colors = function (colors, args) {
				var i;
				if (args === undefined) {
					args = {};
				}
				if (om.is_jquery(colors)) {
					if (args.surface === undefined) {
						args.surface = 'color';
					}
				}
				fade.colors = [];
				if (! (jQuery.isArray(colors) || om.is_jquery(colors))) {
					throw new Error("Invalid colors for fade; jQuery ref or array expected.");
				}
				for (i = 0; i < colors.length; i += 1) {
					fade.colors[i] = om.cf.get(colors[i], args);
				}
			};

			fade.get_color = function (i, args) {
				var start_color_num, end_color_num, offset, blend_ratio,
					depth = 0;
				if (args === undefined) {
					args = {};
				}
				if (args.format === undefined) {
					args.format = 'hex';
				}
				if (i < 0 || i >= fade.size) {
					if (! fade._args.allow_oob) {
						throw new Error("Please enter a color number between 0 and " + fade.size - 1 + ".");
					} else {
						// otherwise, translate the step to exist in the range we've got
						if (i < 0) {
							i = i * -1;
						}
						if (i >= fade.size) {
							depth = parseInt(i / fade.size, 10);
							// if we're an odd number in depth then flip the fade around for smooth gradients (e.g. 0 1 2 3 2 1 0 1 2 ...)
							if (depth % 2 === 1) {
								i = fade.size - (i % fade.size) - 1;
							} else {
								i = i % fade.size;
							}
						}
					}
				}
				// now get the color for the step we are on
				// figure out what color it is based on, what it is fading to, and which fade step it is on
				start_color_num = parseInt((i / fade.size), 10);
				end_color_num = start_color_num + 1;
				offset = i % (fade.steps + 2);
				blend_ratio = offset / (fade.steps + 2);
				return om.cf.blend(
					fade.colors[start_color_num],
					fade.colors[end_color_num],
					{ratio: blend_ratio, format: args.format}
				);
			};

			fade.set_colors(colors);
			fade.set_steps(args.steps);
			return fade;
		}
	};

	om.cf.blend = function (source, target, args) {
		var color, part;
		// if there are no steps or the offset is zero take the easy way out
		if (args === undefined) {
			args = {};
		}
		if (args.format === undefined) {
			args.format = 'hex';
		}
		if (args.ratio === undefined) {
			args.ratio = 0.5;
		}
		source = om.cf.get(source, {format: 'rgb_obj'});
		target = om.cf.get(target, {format: 'rgb_obj'});
		// easy cases
		if (args.ratio === 0) {
			color = source;
		} else if (args.ratio === 1) {
			color = target;
		} else {
			// and blend each part
			color = {
				'r': parseInt((source.r * (1 - args.ratio)) + (target.r * args.ratio), 10),
				'g': parseInt((source.g * (1 - args.ratio)) + (target.g * args.ratio), 10),
				'b': parseInt((source.b * (1 - args.ratio)) + (target.b * args.ratio), 10)
			};
			// limit values to 0-255, in case the ratio is > 1 or < 0
			for (part in color) {
				if (color.hasOwnProperty(part)) {
					if (parseInt(color[part], 10) > 255) {
						color[part] = 255;
					} else if (parseInt(color[part], 10) < 0) {
						color[part] = 0;
					}
				}
			}
		}
		return om.cf.get(color, {format: args.format});
	};
	
	om.cf.mix = function (color, args) {
		if (args === undefined) {
			args = {};
		}
		color = om.cf.get(color, {format: 'hsv_obj'});
		if (args.hue !== undefined) {
			color.h = args.hue;
		} else if (args.hue_shift !== undefined) {
			color.h += args.hue_shift;
		} else if (args.hue_mult !== undefined) {
			color.h *= args.hue_mult;
		}
		if (args.saturation !== undefined) {
			color.s = args.saturation;
		} else if (args.saturation_shift !== undefined) {
			color.s += args.saturation_shift;
		} else if (args.saturation_mult !== undefined) {
			color.s *= args.saturation_mult;
		}
		if (args.value !== undefined) {
			color.v = args.value;
		} else if (args.value_shift !== undefined) {
			color.v += args.value_shift;
		} else if (args.value_mult !== undefined) {
			color.v *= args.value_mult;
		}
		if (color.h < 0) {
			color.h = 360 + (color.h % 360);
		}
		if (color.h > 360) {
			color.h = color.h % 360;
		}
		if (color.s > 1) {
			color.s = 1;
		}
		if (color.s < 0) {
			color.s = 0;
		}
		if (color.v > 1) {
			color.v = 1;
		}
		if (color.v < 0) {
			color.v = 0;
		}
		return om.cf.get(color, args.format);
	};
}(om));
/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

(function (om) {
	om.Visualizer = {
		obj2html: function (data, args) {
			var html = '',
				data_type = typeof(data),
				fade, key, str;
			if (args === undefined) {
				args = {};
			}
			if (args.depth === undefined) {
				args.depth = 1;
			}
			if (args.colors === undefined) {
				args.colors = ['#FFFFFF', '#FF6000'];
			}
			if (args.add_spaces === undefined) {
				args.add_spaces = false;
			}
			fade = om.cf.make.fade(args.colors, {steps: 2});
			if (data_type === 'undefined') {
				html += '<span class="om_type_' + data_type + '">undefined</span>';
			} else if (data === null) {
				html += '<span class="om_type_' + data_type + '">null</span>';
			} else if (data_type === 'object' && data !== null) {
				for (key in data) {
					if (data.hasOwnProperty(key)) {
						html += '<span class="om_object_key" style="color: ' + fade.get_color(args.depth) + '; ">';
						if (args.add_spaces) {
							html += key.replace(/_/g, ' ');
						} else {
							html += key;
						}
						html += '</span>';
						if (typeof(data[key]) === 'object' && data[key] !== null) {
							html += '<br/><div class="om_object_value" style="padding-left: 7px;">';
							html += arguments.callee(data[key], {
								depth: args.depth + 1,
								colors: args.colors,
								add_spaces: args.add_spaces
							});
							html += '</div>\n';
						} else {
							html += ': ' + arguments.callee(data[key], {
								colors: args.colors,
								add_spaces: args.add_spaces
							});
							html += '<br/>';
						}
					}
				}
			} else {
				// strip tags
				str = String(data).replace(/</g, '&lt;').replace(/>/g, 'gt;');
				return '<span class="om_type_' + data_type + '">' + str + '</span>\n';
			}
			return html;
		}
	};
	om.vis = om.Visualizer;
}(om));

/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
(function (om) {
	om.DataShed = function (om_client, args) {
		var shed = {
			_args: args,
			storage: {},
			enabled: true
		};

		if (args === undefined) {
			args = {};
		}
		if (args.enabled === undefined) {
			args.enabled = true;
		}
		shed.enabled = args.enabled;

		shed.bind_service = function (om_client) {
			shed.client = om_client;
		};

		shed.clear_shed = function () {
			shed.storage = {};
		};

		shed.forget = function (bin, key) {
			if (bin in shed.storage && key in shed.storage[bin]) {
				delete shed.storage[bin][key];
			}
		};

		shed.dump_bin = function (bin) {
			if (bin in shed.storage) {
				delete shed.storage[bin];
			}
		};

		shed.store = function (bin, key, value) {
			if (bin in shed.storage) {
				shed.storage[bin][key] = value;
			} else {
				shed.storage[bin] = {};
				shed.storage[bin][key] = value;
			}
		};

		shed.get_bin = function (bin) {
			if (bin in shed.storage) {
				return shed.storage[bin];
			}
		};

		shed.get = function (bin, key) {
			if (bin in shed.storage) {
				if (key in shed.storage[bin]) {
					return shed.storage[bin][key];
				}
			}
		};

		shed.fetch = function (bin, key, api, params, on_complete, on_failure) {
			if (shed.enabled && bin in shed.storage && key in shed.storage[bin]) {
				on_complete(shed.storage[bin][key]);
			} else {
				shed.client.exec(
					api,
					params,
					function (response) {
						shed.store(bin, key, response);
						if (typeof(on_complete) === 'function') {
							on_complete(response);
						}
					},
					on_failure
				);
			}
		};

		shed.fetch_na = function (bin, key, api, params, on_complete, on_failure) {
			if (shed.enabled && bin in shed.storage && key in shed.storage[bin]) {
				on_complete(shed.storage[bin][key]);
			} else {
				shed.client.exec_na(
					api,
					params,
					function (response) {
						shed.store(bin, key, response);
						if (typeof(on_complete) === 'function') {
							on_complete(response);
						}
					},
					on_failure
				);
			}
		};

		/*
		shed.save_to_session = function () {
			om.set_cookie('_om_ds_shed', shed.storage);
		};

		shed.load_from_session = function () {
			shed.storage = om.get_cookie('_om_ds_shed');
		};

		shed.toggle_auto_save = function (bool) {
			om.set_cookie('_om_ds_autosave', true);
		};
		*/

		shed.bind_service(om_client);
		return shed;
	};
	om.ds = om.DataShed;
}(om));

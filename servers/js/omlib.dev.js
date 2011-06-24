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
			expiration.setDate(expiration.getDate() + ttl);
			cookie += ";expires=" + expiration.toGMTString();
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
				}
				else if (node_type === 'array') {
					match = parse_tree[i]; // convenience purposes only
					if (match[2]) { // keyword argument
						arg = argv[cursor];
						for (k = 0; k < match[2].length; k++) {
							if (!arg.hasOwnProperty(match[2][k])) {
								throw(om.sprintf('[sprintf] property "%s" does not exist', match[2][k]));
							}
							arg = arg[match[2][k]];
						}
					}
					else if (match[1]) { // positional argument (explicit)
						arg = argv[match[1]];
					}
					else { // positional argument (implicit)
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
				}
				else if ((match = /^\x25{2}/.exec(_fmt)) !== null) {
					parse_tree.push('%');
				}
				else if ((match = /^\x25(?:([1-9]\d*)\$|\(([^\)]+)\))?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(_fmt)) !== null) {
					if (match[2]) {
						arg_names |= 1;
						var field_list = [], replacement_field = match[2], field_match = [];
						if ((field_match = /^([a-z_][a-z_\d]*)/i.exec(replacement_field)) !== null) {
							field_list.push(field_match[1]);
							while ((replacement_field = replacement_field.substring(field_match[0].length)) !== '') {
								if ((field_match = /^\.([a-z_][a-z_\d]*)/i.exec(replacement_field)) !== null) {
									field_list.push(field_match[1]);
								}
								else if ((field_match = /^\[(\d+)\]/.exec(replacement_field)) !== null) {
									field_list.push(field_match[1]);
								}
								else {
									throw('[sprintf] huh?');
								}
							}
						}
						else {
							throw('[sprintf] huh?');
						}
						match[2] = field_list;
					}
					else {
						arg_names |= 2;
					}
					if (arg_names === 3) {
						throw('[sprintf] mixing positional and named placeholders is not (yet) supported');
					}
					parse_tree.push(match);
				}
				else {
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
			} else if (color.substr(0, 4) === 'rgb(') {
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

		// and return as the requested format
		if (args.format === 'hex') {
			// still nothin' to do
		} else if (args.format === 'rgb') {
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
		} else {
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
		} else if (args.saturation !== undefined) {
			color.s = args.saturation;
		} else if (args.saturation_shift !== undefined) {
			color.s += args.saturation_shift;
		} else if (args.saturation_mult !== undefined) {
			color.s *= args.saturation_mult;
		} else if (args.value !== undefined) {
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
			delete shed.storage[bin][key];
		};

		shed.dump_bin = function (bin) {
			delete shed.storage[bin];
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

(function (om) {
    /* core.js methods */
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
        obj: om.get
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
        obj: om.subtract
    });

    om.ucfirst = om.doc({
        desc: 'Convert the first character of the string to upper case.',
        params: {
            str: {
                desc: 'String to convert first character of.',
                type: 'string'
            }
        },
        obj: om.ucfirst
    });

    om.lcfirst = om.doc({
        desc: 'Lower-case first letter of string.',
        params: {
            str: {
                desc: 'String to convert first character of.',
                type: 'string'
            }
        },
        obj: om.lcfirst;
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
        obj: om.flatten
    });

    om.is_jquery = om.doc({
        desc: 'Returns whether the object is a vailid jQuery object.',
        params: {
            obj: {
                desc: 'Object to examine whether it is a jQuery object or not.',
                type: 'object'
            }
        },
        obj: om.is_jquery
    });

    om.is_numeric = om.doc({
        desc: 'Returns whether or not a string is numerical.',
        parms: {
            str: {
                desc: 'String to determine if the value is numerical or not.',
                type: 'undefined'
            }
        },
        obj: om.is_numeric
    });

    /* Same as "om.is_numeric", but negated for the pedantic. */
    om.isnt_numeric = om.doc({
        desc: 'Returns whether or not the string argument is numeric.',
        params: {
            str: 'String to determine if the value is numerical or not.',
            type: 'undefined'
        },
        obj: om.isnt_numeric
    });

    om.empty = om.doc({
        desc: 'Returns whether or not an object or array is empty.',
        params: {
            obj: {
                desc: 'Object to examine.',
                type: 'object'
            }
        },
        obj: om.empty
    });

    om.plural = om.doc({
        desc: 'Returns whether or not an object or array has at least two items.',
        params: {
            obj: {
                desc: 'Object to check for multiple properties of.',
                type: 'object'
            }
        },
        obj: om.plural
    });

    om.link_event = om.doc({
        desc: 'Cause events from one object to automatically trigger on the other object.',
        params: {
        },
        obj: om.link_event
    });

    om.assemble = om.doc({
        desc: 'Returns a string of an assembled HTML element.',
        params: {
        },
        obj: om.assemble
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
        obj: om.set_cookie
    });

    om.get_cookies = om.doc({
        desc: 'Get a list of the cookies as an object.',
        params: {
        },
        obj: om.get_cookies
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
        obj: om.get_cookie
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
        obj: om.find_cookies
    });

    om.round = om.doc({
        desc: 'Round numbers to some arbitrary precision or interval.',
        params: {
            num: {
                desc: 'Number to round',
                type: 'number'
            },
            args: {
                desc: 'Arguments to set interval, decimal points, min decimal length.'
                type: 'object',
                params: {
                    interval: {
                        desc: 'If set, the number will be rounded to the closest interval (e.g. 14 would round to 15 with an internval of 5).'
                        type: 'number'
                    }
                }
            }
        },
        obj: om.round
    });

    om.Error = om.doc({
        desc: 'Generic error handling.',
        params: {
        },
        obj: om.error
    });
}(om));

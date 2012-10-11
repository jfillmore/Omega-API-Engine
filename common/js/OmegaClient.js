/* omega - web client
   https://github.com/jfillmore/Omega-API-Engine
  
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

        om_client.handlers = {}; // http event handlers for certain response codes
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
                /*
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
                */
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

        om_client.get_fields = function (method_info) {
            var i, fields, param, info, input_type, args;
            fields = {};
            for (i = 0; i < method_info.params.length; i += 1) {
                info = method_info.params[i];
                param = info.name;
                // initialize the args
                args = {};
                param_type = info.type ? info.type : 'undefined';
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
                        input_type = 'text';
                        //throw new Error("Unrecognized parameter type: '" + param_type + "'.");
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
                        args.message = service_info.desc;
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

        /** register a handler for certain HTTP responses. */
        om_client.on_http = function (code, handler) {
            if (! (code in om_client.handlers)) {
                om_client.handlers[code] = [];
            }
            om_client.handlers[code].push(handler);
        };

        om_client._parse_response = function (xml_http_response, response) {
            var i, handlers;
            if (xml_http_response.status in om_client.handlers) {
                handlers = om_client.handlers[xml_http_response.status];
                for (i = 0; i < handlers.length; i++) {
                    // call each event handler we got for this HTTP code
                    om.get(handlers[i], response, xml_http_response);
                }
            }
        };

        /** Old-style API execution. */
        om_client.exec = function (api, params, callback, fail_callback, args) {
            args = om.get_args({
                async: true
            }, args, true);
            om_client.do_ajax(api, params, callback, fail_callback, args);
        };

        /** Old-style API execution, non-async. */
        om_client.exec_na = function (api, params, callback, fail_callback, args) {
            args = om.get_args({
                async: false
            }, args, true);
            om_client.do_ajax(api, params, callback, fail_callback, args);
        };

        /** Generic AJAX wrapper. */
        om_client.do_ajax = function (api, params, callback, fail_callback, args) {
            var ajax, ajax_args;
            args = om.get_args({
                async: true,
                method: 'POST',
                post: []
            }, args);
            ajax_args = arguments;
            ajax = {
                _args: args,
                data: null
            };

            ajax.on_ajax_success = function (response, text_status, xml_http_request) {
                var json_response, spillage, message, error, response_encoding,
                    response_charset, response_parts, cookies, header;
                om_client._parse_response(xml_http_request, response);
                header = xml_http_request.getResponseHeader('Content-Type');
                if (header) {
                    response_parts = header.split('; ');
                    response_encoding = response_parts[0];
                    if (response_parts.length > 1) {
                        response_charset = response_parts[1];
                    }
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
                var response_parts, response_encoding, response, header;
                header = xml_http_request.getResponseHeader('Content-Type');
                if (header) {
                    response_parts = header.split('; ');
                    response_encoding = response_parts[0];
                    if (response_parts.length > 1) {
                        response_charset = response_parts[1];
                    }
                }
                response = xml_http_request.responseText;
                om_client._parse_response(xml_http_request, response);
                if (response_encoding === 'application/json') {
                    // if there was any spillage then note
                    response = om.json.decode(response);
                    if (response.spillage !== undefined) {
                        spillage = om.bf.make.confirm(
                            $('body'),
                            'API Spillage: ' + api,
                            '<div class="om_spillage">' + response.spillage + '</div>'
                        );
                        spillage._constrain_to();
                    }
                    // if this succeeded then execute any included callback code
                    if (response.result !== undefined) {
                        if (response.reason === undefined) {
                            response.reason = "Failed to execute '" + api + "'; an unknown error has occurred.";
                        }
                        if (typeof(fail_callback) === 'function') {
                            fail_callback(response.reason, ajax_args);
                        } else {
                            throw new Error(response.reason);
                        }
                    } else {
                        if (typeof(fail_callback) === 'function') {
                            fail_callback("Failed to execute '" + api + "'; response object contains no result boolean.", ajax_args);
                        } else {
                            throw new Error("Failed to execute '" + api + "'; response object contains no result boolean.");
                        }
                    }
                } else {
                    om.get(om_client.on_fail, response, ajax_args);
                }
            };

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
                type: args.method,
                url: om_client.url + escape(api),
                data: ajax.data.join('&'),
                error: ajax.on_ajax_failure,
                success: ajax.on_ajax_success
            });
        };
        
        om_client.get = function (api, params, callback, fail_callback, args) {
            return om_client.request('GET', api, params, callback, fail_callback, args);
        };

        om_client.post = function (api, params, callback, fail_callback, args) {
            return om_client.request('POST', api, params, callback, fail_callback, args);
        };

        om_client.put = function (api, params, callback, fail_callback, args) {
            return om_client.request('PUT', api, params, callback, fail_callback, args);
        };

        om_client.patch = function (api, params, callback, fail_callback, args) {
            return om_client.request('PATCH', api, params, callback, fail_callback, args);
        };

        om_client.del = function (api, params, callback, fail_callback, args) {
            return om_client.request('DELETE', api, params, callback, fail_callback, args);
        };
        om_client['delete'] = om_client.del;

        om_client.request = function (method, api, params, callback, fail_callback, args) {
            var ajax, ajax_args;
            args = om.get_args({
                async: true,
                context: {}
            }, args);
            ajax_args = arguments;
            ajax = {
                _args: args,
                data: null
            };

            ajax.on_ajax_success = function (response, text_status, xml_http_request) {
                var json_response, spillage, message, error, response_encoding,
                    response_charset, response_parts, cookies, header;
                om_client._parse_response(xml_http_request, response);
                header = xml_http_request.getResponseHeader('Content-Type');
                if (header) {
                    response_parts = header.split('; ');
                    response_encoding = response_parts[0];
                    if (response_parts.length > 1) {
                        response_charset = response_parts[1];
                    }
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
                                return callback(response.data, args.context);
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
                var response_parts, response_encoding, response, header;
                header = xml_http_request.getResponseHeader('Content-Type');
                if (header) {
                    response_parts = header.split('; ');
                    response_encoding = response_parts[0];
                    if (response_parts.length > 1) {
                        response_charset = response_parts[1];
                    }
                }
                response = xml_http_request.responseText;
                om_client._parse_response(xml_http_request, response);
                if (response_encoding === 'application/json') {
                    // if there was any spillage then note
                    response = om.json.decode(response);
                    if (response.spillage !== undefined) {
                        spillage = om.bf.make.confirm(
                            $('body'),
                            'API Spillage: ' + api,
                            '<div class="om_spillage">' + response.spillage + '</div>'
                        );
                        spillage._constrain_to();
                    }
                    // if this succeeded then execute any included callback code
                    if (response.result !== undefined) {
                        if (response.reason === undefined) {
                            response.reason = "Failed to execute '" + api + "'; an unknown error has occurred.";
                        }
                        if (typeof(fail_callback) === 'function') {
                            fail_callback(response.reason, ajax_args);
                        } else {
                            throw new Error(response.reason);
                        }
                    } else {
                        if (typeof(fail_callback) === 'function') {
                            fail_callback("Failed to execute '" + api + "'; response object contains no result boolean.", ajax_args);
                        } else {
                            throw new Error("Failed to execute '" + api + "'; response object contains no result boolean.");
                        }
                    }
                } else {
                    om.get(om_client.on_fail, response, ajax_args);
                }
            };

            // automatically assume no params if not present
            if (params === undefined || params === null || om.empty(params)) {
                params = undefined
            } else {
                params = om.json.encode(params);
            }
            /* // TODO
            if (om_client.creds !== undefined) {
                ajax.data.push('OMEGA_CREDENTIALS=' + escape(om.JSON.encode(om_client.creds)));
            }
            */
            // and fire off the API
            jQuery.ajax({
                async: args.async,
                type: method,
                url: om_client.url + '/' + escape(api),
                dataType: 'json',
                contentType: 'application/json',
                data: params,
                error: ajax.on_ajax_failure,
                success: ajax.on_ajax_success
            });
        };

        /* // testing auto-init code, needs to be moved elsewhere
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
                    om_client.service_desc = service_info.desc;
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

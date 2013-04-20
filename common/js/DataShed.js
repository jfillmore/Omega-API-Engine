/* omega - web client
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */
   
(function (om) {
    om.DataShed = om.doc({
        desc: 'Key/Value store with optional integration into the AJAX OmegaClient lib.',
        obj: function (om_client, args) {
            var shed;
            args = om.get_args({
                enabled: true
            }, args);
            shed = {
                _args: args,
                storage: {},
                enabled: true
            };
            shed.enabled = args.enabled;

            /* Sets the OmegaClient object to use for fetching data via API. */
            shed.bind_service = function (om_client) {
                shed.client = om_client;
            };

            /* Remove all of the stored data. Returns the old data. */
            shed.clear_shed = function () {
                var old_storage;
                old_storage = shed.storage;
                shed.storage = {};
                return old_storage;
            };

            /* Delete an object in a bin. Returns the old data. */
            shed.forget = function (bin, key) {
                var old_val;
                if (bin in shed.storage && key in shed.storage[bin]) {
                    old_val = shed.storage[bin][key];
                    delete shed.storage[bin][key];
                }
                return old_val;
            };

            /* Deletes the contents of a bin, returning the old data. */
            shed.dump_bin = function (bin) {
                var old_bin;
                if (bin in shed.storage) {
                    old_bin = shed.storage[bin];
                    delete shed.storage[bin];
                }
                return old_bin;
            };

            /* Store a value in the specified bin with the given key. */
            shed.store = function (bin, key, value) {
                if (bin in shed.storage) {
                    shed.storage[bin][key] = value;
                } else {
                    shed.storage[bin] = {};
                    shed.storage[bin][key] = value;
                }
                return value;
            };

            /* Returns the contents of a bin. */
            shed.get_bin = function (bin) {
                if (bin in shed.storage) {
                    return shed.storage[bin];
                }
            };

            /* Retrieve an object from a bin with the given key. */
            shed.get = function (bin, key) {
                if (bin in shed.storage) {
                    if (key in shed.storage[bin]) {
                        return shed.storage[bin][key];
                    }
                }
            };

            /* Fetch an object, loading the value with the specified API & params. */
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

            /* Fetch an object, loading the value with the specified API & params.
            Non-asyncronous version */
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

            if (om_client) {
                shed.bind_service(om_client);
            }
            return shed;
        }
    });
    om.ds = om.DataShed;
}(om));

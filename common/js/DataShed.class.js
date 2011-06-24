
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

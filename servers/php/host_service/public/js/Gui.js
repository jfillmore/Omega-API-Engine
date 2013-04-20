$(document).ready(function () {
    window.Gui = function (omega_client) {
        var gui, loading, os;
        /*--------------
          Structure/HTML
          --------------*/
        loading = om.bf.make.loading();

        os = omega_client;
        gui = om.bf.make.box();
        gui.$.toggleClass('gui', true);
        gui.services = om.bf.make.menu(gui.$, {}, {
            name: 'services',
            multi_select: false,
            options_orient: 'left'
        });
        gui.services.canvas = gui.services._extend('middle', 'canvas');
        // add refresh link
        /*
        gui.services.refresh = om.bf.make.input.link(
            gui.services._box_left.$, {
                on_click: function (click_event) {
                    gui.init();
                },
                caption: 'refresh'
            }
        );
        */

        /*-------
          Methods
          ------- */
        gui.services.select = function (click_event) {
            var caption = $(click_event.target),
                service_name,
                service_config,
                loading = om.bf.make.loading(gui.$);
            service_name = caption.text();
            // fetch the service config
            gui.shed.fetch(
                'service_config',
                service_name,
                'os.config.get',
                {service: service_name},
                function (config) {
                    var diviner, menu_depth, ratio, win, colors, gen_color;
                    gen_colors = function (name) {
                        var i, j, chars, seed = '', colors;
                        // return two colors based on the name
                        if (name === '') {
                            throw new Error("name cannot be blank for color generation");
                        }
                        while (seed.length < 6) {
                            seed += name;
                        }
                        seed = String(parseInt(seed.substr(0, 6), 36)); // parse the chars as base 36 to get a base 10 number :P
                        if (seed.length === 0) {
                            throw new Error("failed to generate color seed");
                        }
                        while (seed.length < 12) {
                            seed += seed;
                        }
                        // reverse the order of the digits to improve entropy
                        seed = seed.split('').reverse();
                        // cap the brightness
                        for (i = 0; i < seed.length; i++) {
                            if (i % 2 === 0) {
                                seed[i] = parseInt((parseInt(seed[i], 10) / 10) * 5, 10) + 2;
                            }
                        }
                        seed = seed.join('');
                        return ['#' + seed.substr(0, 6), '#' + seed.substr(6, 6)];
                    };

                    service_config = config;
                    if (gui.services._options[service_name].diviner === undefined) {
                        // make up some color scheme for the service based on the name
                        colors = gen_colors(service_name);
                        // load in a diviner for this service
                        diviner = om.Diviner(
                            gui.services.canvas.$,
                            om.OmegaClient(
                                {
                                    url: config.omega.location,
                                    creds: {
                                        username: config.omega.nickname,
                                        password: config.omega.key
                                    }
                                }
                            ),
                            {gui_colors: colors}
                        );
                        // remove the logout item for this purpose
                        diviner.toolbar.$.find('.logout').remove();
                        // show it relative to where it is in the menu to spread things out
                        menu_depth = caption.parent('.om_menu_option').prevAll('.service').length;
                        win = $(window);
                        ratio = {
                            xy: win.width() / win.height(),
                            yx: win.height() / win.width()
                        };
                        diviner.$.css('left', '151px')
                            .css('top', '0px')
                            .css('height', '100%')
                            .css('right', '0px');
                        diviner.$.bind('click', diviner._raise);
                        diviner._draggable(diviner.toolbar.$, {
                            constraint: gui.services.canvas.$
                        });
                        /*
                        diviner.toolbar.$.bind('dblclick',
                            function (dbclick_event) {
                                diviner._resize_to(
                                    gui.services.canvas.$,
                                    {target: diviner.nav.$}
                                );
                                // loosen the constraints on the diviner so it can stretch
                                diviner.$.css('width', 'auto');
                                diviner.$.css('height', 'auto');
                            }
                        );
                        */
                        // show a menu to configure the service
                        //gui.services._options[service_name].menu = gui.services._options[service_name]._extend('bottom', 'menu');
                        //gui.services._options[service_name].menu.$.
                        diviner.$.bind('win_minimize.om', function () {
                            // unselect ourself
                            gui.services._options[service_name]._unselect();
                        });
                        diviner.$.bind('win_close.om', function () {
                            // unselect ourself and clear the diviner out of memory

                            gui.services._options[service_name].$.toggleClass('om_selected', false);
                            delete gui.services._options[service_name].diviner;
                        });
                        gui.services._options[service_name].diviner = diviner;
                    }
                    loading._remove();
                    gui.services._options[service_name].diviner._show();
                    // sometimes columns get collapsed when hidden, so make sure our sizing is right
                    gui.services._options[service_name].diviner.nav.resize_cols();
                    gui.services._options[service_name].diviner._raise();
                },
                function (error) {
                    om.bf.make.confirm(
                        gui.$, 
                        'Service Error',
                        error,
                        {modal: true}
                    );
                    loading._remove();
                }
            );
        };

        gui.services.unselect = function (click_event) {
            var caption = $(click_event.target),
                service_name;
            service_name = caption.text();
            gui.services._options[service_name].diviner._hide();
        };

        gui.init = function () {
            var loading = om.bf.make.loading(gui.services.$);
            // build the menu containin the services
            os.exec(
                'os.service_manager.get_config',
                {},
                function (config) {
                    var service, enabled;
                    gui.services._clear_options();
                    // load each service as a menu option
                    for (service in config.services) {
                        if (! config.services.hasOwnProperty(service)) {
                            continue;
                        }
                        gui.services._add_option(service, {
                            'class': 'service',
                            on_select: gui.services.select,
                            on_unselect: gui.services.unselect
                        });
                    }
                    loading._remove();
                },
                function (error) {
                    var msg = om.bf.make.confirm(
                        gui.$,
                        'Error',
                        error,
                        {modal: true}
                    );
                    loading._remove();
                }
            );
        };

        /* --------
           Bindings
           -------- */
        
        /* --------------
           Initialization
           -------------- */
        gui.shed = om.DataShed(os);
        gui.init();
        loading._remove();
        return gui;
    };
});

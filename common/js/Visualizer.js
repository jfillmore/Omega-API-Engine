/* omega - web client
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

(function (om) {
    om.Visualizer = om.doc({
        desc: 'Data dumping and visualization. Also aliased as "om.vis".',
        obj: {
            obj2html: om.doc({
                desc: 'Recursively iterate over an object and pretty-print it with HTML',
                params: {
                    data: {
                        desc: 'The data to pretty-print.'
                    },
                    args: {
                        desc: 'Options to set colors, indentation depth, etc',
                        type: 'object'
                    }
                },
                obj: function (data, args) {
                    var html = '',
                        data_type = typeof(data),
                        fade, key, str;
                    args = om.get_args({
                        depth: 1, // indentation depth
                        colors: ['#FFFFFF', '#FF6000'], // colors to fade between for obj keys
                        color_steps: 2,
                        add_spaces: false
                    }, args);
                    fade = om.cf.make.fade(args.colors, {steps: args.color_steps});
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
            })
        }
    });
    om.vis = om.Visualizer;
}(om));

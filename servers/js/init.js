/* omega - node.js server
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

var http = require('http');
var om = require('./omlib');
var omega = require('./omega');

// our configuration data, hard coded for now
conf = {
	iface: '192.168.1.3',
	port: 5608,
	verbose: true
};

// run the omega server
omega.Server(conf);

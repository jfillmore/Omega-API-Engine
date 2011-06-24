# omega - python server
# http://code.google.com/p/theomega/
# 
# Copyright 2011, Jonathon Fillmore
# Licensed under the MIT license. See LICENSE file.
# http://www.opensource.org/licenses/mit-license.php

import sys
import os

import jframe
import web_app

# TODO: a mode where you have a button on your web app that calls jf->setDebug and requests are auto-routed to the debug module, which is another webapp (that has a button to turn itself back off :D)-- root API user
def debug(environ, start_response, output):
	pairs = []
	for key in os.environ:
		pairs.append(key + ': ' + str(os.environ[key]))
	output = ''.join(((output), '\n\n==============================================\n', '\n'.join(pairs)))
	start_response('200 OK', [
		('Content-type', 'text/plain'),
		('Content-Length', str(len(output)))
		])
	return output

# TODO: session handling
def application(environ, start_response):
	# request debugging
	#global tmp
	#return debug(environ, start_response, tmp);
	try:
		# load in the hosted application class
		global jf
		# initialize ourselves on the first run
		if jf is None:
			jf = jframe.Jframe(web_app.application_class)
		jf.update_request_info(environ, start_response)
		return [jf.process_request()]
	except:
		e_type, e_value, e_tb = sys.exc_info()
		import traceback
		tb = traceback.format_exception(e_type, e_value, e_tb)
		message = 'Internal application error:\n%s' % '\n'.join(tb)
		start_response('500 Internal Error', [
			('Content-type', 'text/plain'),
			('Content-Length', str(len(message)))
			])
		return [message]
		
jf = None

# if run directly simulate being ran through the web server
if __name__ == "__main__":
	def start_response(http_status, http_response_headers):
		sys.stdout.write(http_status)
		sys.stdout.write(http_response_headers)
	print application(os.environ, start_response)

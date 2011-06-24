import sys
from jframe import config

class Jframe:
	"""JFrame Request"""
	config = None
	app = None

	# request specific stuff
	http_response_headers = [('Content-type', 'text/plain')]
	http_status = '200 OK'
	http_response = None
	http_start_response = None
	environ = None

	def __init__(self, app_class):
		self.config = config.Config()
		self.app = app_class(3)

	def update_request_info(self, environ, start_response):
		self.environ = environ
		self.http_start_response = start_response
	
	def process_request(self):
		self.http_response = self.get_output()
		self.http_response_headers.append(('Content-Length', str(len(self.http_response))))
		# TODO: handle default content-type - set based on API type
		self.http_response_headers.append(('Content-Type', 'text\html'))
		self.http_start_response(self.http_status, self.http_response_headers)
		return self.http_response
	
	def get_output(self):
		# determine the request type (SOAP? JSON? RAW? XML?)
		return '\n'.join(self.config.services) # self.app.foo(3)


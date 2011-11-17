#!/usr/bin/python -tt

# omega - python client
# http://code.google.com/p/theomega/
# 
# Copyright 2011, Jonathon Fillmore
# Licensed under the MIT license. See LICENSE file.
# http://www.opensource.org/licenses/mit-license.php


"""Omega client for talking to an Omega server."""

import pycurl
import urllib
import json
import re
import dbg
import StringIO
import os
import tempfile
from error import Error

class OmegaClient:
	"""Client for talking to an Omega Server."""
	_version = '0.2'
	_request_type = 'POST'
	_curl = None
	_hostname = None
	_folder = '/'
	_url = None
	_session_coookie = None
	_cookie_file = None
	_useragent = 'OmegaClient/0.2'

	def __init__(self, url = 'localhost', credentials = None, port = 5800, use_https = True):
		self._cookie_file = os.path.expanduser('~/.omega_cookie') # tempfile.NamedTemporaryFile()
		self._curl = pycurl.Curl()
		self.set_url(url)
		self.set_credentials(credentials)
		self.set_port(port)
		self.set_https(use_https)
		# TODO: python 2.7 supports an order tuple object we can use to preserve order :)
		self.encode = json.JSONEncoder().encode
		self.decode = json.JSONDecoder().decode
		# setup cookie jar
	
	def set_url(self, url):
		if url != '':
			self._url = url
			if url.find('/') == -1:
				self._hostname = url
				self._folder = ''
			else:
				# check for protocol
				if url.lower()[0:7] == 'http://':
					url = url[8:]
					self.set_https(false)
				elif url.lower()[0:8] == 'https://':
					url = url[9:]
					self.set_https(true)
				else:
					# some other protocol perhaps?
					if re.match('^\w+://', url):
						raise Exception('Only HTTP and HTTPS are supported protocols.')
				# do we have a port?
				match = re.match('^(\w+\.)*\w+(:(\d{0,5}))/?', url)
				if match:
					self.set_port(match.group(3))
					# chop out the port
					url = '/'.join((re.split(':\d+/?', url)))
				(self._hostname, self._folder) = url.split('/', 1)
				# force ourself to end in a slash
				if not self._folder.endswith('/'):
					self._folder = ''.join((self._folder, '/'))
		else:
			raise Exception('Invalid API service URL: %s.' % url)
		
	def get_url(self):
		return self._url

	def get_hostname(self):
		return self._hostname

	def get_folder(self):
		return self._folder
	
	def get_request_type(self):
		return self._request_type;
	
	def set_request_type(self, type):
		if type.upper() in ('POST', 'GET'):
			self._request_type = type
		else:
			raise Exception('The request must be either "GET" or "POST".')

	def set_credentials(self, creds):
		if type(creds).__name__ == 'dict':
			# make sure we have a user/pass
			if 'username' in creds and 'password' in creds:
				self._credentials = creds
			elif 'token' in creds:
				self._credentials = creds
			else:
				raise Exception("Invalid credentials. Keys of 'username'/'password' or 'token' expected, but were not found.")
		elif creds == None:
			self._credentials = creds
		else:
			raise Exception("Invalid credentials. Keys of 'username'/'password' or 'token' expected, but were not found.")

	def set_https(self, secure = True):
		if secure:
			self._use_https = True
		else:
			self._use_https = False

	def set_port(self, port):
		if port >= 0 and port <= 65535:
			self._port = port
		else:
			raise Exception('Invalid API service port: %i.' % port)
	
	def run(self, api, args = (), raw_response = False, full_response = False, get = None, post = None, files = None):
		# check and prep the data
		if api == '':
			raise Exception("Invalid service API: '%s'." %api)
		api = urllib.quote(api)
		curl = pycurl.Curl()
		data = [
			('OMEGA_ENCODING', (curl.FORM_CONTENTS, 'json')),
			('OMEGA_API_PARAMS', (curl.FORM_CONTENTS, self.encode(args)))
		]
		if self._credentials:
			data.append(('OMEGA_CREDENTIALS', (curl.FORM_CONTENTS, self.encode(self._credentials))))
		# include any extra post data
		if post:
			(name, value) = post.split('=', 1)
			data.append((name, (curl.FORM_CONTENTS, value)))
		if files:
			# add in our files to the data
			for name in files:
				data.append((name, (curl.FORM_FILE, files[name])))
		# figure our our URL and get args
		url = self._url
		if self._use_https:
			url = ''.join(('https://', self._hostname))
		else:
			url = ''.join(('http://', self._hostname))
		url = '/'.join((':'.join((url, str(self._port))), self._folder))
		url = '/'.join((url, api))
		if get:
			url = '?'.join((url, get))
		# fire away
		curl.setopt(curl.URL, url) 
		curl.setopt(curl.POST, 1)
		curl.setopt(curl.USERAGENT, self._useragent)
		curl.setopt(curl.COOKIEFILE, self._cookie_file)
		curl.setopt(curl.COOKIEJAR, self._cookie_file)
		if self._use_https:
			curl.setopt(curl.SSL_VERIFYPEER, 0) # TODO: don't always assume
			curl.setopt(curl.SSL_VERIFYHOST, 0) # TODO: don't always assume
		if data:
			curl.setopt(curl.HTTPPOST, data)
		else:
			curl.setopt(curl.POSTFIELDS, '&'.join(args))
		response = StringIO.StringIO()
		curl.setopt(curl.WRITEFUNCTION, response.write)
		curl.perform()
		response = response.getvalue()
		http_code = curl.getinfo(curl.HTTP_CODE)
		if http_code != 200:
			raise Exception("Failed to execute API %s: server returned HTTP code %s" % (api, str(http_code)))
		curl.close()
		if raw_response:
			return response
		else:
			# decode the response and check whether or not it was successful
			# TODO: check response encoding in header
			try:
				response = self.decode(response)
			except:
				raise Error('Failed to decode API response.', response)
			# check to see if our API call was successful
			if 'result' in response and response['result'] == False:
				if 'reason' in response:
					if full_response:
						raise Error('API "%s" failed.\n%s' % (urllib.unquote(api), dbg.obj2str(response)))
					else:
						raise Error(response['reason'])
				else:
					raise Error('API "%s" failed, but did not provide an explanation. Response: %s' % (api, response))
			else: 		
				if full_response:
					return response
				else:
					if 'data' in response:
						return response['data']
					else:
						return None

if __name__ == '__main__':
	import dbg
	dbg.pretty_print(OmegaClient());

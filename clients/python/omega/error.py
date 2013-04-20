#!/usr/bin/python

# omega - python client
# https://github.com/jfillmore/Omega-API-Engine
# 
# Copyright 2011, Jonathon Fillmore
# Licensed under the MIT license. See LICENSE file.
# http://www.opensource.org/licenses/mit-license.php


"""Exception handling with advanced options (data objs, alerting, etc)."""

# local
import dbg
from util import get_args

class Exception(Exception):
	def __init__(self, message, data = None, **args):
		my_args = {
			'email': None, # who to e-mail an error report to
			'email_bcc': None # who to bcc error report to
		}
		args = get_args(my_args, args);
		self.message = message;
		self.data = data;
		self._args = args;
		if my_args['email'] != None:
			self.send_report(my_args['email'], my_args['email_bcc'])
			
	def send_report(self, to, bcc = None):
		import time
		# generate the body
		body = [
			'Exception Report',
			time.strftime("%Y-%m-%d %H:%M:%S"),
			'------------------------------',
			self.message,
		]
		if self.data != None:
			body.push('\nData\n----')
			body.push(dbg.obj2str(self.data))
		body = body.join('\n')
		# send mail
		if type(to) == type(''):
			self.send_mail(to, body)
		elif type(to) == type([]):
			for email in to:
				self.send_mail(to, body)
	
	def send_mail(self, to, body):
		pass
		# TODO
	
	def __str__(self):
		return self.__repr__()

	def __repr__(self):
		str = self.message
		if self.data != None:
			str = '\n'.join([str, dbg.obj2str(self.data)])
		return str
		

#!/usr/bin/python

# omega - python client
# https://github.com/jfillmore/Omega-API-Engine
# 
# Copyright 2011, Jonathon Fillmore
# Licensed under the MIT license. See LICENSE file.
# http://www.opensource.org/licenses/mit-license.php

import re

"""Generic utility functions automatically loaded within omega core."""

def get_args(my_args = {}, args = {}, merge = False):
	'''Returns a dict of items in args found in my_args.'''
	for arg in args:
		value = args[arg]
		if arg in my_args or merge:
			my_args[arg] = value
	return my_args

def pyv(version):
	'''Returns whether or not the current interpreter is the version specified or newer.'''
	# e.g. >>> sys.version_info 
	#      (2, 6, 4, 'final', 0)
	import sys
	i = 0
	for num in version:
		if num > sys.version_info[i]:
			return False
		i += 1
	return True

def pretty_path(path, absolute = False):
    path = path.rstrip('/')
    if absolute:
        path = '/' + path
    regex = re.compile(r'/+')
    path = regex.sub('/', path)
    return path

def is_number(s):
    try:
        float(s)
        return True
    except ValueError:
        return False

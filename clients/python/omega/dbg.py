#!/usr/bin/python -tt

# omega - python client
# https://github.com/jfillmore/Omega-API-Engine
# 
# Copyright 2011, Jonathon Fillmore
# Licensed under the MIT license. See LICENSE file.
# http://www.opensource.org/licenses/mit-license.php


"""Uses python introspection to provide PHP-like "var_dump" functionality for debugging objects."""

import sys
import time
import types
import inspect
	
dark_colors = {
	'str': '0;37',
	'unicode': '0;37',
	'bool': '1;36',
	'int': '0;32',
	'float': '1;32',
	'NoneType': '0;36',
	'object': '0;36',
	'instance': '0;36',
	'module': '0;36',
	'classobj': '0;36',
	'builtin_function_or_method': '0;36',
	'ArgSpec': '0:36:40',
	'list': ['1;37', '1;33', '0;33', '1;31', '0;31'],
	'tuple': ['1;37', '1;33', '0;33', '1;31', '0;31'],
	'dict': ['1;37', '1;33', '0;33', '1;31', '0;31'],
	'bullet': '1;30',
	'seperator': '1;30'
}

def get_obj_info(obj, include_private = False):
	try:
		value = str(obj)
	except UnicodeEncodeException:
		value = repr(obj)
	obj_info = {
		'type': type(obj).__name__,
		'callable': callable(obj),
		'value': value,
		'repr': repr(obj),
		'description': str(getattr(obj, '__doc__', '')).strip()
	}
	# take a look at what it contains and build up description of what we've got
	if obj_info['type'] == 'function':
		obj_info['arg_spec'] = inspect.getargspec(obj)
	elif not obj_info['type'] in ('str', 'int', 'float', 'bool', 'NoneType', 'unicode', 'ArgSpec'):
		for key in sorted(dir(obj)):
			if key.startswith('__') and not include_private:
				continue
			item = getattr(obj, key)
			if inspect.ismethod(item):
				if not 'methods' in obj_info:
					obj_info['methods'] = {}
				obj_info['methods'][key] = {
					'description': str(item.__doc__)[0:64].strip(),
					'arg_spec': inspect.getargspec(item)
					}
			elif inspect.ismodule(item):
				if not 'modules' in obj_info:
					obj_info['modules'] = {}
				obj_info['modules'][key] = str(item.__doc__)[0:64].strip()
			elif inspect.isclass(item):
				if not 'classes' in obj_info:
					obj_info['classes'] = {}
				obj_info['classes'][key] = str(item.__doc__)[0:64].strip()
			else:
				if not 'properties' in obj_info:
					obj_info['properties'] = {}
				obj_info['properties'][key] = obj2str(item, short_form = True)
	return obj_info

def print_tb():
	import traceback
	tb = traceback.extract_stack()
	#tb.pop() # no need to show the last item, which is the line of code executing traceback.extract_stack()
	print '\n'.join([
		"\tTraceback (most recent call on bottom):",
		'\n'.join(['\t\t%s:%i, method "%s"\n\t\t\tLine: %s' % t for t in tb])
	])

def obj2str(obj, depth = 0, color = True, indent_char = ' ', indent_size = 4, inline = True, short_form = False):
	def shell_color(text, obj_color):
		if color:
			return '\033[%sm%s\033[0;0m' % (obj_color, text)
		else:
			return str(obj)

	"""Returns a formatted string, optionally with color coding"""
	def rdump(obj, depth = 0, indent_size = 4, inline = False, short_form = False):
		if short_form:
			return str(obj)[0:80 - (depth * indent_size)]
		obj_info = get_obj_info(obj)
		# indent ourselves
		dump = depth * (indent_size * indent_char)
		# see what we've got and recurse as needed
		if obj_info['type'] == 'list':
			if not len(obj):
				dump += shell_color(' (empty)', dark_colors['object']) + '\n'
			else:
				skip_next_indent = True
				for i in range(0, len(obj)):
					item = obj[i]
					item_info = get_obj_info(item)
					# handy any indentation we may need to do
					if skip_next_indent:
						skip_next_indent = False
					else:
						dump += depth * (indent_size * indent_char)
					# add in the key, cycling through the available colors based on depth
					dump += shell_color(i, dark_colors[obj_info['type']][(depth) % (len(dark_colors[obj_info['type']]))])
					# format it depending on whether we've nested list with any empty items
					if item_info['type'] in ('dict', 'tuple', 'list'):
						if not len(item):
							dump += rdump(item, 0, indent_size, True)
						else:
							dump += '\n' + rdump(item, depth + 1, indent_size, True)
					else:
						dump += rdump(item, 1, 1);
		elif obj_info['type'] == 'dict':
			if not len(obj):
				dump += shell_color(' (empty)', dark_colors['object'])
			else:
				skip_next_indent = True
				for key in sorted(obj):
					item = obj[key]
					item_info = get_obj_info(item)
					# handy any indentation we may need to do
					if skip_next_indent:
						skip_next_indent = False
					else:
						dump += depth * (indent_size * indent_char)
					# add in the key, cycling through the available colors based on depth
					dump += shell_color(key, dark_colors[obj_info['type']][(depth) % (len(dark_colors[obj_info['type']]))])
					# add in a bullet
					dump += shell_color(':', dark_colors['bullet'])
					# format it depending on whether we've nested list with any empty items
					if item_info['type'] in ('dict', 'tuple', 'list'):
						if not len(item):
							dump += rdump(item, 0, indent_size, True)
						else:
							dump += '\n' + rdump(item, depth + 1, indent_size, True)
							if item_info['type'] == 'tuple':
								dump += '\n'
					else:
						dump += rdump(item, 1, 1);
		elif obj_info['type'] == 'tuple':
			if not len(obj):
				dump += shell_color(' (empty)', dark_colors['object'])
			else:
				dump += shell_color('(', dark_colors['bullet'])
				dump += ', '.join([str(item)[0:32] for item in sorted(obj) if item != ()])
				dump += shell_color(')', dark_colors['bullet'])
		elif obj_info['type'] == 'str':
			dump += shell_color(obj, dark_colors[obj_info['type']])
		elif obj_info['type'] == 'unicode':
			dump += shell_color(obj, dark_colors[obj_info['type']])
		elif obj_info['type'] == 'bool':
			dump += shell_color(str(obj), dark_colors[obj_info['type']])
		elif obj_info['type'] == 'NoneType':
			dump += shell_color('(none/null)', dark_colors[obj_info['type']])
		elif obj_info['type'] == 'int':
			dump += shell_color(str(obj), dark_colors[obj_info['type']])
		elif obj_info['type'] == 'float':
			dump += shell_color(str(obj), dark_colors[obj_info['type']])
		elif obj_info['type'] == 'object':
			dump += shell_color('(object)', dark_colors[obj_info['type']])
		elif obj_info['type'] == 'instance':
			dump += rdump(obj_info, depth)
		elif obj_info['type'] == 'module':
			dump += rdump(obj_info, depth)
		elif obj_info['type'] == 'function':
			dump += rdump(obj_info, depth)
		elif obj_info['type'] == 'classobj':
			dump += rdump(obj_info, depth)
		elif obj_info['type'] == 'builtin_function_or_method':
			dump += rdump(obj_info, depth)
		elif obj_info['type'] == 'ArgSpec':
			dump += '\n' + rdump({
					'args': obj.args,
					'varargs': obj.varargs,
					'keywords': obj.keywords,
					'defaults': obj.defaults,
				}, depth + 1, inline = True)
		else:
			dump += rdump(obj_info, depth)
		if not inline:
			dump += '\n'
		return dump # hack hack hack!
	string = rdump(obj, depth, indent_size, inline, short_form)
	if string.endswith('\n'):
		string = string[0:-1]
	return string 

def pause():
	"""Loads pdb and breaks execution."""
	import pdb
	pdb.set_trace()

def pretty_print(obj, depth = 0, color = True, indent_char = ' ', indent_size = 4):
	"""Pretty-prints the contents of the list, tupple, sequence, etc."""
	print obj2str(obj, depth, color, indent_char, indent_size, True)

pp = pretty_print

if __name__ == '__main__':
	sys.stdout.write('Pretty print:\n');
	pp(pp, depth = 1)

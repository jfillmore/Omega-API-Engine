#!/usr/bin/python -tt

# omega - python shell
# https://github.com/jfillmore/Omega-API-Engine
# 
# Copyright 2011, Jonathon Fillmore
# Licensed under the MIT license. See LICENSE file.
# http://www.opensource.org/licenses/mit-license.php


"""Shell for interacting with a RESTful server."""

import re
import os
import sys
import os.path
import readline
import shlex # simple lexical anaysis for command line parsing
import subprocess # for shell commands

from error import Exception
import util
import dbg

class Shell:
    """Shell for interacting with omega client."""
    _version = '0.2'
    # a list of our internal commands
    _api_cmds = (
        'get',
        'post',
        'put',
        'patch',
        'delete',
        'exec'
    )
    _cmds = {
        'set': {},
        'reload': {},
        'config': {},
        'help': {},
        'quit': {},
        'sh': {}
    }
    _env = {
        'cwd': '/', # where in the URL we are operating
        'last_cwd': '/',
        'histfile': None
    }
    # client to talk to omega service
    client = None
    # our default arguments
    args = {
        'color': sys.stdout.isatty(),
        'full_response': False,
        'raw_response': False,
        'headers': {},
        'verbose': False
    }

    def __init__(self, client, args = {}):
        self.env(
            'histfile',
            os.path.join(os.path.expanduser('~'), '.om_history')
        )
        self.client = client
        self.args = util.get_args(self.args, args)

    def start(self):
        # test our client first and load info about the API
        # disabled until rest support works: //TODO readline.parse_and_bind('tab: complete')
        # disabled until rest support works: //TODO readline.set_completer(self.cmd_complete)
        #self.set_edit_mode(self.args['edit_mode'])
        # load our history
        try:
            readline.read_history_file(self.env('histfile'))
        except:
            pass
        # run APIs until the cows come home
        try:
            while self.parse_cmd(raw_input(self.get_prompt())):
                pass
        except KeyboardInterrupt, e:
            pass
        except EOFError, e:
            pass
        except Exception, e:
            sys.stderr.write('! ' + error + '\n')
            pass
        self.stop()
    
    def stop(self):
        # save our history
        readline.write_history_file(self.env('histfile'))
    
    def get_prompt(self):
        # : using colors messes up term spacing w/ readline history support
        # http://bugs.python.org/issue12972
        # likely fixed in 2.7+?
        if self.args['color'] and sys.version_info >= (2, 7):
            prompt = ''.join([
                '\033[0;31m',
                '[',
                '\033[1;31m',
                self.env('cwd'),
                '\033[0;31m',
                '] ',
                '\033[0;37m',
                '> ',
                '\033[0;0m'
            ])
        else:
            prompt = ' '.join([
                self.env('cwd'),
                '> '
            ])
        return prompt
    
    def set_edit_mode(self, mode):
        # TODO: figure out why 'vi' doesn't let you use the 'm' key :/
        modes = ['vi', 'emacs']
        if mode in modes:
            readline.parse_and_bind(''.join(['set', 'editing-mode', mode]))
            self.args['edit_mode'] = mode
        else:
            raise Exception(''.join(['Invalid editing mode: ', mode, ' Supported modes are: ', ', '.join(modes), '.']))
    
    def print_help(self):
        sys.stderr.write(
'''COMMANDS
   help                   This information
   [HTTP-verb] API ARGS   Perform an API call
   cd                     Change the base URL (e.g. /cd site/foo.com; cd ../bar.com)
   quit                   Adios!
   set                    Set configuration options
   config                 List current configuration infomation
   sh CMD                 Run a BASH shell command

OPTIONAL OPTIONS (each may be specified multiple times)
   -F, --file FILE        File to add to request
   -G, --get GET_DATA     GET data to include (e.g. foo=bar&food=yummy)
   -P, --post POST_DATA   Extra POST data to add to request

OTHER OPTIONS (may also be set via 'set' command)
   -f, --full             Return full response instead of just data
   -r, --raw              Print response data in raw form
   -v, --verbose          Print verbose debugging info to stderr
   -c, --color            Colorize return output (unless returning raw data)
   > FILE                 Write API response to specified file
   >> FILE                Append API response to specified file

DEPRECATED COMMANDS
   exec API ARGS          Execute an old-style Omega API

EXAMPLE COMMANDS:
> get site/foo.com -v
> post site -j domain=foo.com
> cd site/foo.com

''')

    def _split_cmd(self, cmd):
        # I love you python for having this module. <3 & =3
        return shlex.split(cmd)

    def parse_cmd(self, cmd):
        cmd = cmd.strip()
        # collect up the command parts
        parts = self._split_cmd(cmd)
        # our API info
        cmd = None
        api = None
        api_params = {}
        cmd_args = []
        args = {
            'verbose': False,
            'headers': {},
            'color': self.args['color'],
            'full_response': self.args['full_response'],
            'raw_response': self.args['raw_response'],
            'FILES': [],
            'GET': [],
            'POST': []
        }
        stdout_redir = None
        redir_type = None
        i = 0
        while i < len(parts):
            part = parts[i]
            if len(part) == 0:
                pass
            elif part == '>' or part[0] == '>' or part == '>>':
                # output redirection! woot
                if part == '>' or parts == '>>':
                    i += 1
                    if part == '>':
                        redir_type = 'w'
                    else:
                        redir_type = 'a'
                    if i == len(parts):
                        raise Exception("Missing file path to output result to.")
                    stdout_redir = parts[i]
                else:
                    if len(part) > 1 and part[0:2] == '>>':
                        stdout_redir = part[2:]
                        redir_type = 'a'
                    else:
                        stdout_redir = part[1:]
                        redir_type = 'w'
            elif part == '-F' or part == '--file':
                i += 1
                if i == len(parts):
                    raise Exception("Missing value for file to upload.")
                # collect up the name
                if parts[i].find('=') == -1 or parts[i].find('&') != -1:
                    raise Exception("Invalid file name=file_path pair.")
                (name, path) = parts[i].split('=', 1)    
                # make sure the file exists
                if not os.path.isfile(path):
                    raise Exception("Unable to either read or locate file '%s." % path)
                args['FILES'][name] = path
            elif part == '-G' or part == '--get':
                i += 1
                if i == len(parts):
                    raise Exception("Missing GET name=value pair.")
                # make sure we have a valid pair
                if parts[i].find('=') == -1 or parts[i].find('&') != -1:
                    raise Exception("Invalid GET name=value pair.")
                args['GET'].append(parts[i])
            elif part == '-P' or part == '--post':
                i += 1
                if i == len(parts):
                    raise Exception("Missing POST name=value pair.")
                # make sure we have a valid pair
                if parts[i].find('=') == -1 or parts[i].find('&') != -1:
                    raise Exception("Invalid POST name=value pair.")
                args['POST'].append(parts[i])
            elif part == '-c' or part == '--color':
                args['color'] = True
            elif part == '-v' or part == '--verbose':
                args['verbose'] = True
            elif part == '-j' or part == '--json':
                i += 1
                if i == len(parts):
                    raise Exception("Missing value for JSON API params.")
                try:
                    api_params = self.client.decode(parts[i])
                except Exception, e:
                    sys.stderr.write('Invalid JSON:' + e.message)
                    return
            elif part == '-f' or part == '--full':
                args['full_response'] = True
            elif part == '-r' or part == '--raw':
                args['raw_response'] = True
            else:
                # we always pick up the command first
                if cmd == None:
                    cmd = part.lower()
                elif cmd in self._api_cmds and not api:
                    # collect the API -- unless this is a internal command
                    api = util.pretty_path(self.parse_path(part))
                else:
                    # anything else is a parameter
                    if cmd in self._api_cmds:
                        # get the name/value
                        api_params = self.parse_param(part, api_params)
                    else:
                        cmd_args.append(part)
            i += 1
        # get any redirection ready, if we can
        # FIXME: if the API fails the file shouldn't be written to
        if stdout_redir != None:
            try:
                file = open(stdout_redir, redir_type)
            except IOException, e:
                sys.stderr.write('! ' + error + '\n')
                return False
        else:
            file = None
        # run the command or API
        if cmd == None or len(cmd) == 0:
            # no command, just do nothing
            return {
                'response': None,
                'result': True
            }
        elif cmd in self._api_cmds:
            # run an API
            if args['verbose']:
                sys.stderr.write('+ API=%s, PARAMS=%s, OPTIONS=%s\n' %
                    (api, self.client.encode(api_params), self.client.encode(args))
                )
            try: 
                if cmd == 'exec':
                    # TODO: fully deprecate and then remove
                    response = self.client.run(
                        api,
                        api_params,
                        args['raw_response'],
                        args['full_response'],
                        '&'.join(args['GET']),
                        '&'.join(args['POST']),
                        args['FILES']
                    )
                else:
                    response = self.client.request(
                        cmd,
                        api,
                        api_params,
                        args['raw_response'],
                        args['full_response'],
                        args['GET'],
                        args['headers'],
                        args['verbose']
                    )
                result = True
            except Exception, e:
                result = False
                response = e.message
        else:
            # run an internal command
            try:
                self.run_cmd(cmd, cmd_args)
                response = None
                if response == False:
                    return False
                result = True
            except Exception, e:
                response = e.message
                result = False
        # print the response
        # TODO: check response type
        self._print_response(
            result,
            response,
            color = args['color'],
            raw_response = args['raw_response'],
            stdout_redir = stdout_redir,
            redir_type = redir_type,
            file = file
        )
        return {
            'result': result,
            'response': response
        }

    def _get_uri(self, api):
        # absolute path?
        if api.startswith('/'):
            return util.pretty_path(api)
        else:
            # otherwise, parse against the current dir for the actual path
            return util.pretty_path(self.parse_path(api));

    def _print_response(self, result, response, **args):
        if result:
            if 'raw_response' in args and args['raw_response']:
                if 'stdout_redir' in args and args['stdout_redir'] != None:
                    args['file'].write(response)
                else:
                    sys.stdout.write(response)
            else:
                if response != None:
                    if 'stdout_redir' in args and args['stdout_redir'] != None:
                        args['file'].write(dbg.obj2str(response, color = False))
                        args['file'].close()
                    else:
                        if 'color' in args:
                            dbg.pretty_print(response, color = args['color'])
                        else:
                            dbg.pretty_print(response, color = False)
                        #sys.stdout.write('\n')
        else:
            sys.stderr.write('! ' + response + '\n')
            if 'data' in args:
                if 'color' in args:
                    dbg.pretty_print(args['data'], color = args['color'], stream = sys.stderr)
                else:
                    dbg.pretty_print(args['data'], color = False, stream = sys.stderr)

    def run(self, method, api, params = {}, args = {}):
        args = util.get_args(self.args, args, True)
        api = self._get_uri(api)
        if args['verbose']:
            sys.stderr.write('+ API=%s, PARAMS=%s, OPTIONS=%s\n' % (api, self.client.encode(params), self.client.encode(args)))
        retval = {}
        # check to see if we need to parse params (e.g. got them from CLI)
        if args['parse_params']:
            api_params = {};
            for param in params:
                api_params = self.parse_param(param, api_params);
            params = api_params
        try: 
            get = None
            if method is None:
                if params:
                    method = 'POST'
                else:
                    method = 'GET'
            if 'GET' in args:
                if isinstance(args['GET'], basestring):
                    get = args['GET']
                else:
                    get = '&'.join(args['GET'])
            if method.upper() == 'EXEC':
                response = self.client.run(
                    api,
                    params,
                    args['raw_response'],
                    args['full_response'],
                    get,
                    '&'.join(args['POST']),
                    args['FILES']
                )
            else:
                response = self.client.request(
                    method,
                    api,
                    params,
                    args['raw_response'],
                    args['full_response'],
                    get,
                    args['headers'],
                    args['verbose']
                )
            result = True
            error = None
        except Exception, e:
            result = False
            response = e.message
        self._print_response(
            result,
            response,
            raw_response = args['raw_response'],
            color = args['color']
        )
        # handle the API response
        return {
            'result': result,
            'reason': response
        }

    def env(self, key, value = None):
        key = key.lower()
        if key in self._env:
            if value == None:
                return self._env[key]
            else:
                # remember the last dir when changing it
                if key == 'cwd':
                    self._env['last_cwd'] = self._env['cwd']
                self._env[key] = value
                return value

    def parse_param(self, str, params = {}):
        param_parts = str.split('=', 1)
        param = param_parts[0]
        # no value given? treat it as a boolean
        if len(param_parts) == 1:
            if param.startswith('!'):
                value = False
            else:
                value = True
            param = param.lstrip('!')
        else:
            value = param_parts.pop()
            # we we need to json-decode the value?
            if param.endswith(':'): # e.g. 'foo:={"bar":42}'
                value = self.client.decode(value)
                param = param.rstrip(':')
        # check the name to see if we have a psuedo array
        # (e.g. 'foo.bar=3' => 'foo = {"bar": 3}')
        if param.find('.') == -1:
            params[param] = value
        else:
            # break the array into the parts
            p_parts = param.split('.')
            key = p_parts.pop()
            param_ptr = params
            for p_part in p_parts:
                if not p_part in params:
                    params[p_part] = {}
                param_ptr = params[p_part]
            param_ptr[key] = value
        return params

    def parse_path(self, path = ''):
        # no path? go to our last working dir
        if not len(path):
            return self._env['last_cwd'];
        # make sure the path is formatted pretty
        path = util.pretty_path(path)
        # parse the dir path for relative portions
        if path.startswith('/'):
            cur_dirs = ['/']
        else:
            cur_dirs = self.env('cwd').split('/');
        dirs = path.split('/')
        for dir in dirs:
            if dir == '' or dir == '.':
                # blanks can creep in on absolute paths, no worries
                continue
            rel_depth = 0
            if dir == '..':
                if not len(cur_dirs):
                    raise Exception ("URI is out of bounds: \"%s\"." % (path))
                cur_dirs.pop()
            else:
                cur_dirs.append(dir)
        # always end up with an absolute path
        return util.pretty_path('/'.join(cur_dirs) + '/', True)

    def run_cmd(self, cmd, params = []):
        if cmd == 'set':
            # break the array into the parts
            for str in params:
                pair = self.parse_param(str)
                param = pair.keys()[0]
                val = pair[param]
                if not (param in self.args):
                    raise Exception('Unrecognized parameter: "%s". Enter "%shelp" or "%sh" for help.' % (param, self._cmd_char, self._cmd_char))
                if param in ['color', 'full_response', 'raw_response', 'verbose', 'headers']:
                    # just so there is no confusion on these...
                    if val in ['1', 'true', 'True']:
                        val = True
                    elif val in ['0', 'false', 'False']:
                        val = False
                    self.args[param] = val
                elif param == 'edit_mode':
                    self.set_edit_mode(val)
                else:
                    raise Exception("Unrecognized configuration option: " + param + ".")
        elif cmd == 'cd':
            path = ''
            if len(params):
                path = params[0]
            self.env('cwd', self.parse_path(path))
        elif cmd == 'config':
            dbg.pp(self.args)
            sys.stdout.write('\n')
        elif cmd == 'quit':
            return False
        elif cmd == 'test':
            for item in ['marian.s', 'omega.config.get -', 'marian.gallery.find --', 'marian.', 'marian', 'marian.galler', 'omega.c', 'marian.style.sear ', 'marian.style.search wher', 'marian.style.search where="id < 3" ', 'marian.style.search where="id < 3" count=10 offset=50']:
                sys.stdout.write('---- [' + item + '] ----' + '\n')
                parts = self._split_cmd(item)
                dbg.pp(self._get_completions(self._split_cmd(item)))
        elif cmd == 'help' or cmd == '*':
            self.print_help()
        elif cmd == 'sh':
            proc = subprocess.Popen(params);
            sys.stdout.write('\n')
        else:
            raise Exception('Unrecognized command: "%s". Enter "help" for help.' % (cmd))
        return True

    #def _api2tree(self, api):
    #    tree = {
    #        'branches': {},
    #        'methods': {},
    #    }
    #    if 'branches' in api:
    #        for branch in api['branches']:
    #            tree['branches'][branch] = self._api2tree(api['branches'][branch])
    #    if 'methods' in api:
    #        for method in api['methods']:
    #            method = api['methods'][method]
    #            if 'accessible' in method and method['accessible']:
    #                tree['methods'][method['name']] = method['params']
    #    return tree

    #def _traverse(self, branches, tree = None):
    #    if not branches:
    #        raise Exception('Unable to traverse empty list of branches.')
    #    if tree is None:
    #        if branches[0] == 'omega':
    #            tree = self._cmd_tree['omega']
    #        else:
    #            tree = self._cmd_tree[self.api_info['name']]
    #        branches = branches[1:]
    #    for name in branches:
    #        if not name in tree['branches']:
    #            raise Exception("The branch %s was not found in %s." % (branch, '.'.join(branches)))
    #        tree = tree['branches'][name]
    #    return tree

    #def _is_api(self, api):
    #    branches = api.split('.')
    #    if len(branches) == 1:
    #        return False
    #    else:
    #        method = branches.pop()
    #    tree = self._traverse(branches)
    #    return method in tree['methods']

    def _get_completions(self, tokens):
        return
        # disabled for now -- needs to be updated for RESTful APIs
        #
        # TODO: pull edit area dynamically for better parsing
        # if we don't have any thing in our command it could be anything in the top of the tree
        #if len(tokens) == 0:
        #    return ['/help', self.api_info['name'], 'omega']
        #elif len(tokens) == 1 and not self._is_api(tokens[0]):
        #    branches = tokens[0].split('.')
        #    if len(branches) == 1:
        #        # api or command?
        #        if tokens[0] in self._cmds:
        #            comps = self._cmds.keys()
        #            text = tokens[0]
        #            text = text[1:]
        #            return [cmd for cmd in comps if cmd.startswith(text)]
        #        else:
        #            comps = [self.api_info['name'], 'omega']
        #            return [opt + '.' for opt in comps if opt.startswith(tokens[0])]
        #    else:
        #        # what branch are we trying to auto complete?
        #        text = branches.pop()
        #        if len(branches):
        #            branch = self._traverse(branches)
        #        else:
        #            branch = self._cmd_tree[branches[0]]
        #        path = '.'.join(branches)
        #        # no text? list 'em all
        #        if text == '':
        #            return branch['branches'].keys() + branch['methods'].keys()
        #        else:
        #            branches = [''.join([path, '.', opt, '.']) for opt in branch['branches'].keys()if opt.startswith(text)]
        #            methods = [''.join([path, '.', opt]) for opt in branch['methods'].keys()if opt.startswith(text)]
        #            return branches + methods
        #else:
        #    def get_def_val(val):
        #        val_type = type(val)
        #        if val_type == type(True):
        #            if val:
        #                return 1
        #            else:
        #                return 0
        #        elif val_type == type(''):
        #            return val
        #        else:
        #            return str(val)
        #    branches = tokens[0].split('.')
        #    method = branches.pop()
        #    # we're past the API and into the parameters
        #    # first figure out which branch we're in
        #    branch = self._traverse(branches)
        #    req_params = None
        #    methods = branch['methods'].keys()
        #    if not method in methods:
        #        return None
        #        raise Exception('Failed to find method ' + method + ' in ' + tokens[0] + '.', {
        #            'methods': methods,
        #            'branch_path': branches
        #        })
        #    params = branch['methods'][method]
        #    param_names = [param['name'] for param in params] + ['='.join([param['name'], get_def_val(self.param['default_val'])]) for param in params if 'default_val' in param]
        #    # one token = no params yet, so return all
        #    if len(tokens) == 1:
        #        text = ''
        #    else:
        #        # handle arguments
        #        text = tokens[-1]
        #        if text.startswith('-'):
        #            if text.startswith('--'):
        #                comps = ['--full', '--raw', '--verbose', '--color']
        #            else:
        #                comps = ['-f', '-r', '-v', '-c', '-F', '-G', '-P']
        #            return [opt for opt in comps if opt.startswith(text)]
        #        # remove any completed params from our param names
        #        for token in tokens[1:-1]:
        #            eq_index = token.find('=')
        #            if eq_index > -1:
        #                token = token[0:eq_index]
        #            # already got it, so no need to offer it to auto complete
        #            if token in param_names:
        #                del param_names[param_names.index(token)]
        #        ## see what param we're typing, and if we have it already
        #        eq_index = text.find('=')
        #        if eq_index > -1:
        #            text = text[0:eq_index]
        #        # are we already a param?
        #        if text in param_names:
        #            del param_names[param_names.index(text)]
        #            text = ''
        #    return [opt + '=' for opt in param_names if opt.startswith(text)]

    def cmd_complete(self, text, state):
        try:
            # we need to compare what we've currently got to where in the command we're at (e.g. API branch/method, arguments, etc)
            input = readline.get_line_buffer().strip()
            parts = self._split_cmd(input)
            nodes = self._get_completions(parts) + [None]
            return nodes[state]
        except Exception, e:
            dbg.pp('Exception: ' + e.message)
            # errors to this handler are otherwise suppressed, but someone else might call us
            raise e

    
if __name__ == '__main__':
    import dbg
    dbg.pretty_print(Shell())

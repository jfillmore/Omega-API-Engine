#!/usr/bin/python -tt

# omega - python client
# https://github.com/jfillmore/Omega-API-Engine
# 
# Copyright 2011, Jonathon Fillmore
# Licensed under the MIT license. See LICENSE file.
# http://www.opensource.org/licenses/mit-license.php


"""Omega client for talking to an Omega server."""

import pycurl
import urllib
import httplib
try:
    import json
except:
    import simplejson
    json = simplejson

import re
import sys
import dbg
import StringIO
import os
import tempfile
import base64
import hashlib
import socket

from error import Exception
import util

class OmegaClient:
    """Client for talking to an Omega Server."""
    http = None
    hostname = None
    folder = '/'
    url = None
    # TODO? session_coookie = None
    cookie_file = None
    use_https = True
    port = 443

    def __init__(self, url = 'https://localhost:443/', credentials = None):
        self.cookie_file = os.path.expanduser('~/.omega_cookie') # tempfile.NamedTemporaryFile()
        self.set_credentials(credentials)
        self.set_url(url)
        # TODO: python 2.7 supports an order tuple object we can use to preserve order :)
        self.encode = json.JSONEncoder().encode
        self.decode = json.JSONDecoder().decode
        # setup cookie jar
    
    def set_url(self, url):
        if url != '':
            self.url = url
            if url.find('/') == -1:
                self.hostname = url
                self.folder = ''
            else:
                # check for protocol
                if url.lower()[0:7] == 'http://':
                    url = url[7:]
                    self.set_https(False)
                elif url.lower()[0:8] == 'https://':
                    url = url[8:]
                    self.set_https(True)
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
                else:
                    if self.use_https:
                        self.set_port(443);
                    else:
                        self.set_port(80);
                (self.hostname, self.folder) = url.split('/', 1)
                # force ourself to end in a slash
                if not self.folder.endswith('/'):
                    self.folder = ''.join((self.folder, '/'))
        else:
            raise Exception('Invalid API service URL: %s.' % url)
        # having set everything, init httplib
        if self.use_https:
            self.http = httplib.HTTPSConnection(self.hostname, self.port)
        else:
            self.http = httplib.HTTPConnection(self.hostname, self.port)
        self.http.cookies = False
        
    def get_url(self):
        return self.url

    def get_hostname(self):
        return self.hostname

    def get_folder(self):
        return self.folder
    
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
            self.use_https = True
        else:
            self.use_https = False

    def set_port(self, port):
        port = int(port)
        if port >= 0 and port <= 65535:
            self.port = port
        else:
            raise Exception('Invalid API service port: %s.' % port)
    
    def get(self, api, params, opts):
        return self.request('GET', api, params, opts);

    def post(self, api, params, opts = {}):
        return self.request('POST', api, params, opts);

    def put(self, api, params, opts = {}):
        return self.request('PUT', api, params, opts);

    def options(self, api, params, opts = {}):
        return self.request('OPTIONS', api, params, opts);

    def delete(self, api, params, opts = {}):
        return self.request('DELETE', api, params, opts);

    def request(self, method, api, params = (), raw_response = False, \
        full_response = False, get = None, headers = {}, \
        verbose = False, no_format = False\
        ):
        '''REST API invoker'''
        # check and prep the data
        if method is None or method == '':
            method = 'GET'
        method = method.upper()
        if api == '' or api == None:
            api = '/'
        api = urllib.quote(api)
        if self._credentials:
            creds = self._credentials
            md5 = hashlib.md5();
            md5.update(':'.join(
                [creds['username'], creds['password']]
            ))
            # TODO: don't always assume base64/md5
            headers['Authentication'] = 'Basic ' + base64.b64encode(
                md5.hexdigest()
            )
        http = self.http
        # figure our our URL and get args
        url = self.url
        headers['Content-type'] = 'application/json'
        headers['Accept'] = 'application/json'
        url = util.pretty_path('/'.join(('', self.folder, api)), True)
        if get:
            url = '?'.join((url, get))
        if method == 'GET':
            url = '?'.join((url, '&'.join([
                '='.join(
                    (urllib.quote(name), urllib.quote(str(params[name])))
                ) for name in params
            ])))
            data = None
        else:
            data = self.encode(params)
        # fire away
        if verbose:
            if self.use_https:
                proto = 'https'
            else:
                proto = 'http'
            sys.stderr.write(
                '# Request: %s %s://%s:%d%s, params: "%s", headers: "%s", cookies: "%s"\n' %
                ((method, proto, self.hostname, self.port, url, data, str(headers), str(http.cookies)))
            )
        #http.request(method, url, data, headers)
        # be willing to try again if the socket got closed on us (e.g. timeout)
        tries = 0
        max_tries = 3
        response = None
        while tries < max_tries and response is None:
            tries += 1
            try:
                # start the request
                http.putrequest(method, url)
                # send our headers
                for hdr, value in headers.iteritems():
                    http.putheader(hdr, value);
                # and our cookies too!
                if http.cookies:
                    [http.putheader('Cookie', value) for value in http.cookies]
                # write the body
                header_names = headers.fromkeys([k.lower() for k in headers])
                if data:
                    body_len = len(data)
                    if body_len:
                        http.putheader('Content-Length', str(body_len))
                http.endheaders()
                if data:
                    http.send(data)
                # get our response back from the server and parse
                response = http.getresponse()
            except socket.error, v:
                http.connect()
            except:
                http.close()
        if response is None:
            raise Exception('HTTP request failed and could not be retried.')
        # see if we get a cookie back
        response_headers = str(response.msg).split('\n');
        cookies = [c.split(': ')[1].split('; ')[0] for c in response_headers if c.startswith('Set-Cookie: ')]
        if cookies:
            # note that we ignore the path
            if verbose:
                for cookie in cookies:
                    sys.stderr.write('# Response Cookie: %s\n' % (cookie))
            http.cookies = cookies
        if verbose:
            sys.stderr.write(
                '# Response Status: %s %s\n# Response Headers: %s\n' %
                (response.status, response.reason, self.encode(
                    str(response.msg).split('\r\n')
                ))
            )
        content_type = response.getheader('Content-Type') or '';
        response_data = response.read();
        # handle any errors based on status code
        if response.status < 200 or response.status >= 300:
            if content_type.startswith("application/json"):
                try:
                    result = self.decode(response_data)
                    # format our response data for any printing later
                    if no_format:
                        response_data = json.dumps(
                            result,
                            ensure_ascii = True
                        )
                    else:
                        response_data = json.dumps(
                            result,
                            ensure_ascii = True,
                            sort_keys = True,
                            indent = 4
                        )
                except:
                    result = {"result": False, "reason": response_data}
                if 'reason' in result:
                    error = result['reason']
                else:
                    error = 'An unknown error has occurred.'
            else:
                result = response_data
                error = response_data
            if full_response:
                if raw_response:
                    msg = response_data
                else:
                    msg = dbg.obj2str(result)
                raise Exception('API "%s" failed (%d %s)\n%s' %
                    (urllib.unquote(api), response.status, response.reason, msg))
            else:
                if raw_response:
                    msg = response_data
                else:
                    msg = error
                raise Exception('API "%s" failed (%d %s)\n%s' %
                     (api, response.status, response.reason, msg))
        # return a raw response if needed; otherwise decode if JSON
        if not content_type.startswith("application/json"):
            return response_data
        try:
            result = self.decode(response_data)
        except:
            raise Exception('Failed to decode API result\n' + response_data)
        # check to see if our API call was successful
        if 'result' in result and result['result'] == False:
            if 'reason' in result:
                if full_response:
                    raise Exception('"%s" failed (%d %s):\n%s' %
                        (urllib.unquote(api), response.status, response.reason, dbg.obj2str(result)))
                else:
                    raise Exception(result['reason'])
            else:
                raise Exception('API "%s" failed\n%s' % (api, result))
        else:         
            # all is well, return the data portion of the response (unless everything is requested)
            if full_response:
                if raw_response:
                    if no_format:
                        result = json.dumps(result, sort_keys = True) + "\n"
                    else:
                        result = json.dumps(result, sort_keys = True, indent = 4) + "\n"
            else:
                if raw_response:
                    if 'data' in result:
                        if no_format:
                            result = self.encode(result['data']) + "\n"
                        else:
                            result = json.dumps(result['data'], sort_keys = True, indent = 4) + "\n"
                    else:
                        result = '{}'
                else:
                    if 'data' in result:
                        result = result['data']
                    else:
                        result = None
            return result

if __name__ == '__main__':
    import dbg
    dbg.pretty_print(OmegaClient());

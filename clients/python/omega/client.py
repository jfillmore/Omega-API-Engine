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

from error import Exception
import util

class OmegaClient:
    """Client for talking to an Omega Server."""
    _version = '0.2'
    _http = None
    _hostname = None
    _folder = '/'
    _url = None
    _session_coookie = None
    _cookie_file = None
    _useragent = 'OmegaClient/0.2'

    def __init__(self, url = 'localhost', credentials = None, port = 5800, use_https = True):
        self._cookie_file = os.path.expanduser('~/.omega_cookie') # tempfile.NamedTemporaryFile()
        self.set_https(use_https)
        self.set_credentials(credentials)
        self.set_port(port)
        self.set_url(url)
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
        # having set everything, init httplib
        if self._use_https:
            self._http = httplib.HTTPSConnection(self._hostname, self._port)
        else:
            self._http = httplib.HTTPConnection(self._hostname, self._port)
        self._http.cookie = False
        
    def get_url(self):
        return self._url

    def get_hostname(self):
        return self._hostname

    def get_folder(self):
        return self._folder
    
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
    
    # old style API invoker
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
        if http_code < 200 or http_code >= 300:
            # see if we got json data back
            try:
                decoded = self.decode(response)
                if 'reason' in decoded:
                    error = decoded['reason']
                else:
                    error = response
            except:
                error = response
            raise Exception("Server returned HTTP code %s. Response:\n%s" %
                (str(http_code), str(error)))
        curl.close()
        if raw_response:
            return response
        else:
            # decode the response and check whether or not it was successful
            # TODO: check response encoding in header
            try:
                response = self.decode(response)
            except:
                raise Exception('Failed to decode API response.', response)
            # check to see if our API call was successful
            if 'result' in response and response['result'] == False:
                if 'reason' in response:
                    if full_response:
                        raise Exception('API "%s" failed.\n%s' %
                            (urllib.unquote(api), dbg.obj2str(response)))
                    else:
                        raise Exception(response['reason'])
                else:
                    raise Exception('API "%s" failed, but did not provide an explanation. Response: %s' % (api, response))
            else:         
                if full_response:
                    return response
                else:
                    if 'data' in response:
                        return response['data']
                    else:
                        return None


    def get(self, api, params, opts):
        return self.request('GET', api, params, opts);

    def post(self, api, params, opts = {}):
        return self.request('POST', api, params, opts);

    def put(self, api, params, opts = {}):
        return self.request('PUT', api, params, opts);

    def delete(self, api, params, opts = {}):
        return self.request('DELETE', api, params, opts);

    def request(self, method, api, params = (), raw_response = False, full_response = False, get = None, headers = {}, verbose = False):
        '''New REST-friendly API invoker'''
        # check and prep the data
        if method is None or method == '':
            method = 'GET'
        method = method.upper()
        if api == '':
            raise Exception("Invalid service API: '%s'." %api)
        api = urllib.quote(api)
        if self._credentials:
            creds = self._credentials
            md5 = hashlib.md5();
            md5.update(':'.join(
                [creds['username'], creds['password']]
            ))
            headers['Authentication'] = 'Basic ' + base64.b64encode(
                md5.hexdigest())
        http = self._http
        # figure our our URL and get args
        url = self._url
        headers['Content-type'] = 'application/json'
        headers['Accept'] = 'application/json'
        if http.cookie:
            headers['Cookie'] = http.cookie;

        url = '/'.join(('', self._folder, api))
        if get:
            url = '?'.join((url, get))
        data = self.encode(params)
        # fire away
        if verbose:
            if self._use_https:
                proto = 'https'
            else:
                proto = 'http'
            sys.stdout.write(
                '+ %s %s://%s:%d/%s, params: "%s", headers: "%s"\n' %
                ((method, proto, self._hostname, self._port, url, data, str(headers))))
        http.request(method, url, data, headers)
        response = http.getresponse()
        # see if we get a cookie back
        response_headers = str(response.msg).split('\n');
        cookie = [c.split(': ')[1].split('; ')[0] for c in response_headers if c.startswith('Set-Cookie: ')]
        if cookie:
            # note that we ignore the path
            cookie = cookie[0]
            if verbose:
                sys.stdout.write('+ Cookie [%s]\n' % (cookie))
            http.cookie = cookie
        #if verbose:
            #sys.stdout.write(
                #'+ Status [%d], Reason [%s], headers [%s]' %
                #(response.status, response.reason, response.msg)
            #)
        if raw_response:
            return response.read()
        else:
            # decode the response and check whether or not it was successful
            # TODO: check response encoding in header
            try:
                response_data = response.read();
                result = self.decode(response_data)
            except:
                raise Exception('Failed to decode API result:\n' + response_data)
            # check to see if our API call was successful
            # the http status code and result should always be in sync, but if either are off call it a failure
            if ('result' in result and result['result'] == False) or \
                (response.status < 200 or response.status >= 300):
                if 'reason' in result:
                    if full_response:
                        raise Exception('"%s" failed (%d %s):\n%s' %
                            (urllib.unquote(api), response.status, response.reason, dbg.obj2str(result)))
                    else:
                        raise Exception(result['reason'])
                else:
                    raise Exception('API "%s" failed, but did not provide an explanation. Response: %s' % (api, result))
            else:         
                if full_response:
                    return result
                else:
                    if 'data' in result:
                        return result['data']
                    else:
                        return None

if __name__ == '__main__':
    import dbg
    dbg.pretty_print(OmegaClient());

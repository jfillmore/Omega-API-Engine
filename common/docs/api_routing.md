API Routing - RESTful
=====================

1. Encoding
-----------
API commands are JSON-encoded, sent within the body of the request (or as escaped parameters within the URL; e.g. "GET /foo/bar?a=3"). Parameters can also be embedded within the URL, as per the discussion below.


2. APIs Resolution
------------------
Class files that extend "OmegaRESTful" communicate the routes (delgations to another class file) and handlers (local mappings of URI fragments to methods).

First, to determine which API service the request is for Omega looks for a FastCGI parameter named "OMEGA_SERVICE". The API configuration file is loaded configuration information from '/var/www/omega/servers/php/data/$OMEGA_SERVICE/config' and the default class file is initialized or deserialized if a session already exists.

If an API has no constructors it will be automatically initialized before processing the underlying API request. However, if there are any required parameters the client must sent a POST request to 



3. Authentication
-----------------
If the API requests contains a cookie by the name 'COMCURE_SESSION' then Omega will grab the serialized session information and proceed directly to routing and calling the appropriate method with the given API parameters.

Otherwise, a session is loaded fresh by calling "Comcure::__constructor()". To initialize a session a POST request must be sent to "/api/cc" with the parameters listed in the constructor. Authentication is performed against HoneyBadger and the basic user information is saved within the API session information. A cookie will be sent back to the user for subsequent API calls.

The user may log out via the API "GET /api/logout".


4. Routing & Parameters
-----------------------
When the Comcure API is initialized it contains several public members that host the API code for the variour branches of the API. Each of these objects extends (sometimes through multiple levels) 'OmegaRESTful'. There are two magic methods that control this behavior:

4a. "_get_routes()"

    This function should return an associated array mapping URI fragments. For example:

        public function _get_routes() {
            return array(
                '/site' => $this->site,
                'account/' => 'account',
                'support' => 'support',
                '/:foo/bar/' => 'foobar'
            );
        }

    The beginning and final slashes are optional, and the fragments may contain multiple levels. The left-hand side can contain tokens like ":id" or "*path" which will be used to automatically collect API parameters for the method that will eventually be invoked. Each level (e.g. '/foo/bar' would be two levels) can contain only one token (e.g. '/:foo-:bar/' is currently invalid). If the first character is '*' then all remaining portions of the URI are matched (e.g. '/foo/*bar' would match '/foo/1/2/abc/').

    The right-hand side values can either be a string of the API branch name within that class file (e.g. the constuctor would have '$this->account = new Account();'), or a direct reference to the object (e.g. "$this->site").
    
    If the beginning of the request URI matches any of the items below then the API will be re-routed to the specified branch. This process is repeated again (minus the portion of the URI already matched). When no routes match, "_get_handlers()" is used to resolve the HTTP method and remaining URI to a local method in that class file.

    For example, suppose the request "GET /api/site/example.com" is made.

        a1. The API base is "/api", so as per section 1 the API will be handled by "Comcure.class.php", not "ComcureAdmin.class.php".
        a2. Omega compares "/site/example.com" to the routes in "Comcure.class.php". Although '*path' matches, '/site' is a direct match and is selected to handle the API.
        a3. The process is repeated on 'Site.class.php' (or whatever the class file name is), but "/example.com" is used in the look-up, as the "/site/" portion was matched already. If no routes are found for '/example.com' then Omega calls "_get_handlers()".

    You may also define a route with the name "@pre_route" (with a value of the function name or reference). If this is defined then the specified method will be called before following a route or invoking handler within that class file. This allows the pre-route handler to handle any special authentication, perform proxying, etc.


4b. "_get_handlers()"

    When no more routes can be traversed Omega can resolve the HTTP method and remaining URI fragment to a local method within that class file. The "_get_handlers" method should return a two-dimensional array, mapping URI fragments to local methods. For example:

        public function _get_handlers() {
            return array(
                'get' => array(
                    '/' => 'get_account'
                ),
                'post' => array(
                    '/reset_pass' => 'reset_password',
                    '/share' => 'share_account'
                ),
                'patch' => array(
                    '/update' => 'update_account'
                ),
                'delete' => array(
                    '/' => 'delete_account',
                    '/share' => 'unshare_account'
                )
            );
        }

    The top-level of the array is a list of all HTTP methods supported within this API branch. Each of those contains a list of handlers for that HTTP method (which are case insensitive).

    The left-hand and right-hand parsing rules for the handlers are the same as "_get_routes()" with one exception. Handlers must match the entire remaining URI (e.g. 'POST /reset_pass' would not match 'POST /reset_pass/junk'). Routes only need to match part of the URL to perform delegation (e.g. '/site' matches '/site/example.com'). If the API cannot be resolved to a handler then a 404 response will be returned to the user instead.

    For example, assume the API request "POST /api/account/share" is made.

        b1. The "/api" portion of the URI is "used up" to resolve to Comcure.
        b2. The "/account" portion of the URI is used to route from "Comcure.class.php" to "Account.class.php".
        b3. The remaining portion of the URI is "/share", which has an entry within the POST handlers that resolves to the local method 'update_account'.


Note that API handlers can be added/updated/removed within the class files and all changes will take affect immediately. However, the pattern for adding new routes typically involves declaring a new public class member that is initialized in the constructor (e.g. Comcure::__constructor() initializes $this->site from "new Site($this)"). Because the constructor only runs when the user logs in you must clear your session (e.g. clear cookie or log out) before the new API branch is initialized.


5. API Parameters
-----------------

API parameters are collected two ways. First, API parameters my be embedded directly within the URI (e.g. 'GET /site/:domain/snapshot'). Additionally, API parameters may be sent via JSON-encoded POST data or via '?foo=bar' when using GET.

When processing APIs Omega will collect up all API parameters, combine them with other parameters given. They are then resolved by name with the parameters requested (honoring any defaults given) by the method specified in the API handler. For example, assume we have the method:

    public function update_site($domain, $props = array()) {
        ...
    }

Lets also assume we resolved 'POST /api/site/example.com/host {"props":{"username":"foobar"}}'  as follows:

    1. "/api" resolves to "Comcure.class.php".
    2. "/site" resolves to "Site.class.php" via the route "/site".
    3. "POST /example.com/host" matches the POST handler "/:domain/host" and 'example.com' is stored as '$domain'.
    4. Site::update_site() is invoked, passing 'example.com' as $domain, and $props as "array("username" => "foobar")" after decoding the JSON data.



API Routing - Deprecated
========================
By default any class file that implements the interface "OmegaApi" has all public methods exported (except those starting with one or more underscores) as part of the API. Any public properties that are other OmegaApi objects are used to generate the API tree.

For example, assume we have the classes "Record", and "Track":

    <?php

    class Record implements OmegaApi

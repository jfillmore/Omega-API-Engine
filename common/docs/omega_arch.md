Omega Architecture
==================

Omega handles the initialization of your application and maps RESTful URIs and parameters to your class methods

A collection of useful methods, sub-services (e.g. for authentication, logging, etc) and interfaces to the HTTP request and response are all available through Omega. When Omega starts it initializes itself as the variable '$omega' (and '$om' for short). There are two ways to access it within your application:

    public function do_something() {
        global $om;
    }


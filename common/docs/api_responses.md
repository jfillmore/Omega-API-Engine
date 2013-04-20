API Responses
=============

1. Response Format
------------------
By default all responses are JSON encoded. The format is:

    {
         // did the API succeed?
        "result": boolean,
         // the return value (string, array, object, etc) from the API call. May contain error debugging information if an API is not in production mode.
        "data": undefined,
         // if the API failed an error message will be provided here
        "reason": string,
        // if an API is NOT in production mode and Omega caught "spilled" messages (e.g. warnings from PHP) they will be listed here (instead of interferring with sending headers)
        "spillage": string
    }

Additionally, the HTTP response code will be 2xx for any successful requests. Errors will use 500 unless the failing API specifies a specific header number.

The response format (and content-type header) can be set manually. The response encoding set using '$om->response->set_encoding("raw")' and then setting the 'Content-Type' header to whatever you like (see section "3. Headers" below).


2. Returning Data
-----------------
Any data returned by a method invoked to handle an API will be used for the body of the API respone. By default this will be a JSON-encoded object, but you can output data of any encoding as needed. For example:

    public function maths($a, $b = 2) {
        return array(
            'sum' => $a + b,
            'diff' => $a - b,
            'mean' => ($a + b) / 2
        );
    }

Alternatively, the response can be forced to a specific value by invoking the method '$om->response->force($value)'.

    public function is_positive($num) {
        $om = Omega::get();
        $om->force->($num > 0);
        return "this string won't be seen by anyone";
    }

Doing so causes '$value' to be returned, regardless of any method return values. If an API constructor requires parameters (and thus explicit initialization) this can also be used to provide a response value.


3. Headers
----------


4. Throwing Errors
------------------

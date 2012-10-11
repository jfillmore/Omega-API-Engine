Master vs RESTful Branches
--------------------------
The "master" branch contains the old, deprecated style of making API calls. API calls are all either GET or POST operations with the encoding information and parameters sent either via GET parameters or as POST form data.

Overview of Client Differences
------------------------------
The Python command-line interface (and javascript OmegaClient object) can handle both styles.

Overview of Server Differences
------------------------------
Instead of using the routing methods defined in OmegaRESTful, the physical structure of the application is used to generate the API. The file "api_rouding.md" has more information on this.

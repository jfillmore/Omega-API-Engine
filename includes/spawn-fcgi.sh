#!/bin/sh -u

PHP_CGI=$(which php-cgi) || PHPFCGI=$(which php5-cgi) || PHPFCGI=$(which php)
SPAWN_FCGI=$(which spawn-fcgi)
OS_USER=jkf
CHILDREN=1
OS_GROUP=jkf
ADDRESS=127.0.0.1
PORT=5859

OPTIONS="-p $PORT -a $ADDRESS -u $OS_USER -g $OS_GROUP -C $CHILDREN -P /var/run/spawn-fcgi.pid -- $PHP_CGI"

$SPAWN_FCGI $OPTIONS

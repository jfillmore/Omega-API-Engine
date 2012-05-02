<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


class OmegaLib extends OmegaRESTful implements OmegaApi {
    /** Camel-cases a word by splitting the word up into clusters of letters, capitalizing the first letter of each cluster.
        expects: word=string
        returns: string */
    public function _camel_case($word) {
        $return = '';
        foreach (preg_split('/[^a-zA-Z]+/', $word) as $hump) {
            $return .= ucfirst($hump);
        }
        return $return;
    }

    /** Flattens a camel-cased word (e.g. fooBar, FooBar) to a lower-case representation (e.g. foobar), optionally inserting an underscore before capital letters (e.g. foo_bar).
        expects: word=string, add_cap_cap
        returns: string */
    public function _flatten($str, $add_cap_gap = false) {
        // force the first character to be lower
        // QQ... lcfirst is only in php 5.3.0
        $str = strtolower(substr($str, 0, 1)) . substr($str, 1);
        // add the cap gap if requested
        if ($add_cap_gap) {
            $str = preg_replace('/([A-Z])/', '_$1', $str);
        }
        // condense spaces/underscores to a single underscore
        // and strip out anything else but alphanums and underscores
        return strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '', preg_replace('/( |_)+/', '_', $str)));
    }

    /** Executes a shell command, possibly writing $stdin to the command, returning the contents of stdout and stderr. Throws an exception if the return value is non-zero. THE COMMAND BEING EXECUTED WILL NOT BE ESCAPED. USE WITH CAUTION.
        expects: cmd=string, stdin=string, env=array
        returns: object */
    public function _exec($cmd, $stdin = null, $env = null) {
        $pipe_info = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
            );
        $pipes = array();
        if ($env == null) {
            $proc = proc_open($cmd, $pipe_info, $pipes, '/');
        } else {
            $proc = proc_open($cmd, $pipe_info, $pipes, '/', $env);
        }
        if (! is_resource($proc)) {
            throw new Exception("Failed to create shell.");
        }
        // write our input to the pipe
        if ($stdin != null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        // read the result
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $ret_val = proc_close($proc);
        if ($ret_val != 0) {
            throw new Exception("Command execution failed with return value $ret_val. Read '$stdout' from stdout, '$stderr' from stderr.");
        }
        return array('stdout' => $stdout, 'stderr' => $stderr);
    }

    /** Executes a shell command as another user, passing the command to su via STDIN to avoid escaping. Defaults to using /bin/bash and does not use a login shell. Returning the contents of stdout and stderr. Throws an exception if the return value is non-zero.
        expects: user=string, cmd=string, env=array, shell=string, login_shel=boolean
        returns: object */
    public function _su($user, $cmd, $env = null, $shell = '/bin/bash', $login_shell = false) {
        if (! preg_match('/^[a-zA-Z\.\-]+$/', $user)) {
            throw new Exception("Invalid user name: '$user'.");
        }
        $pipe_info = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w')
        );
        if ($login_shell) {
            $login_shell = '-';
        } else {
            $login_shell = '';
        }
        $pipes = array();
        if ($env == null) {
            $proc = proc_open("cat | su $login_shell '$user' -s '$shell'", $pipe_info, $pipes, '/');
        } else {
            $proc = proc_open("cat | su $login_shell '$user' -s '$shell'", $pipe_info, $pipes, '/', $env);
        }
        if (! is_resource($proc)) {
            throw new Exception("Failed to create shell for $user.");
        }
        // write our command to the pipe
        fwrite($pipes[0], $cmd);
        fclose($pipes[0]);
        // read the result
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $ret_val = proc_close($proc);
        if ($ret_val != 0) {
            throw new Exception("Command execution failed with return value $ret_val. Read '$stdout' from stdout, '$stderr' from stderr.");
        }
        return array('stdout' => $stdout, 'stderr' => $stderr);
    }

    /** Return the default arg if set, otherwise use corresponding value in args. */
    public function _get_args($defaults, $args) {
        if ($args === null) {
            $my_args = $defaults;
        } else if (is_array($defaults) && is_array($args)) {
            $my_args = array();
            foreach ($defaults as $name => $default) {
                if (isset($args[$name])) {
                    $my_args[$name] = $args[$name];
                } else {
                    $my_args[$name] = $default;
                }
            }
        } else {
            throw new Expeception('Either default arguments or given arguments are not an array or null.');
        }
        return $my_args;
    }

    /** Abort execution, dumping structure of supplied object. Arguments are passed to thrown OmegaException for notifications/etc.
        expects: obj=object, args=object */
    public function _die($obj, $args = null) {
        $args = $this->_get_args(array(
            'alert' => false
        ), $args);
        $err = var_export($obj, true);
        throw new OmegaException($err, $obj, $args);
    }

    /** Returns a cleaned up version of a path (e.g. condense multiple slashes into one, trims trailing slashes). May optionally also force to be absolute.
        expects: path=string, absolute=boolean
        returns: string */
    public function _pretty_path($path, $absolute = false) {
        $path = rtrim($path, '/');
        if ($absolute) {
            $path = '/' . $path;
        }
        $path = preg_replace('/\/+/', '/', $path);
        return $path;
    }
}

?>

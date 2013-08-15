<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/* Handy methods. Underscore versions exist for backwards compatibility. */
class OmegaLib extends OmegaRESTful implements OmegaApi {
    /** Camel-cases a word by splitting the word up into clusters of letters, capitalizing the first letter of each cluster.
        expects: word=string
        returns: string */
    static public function camel_case($word) {
        $return = '';
        foreach (preg_split('/[^a-zA-Z]+/', $word) as $hump) {
            $return .= ucfirst($hump);
        }
        return $return;
    }
    public function _camel_case($word) {
        return OmegaLib::camel_case($word);
    }

    /** Flattens a camel-cased word (e.g. fooBar, FooBar) to a lower-case representation (e.g. foobar), optionally inserting an underscore before capital letters (e.g. foo_bar).
        expects: word=string, add_cap_cap
        returns: string */
    static public function flatten($str, $add_cap_gap = false) {
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
    public function _flatten($str, $add_cap_gap = false) {
        return OmegaLib::flatten($str, $add_cap_gap);
    }

    /** Executes a shell command, possibly writing $stdin to the command, returning the contents of stdout and stderr. Throws an exception if the return value is non-zero. THE COMMAND BEING EXECUTED WILL NOT BE ESCAPED. USE WITH CAUTION.
        expects: cmd=string, stdin=string, env=array, ignore_errors=boolean
        returns: object */
    static public function exec($cmd, $stdin = null, $env = null, $ignore_errors = false) {
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
        if ($ret_val != 0 && ! $ignore_errors) {
            $stdout2 = substr(trim($stdout), 0, 256);
            $stderr2 = substr(trim($stderr), 0, 256);
            if (trim($stdout) != $stdout2) {
                $stdout2 .= '...';
            }
            if (trim($stderr) != $stderr2) {
                $stderr2 .= '...';
            }
            throw new Exception("Command execution failed with return value $ret_val. Read '$stdout2' from stdout, '$stderr2' from stderr.");
        }
        return array('stdout' => $stdout, 'stderr' => $stderr, 'retval' => $ret_val);
    }
    public function _exec($cmd, $stdin = null, $env = null) {
        return OmegaLib::exec($cmd, $stdin, $env);
    }

    /** Executes a shell command as another user, passing the command to su via STDIN to avoid escaping. Defaults to using /bin/bash and does not use a login shell. Returning the contents of stdout and stderr. Throws an exception if the return value is non-zero.
        expects: user=string, cmd=string, env=array, shell=string, login_shel=boolean
        returns: object */
    static public function su($user, $cmd, $env = null, $shell = '/bin/bash', $login_shell = false) {
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
    public function _su($user, $cmd, $env = null, $shell = '/bin/bash', $login_shell = false) {
        return OmegaLib::su($user, $cmd, $env, $shell, $login_shell);
    }

    /** Return the default arg if set, otherwise use corresponding value in args. */
    static public function get_args($defaults, $args, $merge = false) {
        if ($args === null) {
            $my_args = $defaults;
        } else if (is_array($defaults) && is_array($args)) {
            if ($merge) {
                // merging? overwrite all of our defaults with the args and we're done
                return array_merge($defaults, $args);
            }
            // otherwise only accept values named within our defaults
            $my_args = array();
            foreach ($defaults as $name => $default) {
                if (isset($args[$name])) {
                    $my_args[$name] = $args[$name];
                } else {
                    $my_args[$name] = $default;
                }
            }
        } else {
            throw new Exception('Either default arguments or given arguments are not an array or null.');
        }
        return $my_args;
    }
    public function _get_args($defaults, $args, $merge = false) {
        return OmegaLib::get_args($defaults, $args, $merge);
    }

    /** Abort execution, dumping structure of supplied object. Arguments are passed to thrown OmegaException for notifications/etc.
        expects: obj=object, args=object */
    static public function _die($obj, $args = null) {
        $args = OmegaLib::get_args(array(
            'alert' => false
        ), $args);
        $err = var_export($obj, true);
        throw new OmegaException($err, $obj, $args);
    }

    /** Returns a cleaned up version of a path (e.g. condense multiple slashes into one, trims trailing slashes). May optionally also force to be absolute.
        expects: path=string, absolute=boolean
        returns: string */
    static public function pretty_path($path, $absolute = false) {
        $path = rtrim($path, '/');
        if ($absolute) {
            $path = '/' . $path;
        }
        $path = preg_replace('/\/+/', '/', $path);
        return $path;
    }
    public function _pretty_path($path, $absolute = false) {
        return OmegaLib::pretty_path($path, $absolute);
    }

    /** Because PHP's array_merge sucks on assoc arrays with numbers as keys. */
    static public function merge($a1, $a2) {
        foreach ($a2 as $k => $v) {
            $a1[$k] = $v;
        }
        return $a1;
    }

    static public function random($length = 64, $symbols = false) {
        $char_pool = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
            'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w',
            'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
            'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W',
            'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        if ($symbols) {
            $char_pool = array_merge($char_pool, array(
                ',', '_', '-', '&', '%', '^', '$', '#', '@', '!', '+'
            ));
        }
        $random = '';
        for ($i = 0; $i < $length; $i++) {
            $random .= $char_pool[rand(0, count($char_pool)-1)];
        }
        return $random;
    }

    /** Validate and convert an epoch, PHP time description, or SQL date into an epoch. */
    static public function to_time($date) {
        if (OmegaTest::int_non_neg($date)) {
            // already an epoch
            return $date;
        } else {
            return strtotime($date);
            if ($ts === -1 || $ts === false) {
                throw new Exception("Unrecognized date: '$date'. Please provide a validate epoch, date, etc.");
            }
        }
    }

    /** Validate and convert an epoch, PHP time description, or SQL date into a MySQL date string (e.g. 2013-05-30 13:30:01). */
    static public function mysql_date($date) {
        if (OmegaTest::int_non_neg($date)) {
            return date("Y-m-d H:i:s", $date);
        } else if (OmegaTest::datetime($date)) {
            return $date;
        } else {
            $ts = strtotime($date);
            if ($ts === -1 || $ts === false) {
                throw new Exception("Unrecognized date: '$date'. Please provide a validate epoch, date, etc.");
            }
            return date("Y-m-d H:i:s", $ts);
        }
    }
}


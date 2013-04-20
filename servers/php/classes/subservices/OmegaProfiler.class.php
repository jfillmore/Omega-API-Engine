<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

/** System stats about the process running the Omega Server. */
class OmegaProfiler extends OmegaSubservice implements OmegaApi {
    /** Returns the username, UID and GID of the service.
        returns: object */
    public function who_am_i() {
        return array(
            'username' => get_current_user(),
            'uid' => getmyuid(),
            'gid' => getmygid()
        );
    }

    /** Returns the current and peak (if supported by PHP) memory usage (in KB) of this service instance.
        returns: object */
    public function get_mem_usage() {
        $mem_info = array(
            'pid' => getmypid(),
            'current' => intval(memory_get_usage() / 1024),
            'peak' => null
            );
        if (function_exists('memory_get_peak_usage')) {
            $mem_info['peak'] = intval(memory_get_peak_usage() / 1024);
        }
        return $mem_info;
    }

    /** Returns the service process resource stats (PID, swaps, page faults, user CPU time in milliseconds).
        returns: object */
    public function get_resource_stats() {
        $usage = getrusage();
        return array(
            'pid' => getmypid(),
            'swaps' => $usage['ru_nswap'],
            'page_faults' => $usage['ru_majflt'],
            'user_cpu_time' => $usage['ru_utime.tv_usec'] / 1000000
        );
    }
}

?>

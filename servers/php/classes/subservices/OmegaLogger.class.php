<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/** Record API calls and log messages/data with simple search capabilities. */
class OmegaLogger extends OmegaSubservice {
    private $buffer;
    private $log;
    private $data;
    private $log_dir;
    private $log_aborted;
    private $verbosity;
    private $verbose_opts = array(
        'api_params' => 'The API parameters',
        'api_data' => 'The request URL, GET/POST data',
        'api_environ' => 'PHP SERVER and ENVIRON data.',
        'app_dump' => 'Dump of API/application state.',
        'data_verbosity' => 'Verbosity level for logging data.'
    );

    public function __construct() {
        global $om;
        $this->log_dir = OmegaConstant::data_dir . '/' . $this->_localize('logs');
        $this->mkdir_r($this->log_dir);
        $this->buffer = '';
        $this->log = array();
        $this->data = array();
        $this->log_aborted = false;
        try {
            $this->verbosity = $om->config->get('omega.logger.verbosity');
        } catch (Exception $e) {
            $this->verbosity = array();
        }
    }

    private function mkdir_r($path) {
        if (! is_dir($path)) {
            if (! @mkdir($path, 0755, true)) {
                throw new Exception("Failed to create omega service log directory '$path'.");
            }
        }
    }

    private function buffer_write($data) {
        $this->buffer .= $data;
    }

    /** Returns a list of the options that can be set to manipulate log verbosity.
        returns: array */
    public function get_verbosity_options() {
        return $this->verbose_opts;
    }

    /** Return the log verbosity for the request.
        returns: number */
    public function get_verbosity() {
        return $this->verbosity;
    }
    
    /** Set the log verbosity for the request. See 'get_verbocity_options' for list of options.
        expects: verbosity=array */
    public function _set_verbosity($verbosity) {
        // older applications use a bit-based system, which we can translate
        if (is_numeric($verbosity)) {
            $bits = str_split(strrev(decbin($verbosity)));
            $items = array();
            if (isset($bits[0]) && $bits[0]) {
                $items[] = 'api_params';
            }
            if (isset($bits[1]) && $bits[1]) {
                $items[] = 'api_data';
            }
            if (isset($bits[2]) && $bits[2]) {
                $items[] = 'api_data';
            }
            if (isset($bits[3]) && $bits[3]) {
                $items[] = 'app_dump';
                $items[] = 'api_environ';
            }
            $verbosity = $items;

        }
        // coerce into an array
        if (! is_array($verbosity)) {
            $verbosity = array($verbosity);
        }
        $errors = array();
        $options = array();
        $keys = array_keys($this->verbose_opts);
        foreach ($verbosity as $item) {
            $item = strtolower($item);
            if (! in_array($item, $keys)) {
                $errors[] = "Verbosity option '$item' not recognized.";
            } else {
                $options[] = $item;
            }
        }
        if (count($errors)) {
            throw new Exception(join(' ', $errors));
        }
        $this->verbosity = $options;
    }
    
    /** Validates the subservice configuration.
        returns: boolean */
    public function validate_config($config) {
        return parent::validate_config($config);
    }

    /** Causes any logged information to be discarded, preventing logging from occurring unless new log requests are made. */
    public function _abort_log() {
        $this->buffer = '';
        $this->log = array();
        $this->data = array();
        $this->log_aborted = true;
    }

    /** Writes the log file to permanent storage, seperating errors out from successful requests.
        expects: successful=boolean, verbosity=string */
    public function commit_log($successful = true, $verbosity = null) {
        global $om;
        // record the time
        $start_time = time();
        if ($verbosity !== null) {
            $this->_set_verbosity($verbosity);
        } else {
            $verbosity = $this->get_verbosity();
        }
        // determine the log file path based on the current date
        $log_path = $this->log_dir . '/' . @date('Y');
        $this->mkdir_r($log_path);

        // Lead with writing the date, user, and API/method called.
        $this->buffer_write(@date('[Y-m-d H:i:s]', $start_time)); // e.g. 2009-04-05 13:50:59
        $this->buffer_write(' ' . $om->request->get_api());
        if ($om->subservice->is_enabled('authority')) {
            $this->buffer_write(' - ' . $om->subservice->authority->authed_username);
        }
        $this->buffer_write(', ' . $_SERVER['REMOTE_ADDR']);
        if ($successful) {
            $this->buffer_write(' (okay)');
        } else {
            $this->buffer_write(' (failed)');
        }
        $this->buffer_write("\n");
        // write out our log entries
        foreach ($this->log as $log) {
            if ($log['verbosity'] >= $this->verbosity) {
                if ($log['result']) {
                    $this->buffer_write("\t* " . $log['entry'] . "\n");
                } else {
                    $this->buffer_write("\t! " . $log['entry'] . "\n");
                }
            }
        }
        // aborted logs don't get data stored for privacy/security reasons
        if (! $this->log_aborted) {
            // and look for any log data
            if (isset($verbosity['data_verbosity'])) {
                $data_verbosity = $verbosity['data_verbosity'];
            } else {
                $data_verbosity = 0;
            }
            if ($this->data != null) {
                foreach ($this->data as $label => $meta) {
                    if ($meta['verbosity'] >= $data_verbosity) {
                        $this->buffer_write("\t$label: " . json_encode($meta['data']) . "\n");
                    }
                }
            }
            // see if we should include any other information about the request based on the log verbosity
            if (in_array('api_params', $verbosity)) {
                $this->buffer_write("\tParameters:\t");
                $this->buffer_write(json_encode($om->request->get_api_params()) . "\n");
            }
            if (in_array('api_data', $verbosity)) {
                $this->buffer_write("\tRequest URI:\t");
                $this->buffer_write($_SERVER['REQUEST_URI'] . "\n");
                $this->buffer_write("\tGET:\t");
                $this->buffer_write(json_encode($_GET) . "\n");
                $this->buffer_write("\tPOST:\t");
                $this->buffer_write(json_encode($_POST) . "\n");
            }
            if (in_array('api_environ', $verbosity)) {
                $this->buffer_write("\tSERVER:\t");
                $this->buffer_write(json_encode($_SERVER) . "\n");
                $this->buffer_write("\tENV:\t");
                $this->buffer_write(json_encode($_ENV) . "\n");
            }
            if (in_array('app_dump', $verbosity)) {
                $this->buffer_write("\tApplication State:\t");
                $this->buffer_write(json_encode($om) . "\n");
            }
        }
        // and finally write ourselves out
        // open and lock the log file
        $log_file = $log_path . '/' . strtolower(@date('m-F'));
        $file_handle = @fopen($log_file, 'a');
        if ($file_handle === false) {
            throw new Exception("Failed to open service log file '$log_file'.");
        }
        if (flock($file_handle, LOCK_EX) === false) {
            throw new Exception("Failed to lock service log file '$log_file'.");
        }
        if (fwrite($file_handle, $this->buffer) === false) {
            throw new Exception("Failed to write service log contents to '$log_file'.");
        }
        // close ourselves
        flock($file_handle, LOCK_UN);
        fclose($file_handle);
        // abort the log, having comitted it to storage
        $this->_abort_log();
    }
    
    /** Adds data to be written to the request log. If success is set to false then the entry will be be noted as having been a failure.
        expects: entry=string, success=boolean */
    public function log($entry, $success = true, $verbosity = 0) {
        $this->log[] = array('entry' => $entry, 'result' => $success, 'verbosity' => (int)$verbosity);
        $this->log_aborted = false;
    }

    /** Adds data to the log.
        expects: label=string, data=object, verbosity=number */
    public function log_data($label, $data, $verbosity = 0) {
        $this->data[$label] = array(
            'verbosity' => (int)$verbosity,
            'data' => $data
        );
        $this->log_aborted = false;
    }

    /** Retreives the log file for the specified year and month.
        expects: year=number, month=number
        returns: array */
    public function get_log_file($year, $month) {
        if (! is_numeric($year)) {
            throw new Exception("Invalid year: '$year'.");
        }
        // only two digits in the year? create a Y3K bug!
        if (strlen($year) == 2) {
            $year = "20$year";
        }
        if (! is_numeric($month)) {
            throw new Exception("Invalid year: '$month'.");
        }
        // zero pad the month if needed
        if (strlen($month) == 1) {
            $month = "0$month";
        }
        $log_time = mktime(0, 0, 0, $month, 1, $year);
        $log_file = $this->log_dir . '/' . strtolower(@date('Y/m-F', $log_time));
        if (! file_exists($log_file)) {
            throw new Exception("No log file exists for the date $month/$year ($log_file).");
        }
        // read in each line and parse it out
        $file_handle = fopen($log_file, 'r');
        if ($file_handle === false) {
            throw new Exception("Failed to open '$log_file' to read logs.");
        }
        $buffer = '';
        $log = array();
        $log_item = array();
        $line_num = 0;
        $cur_date = null;
        // ugly as all hell :(
        while (! feof($file_handle)) {
            $buffer_start = strlen($buffer);
            $buffer .= fread($file_handle, 4096);
            // for each line in the buffer...
            for ($i = $buffer_start; $i < strlen($buffer); $i++) {
                if ($buffer[$i] == "\n") {
                    $line_num++; // we found a line!
                    // we've read in a new line
                    $line = substr($buffer, 0, $i);
                    // take it out of the buffer
                    $buffer = substr($buffer, $i + 1);
                    // parse it-- start by lookign for a new log entry
                    if (substr($line, 0, 1) == '[') {
                        // get the date, API, etc
                        $cur_date = substr($line, 1, 19);
                        $line = substr($line, 22);
                        $matches = array();
                        // omega.subservice.disable, 192.168.1.1 (okay)
                        if (! preg_match('/^([^ ]+)( - [^ ]+)?, ([^ ]+) \((\w+)\)$/', $line, $matches)) {
                            throw new Exception("Failed to parse log information from '" . $line . "' in '$log_file', line $line_num.");
                        }
                        // if we matched 4 ()'s then there is a username in the entry, otherwise there should be 3 matches 
                        if (count($matches) == 5) {
                            $log[$cur_date]['api_user'] = substr($matches[2], 3); // omit the preceding ' - ' chars
                            $log[$cur_date]['ip_address'] = $matches[3];
                            $log[$cur_date]['result'] = $matches[4];
                        } else if (count($matches) == 4) {
                            $log[$cur_date]['ip_address'] = $matches[2];
                            $log[$cur_date]['result'] = $matches[3];
                        } else {
                            throw new Exception("Failed to parse request information from '" . $line . "' in '$log_file', line $line_num. An unexpected number of items were matched. This should never happen.");
                        }
                        $log[$cur_date]['api'] = $matches[1];
                        $log[$cur_date]['entries'] = array();
                        $log[$cur_date]['data'] = array();
                    } elseif (substr($line, 0, 1) == "\t") { // otherwise add to the current log item
                        // check to see if this is a log entry or some data
                        if (substr($line, 1, 1) == '*' || substr($line, 1, 1) == '!') {
                            $log[$cur_date]['entries'][] = substr($line, 3); // don't include '\t* ' in front
                        } else {
                            $colon_pos = strpos($line, ':', 1);
                            if ($colon_pos === false) {
                                // we didn't find a ':', so complain!
                                throw new Exception("Failed to parse log data from '$line' in '$log_file', line $line_num. Unable to locate ':' to find data label.");
                            }
                            // gather up the data, organized by label
                            $log[$cur_date]['data'][substr($line, 1, $colon_pos)] = json_decode(substr($line, $colon_pos + 1), true);
                        }
                    } else {
                        throw new Exception("Failed to parse log line '$line' in '$log_file', line $line_num. The first character of the line is unrecognized.");
                    }
                    // reset $i back to the beginning to search for more lines in the buffer
                    $i = 0;
                }
            }
        }
        fclose($file_handle);
        return $log;
    }

    /** Retreives the unparsed log file for the specified year and month.
        expects: year=number, month=number
        returns: string */
    public function get_log_file_raw($year, $month) {
        if (! is_numeric($year)) {
            throw new Exception("Invalid year: '$year'.");
        }
        // only two digits in the year? create a Y3K bug!
        if (strlen($year) == 2) {
            $year = "20$year";
        }
        if (! is_numeric($month)) {
            throw new Exception("Invalid year: '$month'.");
        }
        // zero pad the month if needed
        if (strlen($month) == 1) {
            $month = "0$month";
        }
        $log_time = mktime(0, 0, 0, $month, 1, $year);
        $log_file = $this->log_dir . '/' . strtolower(@date('Y/m-F', $log_time));
        if (! file_exists($log_file)) {
            throw new Exception("No log file exists for the date $month/$year ($log_file).");
        }
        $log = file_get_contents($log_file);
        if ($log === false) {
            throw new Exception("Failed to read contents of log file '$log_file'.");
        }
        return $log;
    }

    /** Retreives the current log file.
        returns: array */
    public function get_cur_log_file() {
        return $this->get_log_file(@date('Y'), @date('m'));
    }

    /** Returns the unparsed contents of the current log file.
        returns: string */
    public function get_cur_log_file_raw() {
        return $this->get_log_file_raw(@date('Y'), @date('m'));
    }

    /** Returns a list of log files, grouped by year, available on the server.
        returns: object */
    public function list_log_files() {
        global $om;
        $log_path = $this->log_dir;
        if (! is_dir($log_path)) {
            throw new Exception('There are no log files for service ' . $om->service_name . '.');
        }
        $dir_handle = opendir($log_path);
        if ($dir_handle === false) {
            throw new Exception("Failed to open log directory '$log_path' to read list of log directories.");
        }
        $logs = array();
        while (($item = readdir($dir_handle)) !== false) {
            if (substr($item, 0, 1) == '.') {
                // ignore hidden files/folders and current/parent folder
                continue;
            }
            if (is_dir("$log_path/$item") && preg_match('/^\d{4}$/', $item)) {
                // get ready to store a list of all the log files we find
                $logs[$item] = array();
                // get a list of all the logs in this year's folder
                $logs_dir_handle = opendir("$log_path/$item");
                if ($logs_dir_handle === false) {
                    throw new Exception("Failed to open log directory '$log_path/$item' to read list of log files.");
                }
                while (($log_item = readdir($logs_dir_handle)) !== false) {
                    // figure out which month we've got
                    $matches = array();
                    if (is_dir("$log_path/$item/$log_item") || ! preg_match('/^(\d{2})-[a-z]+$/', $log_item, $matches)) {
                        // skip everything but our log files
                        continue;
                    }
                    // and record the month
                    $logs[$item][] = $log_item;
                }
                closedir($logs_dir_handle);
            }
        }
        closedir($dir_handle);
        return $logs;
    }

    /** Returns the last N number of log entries from the current log. If more lines are requested than exist in the log then the entire log will be returned.
        expects: lines=number
        returns: object */
    public function get_last_entries($lines = 20) {
        if (! is_numeric($lines)) {
            throw new Exception("Invalid number of lines: '$lines'.");
        }
        $log = array_reverse($this->get_cur_log_file());
        // if the log is shorter than what is requested just return what we've got
        if ($lines >= count($log)) {
            return $log;
        } else {
            // gather up the last X lines
            $last_lines = array();
            while (count($last_lines) < $lines) {
                $last_lines[key($log)] = array_shift($log);
            }
            // and return 'em
            return $last_lines;
        }
    }

    public function _date2epoch($date) {
        return mktime( // hour, min, sec, month, day, year
            substr($date, 11, 2),
            substr($date, 14, 2),
            substr($date, 17, 2),
            substr($date, 5, 2),
            substr($date, 8, 2),
            substr($date, 0, 4)
        );
    }

    /** Returns log entries that match the specified criteria. Times are integers based on the unix epoch or date format 'YYYY-MM-DD HH:MM:SS'. Usernames, APIs, and IP addresses can supplied either as an array or listed out, separated by whitespace, colons, semi-colons, and commas.
        expects: start_time=number, end_time=number, usernames=array, apis=array, ips=array, message=string, limit=number, offset=number
        returns: array */
    public function find($start_time = null, $end_time = null, $usernames = null, $apis = null, $ips = null, $message = null, $limit = null, $offset = null, $new_first = true) {
        $matches = array();
        $date_re = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        // if our times are formatted as dates then convert to epoch
        if (preg_match($date_re, $start_time)) {
            $start_time = $this->_date2epoch($start_time);
        }
        if (preg_match($date_re, $end_time)) {
            $end_time = $this->_date2epoch($end_time);
        }
        // coerce our usernames/apis/ips into arrays
        if ($usernames === null) {
            $usernames = array();
        } else {
            if (! is_array($usernames)) {
                $usernames = preg_split("/[:;, \n\t]+/", $usernames);
            }
        }
        if ($apis === null) {
            $apis = array();
        } else {
            if (! is_array($apis)) {
                $apis = preg_split("/[:;, \n\t]+/", $apis);
            }
        }
        if ($ips === null) {
            $ips = array();
        } else {
            if (! is_array($ips)) {
                $ips = preg_split("/[:;, \n\t]+/", $ips);
            }
        }
        // get a list of log files to parse
        $log_files = $this->list_log_files();
        if (count($log_files) === 0) {
            // no logs? no matches!
            return $matches;
        }
        // figure out which logs we need to parse
        $start_year = ($start_time === null ? null : (int)@date('Y', $start_time));
        $start_month = ($start_time === null ? null : (int)@date('n', $start_time));
        $end_year = ($end_time === null ? null : (int)@date('Y', $end_time));
        $end_month = ($end_time === null ? null : (int)@date('n', $end_time));
        foreach ($log_files as $year => $logs) {
            $year = (int)$year;
            if ($start_time !== null) {
                // skip these logs if it's before our start time
                if ($year < $start_year) {
                    continue;
                }
            }
            $on_start_year = ($year == $start_year);
            if ($end_time !== null) {
                // skip these logs if it's after our end time
                if ($year > $end_year) {
                    continue;
                }
            }
            $on_end_year = ($year == $end_year);
            foreach ($logs as $file) {
                // e.g. $file = '05-may'
                $month = (int)substr($file, 0, 2);
                // skip files if we're on an edge year and too early/late
                if ($on_start_year) {
                    if ($month < $start_month) {
                        continue;
                    }
                }
                $on_start_month = ($month == $start_month);
                if ($on_end_year) {
                    if ($month > $end_month) {
                        continue;
                    }
                }
                $on_end_month = ($month == $end_month);
                // get the log lines from this file and start searching
                foreach ($this->get_log_file($year, $month) as $date => $data) {
                    // if we're in the start month or end month make sure we skip out of bounds log items
                    if ($on_start_month || $on_end_month) {
                        // e.g. $date = '2011-05-24 19:17:31'
                        //               0123456789012345678
                        $epoc = $this->_date2epoch($date);
                        if ($on_start_month && $epoc < $start_time) {
                            continue;
                        }
                        if ($on_end_month && $epoc > $end_time) {
                            continue;
                        }
                    }
                    // check to see if this log line fits our search criteria (usernames, apis, ips, message)
                    if (count($usernames)) {
                        $matched = false;
                        foreach ($usernames as $username) {
                            if ($data['api_user'] === $username) {
                                $matched = true;
                                break;
                            }
                        }
                        if (! $matched) {
                            continue;
                        }
                    }
                    if (count($apis)) {
                        $matched = false;
                        foreach ($apis as $api) {
                            if ($data['api'] === $api) {
                                $matched = true;
                                break;
                            }
                        }
                        if (! $matched) {
                            continue;
                        }
                    }
                    if (count($ips)) {
                        $matched = false;
                        foreach ($ips as $ip) {
                            if ($data['ip_address'] === $ip) {
                                $matched = true;
                                break;
                            }
                        }
                        if (! $matched) {
                            continue;
                        }
                    }
                    if ($message !== null) {
                        $matched = false;
                        foreach ($data['entries'] as $entry) {
                            if (strpos($entry, $message) !== false) {
                                $matched = true;
                            }
                        }
                        if (! $matched) {
                            continue;
                        }
                    }
                    // if we have an offset then skip this item
                    if ($offset !== null && $offset > 0) {
                        $offset -= 1;
                        continue;
                    }
                    $matches[$date] = $data;
                    // limiting how many matches?
                    if ($limit !== null && count($matches) == $limit) {
                        if ($new_first) {
                            return array_reverse($matches);
                        } else {
                            return $matches;
                        }
                    }
                }
            }
        }
        if ($new_first) {
            return array_reverse($matches);
        } else {
            return $matches;
        }
    }
}

?>

<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


class OmegaException extends Exception {
    public $data = null;
    public $comment = null;
    public $user_error = false;
    public $subject;
    public $body;
    private $args;

    public function __construct($message, $data = null, $args = null) {
        global $om;
        parent::__construct($message);
        $this->data = $data;
        if ($args === null) {
            $args = array();
        }
        $this->args = $args;
        $this->generate_report();
        if (isset($args['email'])) {
            mail($args['email'], $this->subject, $this->body);
            if (isset($args['email_bcc'])) {
                mail($args['email_bcc'], $this->subject, $this->body);
            }
        }
        // user errors get flagged slightly differently in the response
        if (isset($args['user_error']) && $args['user_error']) {
            $this->user_error = $args['user_error'];
        }
        if (isset($args['alert']) && $args['alert']) {
            $admin_email = null;
            try {
                $admin_email = $om->config->get('omega/admin/email');
            } catch (Exception $e) {
                // QQ
                try {
                    $admin_email = $om->config->get('omega/admin_email');
                } catch (Exception $e) {
                    try {
                        $admin_email = $om->config->get('admin/email');
                    } catch (Exception $e) {}
                }
            }
            if ($admin_email) {
                if (! is_array($admin_email)) {
                    $admin_email = array($admin_email);
                }
                foreach ($admin_email as $email) {
                    mail($email, $this->subject, $this->body);
                }
            } else {
                $om->log("Unable to send e-mail exception; no admin e-mail address defined.");
                $om->log(array(
                    "subject" => $this->subject,
                    "body" => $this->body
                ));
            }
            try {
                // old school backup
                $babysitter_email = $om->config->get('admin.babysitter_email');
                if ($babysitter_email != $admin_email) {
                    mail($babysitter_email, $this->subject, $this->body);
                }
            } catch (Exception $e) {}
        }
        if (isset($args['comment'])) {
            $this->comment = $args['comment'];
        }
    }

    private function generate_report() {
        global $om;
        $message = $this->getMessage();
        if (isset($om->service_name) && isset($om->whoami)) {
            $email_body = "The following exception was thrown by " . $om->service_name . ' (' . $om->whoami() . ") during API '" . $om->request->get_api() . "': $message\n\n";
        } else {
            $email_body = 'Exception: ';
        }
        $email_body .= "Back trace:\n";
        if (isset($om->_clean_trace)) {
            $bt_lines = $om->_clean_trace($this->getTrace());
            // get rid of parts of the trace we don't need
            array_pop($bt_lines);
            array_pop($bt_lines);
            array_pop($bt_lines);
            $email_body .= implode("\n", $bt_lines);
        }
        if ($this->comment !== null) {
            $email_boxy .= "\nError Comment:\n$comment\n";
        }
        //$email_body .= "\nGET:\n" . var_export($_GET, true) . "\n";
        //$email_body .= "\nPOST:\n" . var_export($_POST, true) . "\n";
        if ($this->data !== null) {
            if (! is_array($this->data)) {
                $this->data = array($this->data);
            }
            $email_body .= "\n\nOBJECTS:\n===============================\n";
            foreach ($this->data as $i => $obj) {
                $email_body .= "[$i]\n";
                $email_body .= var_export($obj, true) . "\n";
                $email_body .= "-------------------------------\n";
            }
        }
        if (isset($om->service_name)) {
            $this->subject = $om->service_name . ' Exception: ' . $message;
        } else if (isset($om->whoami)) {
            $this->subject = $om->service_name . ' (' . $om->whoami() . ') Exception: ' . $message;
        } else {
            $this->subject = 'Exception: ' . $message;
        }
        $this->body = $email_body;
    }
}

?>

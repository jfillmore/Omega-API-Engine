<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


/* Because we can't always use OmegaException to send alerts... */
class OmegaAlert {
    public function __construct($subject = '', $message = '', $data = array(), $args = array()) {
        $om = Omega::get();
        // no args? just return. hack for init.php
        // see "http://dev.kohanaframework.org/issues/4191" for why
        if (! $subject && ! $message) {
            return;
        }
        if (! $subject) {
            $subject = "Internal Server Error";
        }
        $args = OmegaLib::get_args(array(
            'email' => array() // list of people to e-mail
        ), $args);
        if (! is_array($data)) {
            $data = array($data);
        }
        $email_body = $subject . "\n\n";
        if (count($data)) {
            $email_body .= "\nData:\n===============================\n";
            foreach ($data as $i => $obj) {
                $email_body .= "[$i]\n";
                $email_body .= var_export($obj, true) . "\n";
                $email_body .= "-------------------------------\n";
            }
        }
        if (! is_array($args['email'])) {
            $args['email'] = array($args['email']);
        }
        // no e-mail given? try to default to an admin e-mail
        if (! count($args['email'])) {
            $admin_email = null;
            try {
                $admin_email = $om->config->get('omega/admin/email');
            } catch (Exception $e) {
                // not setup? we'll just skip it then
            }
            if ($admin_email) {
                $args['email'][] = $admin_email;
            }
        }
        foreach ($args['email'] as $email) {
            mail($email, $subject, $email_body);
        }
        // never sent any e-mails? that was the hole point!
        if (! $args['email']) {
            throw new Exception("Failed to send e-mail \"$subject\": no e-mail address given and no default e-mail address configured.");
        }
    }
}


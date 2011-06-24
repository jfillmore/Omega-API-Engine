<?php
/* omega - PHP server
   http://code.google.com/p/theomega/
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */


class OmegaException extends Exception {
	public $data = null;
	public $comment = null;
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
		if (isset($args['alert']) && $args['alert']) {
			// TODO: change e-mail keys to something more predictable (e.g. omega.admin)
			$admin_email = $om->config->get('admin.email');
			mail($admin_email, $this->subject, $this->body);
			$babysitter_email = $om->config->get('admin.babysitter_email');
			if ($babysitter_email != $admin_email) {
				mail($babysitter_email, $this->subject, $this->body);
			}
		}
		if (isset($args['comment'])) {
			$this->comment = $args['comment'];
		}
	}

	private function generate_report() {
		global $om;
		$message = $this->getMessage();
		$email_body = "The following exception was thrown by " . $om->service_name . ' (' . $om->whoami() . ") during API '" . $om->request->get_api() . "': $message\n\n";
		$email_body .= "Back trace:\n";
		$bt_lines = $om->_clean_trace($this->getTrace());
		// get rid of parts of the trace we don't need
		array_pop($bt_lines);
		array_pop($bt_lines);
		array_pop($bt_lines);
		$email_body .= implode("\n", $bt_lines);
		if ($this->comment !== null) {
			$email_boxy .= "\nError Comment:\n$comment\n";
		}
		//$email_body .= "\nGET:\n" . var_export($_GET, true) . "\n";
		//$email_body .= "\nPOST:\n" . var_export($_POST, true) . "\n";
		if ($this->data !== null) {
			if (! is_array($this->data)) {
				$this->data = array($this->data);
			}
		}
		$email_body .= "\n\nOBJECTS:\n===============================\n";
		foreach ($this->data as $i => $obj) {
			$email_body .= "[$i]\n";
			$email_body .= var_export($obj, true) . "\n";
			$email_body .= "-------------------------------\n";
		}
		$this->subject = $om->service_name . ' (' . $om->whoami() . ') Exception: ' . $message;
		$this->body = $email_body;
	}
}

?>

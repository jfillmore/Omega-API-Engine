<?php

class OmegaCypher {
    protected $cypher;
    protected $mode;

    public function __construct($key = null, $cypher = 'twofish', $mode = 'cfb') {
        $om = Omega::get();
        if (! $key) {
            throw new Exception("No key provided.");
        }
        $this->key = $key;
        $this->cypher = $cypher;
        $this->mode = $mode;
    }

    public function encode($plain_text, $base64 = true) {
        $td = mcrypt_module_open($this->cypher, '', $this->mode, '');
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
        mcrypt_generic_init($td, $this->key, $iv);
        $crypted = mcrypt_generic($td, $plain_text);
        mcrypt_generic_deinit($td);
        $result = $iv . $crypted;
        if ($base64) {
            $result = base64_encode($result);
        }
        return $result;
    }

    public function decode($crypted, $base64 = true) {
        $plain_text = '';
        if ($base64) {
            $crypted = base64_decode($crypted);
        }
        $td = mcrypt_module_open($this->cypher, '', $this->mode, '');
        $ivsize = mcrypt_enc_get_iv_size($td);
        $iv = substr($crypted, 0, $ivsize);
        $crypted = substr($crypted, $ivsize);
        if ($iv) {
            mcrypt_generic_init($td, $this->key, $iv);
            $plain_text = mdecrypt_generic($td, $crypted);
        }
        if ($plain_text === false) {
            throw new Exception("Failed to decode encrypted data.");
        }
        return $plain_text;
    }

    public function test($plain_text) {
        $crypted = $this->encode($plain_text);
        return array(
            'plain_text' => $plain_text,
            'encoded' => $crypted,
            'decoded' => $this->decode($crypted)
        );
    }
}

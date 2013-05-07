<?php

//namespace Doqumentor;

/**
    This file is part of Doqumentor.

    https://github.com/murraypicton/Doqumentor/blob/master/parser.php

    Doqumentor is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Doqumentor is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Doqumentor.  If not, see <http://www.gnu.org/licenses/>.

    ------------

    Updated to be used in omega docstring parsing. Improved param parsing and fixed several bugs.
*/
/**
* PHPDoc parser for use in Doqumentor
*
* Simple example usage: 
* $a = new OmegaDocParser($string); 
* 
* @author Murray Picton
* @copyright 2010 Murray Picton
*/
class OmegaDocParser {
    // The string that we want to parse
    private $string;
    // Storge for the short description
    private $shortDesc;
    // Storge for the long description
    private $longDesc;
    // Storge for all the PHPDoc tokens
    private $tokens;
    // Method parameters
    private $params = array();
    // track the last parameter for multi-line descriptions
    private $last = array(
        'type' => null, // param/return
        'name' => null, // param name
    );
    // what is our indentation level?
    private $indent = 0;

    /**
    * Parse each line
    *
    * Takes an array containing all the lines in the string and stores
    * the parsed information in the object properties
    * 
    * @param array $lines An array of strings to be parsed
    */
    private function parseLines($lines) {
        $desc = array();
        foreach ($lines as $line) {
            $parsed = $this->parseLine($line);
            if ($parsed !== true) {
                $desc[] = $parsed;
            }
        }
        if ($desc) {
            $this->shortDesc = $desc[0];
        }
        $this->longDesc = trim(implode(PHP_EOL, $desc));
    }

    /**
    * Takes a string and parses it as a PHPDoc comment
    * 
    * @param string $line The line to be parsed
    * @return mixed True if the line was parsed and used, otherwise returns the line back.
    */
    private function parseLine($line) {
        $trimmed = trim($line);
        if (empty($trimmed)) return true;
        if (strpos($trimmed, '@') === 0) {
            $this->indent = strpos($line, '@');
            $token = substr($trimmed, 1, strpos($trimmed, ' ') - 1); // parameter name
            $value = substr($trimmed, strlen($token) + 2); // parameter value
            // save the data found within the line
            return $this->setToken($token, $value);
        } else if ($this->last['type']) {
            // preserve formatting by only trimming up to our indent level
            // how much leading space on this line?
            $offset = strpos($line, $trimmed[0]);
            // how much do we need to restore to our trim?
            $trimmed = str_repeat(' ', max($offset - $this->indent, 0))
                . $trimmed;
            // this is part of a previous param/return, so reassociate it
            if ($this->last['type'] == 'return') {
                $this->tokens['return']['desc'] .= PHP_EOL . $trimmed;
            } else if ($this->last['type'] == 'param') {
                $this->params[$this->last['name']]['desc'] .= PHP_EOL . $trimmed;
            } else {
                $this->setToken($this->last['name'], $trimmed);
            }
            return true;
        }
        // we're still parsing the description, so return it back
        return $line;
    }

    /**
    * Parse a parameter or string to display in simple typecast display
    *
    * @param string $string The string to parse
    * @return string Formatted string with typecast
    */
    private function parseReturn($string) {
        $pos = strpos($string, ' ');
        $type = substr($string, 0, $pos);
        return array(
            'type' => $type,
            'desc' => substr($string, $pos + 1)
        );
    }

    /**
    * Parse parameters from docstring line.
    *
    * @param string $string The string to parse
    * @return string Formatted string with typecast
    */
    private function parseParam($string) {
        $pos = strpos($string, ' ');
        if ($pos === false) {
            throw new Exception("Invalid parameter formatting; missing param type from: '$string'.");
        }
        $type = substr($string, 0, $pos);
        // trim out the type and name
        $trimmed = substr($string, $pos + 1);
        $pos = strpos($trimmed, ' ');
        if ($pos === false) {
            // no description given is all
            $pos = strlen($trimmed);
        }
        $name = substr($trimmed, 0, $pos);
        if (substr($name, 0, 1) != '$') {
            throw new Exception("Invalid parameter formatting; param name missing '$' in: '$string'.");
        }
        $name = ltrim($name, '$');
        $trimmed = substr($trimmed, $pos + 1);
        $this->params[$name] = array(
            'type' => $type,
            'desc' => $trimmed
        );
        $this->last = array(
            'type' => 'param',
            'name' => $name
        );
        return '(' . $type . ') ' . $trimmed;
    }

    /**
    * Set a parameter.
    * 
    * @param string $param The parameter name to store
    * @param string $value The value to set
    * @return bool True = the parameter has been set, false = the parameter was invalid
    */
    private function setToken($param, $value) {
        if (! array_key_exists($param, $this->tokens)) {
            throw new Exception("Invalid PHP documentation parameter type: '$param'.");
        }
        if ($param == 'param') {
            // this will take care of saving the param in $this->params
            $this->parseParam($value);
            return true;
        }
        // normalize our return data to match how params are handled
        if ($param == 'return') {
            $value = $this->parseReturn($value);
        }
        // default to each param value storing a string, unless given multiple times
        if (empty($this->tokens[$param])) {
            $this->tokens[$param] = $value;
        } else {
            $arr = $this->tokens[$param];
            if (count($arr) == 1) {
                $arr = array($arr, $value);
            } else {
                $arr[] = $value;
            }
            $this->tokens[$param] = $arr;
        }
        $this->last = array(
            'type' => $param,
            'name' => $param
        );
        return true;
    }

    /**
    * Setup the initial object
    * 
    * @param string $string The string we want to parse
    */
    public function __construct($string) {
        $this->string = $string;
        $this->setupTokens();
        $this->parse();
    }

    /**
    * Parse the string
    */
    private function parse() {
        // get the comment
        if (preg_match('#^/\*\*(.*)\*/#s', $this->string, $comment) === false)
            die("Error");
        $comment = trim($comment[1]);
        // get all the lines and left-trim any * characters
        $lines = explode("\n", $comment);
        foreach ($lines as &$line) {
            $line = preg_replace('/^(\s*)\* ?(.*)$/', '\1\2', $line);
        }
        $this->parseLines($lines);
    }

    /**
    * Get the short description
    *
    * @return string The short description
    */
    public function getShortDesc() {
        return $this->shortDesc;
    }

    /**
    * Get the long description
    *
    * @return string The long description
    */
    public function getDesc() {
        return $this->longDesc;
    }

    /**
    * Get method parameters
    * @return array Associated array of method parameters
    */
    public function getParams() {
        return $this->params;
    }

    /**
    * Get the tokens
    *
    * @param empty Whether or not to return empty tokens
    * @return array The tokens
    */
    public function getTokens($empty = false) {
        if ($empty) {
            return $this->tokens;
        } else {
            $tokens = array();
            foreach ($this->tokens as $name => $value) {
                if ($value !== '') {
                    $tokens[$name] = $value;
                }
            }
            return $tokens;
        }
    }

    /**
    * Setup the valid tokens
    */
    private function setupTokens() {
        $tokens = array(
            'abstract' => '',
            'access' => '',
            'author' => '',
            'copyright' => '',
            'deprecated' => '',
            'deprec' => '',
            'example' => '',
            'exception' => '',
            'global' => '',
            'ignore' => '',
            'internal' => '',
            'link' => '',
            'name' => '',
            'magic' => '',
            'package' => '',
            'param' => '',
            'return' => '',
            'see' => '',
            'since' => '',
            'static' => '',
            'staticvar' => '',
            'subpackage' => '',
            'throws' => '',
            'todo' => '',
            'var' => '',
            'version' => ''
        );
        $this->tokens = $tokens;
    }

}


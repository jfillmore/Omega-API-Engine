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
* $a->parse();
* 
* @author Murray Picton
* @copyright 2010 Murray Picton
*/
class OmegaDocParser {
	/**
	* The string that we want to parse
	*/
	private $string;
	/**
	* Storge for the short description
	*/
	private $shortDesc;
	/**
	* Storge for the long description
	*/
	private $longDesc;
	/**
	* Storge for all the PHPDoc tokens
	*/
	private $tokens;
	/**
	* Method parameters
	*/
	private $params = array();

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
			$parsedLine = $this->parseLine($line); //Parse the line
			if ($parsedLine === false && empty($this->shortDesc)) {
				$this->shortDesc = trim($parsedLine);
                $desc[] = $this->shortDesc;
			} elseif ($parsedLine !== false) {
                $desc[] = $parsedLine;
			}
		}
		$this->longDesc = trim(implode(PHP_EOL, $desc));
	}

	/**
	* Parse the line
	*
	* Takes a string and parses it as a PHPDoc comment
	* 
	* @param string $line The line to be parsed
	* @return mixed False if the line contains no tokens
	* that aren't valid otherwise, the line that was passed in.
	*/
	private function parseLine($line) {

		//Trim the whitespace from the line
		$line = trim($line);

		if (empty($line)) return false; //Empty line

		if (strpos($line, '@') === 0) {
			$token = substr($line, 1, strpos($line, ' ') - 1); //Get the parameter name
			$value = substr($line, strlen($token) + 2); //Get the value
			if ($this->setToken($token, $value)) return false; //Parse the line and return false if the parameter is valid
		}

		return $line;
	}

	/**
	* Setup the valid tokens
	* 
	* @param string $type NOT USED
	*/
	private function setupTokens($type = "") {
		$tokens = array(
			"access"	=>	'',
			"author"	=>	'',
			"copyright"	=>	'',
			"deprecated"=>	'',
			"example"	=>	'',
			"ignore"	=>	'',
			"internal"	=>	'',
			"link"		=>	'',
			"param"		=>	'',
			"return"	=> 	'',
			"see"		=>	'',
			"since"		=>	'',
			"tutorial"	=>	'',
			"version"	=>	''
		);

		$this->tokens = $tokens;
	}

	/**
	* Parse a parameter or string to display in simple typecast display
	*
	* @param string $string The string to parse
	* @return string Formatted string with typecast
	*/
	private function formatReturn($string) {
		$pos = strpos($string, ' ');

		$type = substr($string, 0, $pos);
		return '(' . $type . ') ' . substr($string, $pos+1);
	}

	/**
	* Parse parameters from docstring line.
	*
	* @param string $string The string to parse
	* @return string Formatted string with typecast
	*/
	private function parseParam($string) {
		$pos = strpos($string, ' ');

		$type = substr($string, 0, $pos);
        // trim out the type and name
        $trimmed = substr($string, $pos + 1);
		$pos = strpos($trimmed, ' ');
        $name = substr($trimmed, 0, $pos);
        if (substr($name, 0, 1) != '$') {
            throw new Exception("Invalid parameter formatting; missing param name from: '$string'.");
        }
        $name = ltrim($name, '$');
        $trimmed = substr($trimmed, $pos + 1);
        $this->params[$name] = array(
            'type' => $type,
            'desc' => $trimmed
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
		if (! array_key_exists($param, $this->tokens)) return false;
		if ($param == 'param') {
            $value = $this->parseParam($value);
        } else if ($param == 'return') {
            $value = $this->formatReturn($value);
        }
        if ($param == 'param') {
            $this->tokens[$param][] = $value;
        } else {
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
        }
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
	public function parse() {
		//Get the comment
		if (preg_match('#^/\*\*(.*)\*/#s', $this->string, $comment) === false)
			die("Error");
		$comment = trim($comment[1]);
		//Get all the lines and strip the * from the first character
		if (preg_match_all('#^\s*\*(.*)#m', $comment, $lines) === false)
			die('Error');
		$this->parseLines($lines[1]);
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
}

?>

<?php
/* omega - PHP server
   https://github.com/jfillmore/Omega-API-Engine
  
   Copyright 2011, Jonathon Fillmore
   Licensed under the MIT license. See LICENSE file.
   http://www.opensource.org/licenses/mit-license.php */

/** Source code viewer for API branches and methods. */
class OmegaEditor extends OmegaSubservice {
    private function get_code($r_obj) {
        // make sure the file exists
        $file_name = $r_obj->getFileName();
        if (! file_exists($file_name)) {
            throw new Exception("Unable to locate file '$file_name'.");
        }
        // read in the contents and split 'em into lines
        $data = file_get_contents($file_name);
        if ($data === false) {
            throw new Exception("Failed to read contents of file '$file_name'.");
        }
        $lines = preg_split('/(\n|\r\n|\r)/', $data);
        // only return the lines defining the object
        $start_line = $r_obj->getStartLine() - 1;
        return array_slice($lines, $start_line, $r_obj->getEndLine() - $start_line); 
    }

    /** Retrieves the source code lines for the specified API method (e.g. "omega.restart_service").
        expects: api_method=string
        returns: array */
    public function view_method_source($api_method) {
        global $om;
        // translate the API
        $api_method = $om->request->translate_api($api_method);
        // resolve it
        $parts = explode('/', $api_method);
        // we should have at least two parts, since we're asking about a method
        if (count($parts) < 2) {
            throw new Exception("API '$api_method' is a branch, not a method.");
        }
        // the method is on top
        $method = array_pop($parts);
        // and the rest is the path to the method
        $branches = $parts;
        // get a reference to the last branch
        $branch = $om->request->_get_branch_ref($branches);
        $r_class = new ReflectionClass(get_class($branch));
        // make sure we have a valid method
        if (! $r_class->hasMethod($method) || substr($method, 0, 1) == '_') {
            throw new Exception("Invalid branch method: '$method'.");
        }
        $r_method = $r_class->getMethod($method);
        if ($r_method->isPrivate()) {
            throw new Exception("Invalid branch method: '$method'.");
        }
        // return the lines defining the method
        return $this->get_code($r_method);
    }

    /** Retrieves the source code lines for the specified API branch (e.g. "omega.config").
        expects: api_branch=string
        returns: string */
    public function view_branch_source($api_branch) {
        global $om;
        // translate the API
        $api_branch = $om->request->translate_api($api_branch);
        // resolve it
        $branches = explode('/', $api_branch);
        // get a reference to the last branch
        $branch = $om->request->_get_branch_ref($branches);
        $r_class = new ReflectionClass(get_class($branch));
        // return the lines defining the method
        return $this->get_code($r_class);
    }
}

?>

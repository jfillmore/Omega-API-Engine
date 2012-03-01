<?php

/** Basic RESTful ORM class file. */
abstract class OmegaORM extends OmegaCrudable implements OmegaApi {
    public function _get_routes() {
        return array();
    }

    /** Some safe default handlers. */
    public function _get_handlers() {
        return array(
            'get' => array(
                //'/:key' => 'get',
                //'/' => 'request',
                '/describe' => 'describe'
            ),
            'post' => array(
                //'/' => 'create',
                '/validate_props' => 'validate_props',
                '/validate' => 'validate'
            ),
            'put' => array(
                //'/:key' => 'update'
            ),
            'delete' => array(
                //'/:key' => 'remove'
            )
        );
    }

    /** Returns information about the object type, optionally including additional descriptive information.
        expects: verbose=boolean
        returns: object */
    public function describe($verbose = false) {
        return parent::_describe($verbose);
    }

    /** Validates the specified properties listed. Returns a list of any errors found.
        expects: props=object
        returns: array */
    public function validate_props($props) {
        return parent::_validate_props($props);
    }

    /** Validates the specified property. Returns a list of any errors found.
        expects: key=string, value=undefined
        returns: array */
    public function validate($key, $value) {
        return parent::_validate($key, $value);
    }

    /** The SQL parsing logic for CRUD operations isn't safe enough against SQL injection so they are not automatically exposed. */
}

?>

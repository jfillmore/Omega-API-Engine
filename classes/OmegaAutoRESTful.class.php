<?php

/* Use PHP introspection to generate API handlers for all GET/POST data. Any OmegaRESTful (or descendent) objects will be automatically added to the routes. */
class OmegaAutoRESTful extends OmegaRESTful {
    public function _get_handlers() {
        $handlers = array();
        $r_class = new ReflectionClass($this);
        $methods = $r_class->getMethods(ReflectionMethod::IS_PUBLIC);
        foreach ($methods as $method) {
            // so long as we don't start with an underscore, we're fair game to add
            $name = $method->getName();
            if (substr($name, 0, 1) !== '_') {
                $handlers[$name] = $name;
            }
        }
        return array(
            'GET' => $handlers,
            'POST' => $handlers
        );
    }
    
    public function _get_routes() {
        $routes = array();
        $r_class = new ReflectionClass($this);
        $props = $r_class->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($props as $prop) {
            // is this some kind of restful API object?
            $name = $prop->getName();
            try {
                $r_prop = new ReflectionClass(@get_class($this->$name));
            } catch (Exception $e) {
                continue;
            }
            if (! $r_prop) {
                continue;
            }
            if ($r_prop->isSubclassOf('OmegaRESTful') === false) {
                continue;
            }
            $routes[$name] = $name;
        }
        return $routes;
    }

}


<?php

namespace dokuwiki\Input;

/**
 * Internal class used for $_GET access in dokuwiki\Input\Input class
 *
 * All inherited accessor methods ({@see Input::param()}, {@see Input::str()},
 * {@see Input::arr()}, {@see Input::ref()}) return attacker-controlled values
 * from $_GET and are marked @psalm-taint-source input on the parent class.
 */
class Get extends Input
{
    /** @noinspection PhpMissingParentConstructorInspection
     * Initialize the $access array, remove subclass members
     */
    public function __construct()
    {
        $this->access = &$_GET;
    }

    /**
     * Sets a parameter in $_GET and $_REQUEST
     *
     * @param string $name Parameter name
     * @param mixed $value Value to set
     */
    public function set($name, $value)
    {
        parent::set($name, $value);
        $_REQUEST[$name] = $value;
    }
}

<?php

namespace dokuwiki\Input;

/**
 * Internal class used for $_SERVER access in dokuwiki\Input\Input class
 *
 * All inherited accessor methods ({@see Input::param()}, {@see Input::str()},
 * {@see Input::arr()}, {@see Input::ref()}) return values from $_SERVER.
 * While some $_SERVER keys are set by the PHP SAPI and effectively trusted
 * (REMOTE_ADDR, HTTPS, SERVER_NAME configured in httpd), many others —
 * HTTP_* headers, QUERY_STRING, REQUEST_URI — are attacker-controllable.
 * We treat the whole class as a taint source via the parent's
 * @psalm-taint-source input annotations and accept the noise on trusted
 * keys in exchange for catching real injection flows.
 */
class Server extends Input
{
    /** @noinspection PhpMissingParentConstructorInspection
     * Initialize the $access array, remove subclass members
     */
    public function __construct()
    {
        $this->access = &$_SERVER;
    }
}

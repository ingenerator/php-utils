<?php

declare(strict_types=1);

/**
 * @author    Craig Gosman <craig@ingenerator.com>
 *
 * @licence   BSD-3-Clause
 */

namespace Ingenerator\PHPUtils\Session;

use ErrorException;

class SessionLockNotObtainedException extends ErrorException
{
}

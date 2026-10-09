<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A staff action on an abandoned cart was refused for a reason the user can fix
 * (unmatched products, no phone number, already converted…). Controllers show
 * the message as-is and answer 422.
 */
class AbandonedCartActionException extends RuntimeException {}

<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A payment that cannot be applied as it stands (Phase 4): too small for a
 * month, below the AI top-up minimum, or not confirmed.
 */
class PaymentRejected extends RuntimeException {}

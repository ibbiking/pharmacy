<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a business has invoice_source = 'fbr' but hasn't finished
 * linking its FBR credentials (NTN, POS registration no, API token) yet.
 */
class FbrNotConfiguredException extends Exception
{
}

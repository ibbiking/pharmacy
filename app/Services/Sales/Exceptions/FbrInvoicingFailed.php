<?php

namespace App\Services\Sales\Exceptions;

use RuntimeException;

/** FBR could not issue a number; the sale is rolled back (business opted into FBR invoicing). */
class FbrInvoicingFailed extends RuntimeException
{
}

<?php

namespace App\Events;

use App\Models\Documents\Document;
use Illuminate\Foundation\Events\Dispatchable;

/** A B2B document was issued ($change = 'issued') or voided ('voided'): Accounting posts or reverses it. */
class DocumentChanged
{
    use Dispatchable;

    public function __construct(public Document $document, public string $change)
    {
    }
}

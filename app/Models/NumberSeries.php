<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The numbering of one document type in one business (prefix + next number + zero padding). */
class NumberSeries extends Model
{
    use SoftDeletes;
    use \App\Traits\Auditable;

    protected $table = 'number_series';
    protected $guarded = [];
    protected $casts = ['next_number' => 'integer', 'padding' => 'integer'];

    public function format(int $number): string
    {
        return $this->prefix.str_pad((string) $number, $this->padding, '0', STR_PAD_LEFT);
    }
}

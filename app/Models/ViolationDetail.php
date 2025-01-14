<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViolationDetail extends Model
{
    protected $fillable = [
        'violation_id', // Foreign key to Violation
        'nature_of_violation', // Nature of the violation
    ];

    /**
     * Define the relationship with the Violation model.
     * A violation detail belongs to a single violation.
     */
    public function violation()
    {
        return $this->belongsTo(Violation::class, 'violation_id', 'violation_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Violation extends Model
{
    protected $primaryKey = 'violation_id'; // Define custom primary key
    public $incrementing = false; // Prevent auto-increment since you're using UUID

    protected $fillable = ['violation_id', 'nature_of_violation', 'type_of_inspection', 'violation_receipt_no', 'violation_date', 'due_date', 'status', 'business_id'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($violation) {
            if (empty($violation->violation_id)) {
                $violation->violation_id = (string) Str::uuid(); // Generate UUID if not provided
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'business_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'violation_id', 'violation_id');
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class, 'violation_id', 'violation_id');
    }
}

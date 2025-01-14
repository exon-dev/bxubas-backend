<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Violation extends Model
{
    protected $primaryKey = 'violation_id'; // Define the primary key
    public $incrementing = false; // Prevent auto-increment since you're using UUID
    protected $keyType = 'string'; // Set the key type for UUID

    protected $fillable = [
        'violation_id',
        'type_of_inspection',
        'violation_receipt_no',
        'violation_date',
        'due_date',
        'status',
        'violation_status',
        'business_id',
        'inspection_id',
    ];

    /**
     * Automatically generate a UUID when creating a new violation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($violation) {
            if (empty($violation->violation_id)) {
                $violation->violation_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Define the relationship with the Business model.
     */
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'business_id');
    }

    /**
     * Define the relationship with the Inspection model.
     */
    public function inspection()
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /**
     * Define the relationship with the ViolationDetail model.
     * A violation can have multiple nature_of_violation entries.
     */
    public function violationDetails()
    {
        return $this->hasMany(ViolationDetail::class, 'violation_id', 'violation_id');
    }

    /**
     * Define the relationship with the Notification model.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'violation_id', 'violation_id');
    }
}

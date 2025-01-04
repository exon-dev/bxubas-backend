<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BusinessOwner extends Model
{
    protected $primaryKey = 'business_owner_id'; // Custom primary key
    public $incrementing = false; // Non-incrementing primary key
    protected $keyType = 'string'; // UUID is stored as a string

    protected $fillable = ['email', 'first_name', 'last_name', 'phone_number'];

    // Automatically generate UUID for business_owner_id
    protected static function booted()
    {
        static::creating(function ($businessOwner) {
            $businessOwner->business_owner_id = (string) Str::uuid();
        });
    }
}


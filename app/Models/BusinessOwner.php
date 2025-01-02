<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessOwner extends Model
{
    protected $primaryKey = 'business_owner_id'; // Define custom primary key
    public $incrementing = false; // Prevent auto-increment since you're using UUID

    protected $fillable = ['email', 'first_name', 'last_name', 'phone_number'];

    public function businesses()
    {
        return $this->hasMany(Business::class, 'owner_id', 'business_owner_id');
    }
}

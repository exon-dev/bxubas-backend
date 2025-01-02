<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    protected $primaryKey = 'business_id'; // Define custom primary key
    public $incrementing = false; // Prevent auto-increment since you're using UUID

    protected $fillable = ['business_permit', 'business_name', 'image_url', 'status', 'owner_id'];

    public function owner()
    {
        return $this->belongsTo(BusinessOwner::class, 'owner_id', 'business_owner_id');
    }

    public function violations()
    {
        return $this->hasMany(Violation::class, 'business_id', 'business_id');
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class, 'business_id', 'business_id');
    }
}

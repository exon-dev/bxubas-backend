<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    // Specify the table name if it's not automatically determined (if you're using a custom table name)
    protected $table = 'address';

    // Define the primary key for this model
    protected $primaryKey = 'address_id'; // Custom primary key for address table
    public $incrementing = true; // Enable auto-increment for address ID

    protected $fillable = ['business_id', 'street', 'city', 'zip'];

    // Define the relationship between address and business
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'business_id');
    }
}

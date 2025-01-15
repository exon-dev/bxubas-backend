<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Business extends Model
{
    protected $primaryKey = 'business_id'; // Custom primary key
    public $incrementing = false; // UUID-based primary key
    protected $keyType = 'string'; // UUID is a string

    protected $fillable = ['business_permit', 'business_name', 'status', 'owner_id'];

    /**
     * Automatically generate UUID for the business_id field
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::uuid()->toString();
            }
        });
    }

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

    public function address()
    {
        return $this->hasOne(Address::class, 'business_id', 'business_id');
    }

}

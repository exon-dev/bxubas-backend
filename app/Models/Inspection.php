<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $primaryKey = 'inspection_id'; // Define the primary key
    public $incrementing = true; // Assuming the primary key is auto-incrementing
    protected $keyType = 'int'; // Set the key type

    protected $fillable = [
        'inspection_date',
        'type_of_inspection',
        'with_violations',
        'business_id',
        'inspector_id',
    ];

    /**
     * Define the relationship with the Business model.
     */
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'business_id');
    }

    /**
     * Define the relationship with the Inspector model.
     */
    public function inspector()
    {
        return $this->belongsTo(Inspector::class, 'inspector_id', 'inspector_id');
    }

    /**
     * Define the relationship with the Violation model.
     */
    public function violations()
    {
        return $this->hasMany(Violation::class, 'inspection_id');
    }

}

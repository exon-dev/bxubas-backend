<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inspection extends Model
{
    protected $primaryKey = 'inspection_id'; // Primary key auto-increment by default
    protected $fillable = ['inspection_date', 'type_of_inspection', 'with_violations', 'business_id', 'inspector_id'];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'business_id');
    }

    public function inspector()
    {
        return $this->belongsTo(Inspector::class, 'inspector_id', 'inspector_id');
    }

    public function violations()
    {
        return $this->hasMany(Violation::class, 'inspection_id', 'inspection_id');
    }
}

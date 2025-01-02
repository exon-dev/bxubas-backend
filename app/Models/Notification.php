<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $primaryKey = 'notification_id'; // Primary key auto-increment by default
    protected $fillable = ['title', 'content', 'violator_id', 'violation_id'];

    public function violator()
    {
        return $this->belongsTo(BusinessOwner::class, 'violator_id', 'business_owner_id');
    }

    public function violation()
    {
        return $this->belongsTo(Violation::class, 'violation_id', 'violation_id');
    }
}

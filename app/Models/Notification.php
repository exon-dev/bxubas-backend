<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';
    public $timestamps = true;

    protected $fillable = [
        'title',
        'content',
        'violator_id',
        'violation_id',
        'status',
        'error_message'
    ];

    public function violator()
    {
        return $this->belongsTo(BusinessOwner::class, 'violator_id', 'business_owner_id');
    }

    public function violation()
    {
        return $this->belongsTo(Violation::class, 'violation_id', 'violation_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Inspector extends Model
{

    use HasApiTokens;

    protected $primaryKey = 'inspector_id'; // Define custom primary key
    public $incrementing = false; // Prevent auto-increment since you're using UUID

    protected $fillable = ['admin_id', 'email', 'first_name', 'last_name', 'password'];

    protected static function booted()
    {
        static::creating(function ($inspector) {
            $inspector->inspector_id = (string) Str::uuid(); // Generate UUID
        });
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }

    public function inspections()
    {
        return $this->hasMany(Inspection::class, 'inspector_id', 'inspector_id');
    }
}

<?php
namespace App\Models;

use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Include this import
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Inspector extends Authenticatable // Extend Authenticatable
{
    use HasApiTokens, CanResetPassword;

    protected $primaryKey = 'inspector_id'; // Define custom primary key
    public $incrementing = false; // Prevent auto-increment since you're using UUID

    protected $hidden = ['password'];

    protected $fillable = ['admin_id', 'email', 'first_name', 'last_name', 'password', 'government_id', 'image_url'];

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

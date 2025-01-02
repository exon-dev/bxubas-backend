<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Model
{
    use HasFactory, HasApiTokens;

    // Specify the primary key
    protected $primaryKey = 'admin_id';

    // Disable auto-incrementing because we're using UUID
    public $incrementing = false;

    // Specify the key type as string
    protected $keyType = 'string';

    // Automatically generate a UUID for the admin_id before creating
    protected static function booted()
    {
        static::creating(function ($admin) {
            $admin->admin_id = (string) Str::uuid(); // Generate UUID
        });
    }

    // Specify the fields that are mass assignable
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
    ];
}

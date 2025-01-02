<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Auth\Authenticatable as AuthenticableTrait;
use Illuminate\Support\Str;

class Admins extends Model implements Authenticatable
{
    use AuthenticableTrait, HasFactory;

    protected $table = 'admins';
    protected $fillable = ['admin_id', 'email', 'first_name', 'last_name', 'password'];
    public $incrementing = false; // Disable auto-increment for primary key
    protected $keyType = 'string'; // Use string type for the primary key

    protected static function boot()
    {
        parent::boot();

        // Automatically generate a UUID for admin_id if not already set
        static::creating(function ($admin) {
            if (empty($admin->admin_id)) {
                $admin->admin_id = (string) Str::uuid();
            }
        });
    }

    public function getAuthIdentifierName()
    {
        return 'admin_id';
    }

    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function getRememberToken()
    {
        return $this->remember_token;
    }

    public function setRememberToken($value)
    {
        $this->remember_token = $value;
    }

    public function getRememberTokenName()
    {
        return 'remember_token';
    }
}

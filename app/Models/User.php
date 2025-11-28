<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'azure_id',
        'azure_tenant_id',
        'azure_access_token',
        'azure_refresh_token',
        'azure_token_expires_at',
        'azure_provider',
        'given_name',
        'surname',
        'job_title',
        'department',
        'phone'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'azure_access_token',
        'azure_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',

            'azure_access_token'     => 'encrypted',
            'azure_refresh_token'    => 'encrypted',
            'azure_token_expires_at' => 'datetime',
        ];
    }
}

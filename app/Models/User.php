<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',

        // Azure / Graph
        'azure_id',
        'azure_tenant_id',
        'azure_access_token',
        'azure_refresh_token',
        'azure_token_expires_at',
        'azure_provider',
        // Basic Graph profile
        'given_name',
        'surname',
        'job_title',
        'department',
        'phone'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'azure_access_token',
        'azure_refresh_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
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

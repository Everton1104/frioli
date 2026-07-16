<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\CustomResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'adm',
        'func',
        'excluido',
        'whatsapp',
        'whatsapp_code',
        'whatsapp_code_expires_at',
        'whatsapp_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'password'                 => 'hashed',
            'whatsapp_code_expires_at' => 'datetime',
            'whatsapp_verified_at'     => 'datetime',
        ];
    }

    public function whatsappVerificado(): bool
    {
        return $this->whatsapp_verified_at !== null;
    }

    // Ordens de pagamento atribuídas a este cliente.
    public function ordensPagamento()
    {
        return $this->hasMany(OrdemPagamento::class, 'user_id');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

}

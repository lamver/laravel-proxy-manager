<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Proxy class
 */
class Proxy extends Model
{
    /**
     * $fillabl variable
     *
     * @var array
     */
    protected $fillable = [
        'ip',
        'port',
        'username',
        'password',
        'type',
        'status',
        'last_checked_at'
    ];

    /**
     * $casts variable
     *
     * @var array
     */
    protected $casts = [
        'last_checked_at' => 'datetime',
    ];
}

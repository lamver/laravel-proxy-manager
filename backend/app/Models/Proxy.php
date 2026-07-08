<?php 

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;

class Proxy extends Model
{
    protected $fillable = [
        'ip', 
        'port', 
        'username', 
        'password', 
        'type', 
        'status', 
        'last_checked_at'
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];
}

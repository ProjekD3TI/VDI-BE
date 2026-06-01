<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'status'
    ];

    public function vm()
    {
        return $this->hasOne(VMs::class);
    }
}

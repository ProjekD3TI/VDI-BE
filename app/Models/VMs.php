<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VMs extends Model
{

    protected $fillable = ['user_id', 'ip_address'];
    public function users()
    {
        $this->belongsTo(User::class);
    }
}

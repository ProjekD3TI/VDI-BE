<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VMs extends Model
{

    protected $fillable = ['user_id', 'ip_address', 'vmid', 'guac_connection_id','template_id'];
    public function users()
    {
        return $this->belongsTo(User::class);
    }
}

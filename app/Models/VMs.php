<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VMs extends Model
{

    protected $fillable = ['user_id', 'ip_address_id', 'vmid', 'guac_connection_id', 'template_id'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function ipAddress(){
        return $this->belongsTo(IpAddress::class);
    }
}

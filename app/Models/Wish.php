<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wish extends Model
{
    public $timestamps = false;

    public function type()
    {
        return $this->hasOne('App\Models\WishType');
    }
}
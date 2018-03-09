<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WishType extends Model
{
    public $timestamps = false;

    const WISH_TEA_ID = 1;
    const WISH_COFFEE_ID = 2;
    const WISH_HUGS_ID = 3;

    public function wishes()
    {
        $this->belongsToMany(Wish::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'id_carrito');
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class,'id_transaccion');
    }

    public function images()
    {
        return $this->morphMany(\App\Models\Image::class, 'imageable');
    }
}

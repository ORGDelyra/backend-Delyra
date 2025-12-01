<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'id_categoria');
    }

    public function carts()
    {
        return $this->belongsToMany(Cart::class,'product_selects','id_carrito','id_producto')->withTimestamps();
    }

    public function images()
    {
        return $this->morphMany(\App\Models\Image::class, 'imageable');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name','price','stock','category_id','description','image'];
    
    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image
            ? url('storage/' . $this->image)
            : null;
    }

    // Relationships
    public function category() { return $this->belongsTo(Category::class); }
    public function cartItems() { return $this->hasMany(CartItem::class); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }
}

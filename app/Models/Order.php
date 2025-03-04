<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'user_id', 
        'transaction_id', 
        'total_amount', 
        'status', 
        'order_number'
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Generate unique order number before creating
        static::creating(function ($order) {
            $order->order_number = 'ORD-' . Str::upper(Str::random(6));
        });
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}

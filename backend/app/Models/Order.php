<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['customer_id', 'idempotency_key', 'idempotency_request_hash', 'status', 'subtotal_cents', 'discount_cents', 'total_cents'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** What OrderResource reads. Load these before returning an order from the API. */
    public const array RESOURCE_RELATIONS = ['customer', 'subOrders.vendor', 'subOrders.items'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<SubOrder, $this> */
    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    /** @return HasManyThrough<OrderItem, SubOrder, $this> */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, SubOrder::class);
    }
}

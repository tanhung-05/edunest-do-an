<?php

namespace App\Models\M12;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * N03 carts — giỏ hàng.
 *
 * @property int $id
 * @property int $user_id
 */
class Cart extends Model
{
    protected $table = 'carts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
        ];
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}

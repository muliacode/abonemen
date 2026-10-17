<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use App\Support\Crypto\Casts\Encrypted;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SPIKE (#12): a minimal subscription to measure field encryption.
 *
 * @property string $id
 * @property string $household_id
 * @property string $name
 * @property string $price
 * @property string $currency
 * @property string|null $notes
 * @property string|null $url
 * @property string $billing_cycle
 * @property Carbon $next_payment_on
 */
#[Fillable(['household_id', 'name', 'price', 'currency', 'notes', 'url', 'billing_cycle', 'next_payment_on'])]
class Subscription extends Model
{
    use BelongsToHousehold;
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => Encrypted::class,
            'price' => Encrypted::class,
            'currency' => Encrypted::class,
            'notes' => Encrypted::class,
            'url' => Encrypted::class,
            'next_payment_on' => 'date',
        ];
    }
}

<?php

namespace Tests\Feature\Protection;

use App\Casts\ProtectedAttribute;
use App\Concerns\HasProtectedAttributes;
use App\Contracts\HoldsWaitingProtectedValues;
use App\Models\Household;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stands in for models that later tickets add (Subscriptions, Budgets ...): amounts and structured details.
 */
class ProtectedThing extends Model implements HoldsWaitingProtectedValues
{
    use HasProtectedAttributes;

    protected $table = 'protected_things';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'price_minor' => ProtectedAttribute::class.':household,,int',
            'details' => ProtectedAttribute::class.':household,,json',
        ];
    }
}

class ProtectedValueTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('protected_things', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id');
            $table->text('price_minor')->nullable();
            $table->text('details')->nullable();
        });
    }

    public function test_an_integer_is_stored_protected_and_read_back_as_an_integer(): void
    {
        $household = Household::factory()->create();

        ProtectedThing::create(['household_id' => $household->id, 'price_minor' => 1599]);

        $this->assertStringNotContainsString('1599', (string) DB::table('protected_things')->value('price_minor'));
        $this->assertSame(1599, ProtectedThing::sole()->price_minor);
    }

    public function test_protected_integers_are_summed_in_memory_per_household(): void
    {
        $household = Household::factory()->create();
        $other = Household::factory()->create();

        foreach ([1599, 999, 250] as $price) {
            ProtectedThing::create(['household_id' => $household->id, 'price_minor' => $price]);
        }
        ProtectedThing::create(['household_id' => $other->id, 'price_minor' => 100000]);

        $this->assertSame(2848, ProtectedThing::where('household_id', $household->id)->get()->sum('price_minor'));
    }

    public function test_structured_details_are_stored_protected_and_read_back_intact(): void
    {
        $household = Household::factory()->create();
        $details = ['url' => 'https://netflix.example', 'notes' => ['family plan', 'cancel before March'], 'shared' => true];

        ProtectedThing::create(['household_id' => $household->id, 'details' => $details]);

        $this->assertStringNotContainsString('netflix', (string) DB::table('protected_things')->value('details'));
        $this->assertSame($details, ProtectedThing::sole()->details);
    }

    public function test_an_empty_value_stays_empty(): void
    {
        $household = Household::factory()->create();

        ProtectedThing::create(['household_id' => $household->id]);

        $this->assertNull(ProtectedThing::sole()->price_minor);
        $this->assertNull(ProtectedThing::sole()->details);
    }

    public function test_a_value_of_the_wrong_type_is_refused(): void
    {
        $household = Household::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        ProtectedThing::create(['household_id' => $household->id, 'price_minor' => 'fifteen']);
    }
}

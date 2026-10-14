<?php

use App\Models\Concerns\BelongsToHousehold;
use App\Models\Scopes\HouseholdScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::create('household_things', function ($table) {
        $table->id();
        $table->unsignedBigInteger('household_id');
    });

    $this->thing = new class extends Model
    {
        use BelongsToHousehold;

        public $timestamps = false;

        protected $table = 'household_things';

        protected $guarded = [];
    };

    $this->thing::withoutGlobalScopes()->insert([
        ['household_id' => 1],
        ['household_id' => 1],
        ['household_id' => 2],
    ]);
});

it('matches nothing when no household is bound', function () {
    expect($this->thing::query()->count())->toBe(0);
});

it('only returns rows of the current household', function () {
    app()->instance(HouseholdScope::CONTAINER_KEY, 1);

    expect($this->thing::query()->count())->toBe(2);

    app()->instance(HouseholdScope::CONTAINER_KEY, 2);

    expect($this->thing::query()->count())->toBe(1);
});

it('fills in the household of new rows', function () {
    app()->instance(HouseholdScope::CONTAINER_KEY, 2);

    $created = $this->thing::query()->create();

    expect($created->household_id)->toBe(2);
});

it('keeps an explicit household on new rows', function () {
    app()->instance(HouseholdScope::CONTAINER_KEY, 2);

    $created = $this->thing::query()->create(['household_id' => 1]);

    expect($created->household_id)->toBe(1);
});

<?php

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Arch\Support\Domains;
use Tests\Arch\Support\HouseholdScopeAudit;

// SEC-07 / NFR-12: every model with a household_id is scoped to one household.

uses(RefreshDatabase::class);

it('scopes every model that has a household_id', function () {
    expect(HouseholdScopeAudit::unscoped(Domains::modelClasses()))
        ->toBe([], 'Models with a household_id column must use '.BelongsToHousehold::class);
});

it('flags a model with a household_id and no scope', function () {
    Schema::create('audit_scoped', fn ($table) => $table->unsignedBigInteger('household_id'));
    Schema::create('audit_unscoped', fn ($table) => $table->unsignedBigInteger('household_id'));
    Schema::create('audit_instance_level', fn ($table) => $table->id());

    $scoped = new class extends Model
    {
        use BelongsToHousehold;

        protected $table = 'audit_scoped';
    };
    $unscoped = new class extends Model
    {
        protected $table = 'audit_unscoped';
    };
    $instanceLevel = new class extends Model
    {
        protected $table = 'audit_instance_level';
    };

    expect(HouseholdScopeAudit::unscoped([$scoped::class, $unscoped::class, $instanceLevel::class]))
        ->toBe([$unscoped::class]);
});

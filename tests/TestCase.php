<?php

namespace Tests;

use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Registration is closed on a new instance. Tests that sign people up open it first.
     */
    protected function openRegistration(): void
    {
        app(PlatformSettings::class)->openRegistration(true);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}

<?php

namespace Tests;

use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Nothing leaves the server by default, and a test proves it: an outbound request that no test faked fails
     * loudly, so a new call to another site cannot slip in unnoticed.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

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

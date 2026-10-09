<?php

namespace Sage\Credible\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Sage\Credible\CredibleServiceProvider;
use Sage\Credible\Tests\TestCase;

class CredibleServiceProviderTest extends TestCase
{
    public function test_it_merges_the_package_config(): void
    {
        $this->assertSame(
            [30, 60, 120, 200, 300, 400, 500, 600, 1000, 1500],
            config('credible.api_backoff_timing')
        );
    }

    public function test_it_publishes_the_config_under_its_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(CredibleServiceProvider::class, 'credible-config');

        $this->assertCount(1, $paths);
        $this->assertSame(config_path('credible.php'), array_values($paths)[0]);
    }
}

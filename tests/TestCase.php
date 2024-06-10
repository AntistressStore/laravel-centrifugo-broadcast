<?php

namespace denis660\Centrifugo\Test;

use denis660\Centrifugo\{Centrifugo, CentrifugoServiceProvider};

/**
 * @internal
 *
 * @coversNothing
 */
class TestCase extends \Orchestra\Testbench\TestCase
{
    protected Centrifugo $centrifuge;

    public function setUp(): void
    {
        parent::setUp();
        $this->centrifuge = $this->app->make('centrifugo');
    }

    protected function getPackageProviders($app): array
    {
        return [
            CentrifugoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('broadcasting.default', 'centrifugo');
        $app['config']->set('broadcasting.connections.centrifugo', [
            'driver' => 'centrifugo',
            'secret' => 'bbe7d157-a253-4094-9759-06a8236543f9',
            'apikey' => 'd7627bb6-2292-4911-82e1-615c0ed3eebb',
            'url' => 'http://host.docker.internal:8001',
        ]);
    }
}

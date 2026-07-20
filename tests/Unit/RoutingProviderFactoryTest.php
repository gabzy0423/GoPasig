<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\RoutingProviderFactory;
use App\Services\Providers\GoogleRoutingProvider;
use App\Services\Providers\OsrmRoutingProvider;
use App\Services\Providers\ManualRoutingProvider;
use InvalidArgumentException;

class RoutingProviderFactoryTest extends TestCase
{
    public function test_factory_resolves_google_driver()
    {
        $provider = RoutingProviderFactory::make('google');
        $this->assertInstanceOf(GoogleRoutingProvider::class, $provider);
        $this->assertEquals('google', $provider->getIdentifier());
    }

    public function test_factory_resolves_osrm_driver()
    {
        $provider = RoutingProviderFactory::make('osrm');
        $this->assertInstanceOf(OsrmRoutingProvider::class, $provider);
        $this->assertEquals('osrm', $provider->getIdentifier());
    }

    public function test_factory_resolves_manual_driver()
    {
        $provider = RoutingProviderFactory::make('manual');
        $this->assertInstanceOf(ManualRoutingProvider::class, $provider);
        $this->assertEquals('manual', $provider->getIdentifier());
    }

    public function test_factory_uses_config_default_when_driver_null()
    {
        config(['routing.default' => 'osrm']);
        $provider = RoutingProviderFactory::make();
        $this->assertInstanceOf(OsrmRoutingProvider::class, $provider);

        config(['routing.default' => 'google']);
        $provider = RoutingProviderFactory::make();
        $this->assertInstanceOf(GoogleRoutingProvider::class, $provider);
    }

    public function test_factory_throws_exception_for_unsupported_driver()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('driver [invalid_driver] is not supported');

        RoutingProviderFactory::make('invalid_driver');
    }
}

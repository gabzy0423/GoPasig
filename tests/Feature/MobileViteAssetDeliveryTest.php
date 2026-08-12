<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MobileViteAssetDeliveryTest extends TestCase
{
    private string $hotFile;
    private bool $hadHotFile = false;
    private string $originalHotContents = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotFile = public_path('hot');
        $this->hadHotFile = is_file($this->hotFile);
        $this->originalHotContents = $this->hadHotFile ? (string) file_get_contents($this->hotFile) : '';

        \Illuminate\Support\Facades\Vite::useHotFile(public_path('hot'));

        Route::get('/__vite-asset-delivery-test', fn () => view('mobile-vite-asset-delivery-test'));
        File::put(resource_path('views/mobile-vite-asset-delivery-test.blade.php'), "@vite(['resources/css/app.css', 'resources/js/app.js'])");
    }

    protected function tearDown(): void
    {
        File::delete(resource_path('views/mobile-vite-asset-delivery-test.blade.php'));

        if ($this->hadHotFile) {
            File::put($this->hotFile, $this->originalHotContents);
        } elseif (is_file($this->hotFile)) {
            File::delete($this->hotFile);
        }

        \Illuminate\Support\Facades\Vite::useHotFile(public_path('hot'));

        parent::tearDown();
    }

    public function test_loopback_desktop_requests_keep_vite_hot_assets(): void
    {
        File::put($this->hotFile, 'http://127.0.0.1:5173');

        $this->get('/__vite-asset-delivery-test')
            ->assertOk()
            ->assertSee('http://127.0.0.1:5173/@vite/client', false)
            ->assertSee('http://127.0.0.1:5173/resources/css/app.css', false);
    }

    public function test_external_mobile_requests_fall_back_to_manifest_when_hot_is_loopback(): void
    {
        if (! is_file(public_path('build/manifest.json'))) {
            $this->markTestSkipped('Vite manifest is required to verify mobile asset fallback.');
        }

        File::put($this->hotFile, 'http://127.0.0.1:5173');

        $this->withServerVariables([
            'HTTP_NGROK_SKIP_BROWSER_WARNING' => '1',
        ])->get('http://mobile-uat.test/__vite-asset-delivery-test')
            ->assertOk()
            ->assertDontSee('127.0.0.1:5173', false)
            ->assertSee('/build/assets/app-', false);
    }
}
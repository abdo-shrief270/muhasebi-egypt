<?php

namespace Tests\Feature\Modules;

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleTier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class ModuleDependenciesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $registry = $this->app->make(ModuleRegistry::class);
        $registry->register(new ModuleManifest(key: 'alpha', name: 'ألفا', tier: ModuleTier::Optional));
        $registry->register(new ModuleManifest(key: 'beta', name: 'بيتا', tier: ModuleTier::Optional, dependsOn: ['alpha']));
    }

    public function test_a_module_needs_its_dependencies_enabled_first(): void
    {
        $this->actingAsOwnerOf();

        $this->postJson('/api/v1/modules/beta/trial')
            ->assertUnprocessable()
            ->assertJson(['code' => 'module_dependencies_missing', 'missing' => ['alpha']]);

        $this->postJson('/api/v1/modules/alpha/trial')->assertOk();
        $this->postJson('/api/v1/modules/beta/trial')->assertOk();
    }

    public function test_a_module_cannot_be_hidden_while_an_enabled_module_depends_on_it(): void
    {
        $this->actingAsOwnerOf();
        $this->postJson('/api/v1/modules/alpha/trial')->assertOk();
        $this->postJson('/api/v1/modules/beta/trial')->assertOk();

        $this->postJson('/api/v1/modules/alpha/disable')
            ->assertUnprocessable()
            ->assertJson(['code' => 'module_has_dependents', 'dependents' => ['beta']]);

        $this->postJson('/api/v1/modules/beta/disable')->assertOk();
        $this->postJson('/api/v1/modules/alpha/disable')->assertOk();
    }
}

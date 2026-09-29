<?php

namespace Tests\Architecture;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Billing\Models\PlatformAdminAction;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Models\User;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Tenancy\SharedBetweenTenants;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Keeps the modular monolith honest:
 *  - a module talks to another module only through its Contracts\ and Events\
 *  - every model holding shop data is tenant scoped (one shop, or shared between the parties)
 *  - every route of an optional module is guarded by `module:{key}`
 */
class ModuleBoundariesTest extends TestCase
{
    /** Models that are intentionally not tenant scoped (resolved before a tenant is known). */
    private const UNSCOPED_MODELS = [
        Tenant::class,
        User::class,
        PlatformAdmin::class,
        PlatformAdminAction::class,
    ];

    public function test_modules_only_use_other_modules_contracts_and_events(): void
    {
        $violations = [];

        foreach ($this->moduleFiles() as [$module, $file]) {
            preg_match_all('/^use\s+App\\\\Modules\\\\(\w+)\\\\([\w\\\\]+)/m', (string) file_get_contents($file), $matches, PREG_SET_ORDER);

            foreach ($matches as [, $otherModule, $rest]) {
                $allowed = $otherModule === $module
                    || str_starts_with($rest, 'Contracts\\')
                    || str_starts_with($rest, 'Events\\');

                if (! $allowed) {
                    $violations[] = str_replace(base_path().'/', '', $file)." uses App\\Modules\\{$otherModule}\\{$rest}";
                }
            }
        }

        $this->assertSame([], $violations, "Cross-module access must go through Contracts\\ or Events\\:\n".implode("\n", $violations));
    }

    public function test_models_are_tenant_scoped(): void
    {
        $unscoped = [];

        foreach (glob(app_path('Modules/*/Models/*.php')) ?: [] as $file) {
            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(app_path()) + 1));

            $traits = class_uses_recursive($class);
            $scoped = in_array(BelongsToTenant::class, $traits, true) || in_array(SharedBetweenTenants::class, $traits, true);

            if (! in_array($class, self::UNSCOPED_MODELS, true) && ! $scoped) {
                $unscoped[] = $class;
            }
        }

        $this->assertSame([], $unscoped, 'These models must use BelongsToTenant or SharedBetweenTenants (or be added to the allowlist on purpose).');
    }

    public function test_optional_module_routes_require_the_module(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            if (! preg_match('/^App\\\\Modules\\\\(\w+)\\\\/', $route->getActionName(), $m)) {
                continue;
            }

            $manifest = require app_path("Modules/{$m[1]}/module.php");

            if ($manifest->isOptional() && ! in_array("module:{$manifest->key}", $route->gatherMiddleware(), true)) {
                $missing[] = $route->uri();
            }
        }

        $this->assertNotEmpty($registry->all());
        $this->assertSame([], $missing, 'Routes of optional modules must use the module:{key} middleware.');
    }

    public function test_every_module_manifest_is_valid(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        foreach ($registry->all() as $module) {
            foreach ($module->dependsOn as $dependency) {
                $this->assertTrue($registry->has($dependency), "{$module->key} depends on unknown module {$dependency}");
            }
        }
    }

    /**
     * @return list<array{string, string}>
     */
    private function moduleFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Modules')));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative = substr($file->getPathname(), strlen(app_path('Modules')) + 1);
                $files[] = [explode('/', $relative)[0], $file->getPathname()];
            }
        }

        return $files;
    }
}

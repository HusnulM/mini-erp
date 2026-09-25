<?php

namespace Tests\Feature;

use App\Central\Models\Module;
use App\Central\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModuleCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function modules_sync_copies_manifests_and_dependencies_into_the_central_catalog(): void
    {
        $this->artisan('modules:sync')->assertSuccessful();

        $this->assertSame(8, Module::count());

        $procurement = Module::where('code', 'procurement')->firstOrFail();
        $this->assertEqualsCanonicalizing(['inventory', 'workflow', 'finance'], $procurement->requires->pluck('code')->all());
        $this->assertTrue((bool) $procurement->requires->firstWhere('code', 'finance')->pivot->is_optional);
        $this->assertTrue(Module::where('code', 'core')->value('is_core'));
    }

    #[Test]
    public function modules_removed_from_code_are_deprecated_not_deleted(): void
    {
        Module::create(['code' => 'legacy', 'name' => 'Legacy', 'version' => '1.0.0']);

        $this->artisan('modules:sync')->assertSuccessful();

        $this->assertSame('deprecated', Module::where('code', 'legacy')->value('status'));
    }

    #[Test]
    public function sync_is_repeatable(): void
    {
        $this->artisan('modules:sync')->assertSuccessful();
        $this->artisan('modules:sync')->assertSuccessful();

        $this->assertSame(8, Module::count());
    }

    #[Test]
    public function plans_include_the_modules_from_the_tdd(): void
    {
        $this->artisan('modules:sync');
        $this->seed(PlanSeeder::class);

        $starter = Plan::where('code', 'STARTER')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['core', 'master', 'inventory', 'pos', 'reporting'],
            $starter->modules->pluck('code')->all()
        );
        $this->assertSame(8, Plan::where('code', 'ENTERPRISE')->first()->modules()->count());
    }
}

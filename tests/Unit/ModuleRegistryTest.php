<?php

namespace Tests\Unit;

use App\Support\Modules\ModuleRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ModuleRegistryTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/erp-modules-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    private function module(string $folder, string $code, array $extra = []): void
    {
        mkdir("{$this->dir}/{$folder}");
        file_put_contents("{$this->dir}/{$folder}/module.json", json_encode(
            ['name' => $folder, 'alias' => $code, 'code' => $code, 'version' => '1.0.0'] + $extra
        ));
    }

    private function erpLikeModules(): ModuleRegistry
    {
        $this->module('Core', 'core', ['is_core' => true]);
        $this->module('MasterData', 'master', ['is_core' => true, 'requires' => ['core']]);
        $this->module('Workflow', 'workflow', ['requires' => ['core']]);
        $this->module('Inventory', 'inventory', ['requires' => ['master']]);
        $this->module('Procurement', 'procurement', ['requires' => ['inventory', 'workflow'], 'optional' => ['finance']]);
        $this->module('Pos', 'pos', ['requires' => ['inventory']]);
        $this->module('Finance', 'finance', ['requires' => ['master']]);

        return new ModuleRegistry($this->dir);
    }

    #[Test]
    public function it_orders_modules_so_dependencies_come_first_and_core_leads(): void
    {
        $order = array_keys($this->erpLikeModules()->all());

        $this->assertSame(['core', 'master'], array_slice($order, 0, 2));
        $this->assertLessThan(array_search('procurement', $order), array_search('inventory', $order));
        $this->assertLessThan(array_search('procurement', $order), array_search('workflow', $order));
    }

    #[Test]
    public function it_resolves_transitive_dependencies_in_install_order(): void
    {
        $registry = $this->erpLikeModules();

        $this->assertSame(['core', 'master', 'inventory', 'workflow'], $registry->dependenciesOf('procurement'));
        $this->assertSame(['core', 'master', 'inventory', 'pos'], $registry->withDependencies(['pos']));
        $this->assertSame([], $registry->dependenciesOf('core'));
    }

    #[Test]
    public function optional_dependencies_are_not_required(): void
    {
        $this->assertNotContains('finance', $this->erpLikeModules()->dependenciesOf('procurement'));
    }

    #[Test]
    public function it_lists_modules_that_block_deactivation(): void
    {
        $registry = $this->erpLikeModules();

        $this->assertEqualsCanonicalizing(['procurement', 'pos'], $registry->dependentsOf('inventory'));
        $this->assertSame([], $registry->dependentsOf('pos'));
    }

    #[Test]
    public function it_rejects_unknown_dependencies(): void
    {
        $this->module('Core', 'core', ['requires' => ['nope']]);

        $this->expectExceptionMessage('depends on unknown module [nope]');
        (new ModuleRegistry($this->dir))->all();
    }

    #[Test]
    public function it_rejects_circular_dependencies(): void
    {
        $this->module('Alpha', 'alpha', ['requires' => ['beta']]);
        $this->module('Beta', 'beta', ['requires' => ['alpha']]);

        $this->expectExceptionMessage('Circular module dependency');
        (new ModuleRegistry($this->dir))->all();
    }

    #[Test]
    public function core_modules_cannot_require_optional_modules(): void
    {
        $this->module('Core', 'core', ['is_core' => true, 'requires' => ['pos']]);
        $this->module('Pos', 'pos');

        $this->expectExceptionMessage('cannot require non-core module');
        (new ModuleRegistry($this->dir))->all();
    }

    #[Test]
    public function it_rejects_invalid_manifests(): void
    {
        $this->module('Bad', 'Bad-Code');

        $this->expectException(InvalidArgumentException::class);
        (new ModuleRegistry($this->dir))->all();
    }

    #[Test]
    public function the_real_modules_directory_is_valid(): void
    {
        $registry = new ModuleRegistry(dirname(__DIR__, 2).'/Modules');

        $this->assertSame(
            ['core', 'master', 'finance', 'inventory', 'pos', 'reporting', 'workflow', 'procurement'],
            array_keys($registry->all())
        );
        $this->assertSame(['core', 'master'], array_keys($registry->core()));
    }

    #[Test]
    public function wildcard_permissions_expand_to_the_standard_actions(): void
    {
        $this->module('Procurement', 'procurement', [
            'permissions' => ['procurement.purchase_order.*', 'procurement.purchase_order.approve', 'procurement.settings.manage'],
        ]);

        $this->assertSame([
            'procurement.purchase_order.view',
            'procurement.purchase_order.create',
            'procurement.purchase_order.update',
            'procurement.purchase_order.delete',
            'procurement.purchase_order.approve',
            'procurement.settings.manage',
        ], (new ModuleRegistry($this->dir))->get('procurement')->expandedPermissions(['view', 'create', 'update', 'delete']));
    }

    #[Test]
    public function menu_items_are_read_with_defaults(): void
    {
        $this->module('Pos', 'pos', ['menu' => [['label' => 'POS', 'route' => 'pos.index']]]);

        $this->assertSame(
            [['label' => 'POS', 'route' => 'pos.index', 'permission' => null, 'order' => 100]],
            (new ModuleRegistry($this->dir))->get('pos')->menu
        );
    }

    #[Test]
    public function a_menu_item_without_a_route_is_rejected(): void
    {
        $this->module('Pos', 'pos', ['menu' => [['label' => 'POS']]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a "label" and a "route"');
        (new ModuleRegistry($this->dir))->all();
    }
}

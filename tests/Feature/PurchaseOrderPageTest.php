<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Frontend prototype: only checks that each page renders. Replace with behaviour tests
 * once PurchaseOrderService exists.
 */
class PurchaseOrderPageTest extends TestCase
{
    use RefreshDatabase;

    private const UUID = '0b9a1c2d-3e4f-4a5b-8c6d-7e8f9a0b1c03';

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/purchase-order')->assertRedirect('/login');
    }

    public function test_pages_render_expected_components(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['role_id' => 2, 'status_aktif' => true]);

        $pages = [
            '/purchase-order' => 'PurchaseOrder/Index',
            '/purchase-order/create' => 'PurchaseOrder/Create',
            '/purchase-order/'.self::UUID => 'PurchaseOrder/Show',
            '/purchase-order/'.self::UUID.'/edit' => 'PurchaseOrder/Edit',
            '/purchase-order/'.self::UUID.'/negosiasi' => 'PurchaseOrder/Negosiasi',
        ];

        foreach ($pages as $url => $component) {
            $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }
    }

    public function test_non_uuid_parameter_is_rejected(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['role_id' => 2, 'status_aktif' => true]);

        $this->actingAs($user)->get('/purchase-order/123')->assertNotFound();
    }
}

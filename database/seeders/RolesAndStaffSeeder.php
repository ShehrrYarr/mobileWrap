<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Preset roles: Admin, Manager, Cashier, Laser Operator.
 *
 * Local demo logins (password for all: "password"):
 *   admin@mobilewrap.test    — Admin
 *   manager@mobilewrap.test  — Manager
 *   cashier@mobilewrap.test  — Cashier
 *   laser@mobilewrap.test    — Laser Operator
 */
class RolesAndStaffSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view' => 'View dashboard',
        'catalog.manage' => 'Manage products, categories, brands & devices',
        'inventory.manage' => 'Adjust stock & view stock ledgers',
        'cost.view' => 'See cost prices & margins',
        'orders.manage' => 'Manage online orders',
        'customers.manage' => 'Manage customers',
        'pos.use' => 'Use the POS',
        'laser.manage' => 'Run the laser cutter queue',
        'finance.manage' => 'Bank, cash & ledgers',
        'marketing.manage' => 'Coupons, deals & popups',
        'storefront.manage' => 'Themes & homepage builder',
        'settings.manage' => 'Store settings, bank accounts & staff',
        'reports.view' => 'View reports',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $roles = [
            'Admin' => array_keys(self::PERMISSIONS),
            'Manager' => ['dashboard.view', 'catalog.manage', 'inventory.manage', 'cost.view', 'orders.manage', 'customers.manage', 'pos.use', 'laser.manage', 'finance.manage', 'marketing.manage', 'reports.view'],
            'Cashier' => ['dashboard.view', 'pos.use', 'orders.manage', 'customers.manage'],
            'Laser Operator' => ['dashboard.view', 'laser.manage'],
        ];

        foreach ($roles as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        $staff = [
            ['Shop Admin', 'admin@mobilewrap.test', 'Admin', '03001112233'],
            ['Store Manager', 'manager@mobilewrap.test', 'Manager', '03002223344'],
            ['Counter Cashier', 'cashier@mobilewrap.test', 'Cashier', '03003334455'],
            ['Laser Operator', 'laser@mobilewrap.test', 'Laser Operator', '03004445566'],
        ];

        foreach ($staff as [$name, $email, $role, $phone]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'phone' => $phone,
                'password' => 'password',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([$role]);
        }
    }
}

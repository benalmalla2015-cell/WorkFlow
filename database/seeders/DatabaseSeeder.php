<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'orders.create',
            'orders.submit_to_factory',
            'orders.factory_pricing',
            'orders.approve',
            'orders.customer_approval',
            'orders.confirm_payment',
            'documents.generate_quotation',
            'documents.generate_invoice',
            'users.manage',
            'settings.manage',
            'audit.view',
            'analytics.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $adminRole = Role::findOrCreate('admin', 'web');
        $salesRole = Role::findOrCreate('sales', 'web');
        $factoryRole = Role::findOrCreate('factory', 'web');

        $adminRole->syncPermissions($permissions);
        $salesRole->syncPermissions([
            'orders.create',
            'orders.submit_to_factory',
            'orders.customer_approval',
            'orders.confirm_payment',
            'documents.generate_quotation',
            'documents.generate_invoice',
        ]);
        $factoryRole->syncPermissions([
            'orders.factory_pricing',
        ]);

        $admin = User::updateOrCreate(
            ['email' => 'admin@workflow.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
        $admin->syncRoles(['admin']);

        $sales = User::updateOrCreate(
            ['email' => 'sales@workflow.com'],
            [
                'name' => 'Sales Representative',
                'password' => Hash::make('sales123'),
                'role' => 'sales',
                'is_active' => true,
            ]
        );
        $sales->syncRoles(['sales']);

        $factory = User::updateOrCreate(
            ['email' => 'factory@workflow.com'],
            [
                'name' => 'Factory Manager',
                'password' => Hash::make('factory123'),
                'role' => 'factory',
                'is_active' => true,
            ]
        );
        $factory->syncRoles(['factory']);

        $defaultSettings = [
            'default_profit_margin' => '20',
            'company_name' => 'DAYANCO',
            'company_address' => 'ROOM 807-1, NO 1, 2ND QILIN STREET, HUANGGE TOWN, NANSHA DISTRICT, GUANGZHOU, 511455, P.R. CHINA',
            'company_phone' => '+86 188188 45411',
            'company_email' => 'team@dayancoofficial.com',
            'company_attn' => 'Mr. Abdulmalek',
            'beneficiary_name' => 'DAYANCO',
            'beneficiary_bank' => 'ZHEJIANG CHOUZHOU COMMERCIAL BANK',
            'account_number' => 'NRA15617142010500006871',
            'swift_code' => 'CZCBCN2X',
            'bank_address' => 'YIWU LEYUAN EAST, JIANGBIN RD, YIWU, ZHEJIANG, CHINA',
            'beneficiary_address' => 'RM906, 9/F FLOOR, RUISHENGGUOJI, NO. 787 ZENGCHA LU, BAIYUN DISTRICT, GUANGZHOU 510000, P.R. CHINA',
            'company_country' => 'China',
            'payment_purpose' => 'PURCHASE OF GOODS',
        ];

        foreach ($defaultSettings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Default login credentials:');
        $this->command->info('Admin: admin@workflow.com / admin123');
        $this->command->info('Sales: sales@workflow.com / sales123');
        $this->command->info('Factory: factory@workflow.com / factory123');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default admin user
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@workflow.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create sample sales user
        User::create([
            'name' => 'Sales Representative',
            'email' => 'sales@workflow.com',
            'password' => Hash::make('sales123'),
            'role' => 'sales',
            'is_active' => true,
        ]);

        // Create sample factory user
        User::create([
            'name' => 'Factory Manager',
            'email' => 'factory@workflow.com',
            'password' => Hash::make('factory123'),
            'role' => 'factory',
            'is_active' => true,
        ]);

        // Ensure default settings exist
        $defaultSettings = [
            'default_profit_margin' => '20',
            'company_name' => 'DAYANCO',
            'company_address' => '123 Business Street, Commercial District, City, Country',
            'company_phone' => '+1234567890',
            'beneficiary_name' => 'DAYANCO TRADING',
            'beneficiary_bank' => 'International Bank of Commerce',
            'account_number' => '1234567890123456',
            'swift_code' => 'IBOCUS33',
            'bank_address' => '456 Banking Avenue, Financial District, City, Country',
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

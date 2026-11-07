<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

/**
 * Demo customers to "act as" (there is no auth, see README, "Assumptions").
 * One is loyal, so the loyalty discount can be shown.
 */
class CustomerSeeder extends Seeder
{
    private const array CUSTOMERS = [
        ['name' => 'Alex Morgan', 'email' => 'alex@example.com', 'is_loyal' => true],
        ['name' => 'Jamie Lee', 'email' => 'jamie@example.com', 'is_loyal' => false],
        ['name' => 'Sam Taylor', 'email' => 'sam@example.com', 'is_loyal' => false],
    ];

    public function run(): void
    {
        foreach (self::CUSTOMERS as $customer) {
            Customer::updateOrCreate(
                ['email' => $customer['email']],
                ['name' => $customer['name'], 'is_loyal' => $customer['is_loyal']],
            );
        }
    }
}

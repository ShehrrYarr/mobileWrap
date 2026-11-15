<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Demo storefront account (password: "password"):
 *   customer@mobilewrap.test / 03001234567
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $demo = Customer::create([
            'type' => 'retail', 'name' => 'Ali Raza', 'phone' => '03001234567', 'email' => 'customer@mobilewrap.test',
            'password' => 'password', 'address' => 'House 21, Street 4, DHA Phase 5', 'city' => 'Lahore',
        ]);
        $demo->addresses()->create(['label' => 'Home', 'name' => 'Ali Raza', 'phone' => '03001234567', 'address' => 'House 21, Street 4, DHA Phase 5', 'city' => 'Lahore', 'is_default' => true]);

        $people = [
            ['Ayesha Khan', '03211234567', 'Karachi', 'Flat 7, Block 13-D, Gulshan-e-Iqbal'],
            ['Hamza Riaz', '03331234567', 'Islamabad', 'House 5, Street 18, F-10/2'],
            ['Sana Malik', '03451234567', 'Lahore', '45-B Model Town'],
            ['Usman Ahmed', '03121234567', 'Multan', 'Gulgasht Colony, Street 9'],
            ['Fatima Noor', '03011234567', 'Rawalpindi', 'Satellite Town, Block C'],
            ['Bilal Sheikh', '03221234567', 'Faisalabad', 'Peoples Colony No. 1'],
        ];
        foreach ($people as [$name, $phone, $city, $address]) {
            Customer::create(['type' => 'retail', 'name' => $name, 'phone' => $phone, 'city' => $city, 'address' => $address]);
        }

        Customer::create([
            'type' => 'wholesale', 'name' => 'Hamza Mobiles (Wholesale)', 'phone' => '03009876543', 'city' => 'Lahore',
            'address' => 'Shop 3, Hall Road', 'credit_limit' => 150000, 'notes' => 'Buys cases and glass in bulk on khata.',
        ]);

        Vendor::create(['name' => 'Shenzhen Accessories Co.', 'company' => 'SZ Accessories Ltd', 'phone' => '+86 755 0000 0000', 'city' => 'Shenzhen']);
        Vendor::create(['name' => 'Lahore Wholesale Mart', 'company' => 'LWM Traders', 'phone' => '03004561234', 'city' => 'Lahore', 'address' => 'Hall Road']);
    }
}

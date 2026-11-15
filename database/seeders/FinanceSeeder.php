<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\ExpenseCategory;
use App\Services\FinanceService;
use Illuminate\Database\Seeder;

/** Demo bank accounts (fake numbers), the cash drawer and expense categories. */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $finance = app(FinanceService::class);

        $accounts = [
            ['Meezan Bank', 'Mobile Wrap', '0101-0104567890', 'PK36MEZN0001010104567890', 'Gulberg III, Lahore', 150000, true],
            ['HBL', 'Mobile Wrap', '1234-5678901-03', 'PK24HABB0012345678901003', 'Liberty Market, Lahore', 85000, true],
            ['Bank Alfalah', 'Mobile Wrap Pvt', '0045-1009876543', null, 'MM Alam Road, Lahore', 40000, false],
        ];

        foreach ($accounts as [$bank, $title, $number, $iban, $branch, $opening, $checkout]) {
            $account = BankAccount::create([
                'bank_name' => $bank,
                'account_title' => $title,
                'account_number' => $number,
                'iban' => $iban,
                'branch' => $branch,
                'opening_balance' => $opening,
                'current_balance' => 0,
                'show_on_checkout' => $checkout,
            ]);
            $finance->opening($account, $opening);
        }

        $drawer = CashDrawer::create(['name' => 'Main Counter', 'opening_balance' => 20000]);
        $finance->opening($drawer, 20000);

        foreach (['Rent', 'Salaries', 'Electricity & utilities', 'Internet & phone', 'Packaging', 'Courier charges', 'Marketing', 'Maintenance', 'Miscellaneous'] as $name) {
            ExpenseCategory::create(['name' => $name]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\CashDrawer;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Vendor;
use App\Services\CustomerLedgerService;
use App\Services\ExpenseService;
use App\Services\FinanceService;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 3 demo data: purchases in different states, vendor payments,
 * a purchase return, this month's expenses, a cash deposit and a khata collection.
 */
class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        Auth::guard('web')->setUser(User::role('Admin')->first() ?? User::first());

        $purchases = app(PurchaseService::class);
        $meezan = BankAccount::where('bank_name', 'Meezan Bank')->first();
        $hbl = BankAccount::where('bank_name', 'HBL')->first();
        $cash = CashDrawer::main();
        $lahore = Vendor::where('name', 'Lahore Wholesale Mart')->first();
        $shenzhen = Vendor::where('name', 'Shenzhen Accessories Co.')->first();

        $item = fn (string $sku, float $qty, ?float $cost = null) => [
            'product_variant_id' => ($v = ProductVariant::where('sku', $sku)->firstOrFail())->id,
            'quantity' => $qty,
            'unit_cost' => $cost ?? (float) $v->cost_price,
        ];
        $bill = fn (Vendor $vendor, ?string $number, int $daysAgo, ?int $dueIn, float $discount = 0, float $shipping = 0, ?string $status = 'ordered') => [
            'vendor_id' => $vendor->id, 'bill_number' => $number, 'order_date' => now()->subDays($daysAgo)->toDateString(),
            'due_date' => $dueIn === null ? null : now()->subDays($daysAgo)->addDays($dueIn)->toDateString(),
            'discount' => $discount, 'shipping' => $shipping, 'notes' => null, 'status' => $status,
        ];
        $receiveAll = fn ($po, int $daysAgo) => $purchases->receive($po, $po->items->mapWithKeys(fn ($i) => [$i->id => (float) $i->quantity])->all(), now()->subDays($daysAgo)->toDateString());

        // Opening payable carried over from the paper ledger
        app(\App\Services\VendorLedgerService::class)->record($lahore, 'opening', 0, 18500, null, 'Opening balance', null, now()->subDays(40)->toDateString());

        // PO-1001: cables & chargers, fully received, part paid, one carton returned
        $po1 = $purchases->save(null, $bill($lahore, 'LWM-2231', 18, 30, 500, 0), [
            $item('UGR-CC-BLAC-1M', 40, 420),
            $item('UGR-CC-WHIT-1M', 30, 420),
            $item('ANK-20W-WHIT', 20, 1180),
            $item('RMX-3IN1-BLAC', 25, 530),
        ]);
        $receiveAll($po1, 16);
        $purchases->pay($lahore, $meezan, 40000, $po1->fresh(), 'Online transfer — part payment', now()->subDays(15)->toDateString());
        $purchases->returnToVendor($po1->fresh('items'), [$po1->items->firstWhere('product_variant_id', ProductVariant::where('sku', 'RMX-3IN1-BLAC')->value('id'))->id => 5], 'Faulty connectors');

        // PO-1002: hydrogel and privacy glass from China, overdue
        $po2 = $purchases->save(null, $bill($shenzhen, 'SZ-88104', 35, 21, 0, 6500), [
            $item('RM-HYD-CLEA', 120000, 0.0550),   // 2 rolls × 30 cm × 20 m
            $item('RM-PRV-PRIV', 40000, 0.1700),
            $item('SPG-CLR-CRYS', 30, 1050),
        ]);
        $receiveAll($po2, 30);
        $purchases->pay($shenzhen, $hbl, 15000, $po2->fresh(), 'Advance via HBL', now()->subDays(30)->toDateString());

        // PO-1003: partly received — power banks still to come
        $po3 = $purchases->save(null, $bill($lahore, 'LWM-2290', 6, 15), [
            $item('JR-SLM-BLAC', 20, 520),
            $item('JR-SLM-OCEA', 20, 520),
            $item('BSU-PB-BLAC-10000MAH', 10, 2150),
        ]);
        $purchases->receive($po3, $po3->items->filter(fn ($i) => $i->quantity == 20)->mapWithKeys(fn ($i) => [$i->id => 20])->all(), now()->subDays(4)->toDateString());

        // PO-1004: ordered, nothing received yet
        $purchases->save(null, $bill($shenzhen, null, 2, 30), [
            $item('ANK-MAG-WHIT', 10, 1850),
            $item('ANK-MPB-BLAC', 8, 2400),
        ]);

        // PO-1005: a draft still being put together
        $purchases->save(null, $bill($lahore, null, 0, null, 0, 0, 'draft'), [$item('MW-LSC-MIDN', 24, 800)]);

        // This month's running costs
        $expenses = app(ExpenseService::class);
        $cat = fn (string $name) => ExpenseCategory::where('name', $name)->value('id');
        $monthDay = fn (int $day) => now()->startOfMonth()->addDays(min($day, now()->day) - 1)->toDateString();
        foreach ([
            ['Rent', 45000, $meezan, 1, 'Shop rent — '.now()->format('F')],
            ['Electricity & utilities', 8650, $cash, 3, 'LESCO bill'],
            ['Internet & phone', 2999, $cash, 3, 'PTCL fibre'],
            ['Packaging', 2200, $cash, 5, 'Mailer bags & bubble wrap'],
            ['Courier charges', 3450, $hbl, 7, 'TCS pickups'],
            ['Salaries', 35000, $meezan, 1, 'Cashier salary — advance'],
        ] as [$name, $amount, $account, $day, $note]) {
            $expenses->create(['expense_category_id' => $cat($name), 'amount' => $amount, 'date' => $monthDay($day), 'note' => $note], $account);
        }

        // Day's cash deposited into Meezan
        app(FinanceService::class)->transfer($cash, $meezan, 5000, 'Cash deposited at branch', now()->subDay()->toDateString());

        // Wholesale customer pays part of their khata at the counter
        $wholesale = Customer::where('type', 'wholesale')->where('current_balance', '>', 0)->first();
        if ($wholesale) {
            app(CustomerLedgerService::class)->receivePayment($wholesale, $cash, round((float) $wholesale->current_balance / 2), 'Part payment at counter');
        }
    }
}

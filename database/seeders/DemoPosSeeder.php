<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\DeviceModel;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Pos\PosCart;
use App\Services\Pos\PosService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/** Two demo POS sales (one with a queued laser cut) so the POS, sales list and laser queue aren't empty. */
class DemoPosSeeder extends Seeder
{
    public function run(): void
    {
        Auth::guard('web')->setUser(User::where('email', 'cashier@mobilewrap.test')->first() ?? User::first());
        $pos = app(PosService::class);

        $line = function (string $sku, int $qty, string $tier = 'retail') {
            $v = ProductVariant::where('sku', $sku)->firstOrFail();

            return ['key' => 'v'.$v->id, 'type' => 'product', 'variant_id' => $v->id, 'name' => $v->product->name, 'label' => $v->label, 'qty' => $qty, 'price' => $v->basePrice($tier), 'discount' => 0];
        };

        // 1) Walk-in: case + glass, paid in cash
        $lines = [$line('MW-LSC-MIDN', 1), $line('MW-UVG-CRYS', 1)];
        $total = PosCart::totals($lines)['total'];
        $pos->checkout($lines, [['method' => 'cash', 'amount' => $total, 'tendered' => ceil($total / 1000) * 1000]], null);

        // 2) Wholesale customer: cables + a privacy-glass laser cut, part bank / part khata
        $customer = Customer::where('type', 'wholesale')->first();
        $device = DeviceModel::where('name', 'Galaxy S25 Ultra')->first();
        $material = ProductVariant::where('sku', 'like', 'RM-PRV%')->first();
        $lines = [$line('UGR-CC-BLAC-1M', 5, 'wholesale')];
        if ($device && $material) {
            $lines[] = ['key' => 'ldemo', 'type' => 'laser', 'name' => 'Laser cut — Privacy glass', 'label' => $device->name, 'device_model_id' => $device->id,
                'material_variant_id' => $material->id, 'side' => 'front', 'width' => (float) $device->front_cut_width_mm, 'height' => (float) $device->front_cut_height_mm,
                'qty' => 1, 'fee' => 800, 'notes' => 'Customer waiting at the counter'];
        }
        $total = PosCart::totals($lines, [], null, $customer)['total'];
        $pos->checkout($lines, [
            ['method' => 'bank_transfer', 'amount' => 2000, 'bank_account_id' => BankAccount::active()->value('id')],
            ['method' => 'customer_credit', 'amount' => round($total - 2000, 2)],
        ], $customer);

        Auth::guard('web')->logout();
    }
}

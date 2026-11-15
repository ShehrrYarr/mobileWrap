<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Services\ReportService;
use App\Support\Period;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** A4 PDF downloads of the Sales, Purchase and Profit & Loss reports (same filters as the screens). */
class ReportPdfController extends Controller
{
    public function download(Request $request, string $report, ReportService $reports)
    {
        $user = $request->user();
        abort_unless($user->can('reports.view'), 403);
        abort_if($report === 'profit-loss' && ! $user->can('cost.view'), 403);

        $range = Period::make($request->query('period'), $request->query('from'), $request->query('to'));
        $canCost = $user->can('cost.view');
        $filters = [];

        switch ($report) {
            case 'sales':
                $channel = in_array($request->query('channel'), ['pos', 'online'], true) ? $request->query('channel') : null;
                $filters['Channel'] = $channel === 'pos' ? 'In store (POS)' : ($channel === 'online' ? 'Online store' : 'All channels');
                $data = [
                    'title' => 'Sales report',
                    's' => $reports->salesSummary($range, $channel),
                    'prev' => $reports->salesSummary($range->previous(), $channel),
                    'days' => $reports->salesByDay($range, $channel),
                    'products' => $reports->salesByProduct($range, $channel, 60),
                    'categories' => $reports->salesByCategory($range, $channel),
                    'payments' => $reports->salesByPayment($range, $channel),
                    'channels' => $channel ? [] : $reports->salesByChannel($range),
                    'staff' => $reports->salesByStaff($range, $channel),
                    'customers' => $reports->salesByCustomer($range, $channel, 20),
                    'pending' => $reports->pendingOnline(),
                ];
                break;

            case 'purchases':
                $vendor = $request->integer('vendor') ? Vendor::find($request->integer('vendor')) : null;
                $filters['Vendor'] = $vendor?->name ?? 'All vendors';
                $data = [
                    'title' => 'Purchase report',
                    's' => $reports->purchaseSummary($range, $vendor?->id),
                    'bills' => $reports->purchaseBills($range, $vendor?->id),
                    'vendors' => $reports->purchasesByVendor($range, $vendor?->id),
                    'products' => $reports->purchasesByProduct($range, $vendor?->id),
                ];
                break;

            case 'profit-loss':
                $data = [
                    'title' => 'Profit & loss',
                    'pl' => $reports->profitLoss($range),
                    'prev' => $reports->profitLoss($range->previous()),
                ];
                break;

            default:
                abort(404);
        }

        // Subsetting embeds only the glyphs used (~50 KB instead of ~900 KB per file).
        $pdf = Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->loadView('admin.reports.pdf.'.$report, $data + [
                'range' => $range,
                'canCost' => $canCost,
                'filters' => $filters,
                'generatedBy' => $user->name,
            ])
            ->setPaper('a4', 'portrait');

        // Page numbers on every page (needs the finished layout, so render first).
        $pdf->render();
        $canvas = $pdf->getDomPDF()->getCanvas();
        $canvas->page_text($canvas->get_width() - 112, $canvas->get_height() - 26, 'Page {PAGE_NUM} of {PAGE_COUNT}', null, 7.5, [0.45, 0.45, 0.45]);

        $name = Str::slug(store_name()).'-'.$report.'-'.$range->fileSlug().'.pdf';

        return $pdf->download($name);
    }
}

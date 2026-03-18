<?php

namespace App\Http\Controllers\Admin;

use App\Models\Sale;
use App\Models\Invoice;
use App\Models\InvoiceItemReturn;
use App\Models\Category;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index(){
        $title = 'dashboard';
        $total_purchases = Purchase::count();
        $total_categories = Category::count();
        $total_suppliers = Supplier::count();
        $total_sales = Invoice::count();
        
        $pieChart = app()->chartjs
                ->name('pieChart')
                ->type('pie')
                ->size(['width' => 400, 'height' => 200])
                ->labels(['Total Purchases', 'Total Suppliers','Total Sales'])
                ->datasets([
                    [
                        'backgroundColor' => ['#FF6384', '#36A2EB','#7bb13c'],
                        'hoverBackgroundColor' => ['#FF6384', '#36A2EB','#7bb13c'],
                        'data' => [$total_purchases, $total_suppliers,$total_sales]
                    ]
                ])
                ->options([
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                ]);
        
        // 7 Days Revenue Bar Chart
        $salesData = [];
        $salesLabels = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $salesLabels[] = Carbon::now()->subDays($i)->format('d M');
            $dailySum = Invoice::whereDate('created_at', $date)->sum('grand_total');
            $salesData[] = $dailySum;
        }

        $barChart = app()->chartjs
            ->name('barChart')
            ->type('bar')
            ->size(['width' => 400, 'height' => 200])
            ->labels($salesLabels)
            ->datasets([
                [
                    "label" => "Daily Revenue",
                    'backgroundColor' => '#7bb13c',
                    'data' => $salesData
                ]
            ])
            ->options([
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'yAxes' => [[
                        'ticks' => ['beginAtZero' => true]
                    ]]
                ]
            ]);

        $total_expired_products = Purchase::whereDate('expiry_date', '<', Carbon::now())->count();
        
        $latest_sales = Invoice::with(['items.product'])->latest()->take(5)->get();
        $latest_returns = InvoiceItemReturn::with(['invoiceItem.product'])->latest()->take(5)->get();
        $today_sales = Invoice::whereDate('created_at', '=', Carbon::now())->sum('grand_total');
        
        $expiring_purchases = Purchase::with(['product'])
            ->whereNotNull('expiry_date')
            ->where('quantity', '>', 0)
            ->whereDate('expiry_date', '<=', Carbon::today()->addMonths(6)->toDateString())
            ->orderBy('expiry_date', 'asc')
            ->take(6)
            ->get();
            
        return view('admin.dashboard',compact(
            'title','pieChart','barChart','total_expired_products',
            'latest_sales','latest_returns','today_sales','total_categories','expiring_purchases'
        ));
    }
}

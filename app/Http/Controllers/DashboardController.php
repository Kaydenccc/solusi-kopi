<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\ReportingService;

class DashboardController extends Controller
{
    protected $reportingService;

    public function __construct(ReportingService $reportingService)
    {
        $this->reportingService = $reportingService;
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('welcome');
        }
        $dateFrom = $request->get('date_from') ? Carbon::parse($request->get('date_from'))->startOfDay() : Carbon::now()->startOfMonth()->startOfDay();
        $dateTo = $request->get('date_to') ? Carbon::parse($request->get('date_to'))->endOfDay() : Carbon::now()->endOfMonth()->endOfDay();
        $recapMode = $request->get('recap_mode', 'daily');
        // Ambil data dari service
        $recap = $this->reportingService->getRecapData($dateFrom, $dateTo, null, $recapMode);
        $summary = $this->reportingService->getSummaryStats($dateFrom, $dateTo);
        $topProducts = $this->reportingService->getTopProducts($dateFrom, $dateTo);
        $outletPerformance = $this->reportingService->getOutletPerformance($dateFrom, $dateTo);
        $chart = $this->reportingService->getChartData($dateFrom, $dateTo);
        $recapData = $recap['recapData'] ?? collect();
        $periods = $recap['periods'] ?? collect();
        $dates = $chart['labels'] ?? collect();
        $omzet7days = $chart['omzet'] ?? collect();
        $orders7days = $chart['orders'] ?? collect();
        // Role-based view logic tetap, data dari service
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return view('dashboard', compact(
                'summary',
                'topProducts',
                'outletPerformance',
                'recapData',
                'periods',
                'dates',
                'omzet7days',
                'orders7days',
                'dateFrom',
                'dateTo',
                'recapMode',
            ));
        } elseif (method_exists($user, 'hasRole') && $user->hasRole('kasir')) {
            // Kasir diarahkan ke halaman Order Management sebagai halaman kerja utama
            return redirect()->route('console.orders.index');
        } elseif (method_exists($user, 'hasRole') && $user->hasRole('user')) {
            return redirect()->route('welcome');
        }
        return redirect()->route('welcome');
    }
}
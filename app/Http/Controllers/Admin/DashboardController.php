<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class DashboardController extends Controller
{
    public function index()
    {
        $counts = Order::selectRaw('fulfillment_status, COUNT(*) AS total')->groupBy('fulfillment_status')->pluck('total', 'fulfillment_status');
        $stats = [
            'total' => $counts->sum(),
            'pending' => ($counts['received'] ?? 0) + ($counts['processing'] ?? 0),
            'submitted' => ($counts['submitted_to_lulu'] ?? 0) + ($counts['print_job_created'] ?? 0) + ($counts['in_production'] ?? 0),
            'processing' => $counts['processing'] ?? 0,
            'in_production' => $counts['in_production'] ?? 0,
            'shipped' => $counts['shipped'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'today' => Order::whereDate('created_at', today())->count(),
            'this_week' => Order::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        $recentOrders = Order::latest()->take(10)->get();
        $failedOrders = Order::failed()->latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'failedOrders'));
    }
}

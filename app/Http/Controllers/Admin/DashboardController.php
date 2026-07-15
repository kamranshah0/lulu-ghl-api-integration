<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total'      => Order::count(),
            'pending'    => Order::pending()->count(),
            'submitted'  => Order::submitted()->count(),
            'processing'  => Order::where('fulfillment_status', 'processing')->count(),
            'in_production' => Order::where('fulfillment_status', 'in_production')->count(),
            'shipped'    => Order::where('fulfillment_status', 'shipped')->count(),
            'failed'     => Order::failed()->count(),
            'today'      => Order::whereDate('created_at', today())->count(),
            'this_week'  => Order::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        $recentOrders = Order::latest()->take(10)->get();
        $failedOrders = Order::failed()->latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'failedOrders'));
    }
}

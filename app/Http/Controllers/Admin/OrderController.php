<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLuluPrintJob;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Orders list with search + filter.
     */
    public function index(Request $request)
    {
        $query = Order::query()->latest();

        // Filter by status
        if ($status = $request->get('status')) {
            $query->where('fulfillment_status', $status);
        }

        // Search by email or name
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('buyer_email', 'like', "%{$search}%")
                  ->orWhere('buyer_name', 'like', "%{$search}%")
                  ->orWhere('ghl_order_id', 'like', "%{$search}%")
                  ->orWhere('lulu_job_id', 'like', "%{$search}%");
            });
        }

        // Date filter
        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $orders = $query->paginate(25)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * Order detail + full event audit log.
     */
    public function show(Order $order)
    {
        $order->load('events');
        return view('admin.orders.show', compact('order'));
    }

    /**
     * Manually retry a failed order.
     */
    public function retry(Order $order)
    {
        if ($order->fulfillment_status !== 'failed') {
            return back()->with('error', 'Only failed orders can be retried.');
        }

        // Reset for retry
        $order->update([
            'fulfillment_status' => 'received',
            'error_message'      => null,
            'retry_count'        => 0,
        ]);

        $order->logEvent('admin_manual_retry', 'admin', [], 'Admin triggered manual retry.');

        ProcessLuluPrintJob::dispatch($order);

        return back()->with('success', "Order #{$order->id} has been queued for retry.");
    }

    /**
     * Failed orders queue — all failed orders for manual review.
     */
    public function failed()
    {
        $orders = Order::failed()->latest()->paginate(25);
        return view('admin.orders.failed', compact('orders'));
    }

    /**
     * Export orders as CSV.
     */
    public function export(Request $request)
    {
        $orders = Order::query()
            ->when($request->get('status'), fn($q, $s) => $q->where('fulfillment_status', $s))
            ->get();

        $csv = "ID,GHL Order ID,Buyer Name,Email,Status,Lulu Job ID,Amount,Created At\n";
        foreach ($orders as $o) {
            $csv .= implode(',', [
                $o->id,
                $o->ghl_order_id,
                '"' . $o->buyer_name . '"',
                $o->buyer_email,
                $o->fulfillment_status,
                $o->lulu_job_id ?? '',
                $o->amount_charged,
                $o->created_at->format('Y-m-d H:i:s'),
            ]) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }
}

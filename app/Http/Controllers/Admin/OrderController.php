<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLuluPrintJob;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Orders list with search + filter.
     */
    public function index(Request $request)
    {
        $statuses = $this->statuses();
        $orders = $this->filteredOrders($request)->latest()->paginate(25)->withQueryString();

        return view('admin.orders.index', compact('orders', 'statuses'));
    }

    private function filteredOrders(Request $request): Builder
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_keys($this->statuses()))],
            'search' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $query = Order::query();

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
                    ->orWhere('lulu_job_id', 'like', "%{$search}%")
                    ->orWhere('shipping_city', 'like', "%{$search}%")
                    ->orWhere('shipping_state', 'like', "%{$search}%")
                    ->orWhere('shipping_zip', 'like', "%{$search}%");
            });
        }

        // Date filter
        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
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
        $queued = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->canRetry()) {
                return false;
            }
            $order->update([
                'fulfillment_status' => 'received',
                'error_message' => null,
                'retry_count' => 0,
            ]);

            $order->logEvent('admin_manual_retry', 'admin', [], 'Admin triggered manual retry.');

            ProcessLuluPrintJob::dispatch($order);

            return true;
        });

        if (! $queued) {
            return back()->with('error', 'Retry blocked: the order is not failed, already has a Lulu job, or needs submission reconciliation.');
        }

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
        $query = $this->filteredOrders($request);

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'GHL Order ID',
                'Buyer Name',
                'Email',
                'Status',
                'Lulu Status',
                'Lulu Job ID',
                'Shipping City',
                'Shipping State',
                'Shipping Zip',
                'Amount',
                'Error',
                'Created At',
            ], ',', '"', '');

            foreach ($query->lazyById(100) as $o) {
                $row = [
                    $o->id,
                    $o->ghl_order_id,
                    $o->buyer_name,
                    $o->buyer_email,
                    $o->fulfillment_status,
                    $o->lulu_status ?? '',
                    $o->lulu_job_id ?? '',
                    $o->shipping_city,
                    $o->shipping_state,
                    $o->shipping_zip,
                    $o->amount_charged,
                    $o->error_message,
                    $o->created_at->format('Y-m-d H:i:s'),
                ];
                // Spreadsheet applications execute formulas even when CSV values are quoted.
                $row = array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/', $value) ? "'".$value : $value, $row);
                fputcsv($handle, $row, ',', '"', '');
            }

            fclose($handle);
        }, 'orders_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function statuses(): array
    {
        return [
            'received' => 'Received',
            'processing' => 'Processing',
            'submitted_to_lulu' => 'Submitted to Lulu',
            'print_job_created' => 'Print Job Created',
            'in_production' => 'In Production',
            'shipped' => 'Shipped',
            'cancelled' => 'Cancelled',
            'failed' => 'Failed',
        ];
    }
}

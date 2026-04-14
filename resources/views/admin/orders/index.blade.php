@extends('layouts.admin')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem;">
    <div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Order Management</h1>
        <p style="color: var(--text-muted); font-weight: 500;">Archive and real-time tracking of all processed transactions.</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('admin.orders.export', request()->all()) }}" class="btn btn-outline">
            <i data-lucide="download" style="width: 16px; height: 16px;"></i> Export CSV
        </a>
    </div>
</div>

<!-- Filter Section -->
<div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
    <form method="GET" action="{{ route('admin.orders.index') }}" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div>
            <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Search Records</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, email, or GHL ID..." 
                   style="width: 100%; padding: 0.75rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.875rem; background: #fafafa;">
        </div>
        <div>
            <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Status</label>
            <select name="status" style="width: 100%; padding: 0.75rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.875rem; background: #fafafa;">
                <option value="">All Statuses</option>
                <option value="received" {{ request('status') == 'received' ? 'selected' : '' }}>Received</option>
                <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                <option value="print_job_created" {{ request('status') == 'print_job_created' ? 'selected' : '' }}>Submitted</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
            </select>
        </div>
        <div>
            <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">From</label>
            <input type="date" name="from" value="{{ request('from') }}" style="width: 100%; padding: 0.75rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.875rem;">
        </div>
        <div>
            <label style="display: block; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">To</label>
            <input type="date" name="to" value="{{ request('to') }}" style="width: 100%; padding: 0.75rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.875rem;">
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.75rem;">Apply</button>
    </form>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div style="padding: 0 1rem;">
        <table>
            <thead>
                <tr>
                    <th style="padding-left: 1.5rem;">GHL Order ID</th>
                    <th>Customer Details</th>
                    <th>Fulfillment Status</th>
                    <th>Lulu Job</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th style="text-align: right; padding-right: 1.5rem;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td style="padding-left: 1.5rem; font-family: monospace; font-weight: 700; color: var(--primary);">#{{ $order->ghl_order_id }}</td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-main);">{{ $order->buyer_name }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $order->buyer_email }}</div>
                    </td>
                    <td>
                        <span class="badge 
                            @if($order->fulfillment_status == 'print_job_created') badge-success 
                            @elseif($order->fulfillment_status == 'failed') badge-danger 
                            @elseif($order->fulfillment_status == 'received' || $order->fulfillment_status == 'processing') badge-warning
                            @else badge-info @endif">
                            {{ str_replace('_', ' ', $order->fulfillment_status) }}
                        </span>
                    </td>
                    <td>
                        @if($order->lulu_job_id)
                            <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;">{{ $order->lulu_job_id }}</code>
                        @else
                            <span style="color: var(--text-muted); font-size: 0.75rem;">Pending</span>
                        @endif
                    </td>
                    <td style="font-weight: 700; color: var(--text-main);">${{ number_format($order->amount_charged, 2) }}</td>
                    <td style="color: var(--text-muted); font-size: 0.8125rem;">{{ $order->created_at->format('M d, Y') }}</td>
                    <td style="text-align: right; padding-right: 1.5rem;">
                        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">View Details</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 5rem; color: var(--text-muted);">
                        <div style="margin-bottom: 1rem; color: var(--border);">
                            <i data-lucide="search-x" style="width: 48px; height: 48px;"></i>
                        </div>
                        <p style="font-weight: 600;">No orders found</p>
                        <p style="font-size: 0.8125rem; margin-top: 0.25rem;">Try adjusting your filters or search term.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($orders->hasPages())
    <div style="padding: 1.5rem; border-top: 1px solid var(--border); background: #fafafa;">
        {{ $orders->links() }}
    </div>
    @endif
</div>

<style>
    /* Pagination Overrides */
    .pagination { display: flex; list-style: none; gap: 0.375rem; justify-content: flex-end; align-items: center; }
    .page-item { border-radius: 0.5rem; overflow: hidden; border: 1.5px solid var(--border); background: #fff; }
    .page-link { display: block; padding: 0.5rem 0.875rem; text-decoration: none; color: var(--text-main); font-size: 0.8125rem; font-weight: 600; }
    .page-item.active { background: var(--primary); border-color: var(--primary); }
    .page-item.active .page-link { color: white; }
    .page-item:hover:not(.active):not(.disabled) { border-color: var(--primary-light); background: #f5f3ff; }
</style>
@endsection

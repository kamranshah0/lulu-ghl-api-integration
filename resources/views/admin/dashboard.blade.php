@extends('layouts.admin')

@section('content')
<div style="margin-bottom: 2.5rem;">
    <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--text-main);">Dashboard Overview</h1>
    <p style="color: var(--text-muted); font-weight: 500;">Real-time monitoring of your GHL to Lulu pipeline.</p>
</div>

<!-- Stats Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
    <!-- Total Orders -->
    <div class="card" style="padding: 1.75rem; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Total Orders</p>
            <h2 style="font-size: 2.25rem; font-family: 'Outfit', sans-serif;">{{ number_format($stats['total']) }}</h2>
        </div>
        <div style="background: rgba(99, 102, 241, 0.1); color: var(--primary); padding: 0.75rem; border-radius: 12px;">
            <i data-lucide="shopping-cart" style="width: 24px; height: 24px;"></i>
        </div>
    </div>

    <!-- Pending Sync -->
    <div class="card" style="padding: 1.75rem; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Pending Sync</p>
            <h2 style="font-size: 2.25rem; font-family: 'Outfit', sans-serif; color: var(--warning);">{{ number_format($stats['pending']) }}</h2>
        </div>
        <div style="background: rgba(245, 158, 11, 0.1); color: var(--warning); padding: 0.75rem; border-radius: 12px;">
            <i data-lucide="clock" style="width: 24px; height: 24px;"></i>
        </div>
    </div>

    <!-- Submitted -->
    <div class="card" style="padding: 1.75rem; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Submitted</p>
            <h2 style="font-size: 2.25rem; font-family: 'Outfit', sans-serif; color: var(--success);">{{ number_format($stats['submitted']) }}</h2>
        </div>
        <div style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 0.75rem; border-radius: 12px;">
            <i data-lucide="check-circle" style="width: 24px; height: 24px;"></i>
        </div>
    </div>

    <!-- Failed Jobs -->
    <div class="card" style="padding: 1.75rem; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <p style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.75rem;">Failed Jobs</p>
            <h2 style="font-size: 2.25rem; font-family: 'Outfit', sans-serif; color: var(--danger);">{{ number_format($stats['failed']) }}</h2>
        </div>
        <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 0.75rem; border-radius: 12px;">
            <i data-lucide="alert-triangle" style="width: 24px; height: 24px;"></i>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
    <!-- Recent Orders -->
    <div class="card" style="overflow: hidden; padding: 0;">
        <div style="padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border);">
            <h3 style="font-size: 1.25rem;">Recent Activity</h3>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.8125rem;">View All</a>
        </div>
        <div style="padding: 0 1rem;">
            <table>
                <thead>
                    <tr>
                        <th>GHL Order</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentOrders as $order)
                    <tr>
                        <td style="font-weight: 700; font-family: monospace; color: var(--primary);">#{{ $order->ghl_order_id }}</td>
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
                        <td style="color: var(--text-muted); font-size: 0.8125rem;">{{ $order->created_at->diffForHumans() }}</td>
                        <td style="text-align: right;">
                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">Details</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">No orders found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alert Sidebar -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>⚠️</span> Critical Items
        </h3>
        @forelse($failedOrders as $order)
        <div style="padding: 1.25rem; border: 1px solid #fee2e2; border-radius: 1rem; background: #fffafb; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="font-weight: 800; font-size: 0.8125rem; color: var(--text-main);">Order #{{ $order->id }}</span>
                <span class="badge badge-danger" style="font-size: 0.625rem; padding: 0.2rem 0.5rem;">FAILED</span>
            </div>
            <p style="font-size: 0.75rem; color: #991b1b; margin-bottom: 1rem; line-height: 1.4; font-weight: 500;">
                {{ Str::limit($order->error_message ?? 'Unknown fulfillment error.', 80) }}
            </p>
            <form action="{{ route('admin.orders.retry', $order) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 0.75rem; padding: 0.625rem;">Retry Fulfillment</button>
            </form>
        </div>
        @empty
        <div style="text-align: center; padding: 3rem 1rem;">
            <div style="font-size: 2.5rem; margin-bottom: 1rem;">🚀</div>
            <p style="font-size: 0.875rem; font-weight: 600; color: var(--success);">All systems clear.</p>
            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">No failed orders in queue.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection

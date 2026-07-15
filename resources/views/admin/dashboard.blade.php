@extends('layouts.admin')

@section('content')
<div style="display: flex; justify-content: space-between; align-items: flex-end; gap: 1.5rem; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--text-main);">Dashboard Overview</h1>
        <p style="color: var(--text-muted); font-weight: 500;">Real-time monitoring of your GHL to Lulu pipeline.</p>
    </div>
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; justify-content: flex-end;">
        <a href="{{ route('admin.orders.index') }}" class="btn btn-outline" style="padding: 0.65rem 1rem;">
            <i data-lucide="list-filter" style="width: 16px; height: 16px;"></i> All Orders
        </a>
        <a href="{{ route('admin.orders.failed') }}" class="btn btn-outline" style="padding: 0.65rem 1rem;">
            <i data-lucide="alert-circle" style="width: 16px; height: 16px;"></i> Failed Queue
        </a>
    </div>
</div>

<!-- Stats Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <a href="{{ route('admin.orders.index') }}" class="card stat-card" style="padding: 1.35rem; display: flex; justify-content: space-between; align-items: flex-start; text-decoration: none;">
        <div>
            <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.65rem;">Total Orders</p>
            <h2 style="font-size: 2rem; font-family: 'Outfit', sans-serif;">{{ number_format($stats['total']) }}</h2>
            <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.4rem;">{{ number_format($stats['today']) }} today</p>
        </div>
        <div style="background: rgba(99, 102, 241, 0.1); color: var(--primary); padding: 0.7rem; border-radius: 12px;">
            <i data-lucide="shopping-cart" style="width: 22px; height: 22px;"></i>
        </div>
    </a>

    <a href="{{ route('admin.orders.index', ['status' => 'processing']) }}" class="card stat-card" style="padding: 1.35rem; display: flex; justify-content: space-between; align-items: flex-start; text-decoration: none;">
        <div>
            <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.65rem;">Needs Queue</p>
            <h2 style="font-size: 2rem; font-family: 'Outfit', sans-serif; color: var(--warning);">{{ number_format($stats['pending']) }}</h2>
            <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.4rem;">{{ number_format($stats['processing']) }} processing</p>
        </div>
        <div style="background: rgba(245, 158, 11, 0.1); color: var(--warning); padding: 0.7rem; border-radius: 12px;">
            <i data-lucide="clock" style="width: 22px; height: 22px;"></i>
        </div>
    </a>

    <a href="{{ route('admin.orders.index', ['status' => 'print_job_created']) }}" class="card stat-card" style="padding: 1.35rem; display: flex; justify-content: space-between; align-items: flex-start; text-decoration: none;">
        <div>
            <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.65rem;">At Lulu</p>
            <h2 style="font-size: 2rem; font-family: 'Outfit', sans-serif; color: var(--success);">{{ number_format($stats['submitted']) }}</h2>
            <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.4rem;">{{ number_format($stats['in_production']) }} in production</p>
        </div>
        <div style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 0.7rem; border-radius: 12px;">
            <i data-lucide="printer" style="width: 22px; height: 22px;"></i>
        </div>
    </a>

    <a href="{{ route('admin.orders.index', ['status' => 'shipped']) }}" class="card stat-card" style="padding: 1.35rem; display: flex; justify-content: space-between; align-items: flex-start; text-decoration: none;">
        <div>
            <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.65rem;">Shipped</p>
            <h2 style="font-size: 2rem; font-family: 'Outfit', sans-serif; color: var(--success);">{{ number_format($stats['shipped']) }}</h2>
            <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.4rem;">Completed jobs</p>
        </div>
        <div style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 0.7rem; border-radius: 12px;">
            <i data-lucide="truck" style="width: 22px; height: 22px;"></i>
        </div>
    </a>

    <a href="{{ route('admin.orders.failed') }}" class="card stat-card" style="padding: 1.35rem; display: flex; justify-content: space-between; align-items: flex-start; text-decoration: none; border-color: {{ $stats['failed'] ? '#fecaca' : 'var(--border)' }};">
        <div>
            <p style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.65rem;">Failed Jobs</p>
            <h2 style="font-size: 2rem; font-family: 'Outfit', sans-serif; color: var(--danger);">{{ number_format($stats['failed']) }}</h2>
            <p style="color: var(--text-muted); font-size: 0.75rem; margin-top: 0.4rem;">Needs review</p>
        </div>
        <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 0.7rem; border-radius: 12px;">
            <i data-lucide="alert-triangle" style="width: 22px; height: 22px;"></i>
        </div>
    </a>
</div>

<div style="display: grid; grid-template-columns: 2fr minmax(320px, 0.85fr); gap: 1.5rem; align-items: start;">
    <!-- Recent Orders -->
    <div class="card" style="overflow: hidden; padding: 0;">
        <div style="padding: 1.35rem 1.5rem; display: flex; justify-content: space-between; align-items: center; gap: 1rem; border-bottom: 1px solid var(--border);">
            <div>
                <h3 style="font-size: 1.2rem;">Recent Activity</h3>
                <p style="color: var(--text-muted); font-size: 0.8125rem; margin-top: 0.25rem;">Latest GHL orders and Lulu fulfillment state.</p>
            </div>
            <a href="{{ route('admin.orders.index') }}" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.8125rem;">View All</a>
        </div>
        <div style="padding: 0 0.75rem; overflow-x: auto;">
            <table style="min-width: 780px;">
                <thead>
                    <tr>
                        <th>GHL Order</th>
                        <th>Customer</th>
                        <th>Ship To</th>
                        <th>Status</th>
                        <th>Lulu Job</th>
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
                            <div style="font-size: 0.75rem; color: var(--text-muted); max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $order->buyer_email }}</div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--text-main);">{{ $order->shipping_city ?: 'Missing city' }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $order->shipping_state ?: 'Missing state' }} {{ $order->shipping_zip }}</div>
                        </td>
                        <td>
                            <span class="badge 
                                @if($order->fulfillment_status == 'print_job_created' || $order->fulfillment_status == 'shipped' || $order->fulfillment_status == 'in_production') badge-success 
                                @elseif($order->fulfillment_status == 'failed') badge-danger 
                                @elseif($order->fulfillment_status == 'received' || $order->fulfillment_status == 'processing') badge-warning
                                @else badge-info @endif">
                                {{ str_replace('_', ' ', $order->fulfillment_status) }}
                            </span>
                            @if($order->error_message)
                                <div style="font-size: 0.7rem; color: var(--danger); margin-top: 0.35rem; max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $order->error_message }}</div>
                            @elseif($order->lulu_status)
                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.35rem;">Lulu: {{ $order->lulu_status }}</div>
                            @endif
                        </td>
                        <td>
                            @if($order->lulu_job_id)
                                <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;">{{ $order->lulu_job_id }}</code>
                            @else
                                <span style="color: var(--text-muted); font-size: 0.75rem;">Pending</span>
                            @endif
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8125rem;">{{ $order->created_at->diffForHumans() }}</td>
                        <td style="text-align: right;">
                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">Details</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                            <i data-lucide="inbox" style="width: 42px; height: 42px; margin-bottom: 0.75rem;"></i>
                            <p style="font-weight: 700;">No orders found.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Alert Sidebar -->
    <div class="card" style="padding: 1.35rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.125rem; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="alert-circle" style="width: 20px; height: 20px; color: var(--danger);"></i> Critical Items
            </h3>
            <span class="badge badge-danger" style="font-size: 0.65rem; padding: 0.25rem 0.55rem;">{{ number_format($stats['failed']) }}</span>
        </div>

        @forelse($failedOrders as $order)
        <div style="padding: 1rem; border: 1px solid #fee2e2; border-radius: 1rem; background: #fffafb; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.5rem;">
                <span style="font-weight: 800; font-size: 0.8125rem; color: var(--text-main);">#{{ $order->ghl_order_id }}</span>
                <span style="font-size: 0.7rem; color: var(--text-muted);">{{ $order->created_at->diffForHumans() }}</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 0.75rem;">
                {{ $order->shipping_city ?: 'Missing city' }}, {{ $order->shipping_state ?: 'Missing state' }} {{ $order->shipping_zip }}
            </div>
            <p style="font-size: 0.75rem; color: #991b1b; margin-bottom: 1rem; line-height: 1.45; font-weight: 600;">
                {{ Str::limit($order->error_message ?? 'Unknown fulfillment error.', 110) }}
            </p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
                <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.625rem;">Inspect</a>
                <form action="{{ route('admin.orders.retry', $order) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 0.75rem; padding: 0.625rem;">Retry</button>
                </form>
            </div>
        </div>
        @empty
        <div style="text-align: center; padding: 3rem 1rem;">
            <div style="width: 3rem; height: 3rem; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.1); color: var(--success); border-radius: 999px;">
                <i data-lucide="check-circle" style="width: 26px; height: 26px;"></i>
            </div>
            <p style="font-size: 0.875rem; font-weight: 700; color: var(--success);">All systems clear.</p>
            <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">No failed orders in queue.</p>
        </div>
        @endforelse
    </div>
</div>

<style>
    .stat-card:hover {
        transform: translateY(-1px);
    }

    @media (max-width: 1180px) {
        [style*="grid-template-columns: 2fr minmax(320px, 0.85fr)"] {
            grid-template-columns: 1fr !important;
        }
    }

    @media (max-width: 720px) {
        [style*="justify-content: space-between; align-items: flex-end"] {
            align-items: flex-start !important;
            flex-direction: column;
        }
    }
</style>
@endsection

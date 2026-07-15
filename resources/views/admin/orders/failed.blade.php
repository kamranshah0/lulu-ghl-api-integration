@extends('layouts.admin')

@section('content')
<div style="margin-bottom: 2.5rem;">
    <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Critical Alert Queue</h1>
    <p style="color: var(--text-muted); font-weight: 500;">Orders that encountered issues during the fulfillment pipeline and require manual intervention.</p>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    @if($orders->isEmpty())
    <div style="text-align: center; padding: 6rem 2rem;">
        <div style="margin-bottom: 1.5rem; color: var(--border);">
            <i data-lucide="party-popper" style="width: 64px; height: 64px;"></i>
        </div>
        <h2 style="font-size: 1.5rem; margin-bottom: 0.5rem; color: var(--text-main);">Queue is Empty</h2>
        <p style="color: var(--text-muted); font-weight: 500; max-width: 400px; margin: 0 auto;">Everything is running smoothly. Your failed order queue is currently clear.</p>
        <div style="margin-top: 2rem;">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-primary" style="padding: 0.75rem 2rem;">Browse All Orders</a>
        </div>
    </div>
    @else
    <div style="padding: 0 1rem;">
        <table>
            <thead>
                <tr>
                    <th style="padding-left: 1.5rem;">Order Reference</th>
                    <th>Customer</th>
                    <th>Shipping</th>
                    <th>Failure Reason</th>
                    <th style="text-align: center;">Retries</th>
                    <th>Timestamp</th>
                    <th style="text-align: right; padding-right: 1.5rem;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($orders as $order)
                <tr>
                    <td style="padding-left: 1.5rem; font-family: monospace; font-weight: 700; color: var(--danger);">#{{ $order->ghl_order_id }}</td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-main);">{{ $order->buyer_name }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $order->buyer_email }}</div>
                    </td>
                    <td>
                        <div style="font-weight: 600; color: var(--text-main);">{{ $order->shipping_city ?: 'Missing city' }}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            {{ $order->shipping_state ?: 'Missing state' }} {{ $order->shipping_zip }}
                            {{ strtoupper($order->shipping_country ?? '') }}
                        </div>
                    </td>
                    <td style="max-width: 320px;">
                        <div style="color: #991b1b; font-size: 0.8125rem; font-weight: 600; background: #fff1f2; padding: 0.5rem 0.75rem; border-radius: 0.5rem; border: 1px solid #fee2e2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            {{ $order->error_message ?? 'Unknown fulfillment error' }}
                        </div>
                    </td>
                    <td style="text-align: center;">
                        <span style="font-weight: 800; font-size: 1rem; color: var(--text-main);">{{ $order->retry_count }}</span>
                        <span style="font-size: 0.7rem; color: var(--text-muted); display: block; font-weight: 600;">ATTEMPTS</span>
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.8125rem;">{{ $order->created_at->diffForHumans() }}</td>
                    <td style="text-align: right; padding-right: 1.5rem;">
                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                            <form action="{{ route('admin.orders.retry', $order) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-size: 0.75rem;">Retry Job</button>
                            </form>
                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.75rem;">Investigate</a>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    @if($orders->hasPages())
    <div style="padding: 1.5rem; border-top: 1px solid var(--border); background: #fafafa;">
        {{ $orders->links() }}
    </div>
    @endif
    @endif
</div>

<style>
    /* Pagination Overrides */
    .pagination { display: flex; list-style: none; gap: 0.375rem; justify-content: flex-end; align-items: center; }
    .page-item { border-radius: 0.5rem; overflow: hidden; border: 1.5px solid var(--border); background: #fff; }
    .page-link { display: block; padding: 0.5rem 0.875rem; text-decoration: none; color: var(--text-main); font-size: 0.8125rem; font-weight: 600; }
    .page-item.active { background: var(--primary); border-color: var(--primary); }
    .page-item.active .page-link { color: white; }
</style>
@endsection

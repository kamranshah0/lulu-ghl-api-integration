@extends('layouts.admin')

@section('content')
<div style="margin-bottom: 2.5rem;">
    <h1 style="font-size: 2rem; margin-bottom: 0.5rem; color: var(--text-main);">Account Settings</h1>
    <p style="color: var(--text-muted); font-weight: 500;">Manage your profile information and account security.</p>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; align-items: start;">
    
    <!-- Profile Information -->
    <div class="card" style="padding: 2.5rem;">
        <div style="display: flex; align-items: center; gap: 0.875rem; margin-bottom: 2rem;">
            <div style="background: rgba(99, 102, 241, 0.1); color: var(--primary); padding: 0.625rem; border-radius: 10px;">
                <i data-lucide="user" style="width: 20px; height: 20px;"></i>
            </div>
            <h3 style="font-size: 1.25rem;">Profile Information</h3>
        </div>

        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Full Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" 
                       style="width: 100%; padding: 0.875rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.9375rem; background: #fafafa;">
                @error('name') <p style="color: var(--danger); font-size: 0.75rem; margin-top: 0.5rem; font-weight: 600;">{{ $message }}</p> @enderror
            </div>

            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Email Address</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" 
                       style="width: 100%; padding: 0.875rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.9375rem; background: #fafafa;">
                @error('email') <p style="color: var(--danger); font-size: 0.75rem; margin-top: 0.5rem; font-weight: 600;">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem;">Save Profile Changes</button>
        </form>
    </div>

    <!-- Security / Password -->
    <div class="card" style="padding: 2.5rem;">
        <div style="display: flex; align-items: center; gap: 0.875rem; margin-bottom: 2rem;">
            <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 0.625rem; border-radius: 10px;">
                <i data-lucide="lock" style="width: 20px; height: 20px;"></i>
            </div>
            <h3 style="font-size: 1.25rem;">Security & Password</h3>
        </div>

        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Current Password</label>
                <input type="password" name="current_password" placeholder="••••••••" 
                       style="width: 100%; padding: 0.875rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.9375rem; background: #fafafa;">
                @error('current_password') <p style="color: var(--danger); font-size: 0.75rem; margin-top: 0.5rem; font-weight: 600;">{{ $message }}</p> @enderror
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">New Password</label>
                <input type="password" name="new_password" placeholder="••••••••" 
                       style="width: 100%; padding: 0.875rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.9375rem; background: #fafafa;">
                @error('new_password') <p style="color: var(--danger); font-size: 0.75rem; margin-top: 0.5rem; font-weight: 600;">{{ $message }}</p> @enderror
            </div>

            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Confirm New Password</label>
                <input type="password" name="new_password_confirmation" placeholder="••••••••" 
                       style="width: 100%; padding: 0.875rem; border: 1.5px solid var(--border); border-radius: 0.75rem; font-size: 0.9375rem; background: #fafafa;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; background: var(--sidebar-bg);">Update Password</button>
        </form>
    </div>

</div>

<div class="card" style="margin-top: 2.5rem; background: linear-gradient(to right, #f8fafc, #ffffff); border-left: 4.5px solid var(--primary);">
    <div style="display: flex; align-items: center; gap: 1.25rem;">
        <div style="font-size: 1.5rem;">💡</div>
        <div>
            <p style="font-weight: 700; color: var(--text-main); font-size: 0.9375rem;">Security Tip</p>
            <p style="color: var(--text-muted); font-size: 0.8125rem; margin-top: 0.125rem;">Ensure your password is at least 8 characters long and contains a mix of letters, numbers, and symbols.</p>
        </div>
    </div>
</div>
@endsection

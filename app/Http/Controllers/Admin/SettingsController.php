<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /**
     * Show the settings page.
     */
    public function index()
    {
        return view('admin.settings', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Update the admin profile or password.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        // If password fields are present, we handle password update
        if ($request->filled('current_password') || $request->filled('new_password')) {
            $request->validate([
                'current_password' => ['required', 'current_password'],
                'new_password' => ['required', 'confirmed', Password::min(8)],
            ]);

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return back()->with('success', 'Password updated successfully.');
        }

        // Handle Profile Update
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($request->only('name', 'email'));

        return back()->with('success', 'Profile updated successfully.');
    }
}

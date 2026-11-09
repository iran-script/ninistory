<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function create() { return view('admin.login'); }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, false) || ! $request->user()?->is_admin) {
            Auth::logout();
            return back()->withErrors(['email' => 'اطلاعات ورود مدیر معتبر نیست.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}

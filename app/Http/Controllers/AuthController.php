<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // LOGIN - show login page
    public function showLogin()
    {
        return view('auth.login');
    }

    // LOGIN - check the email and password
    public function login(Request $request)
    {
        $credential = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credential, $request->boolean('remember'))) {

            $request->session()->regenerate(); // new session id after login

            return redirect()->intended(route('overview'));
        }

        return back()
            ->withErrors(['email' => 'That email and password don’t match an account. Check them and try again.'])
            ->onlyInput('email');

    }

    // REGISTER - show register page
    public function showRegister()
    {
        return view('auth.register');
    }

    // REGISTER - create the account, then logs the user in
    public function register(Request $request)
    {

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('overview')->with('success', 'Your account is ready.');
    }

    // LOGOUT - ends the session
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate(); // throws away the session data
        $request->session()->regenerateToken(); // regenerate CSRF tokens

        return redirect()->route('login')->with('success', 'You’re signed out.');

    }
}

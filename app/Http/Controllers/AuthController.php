<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function index()
    {
        return view('pages.auth.signin');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required', // ini bisa email atau username
            'password' => 'required|min:8',
        ]);

        $loginType = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $loginType => $request->username,
            'password' => $request->password,
        ];

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            return redirect()->intended('/dashboard');
        }
        return back()->withErrors([
            'username' => 'Email atau Username atau Password salah.'
        ])->withInput();
    }

    public function logout()
    {
        Auth::logout();
        return redirect('/login');
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function showLoginForm ()
    {
        return view('auth.login');
    }


    public function login(Request $request)
    {
        $va = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $data = $request->only([
            'email',
            'password',
        ]);
        if (Auth::attempt($data)) {
            return redirect('dashboard');
        } else {
            //return redirect()->back()->with('email', 'Email hoặc Password không chính xác');
            return back()->withErrors([
                'email' => 'The email or password is incorrect, please try again'
            ]);
        }
    }


}

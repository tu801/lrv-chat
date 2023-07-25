<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    function dashboard()
    {
        if(Auth::check())
        {
            return view('admin/dashboard');
        }

        return redirect('login')->with('success', 'you are not allowed to access');
    }
}

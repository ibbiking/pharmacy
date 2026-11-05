<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;

/**
 * Legacy "register" (created accounts without email verification and without a duplicate check).
 * Retired in Roadmap 5.0: everyone signs up through the verified sign-up flow.
 */
class RegisterController extends Controller
{
    public function index()
    {
        return redirect()->route('signup');
    }

    public function store()
    {
        return redirect()->route('signup')->with('message', 'Please sign up here: we verify your email first.');
    }
}

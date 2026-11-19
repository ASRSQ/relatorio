<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Professor
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect()->route('admin.login');
        }
        abort_unless(Auth::user()->ativo && (Auth::user()->is_admin || Auth::user()->is_professor), 403);

        return $next($request);
    }
}

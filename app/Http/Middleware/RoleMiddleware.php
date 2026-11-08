<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
  /**
   * Batasi akses route berdasarkan role efektif (session 'wewenang').
   *
   * Contoh: ->middleware('role:admin') atau ->middleware('role:op1,op2')
   */
  public function handle(Request $request, Closure $next, string ...$roles): Response
  {
    $user = $request->user();

    if (!$user) {
      return redirect()->route('login');
    }

    // Session tanpa wewenang (mis. sisa login lama) dianggap tidak sah.
    if (!$user->role()) {
      auth()->logout();
      $request->session()->invalidate();
      $request->session()->regenerateToken();

      return redirect()->route('login');
    }

    if (!$user->hasAnyRole($roles)) {
      abort(401, 'Anda tidak memiliki hak akses ke halaman ini.');
    }

    return $next($request);
  }
}

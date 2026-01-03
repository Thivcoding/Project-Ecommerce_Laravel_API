<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class checkRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next,$role): Response
    {
        if(!auth()->check()){
            return response()->json([
                'message'=>'Not Unauthorized Bro'
            ],403);
        }

        if (auth()->user()->role !== $role) {
            return response()->json(['message'=>'Unauthorized'],403);
        }
        
        return $next($request);
    }
}

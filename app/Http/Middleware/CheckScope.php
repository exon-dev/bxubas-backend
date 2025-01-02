<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckScope
{
    public function handle(Request $request, Closure $next, $scope)
    {
        // You can access the $scope here
        if (!$request->user() || !$request->user()->tokenCan($scope)) {
            return response(['message' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}

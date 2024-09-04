<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiAccess
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return \Illuminate\Http\JsonResponse|mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ( $this->checkToken( $request ) ) {
            return $next( $request );
        }

        return response()->json( [ 'error' => 'Unauthorized' ], 403 );
    }
    public function checkToken( $request ): bool
    {
        $token = $request->bearerToken();
        return $token == config('token-api.token');
    }
}

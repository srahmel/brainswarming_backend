<?php

namespace App\Http\Middleware;

use Closure;
use Fruitcake\Cors\CorsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * The CORS service instance.
     *
     * @var \Fruitcake\Cors\CorsService
     */
    protected $cors;

    /**
     * Create a new middleware instance.
     *
     * @param  \Fruitcake\Cors\CorsService  $cors
     * @return void
     */
    public function __construct(CorsService $cors)
    {
        $this->cors = $cors;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request is a CORS request
        if (! $this->cors->isCorsRequest($request)) {
            return $next($request);
        }

        // Check if the request is a preflight request
        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);

            return $response;
        }

        // Handle the actual request
        $response = $next($request);

        // Add the CORS headers to the response
        return $this->cors->addActualRequestHeaders($response, $request);
    }
}

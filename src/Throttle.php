<?php
declare(strict_types=1);
/**
 * +----------------------------------------------------------------------+
 * |                   At all timesI love the moment                      |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2019 www.Woisk.com All rights reserved.                |
 * +----------------------------------------------------------------------+
 * | This source file is subject to version 2.0 of the Apache license,    |
 * | that is bundled with this package in the file LICENSE, and is        |
 * | available through the world-wide-web at the following url:           |
 * | www.apache.org/licenses/LICENSE-2.0.html                             |
 * +----------------------------------------------------------------------+
 * |  Author:  Maple Grove  <bolelin@126.com>   QQ:364956690   286013629  |
 * +----------------------------------------------------------------------+
 */


namespace Woisk\Throttle;


use Closure;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class Throttle
{
    /**
     * Notes:limiter
     * @var RateLimiter
     * ------------------------------------------------------
     * Author: Maple Grove  <bolelin@126.com> 2019/4/1 19:34
     */
    protected $limiter;

    /**
     * Throttle constructor.
     * @param RateLimiter $limiter
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Notes: handle
     * @param         $request
     * @param Closure $next
     * @param int     $maxAttempts
     * @param int     $decaySeconds
     * @return Response
     * ------------------------------------------------------
     * Author: Maple Grove  <bolelin@126.com> 2019/4/1 19:51
     */
    public function handle($request, Closure $next, int $maxAttempts = 60, int $decaySeconds = 60)
    {
        $key = $this->resolveRequestSignature($request);

        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return $this->buildResponse($key, $maxAttempts);
        }

        $this->limiter->hit($key, $decaySeconds);

        $response = $next($request);

        return $this->addHeaders(
            $response, $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts)
        );
    }

    /**
     * Notes: resolveRequestSignature
     * @param $request
     * @return mixed
     * ------------------------------------------------------
     * Author: Maple Grove  <bolelin@126.com> 2019/4/1 19:52
     */
    protected function resolveRequestSignature($request)
    {
        return $request->fingerprint();
    }

    /**
     * Notes: buildResponse
     * @param $key
     * @param $maxAttempts
     * @return Response
     * ------------------------------------------------------
     * Author: Maple Grove  <bolelin@126.com> 2019/4/1 19:52
     */
    protected function buildResponse($key, $maxAttempts)
    {
        $response = res(429, 'Excessive frequency of requests');

        $retryAfter = $this->limiter->availableIn($key);

        return $this->addHeaders(
            $response, $maxAttempts,
            $this->calculateRemainingAttempts($key, $maxAttempts, $retryAfter),
            $retryAfter
        );
    }

    /**
     * Notes: addHeaders
     * @param Response $response
     * @param          $maxAttempts
     * @param          $remainingAttempts
     * @param null     $retryAfter
     * @return Response
     * ------------------------------------------------------
     * Author: Maple Grove  <bolelin@126.com> 2019/4/1 19:53
     */
    protected function addHeaders(Response $response, $maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $headers = [
            'X-RateLimit-Limit'     => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ];

        if ( !is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            $headers['Content-Type'] = 'application/json';
        }

        $response->headers->add($headers);

        return $response;
    }

    /**
     * Notes: calculateRemainingAttempts
     * @param      $key
     * @param      $maxAttempts
     * @param null $retryAfter
     * @return int
     * ------------------------------------------------------
     * Author: Maple Grove  <bolelin@126.com> 2019/4/1 19:53
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null)
    {
        if ( !is_null($retryAfter)) {
            return 0;
        }

        return $this->limiter->retriesLeft($key, $maxAttempts);
    }
}
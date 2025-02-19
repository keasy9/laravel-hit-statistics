<?php

namespace Keasy9\HitStatistics\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Jaybizzle\LaravelCrawlerDetect\Facades\LaravelCrawlerDetect as CrawlerDetect;
use Keasy9\HitStatistics\Repositories\HitRepository;
use Symfony\Component\HttpFoundation\Response;

class CollectHit
{
    public function handle(
        Request $request,
        Closure $next,
    ): Response
    {
        $referer = $request->header('referer');

        if (
            $referer
            && !CrawlerDetect::isCrawler()
            && !Str::contains($referer, config('app.url'))
            && (config('hits.authorized') || !Auth::check())
        ) {
            $exclude = config('hits.exclude');
            if (
                (isset($exclude['referer']) && preg_match($exclude['referer'], $referer))
                || (isset($exclude['useragent']) && preg_match($exclude['useragent'], $request->header('User-Agent')))
            ) {
                return $next($request);
            }

            HitRepository::addFromRequest($request);
        }

        return $next($request);
    }
}

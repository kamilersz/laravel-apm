<?php namespace Done\LaravelAPM\Watchers;

use Done\LaravelAPM\LogWriter;

class RequestWatcher
{
    public static function record($event)
    {
        $duration = microtime(true) - LARAVEL_START;

        $route = \Request::route();
        if ($route !== null) {
            $name = $route->uri();
        } else {
            $name = request()->path();
        }

        LogWriter::log(
            round(LARAVEL_START),
            $duration,
            QueryWatcher::getMilliseconds() / 1000,
            'request',
            $name,
            \Auth::check() ? \Auth::user()->email : request()->ip(),
            QueryWatcher::getQueries()
        );
    }
}

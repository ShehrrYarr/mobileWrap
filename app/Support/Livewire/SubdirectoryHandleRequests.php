<?php

namespace App\Support\Livewire;

use Livewire\Mechanisms\HandleRequests\HandleRequests;

/**
 * Sub-folder deployments (APP_URL=http://23.230.253.206/mobileWrap).
 *
 * Livewire builds its endpoints root-relative ("/livewire/update",
 * "/livewire/livewire.min.js"), which would hit the domain root instead of this
 * app. These helpers put the base path back. Served at a domain root (local dev),
 * the base path is empty and nothing changes.
 */
class SubdirectoryHandleRequests extends HandleRequests
{
    /** "/mobileWrap" — or "" when the app lives at the domain root. */
    public static function basePath(): string
    {
        return rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
    }

    public static function prefix(string $uri): string
    {
        $base = static::basePath();
        $uri = '/'.ltrim($uri, '/');

        return $base === '' || str_starts_with($uri, $base.'/') ? $uri : $base.$uri;
    }

    public function getUpdateUri()
    {
        return static::prefix(parent::getUpdateUri());
    }
}

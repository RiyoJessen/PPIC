<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AccessControl;

class AccessControlMiddleware
{
    public static function handle(string $uri): bool
    {
        return AccessControl::checkAccess($uri);
    }
}

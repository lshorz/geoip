<?php
declare(strict_types=1);

namespace Lshorz\GeoIp\Facades;
use Illuminate\Support\Facades\Facade;

/**
 * @method static $this getLocation(?string $ip = null)
 */

class GeoIp extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'GeoIp';
    }
}

<?php
declare(strict_types=1);

use Lshorz\GeoIp\GeoIp;

/**
 * 根据IP获取地理位置
 *
 * @param string $ip
 * @return array
 */
function get_ip_location(?string $ip = null): array
{
    return (new GeoIp())->getLocation($ip);
}
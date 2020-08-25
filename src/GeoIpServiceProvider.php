<?php
declare(strict_types=1);

namespace Lshorz\GeoIp;

use Illuminate\Support\ServiceProvider;

/**
 * 基于纯真IP库的地理位置
 *
 * use Lshorz\GeoIp\Facades\GeoIp;
 * 使用方法:
 * get_ip_location($ip = null)[直接使用辅助函数] 或 GeoIp::getLocation()
 */
class GeoIpServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('GeoIp', function () {
            return new GeoIp();
        });
    }
}

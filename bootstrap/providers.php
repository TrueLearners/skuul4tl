<?php

use App\Providers\AppServiceProvider;
use App\Providers\MonitoringServiceProvider;
use SocialiteProviders\Manager\ServiceProvider as SocialiteProvidersServiceProvider;

return [
    AppServiceProvider::class,
    MonitoringServiceProvider::class,
    SocialiteProvidersServiceProvider::class,
];

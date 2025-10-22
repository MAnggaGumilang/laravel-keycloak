<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Keycloak\Provider as KeycloakProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Event listeners aplikasi.
     *
     * Untuk saat ini dikosongkan karena kita tidak menggunakan listener internal Laravel.
     */
    protected $listen = [];

    /**
     * Metode boot untuk mendaftarkan listener eksternal.
     */
    public function boot(): void
    {
        parent::boot();

        // Daftarkan driver Keycloak agar dikenal oleh Laravel Socialite
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('keycloak', KeycloakProvider::class);
        });
    }
}

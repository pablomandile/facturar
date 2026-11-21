<?php

namespace App\Providers;

use App\Contracts\Arca\FabricaDeTransportes;
use App\Services\Arca\FabricaDeTransportesSoap;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El cable hacia ARCA. Los tests lo reemplazan por uno falso.
        $this->app->bind(FabricaDeTransportes::class, FabricaDeTransportesSoap::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Facturas por email: la casilla de la app es una sola para todas las
        // cuentas, y Hostinger tiene un tope diario de envíos.
        RateLimiter::for('envios', fn (Request $peticion): Limit => Limit::perHour(20)
            ->by((string) ($peticion->user()?->getAuthIdentifier() ?? $peticion->ip())));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

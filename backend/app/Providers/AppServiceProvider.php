<?php

namespace App\Providers;

use App\Paiement\PasserellePaiement;
use App\Paiement\PaytechPasserelle;
use App\Paiement\SimulateurPaiement;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // La passerelle est choisie par PAIEMENT_PASSERELLE : le simulateur en développement, PayTech en production
        $this->app->bind(PasserellePaiement::class, fn () => match (config('kaarpro.paiement.passerelle')) {
            'simulateur' => new SimulateurPaiement,
            'paytech' => new PaytechPasserelle,
            default => throw new RuntimeException('Passerelle de paiement inconnue : '.config('kaarpro.paiement.passerelle')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

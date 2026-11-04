<?php

namespace App\Paiement;

use App\Enums\TypeDemande;
use App\Models\Paiement;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Paiement par l'agrégateur PayTech (https://paytech.sn), qui donne accès à Wave et à Orange Money.
 * KaarPro demande un jeton de paiement, redirige le client vers la page PayTech, puis PayTech
 * confirme le résultat par une notification serveur (IPN), traitée par PaytechController.
 */
class PaytechPasserelle implements PasserellePaiement
{
    public function initier(Paiement $paiement): string
    {
        $config = config('kaarpro.paytech');
        if (! $config['cle_api'] || ! $config['cle_secrete']) {
            throw new RuntimeException('Les clés PayTech (PAYTECH_API_KEY, PAYTECH_API_SECRET) ne sont pas configurées.');
        }

        $paiement->loadMissing('demande.vehicule');
        $demande = $paiement->demande;
        $objet = ($demande->type === TypeDemande::Location ? 'Location' : 'Achat')
            ." {$demande->vehicule->marque} {$demande->vehicule->modele}";
        $retour = config('kaarpro.frontend_url')."/paiement/{$paiement->reference}";
        // Adresse publique (APP_URL), quelle que soit l'adresse par laquelle la requête courante est arrivée
        $ipn = rtrim((string) config('app.url'), '/').route('webhooks.paytech', absolute: false);

        $reponse = Http::withHeaders(['API_KEY' => $config['cle_api'], 'API_SECRET' => $config['cle_secrete']])
            ->acceptJson()
            ->asJson()
            ->timeout(20)
            ->post("{$config['url']}/payment/request-payment", array_filter([
                'item_name' => $objet,
                'item_price' => $paiement->montant_total,
                'currency' => 'XOF',
                'ref_command' => $paiement->reference,
                'command_name' => "KaarPro — {$objet} (demande n°{$demande->id})",
                'env' => $config['environnement'],
                // L'avis de paiement n'est accepté qu'en HTTPS : sur un poste de développement, il n'est pas envoyé
                'ipn_url' => str_starts_with($ipn, 'https://') ? $ipn : null,
                'success_url' => $retour,
                'cancel_url' => "{$retour}?annule=1",
                // Le client va directement au moyen de paiement choisi sur KaarPro
                'target_payment' => $paiement->operateur->libelle(),
                'custom_field' => json_encode(['reference' => $paiement->reference]),
            ]));

        if (! $reponse->successful() || (int) $reponse->json('success') !== 1 || ! $reponse->json('redirect_url')) {
            throw new RuntimeException('PayTech a refusé la demande de paiement : '.($reponse->json('message') ?? $reponse->status()));
        }

        // Le jeton PayTech identifie la transaction chez l'opérateur
        $paiement->update(['reference_operateur' => $reponse->json('token')]);

        return $reponse->json('redirect_url');
    }

    /**
     * Vérifie qu'une notification vient bien de PayTech.
     * Méthode HMAC si « hmac_compute » est fourni (message « montant|référence|clé API », clé : la clé secrète),
     * sinon comparaison des empreintes SHA-256 de la clé API et de la clé secrète.
     */
    public static function notificationAuthentique(array $donnees): bool
    {
        $cle = (string) config('kaarpro.paytech.cle_api');
        $secret = (string) config('kaarpro.paytech.cle_secrete');
        if ($cle === '' || $secret === '') {
            return false;
        }

        if (! empty($donnees['hmac_compute'])) {
            $montant = $donnees['final_item_price'] ?? $donnees['item_price'] ?? '';
            $attendu = hash_hmac('sha256', "{$montant}|".($donnees['ref_command'] ?? '')."|{$cle}", $secret);

            return hash_equals($attendu, (string) $donnees['hmac_compute']);
        }

        return hash_equals(hash('sha256', $cle), (string) ($donnees['api_key_sha256'] ?? ''))
            && hash_equals(hash('sha256', $secret), (string) ($donnees['api_secret_sha256'] ?? ''));
    }
}

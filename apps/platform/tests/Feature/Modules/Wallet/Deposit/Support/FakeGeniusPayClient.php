<?php

namespace Tests\Feature\Modules\Wallet\Deposit\Support;

use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentIntent;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentRequest;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Support\Str;

/**
 * Double de test de {@see GeniusPayClient} : aucun appel réseau, résultat
 * déterministe piloté par le test. Isole `DepositInitiationService` et les
 * routes du comportement réel d'un prestataire externe — la correction de
 * l'adaptateur HTTP réel est vérifiée séparément par
 * `HttpGeniusPayClientTest` (Http::fake).
 */
class FakeGeniusPayClient implements GeniusPayClient
{
    public bool $shouldFail = false;

    /** @var list<GeniusPayPaymentRequest> */
    public array $requests = [];

    public function createCheckoutPayment(GeniusPayPaymentRequest $request): GeniusPayPaymentIntent
    {
        $this->requests[] = $request;

        if ($this->shouldFail) {
            throw new GeniusPayRequestFailedException('panne simulée du prestataire (test)');
        }

        $reference = 'MTX-'.strtoupper(Str::random(10));

        return new GeniusPayPaymentIntent(
            providerPaymentId: (string) random_int(1000, 999999),
            reference: $reference,
            amount: $request->amount,
            fees: (int) round($request->amount * 0.03),
            netAmount: $request->amount - (int) round($request->amount * 0.03),
            status: 'pending',
            checkoutUrl: "https://pay.genius.ci/checkout/{$reference}",
            environment: 'sandbox',
        );
    }
}

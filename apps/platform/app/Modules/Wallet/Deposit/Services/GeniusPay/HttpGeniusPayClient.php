<?php

namespace App\Modules\Wallet\Deposit\Services\GeniusPay;

use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayClient;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentIntent;
use App\Modules\Wallet\Deposit\Contracts\GeniusPay\GeniusPayPaymentRequest;
use App\Modules\Wallet\Deposit\Services\Exceptions\GeniusPayRequestFailedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

/**
 * Adaptateur HTTP réel de {@see GeniusPayClient} (ADR-0007 §14, §20 :
 * délai de connexion/réponse borné, aucune reprise automatique aveugle —
 * un échec de création remonte tel quel, `DepositInitiationService`
 * n'invente jamais un état de succès à partir d'une exception).
 *
 * Les clés d'API sont lues depuis `config('services.geniuspay')`, jamais
 * codées en dur (EXE-0001 §5) ; ce fichier ne contient aucun secret.
 */
final class HttpGeniusPayClient implements GeniusPayClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {}

    public function createCheckoutPayment(GeniusPayPaymentRequest $request): GeniusPayPaymentIntent
    {
        try {
            $response = $this->http
                ->withHeaders([
                    'X-API-Key' => $this->apiKey,
                    'X-API-Secret' => $this->apiSecret,
                ])
                ->connectTimeout(5)
                ->timeout(15)
                ->post(rtrim($this->baseUrl, '/').'/payments', [
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'description' => $request->description,
                    'success_url' => $request->successUrl,
                    'error_url' => $request->errorUrl,
                    'metadata' => [
                        'wallet_deposit_idempotency_key' => $request->idempotencyKey,
                    ],
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new GeniusPayRequestFailedException(
                "l'appel GeniusPay a échoué : {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $data = $response->json('data');

        if (! is_array($data) || ! isset($data['reference'], $data['checkout_url'], $data['id'], $data['amount'], $data['status'])) {
            throw new GeniusPayRequestFailedException(
                'réponse GeniusPay incomplète ou malformée : champs attendus absents de data.*'
            );
        }

        return new GeniusPayPaymentIntent(
            providerPaymentId: (string) $data['id'],
            reference: (string) $data['reference'],
            amount: (int) $data['amount'],
            fees: isset($data['fees']) ? (int) $data['fees'] : null,
            netAmount: isset($data['net_amount']) ? (int) $data['net_amount'] : null,
            status: (string) $data['status'],
            checkoutUrl: (string) $data['checkout_url'],
            environment: (string) ($data['environment'] ?? 'sandbox'),
        );
    }
}

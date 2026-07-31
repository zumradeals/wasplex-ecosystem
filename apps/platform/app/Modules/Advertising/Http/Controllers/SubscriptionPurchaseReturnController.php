<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\SubscriptionPurchase;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page de retour après redirection GeniusPay pour un achat d'abonnement
 * (instruction explicite du fondateur, 2026-07-31 ; mirroir exact de
 * {@see \App\Modules\Advertising\Http\Controllers\AdvertiserWalletDepositReturnController}).
 * L'accès se vérifie sur la propriété de l'achat (son propre `person_id`),
 * pas sur une capacité dédiée : même contenu que ce que l'achat lui-même a
 * déjà autorisé.
 */
class SubscriptionPurchaseReturnController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
    ) {}

    public function show(Request $request, SubscriptionPurchase $purchase): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            return Inertia::render('advertising/subscription-purchase-return', [
                'access' => ['allowed' => false, 'reason' => 'subject_not_resolved'],
                'purchase' => null,
            ]);
        }

        if ($subject->personAccountLink->person_id !== $purchase->person_id) {
            return Inertia::render('advertising/subscription-purchase-return', [
                'access' => ['allowed' => false, 'reason' => 'no_active_grant'],
                'purchase' => null,
            ]);
        }

        return Inertia::render('advertising/subscription-purchase-return', [
            'access' => ['allowed' => true, 'reason' => null],
            'purchase' => [
                'id' => $purchase->id,
                'state' => $purchase->state->value,
                'amount' => $purchase->amount,
                'currency' => $purchase->currency,
            ],
        ]);
    }
}

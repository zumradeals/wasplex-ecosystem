<?php

namespace App\Modules\Advertising\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Advertising\Models\PersonSubscription;
use App\Modules\Advertising\Models\SubscriptionPlan;
use App\Modules\Governance\Authorization\Integration\Exceptions\SubjectResolutionFailedException;
use App\Modules\Governance\Authorization\Integration\Http\AuthenticatedSubjectHttpResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran « Abonnements » (instruction explicite du fondateur, 2026-07-31 ;
 * docs/02 §9). Contenu de diffusion public au même titre que le Feed
 * (parcourir les plans actifs n'expose aucune donnée personnelle) —
 * aucune capacité `Governance/Authorization` nouvelle pour la simple
 * lecture, même raisonnement que {@see FeedController}. L'achat lui-même
 * reste gouverné par `subscription.purchase`
 * ({@see SubscriptionPurchaseInitiationController}).
 */
class SubscriptionsController extends Controller
{
    public function __construct(
        private readonly AuthenticatedSubjectHttpResolver $subjectResolver,
    ) {}

    public function index(Request $request): Response
    {
        try {
            $subject = $this->subjectResolver->resolve($request);
        } catch (SubjectResolutionFailedException) {
            $subject = null;
        }

        $currentSubscription = $subject !== null
            ? PersonSubscription::query()
                ->where('person_id', $subject->personAccountLink->person_id)
                ->with('subscriptionPlan')
                ->first()
            : null;

        return Inertia::render('subscriptions', [
            'plans' => SubscriptionPlan::query()
                ->with('economicType')
                ->where('state', 'active')
                ->orderBy('price_amount')
                ->get()
                ->map(fn (SubscriptionPlan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price_amount' => $plan->price_amount,
                    'currency' => $plan->currency,
                    'duration_days' => $plan->duration_days,
                    'economic_type_name' => $plan->economicType->name,
                ])
                ->values()
                ->all(),
            'currentSubscription' => $currentSubscription !== null ? [
                'plan_name' => $currentSubscription->subscriptionPlan->name,
                'ends_at' => $currentSubscription->ends_at->toIso8601String(),
                'is_active' => $currentSubscription->isActive(),
            ] : null,
        ]);
    }
}

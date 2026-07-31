import { Head } from '@inertiajs/react';
import { useState } from 'react';
import MobileLayout from '@/layouts/mobile-layout';
import { amountFormatter } from '@/lib/advertising-labels';
import { postJson } from '@/lib/api';

type SubscriptionPlan = {
    id: string;
    name: string;
    price_amount: number;
    currency: string;
    duration_days: number;
    economic_type_name: string;
};

type CurrentSubscription = {
    plan_name: string;
    ends_at: string;
    is_active: boolean;
};

function PurchaseButton({ plan }: { plan: SubscriptionPlan }) {
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function purchase() {
        setSubmitting(true);
        setError(null);

        const result = await postJson<{ checkout_url: string }>(
            '/subscriptions/purchases',
            {
                subscription_plan_id: plan.id,
                idempotency_key: crypto.randomUUID(),
            },
        );

        if (result.ok) {
            window.location.href = result.data.checkout_url;

            return;
        }

        setSubmitting(false);
        setError(
            result.status === 503
                ? "Le service de paiement n'est pas disponible pour le moment."
                : "L'achat n'a pas pu être initié.",
        );
    }

    return (
        <div className="space-y-1.5">
            <button
                type="button"
                onClick={purchase}
                disabled={submitting}
                className="w-full rounded-lg bg-[#C75100] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#A84300] disabled:opacity-50"
            >
                {submitting ? 'Redirection...' : "S'abonner"}
            </button>
            {error && (
                <p className="text-xs text-[var(--status-danger,#E5484D)]">
                    {error}
                </p>
            )}
        </div>
    );
}

export default function Subscriptions({
    plans,
    currentSubscription,
}: {
    plans: SubscriptionPlan[];
    currentSubscription: CurrentSubscription | null;
}) {
    return (
        <MobileLayout>
            <Head title="Abonnements" />

            <div className="space-y-5 px-4 py-5">
                <h1 className="text-lg font-bold">Abonnements</h1>

                {currentSubscription && (
                    <div className="rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-3.5">
                        <p className="text-xs text-[#A9B7C8]">
                            {currentSubscription.is_active
                                ? 'Abonnement actif'
                                : 'Abonnement expiré'}
                        </p>
                        <p className="mt-1 text-sm font-semibold text-[#F5F8FC]">
                            {currentSubscription.plan_name}
                        </p>
                        <p className="mt-0.5 text-xs text-[#A9B7C8]">
                            {currentSubscription.is_active
                                ? "Jusqu'au "
                                : 'Expiré le '}
                            {new Date(
                                currentSubscription.ends_at,
                            ).toLocaleDateString('fr-FR')}
                        </p>
                    </div>
                )}

                {plans.length === 0 ? (
                    <p className="text-sm text-[#A9B7C8]">
                        Aucun plan d'abonnement disponible pour le moment.
                    </p>
                ) : (
                    <div className="space-y-3">
                        {plans.map((plan) => (
                            <div
                                key={plan.id}
                                className="space-y-3 rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-4"
                            >
                                <div className="flex items-baseline justify-between">
                                    <p className="text-sm font-semibold text-[#F5F8FC]">
                                        {plan.name}
                                    </p>
                                    <p className="text-sm font-bold text-[#F5F8FC] tabular-nums">
                                        {amountFormatter.format(
                                            plan.price_amount,
                                        )}{' '}
                                        <span className="text-xs font-normal text-[#A9B7C8]">
                                            {plan.currency}
                                        </span>
                                    </p>
                                </div>
                                <p className="text-xs text-[#A9B7C8]">
                                    {plan.duration_days} jours — type{' '}
                                    {plan.economic_type_name}
                                </p>
                                <PurchaseButton plan={plan} />
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </MobileLayout>
    );
}

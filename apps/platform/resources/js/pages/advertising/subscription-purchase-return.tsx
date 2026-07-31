import { Head, Link } from '@inertiajs/react';
import MobileLayout from '@/layouts/mobile-layout';

type Purchase = {
    id: string;
    state: string;
    amount: number;
    currency: string;
};

type Access = {
    allowed: boolean;
    reason: string | null;
};

const amountFormatter = new Intl.NumberFormat('fr-FR');

const ACCESS_DENIED_MESSAGES: Record<string, string> = {
    no_active_grant:
        "Cet achat n'appartient pas à ce compte, ou l'accès n'est pas encore activé.",
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

const STATE_MESSAGES: Record<
    string,
    { title: string; description: string; tone: string }
> = {
    completed: {
        title: 'Abonnement activé',
        description: 'Votre abonnement est actif.',
        tone: 'text-[#4FA3FF]',
    },
    failed: {
        title: 'Paiement échoué',
        description:
            "Le paiement n'a pas abouti. Aucun montant n'a été débité côté Wasplex.",
        tone: 'text-[#D92D20]',
    },
    pending: {
        title: 'Paiement en cours de confirmation',
        description:
            'Nous attendons la confirmation du prestataire de paiement. Cette page ne se met pas à jour automatiquement — revenez sur vos abonnements dans un instant.',
        tone: 'text-[#F2C14E]',
    },
    awaiting_provider: {
        title: 'Paiement en cours de confirmation',
        description:
            'Nous attendons la confirmation du prestataire de paiement. Revenez sur vos abonnements dans un instant.',
        tone: 'text-[#F2C14E]',
    },
    unknown_reconciliation: {
        title: 'Résultat en cours de vérification',
        description:
            "Le résultat de ce paiement n'est pas encore confirmé avec certitude. Aucun abonnement n'est activé tant que ce n'est pas confirmé.",
        tone: 'text-[#FF9A3D]',
    },
    draft: {
        title: 'Achat non initié',
        description:
            "Cet achat n'a pas pu être transmis au prestataire de paiement.",
        tone: 'text-[#A9B7C8]',
    },
};

export default function SubscriptionPurchaseReturn({
    access,
    purchase,
}: {
    access: Access;
    purchase: Purchase | null;
}) {
    const stateInfo = purchase
        ? (STATE_MESSAGES[purchase.state] ?? {
              title: purchase.state,
              description: '',
              tone: 'text-[#A9B7C8]',
          })
        : null;

    return (
        <MobileLayout>
            <Head title="Achat d'abonnement" />

            <div className="space-y-6 p-4">
                <h1 className="text-xl font-bold text-[#F5F8FC]">
                    Achat d'abonnement
                </h1>

                {!access.allowed && (
                    <div className="rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-4">
                        <p className="text-sm font-semibold text-[#FF9A3D]">
                            Consultation indisponible
                        </p>
                        <p className="mt-1.5 text-xs leading-relaxed text-[#A9B7C8]">
                            {ACCESS_DENIED_MESSAGES[access.reason ?? ''] ??
                                "Cet écran n'est pas accessible pour votre compte."}
                        </p>
                    </div>
                )}

                {access.allowed && purchase && stateInfo && (
                    <div className="rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-5">
                        <p
                            className={`text-base font-semibold ${stateInfo.tone}`}
                        >
                            {stateInfo.title}
                        </p>
                        <p className="mt-2 text-sm leading-relaxed text-[#A9B7C8]">
                            {stateInfo.description}
                        </p>
                        <p className="mt-4 text-lg font-bold text-white tabular-nums">
                            {amountFormatter.format(purchase.amount)}{' '}
                            <span className="text-sm font-normal text-[#A9B7C8]">
                                {purchase.currency}
                            </span>
                        </p>
                    </div>
                )}

                <Link
                    href="/subscriptions"
                    className="block w-full rounded-lg bg-[#173251] py-3 text-center text-sm font-semibold text-white"
                >
                    Retour aux abonnements
                </Link>
            </div>
        </MobileLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import MobileLayout from '@/layouts/mobile-layout';
import wallet from '@/routes/wallet';

type Deposit = {
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

// Wasplex never exposes internal denial reasons (grant, policy, capability
// definition — ADR-0004 §"décision explicable").
const ACCESS_DENIED_MESSAGES: Record<string, string> = {
    no_active_grant:
        "Ce dépôt n'appartient pas à ce compte, ou l'accès n'est pas encore activé.",
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

const STATE_MESSAGES: Record<
    string,
    { title: string; description: string; tone: string }
> = {
    completed: {
        title: 'Dépôt confirmé',
        description: 'Le montant a été crédité sur votre Wallet.',
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
            'Nous attendons la confirmation du prestataire de paiement. Cette page ne se met pas à jour automatiquement — revenez sur votre Wallet dans un instant.',
        tone: 'text-[#F2C14E]',
    },
    awaiting_provider: {
        title: 'Paiement en cours de confirmation',
        description:
            'Nous attendons la confirmation du prestataire de paiement. Revenez sur votre Wallet dans un instant.',
        tone: 'text-[#F2C14E]',
    },
    unknown_reconciliation: {
        title: 'Résultat en cours de vérification',
        description:
            "Le résultat de ce paiement n'est pas encore confirmé avec certitude. Il sera résolu après rapprochement — aucun montant n'est crédité tant que ce n'est pas confirmé.",
        tone: 'text-[#FF9A3D]',
    },
    draft: {
        title: 'Dépôt non initié',
        description:
            "Ce dépôt n'a pas pu être transmis au prestataire de paiement.",
        tone: 'text-[#A9B7C8]',
    },
};

export default function DepositReturn({
    access,
    deposit,
}: {
    access: Access;
    deposit: Deposit | null;
}) {
    const stateInfo = deposit
        ? (STATE_MESSAGES[deposit.state] ?? {
              title: deposit.state,
              description: '',
              tone: 'text-[#A9B7C8]',
          })
        : null;

    return (
        <MobileLayout>
            <Head title="Recharge Wallet" />

            <div className="space-y-6 p-4">
                <h1 className="text-xl font-bold text-[#F5F8FC]">
                    Recharge Wallet
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

                {access.allowed && deposit && stateInfo && (
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
                            {amountFormatter.format(deposit.amount)}{' '}
                            <span className="text-sm font-normal text-[#A9B7C8]">
                                {deposit.currency}
                            </span>
                        </p>
                    </div>
                )}

                <Link
                    href={wallet.show()}
                    className="block w-full rounded-lg bg-[#173251] py-3 text-center text-sm font-semibold text-white"
                >
                    Retour au Wallet
                </Link>
            </div>
        </MobileLayout>
    );
}

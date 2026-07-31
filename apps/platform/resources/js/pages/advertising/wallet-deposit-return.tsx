import { Head, Link } from '@inertiajs/react';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { amountFormatter } from '@/lib/advertising-labels';

type WalletDeposit = {
    id: string;
    state: string;
    amount: number;
    currency: string;
};

type Access = {
    allowed: boolean;
    reason: string | null;
};

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
        description: 'Le montant a été crédité à votre solde Wallet.',
        tone: 'text-[var(--status-success)]',
    },
    failed: {
        title: 'Paiement échoué',
        description:
            "Le paiement n'a pas abouti. Aucun montant n'a été débité côté Wasplex.",
        tone: 'text-[var(--status-danger)]',
    },
    pending: {
        title: 'Paiement en cours de confirmation',
        description:
            'Nous attendons la confirmation du prestataire de paiement. Cette page ne se met pas à jour automatiquement — revenez sur votre Wallet dans un instant.',
        tone: 'text-[var(--status-warning)]',
    },
    awaiting_provider: {
        title: 'Paiement en cours de confirmation',
        description:
            'Nous attendons la confirmation du prestataire de paiement. Revenez sur votre Wallet dans un instant.',
        tone: 'text-[var(--status-warning)]',
    },
    unknown_reconciliation: {
        title: 'Résultat en cours de vérification',
        description:
            "Le résultat de ce paiement n'est pas encore confirmé avec certitude. Aucun montant n'est crédité tant que ce n'est pas confirmé.",
        tone: 'text-[var(--status-warning)]',
    },
    draft: {
        title: 'Dépôt non initié',
        description:
            "Ce dépôt n'a pas pu être transmis au prestataire de paiement.",
        tone: 'text-[var(--text-secondary)]',
    },
};

export default function AdvertiserWalletDepositReturn({
    access,
    deposit,
}: {
    access: Access;
    deposit: WalletDeposit | null;
}) {
    const stateInfo = deposit
        ? (STATE_MESSAGES[deposit.state] ?? {
              title: deposit.state,
              description: '',
              tone: 'text-[var(--text-secondary)]',
          })
        : null;

    return (
        <AdvertiserLayout
            title="Dépôt Wallet"
            description="Retour après redirection GeniusPay."
        >
            <Head title="Espace annonceur — Dépôt Wallet" />

            <div className="space-y-6">
                {!access.allowed && (
                    <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4">
                        <p className="text-sm font-semibold text-[var(--status-warning)]">
                            Consultation indisponible
                        </p>
                        <p className="mt-1.5 text-sm text-[var(--text-secondary)]">
                            {ACCESS_DENIED_MESSAGES[access.reason ?? ''] ??
                                "Cet écran n'est pas accessible pour votre compte."}
                        </p>
                    </div>
                )}

                {access.allowed && deposit && stateInfo && (
                    <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-5">
                        <p
                            className={`text-base font-semibold ${stateInfo.tone}`}
                        >
                            {stateInfo.title}
                        </p>
                        <p className="mt-2 text-sm leading-relaxed text-[var(--text-secondary)]">
                            {stateInfo.description}
                        </p>
                        <p className="mt-4 text-lg font-bold text-[var(--text-primary)] tabular-nums">
                            {amountFormatter.format(deposit.amount)}{' '}
                            <span className="text-sm font-normal text-[var(--text-secondary)]">
                                {deposit.currency}
                            </span>
                        </p>
                    </div>
                )}

                <Link
                    href="/advertising/wallet"
                    className="inline-block rounded-lg bg-[var(--brand-blue)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                >
                    Retour au Wallet
                </Link>
            </div>
        </AdvertiserLayout>
    );
}

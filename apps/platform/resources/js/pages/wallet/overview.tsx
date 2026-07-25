import { Head } from '@inertiajs/react';
import MobileLayout from '@/layouts/mobile-layout';

type Balance = {
    currency: string;
    available: number;
    provisional: number;
    reserved: number;
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
        "L'accès à votre Wallet n'est pas encore activé sur ce compte. Cet écran fonctionne, mais aucun droit de consultation ne vous a encore été accordé (P006, TD-0005-D).",
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

type BalanceTone = 'available' | 'provisional' | 'reserved';

const TONE_STYLES: Record<BalanceTone, { value: string; label: string; dot: string }> = {
    available: {
        value: 'text-[#F2C14E]',
        label: 'text-[#A9B7C8]',
        dot: 'bg-[#F2C14E]',
    },
    provisional: {
        value: 'text-[#E7CF61]',
        label: 'text-[#A9B7C8]',
        dot: 'bg-[#E7CF61]',
    },
    reserved: {
        value: 'text-[#A9B7C8]',
        label: 'text-[#A9B7C8]',
        dot: 'bg-[#A9B7C8]',
    },
};

function BalanceRow({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone: BalanceTone;
}) {
    const styles = TONE_STYLES[tone];

    return (
        <div className="flex items-center justify-between rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-4">
            <div className="flex items-center gap-3">
                <span className={`h-2.5 w-2.5 rounded-full ${styles.dot}`} aria-hidden="true" />
                <span className={`text-sm font-medium ${styles.label}`}>{label}</span>
            </div>
            <div className="text-right">
                <p className={`text-lg font-bold tabular-nums ${styles.value}`}>
                    {amountFormatter.format(value)}
                    <span className="ml-1 text-sm font-normal text-[#A9B7C8]">WP</span>
                </p>
                <p className="text-xs text-[#A9B7C8]">
                    ≈ {amountFormatter.format(value)} FCFA
                </p>
            </div>
        </div>
    );
}

export default function WalletOverview({
    access,
    balances,
}: {
    access: Access;
    balances: Balance[];
}) {
    return (
        <MobileLayout>
            <Head title="Wallet" />

            <div className="p-4 space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-xl font-bold text-[#F5F8FC]">Votre Wallet</h1>
                    <p className="mt-1 text-xs text-[#A9B7C8]">
                        1 WP = 1 FCFA · solde reconstruit depuis le registre comptable
                    </p>
                </div>

                {/* Access denied */}
                {!access.allowed && (
                    <div className="rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-4">
                        <p className="text-sm font-semibold text-[#FF9A3D]">
                            Consultation indisponible pour le moment
                        </p>
                        <p className="mt-1.5 text-xs text-[#A9B7C8] leading-relaxed">
                            {ACCESS_DENIED_MESSAGES[access.reason ?? ''] ??
                                "Cet écran n'est pas encore disponible pour votre compte."}
                        </p>
                    </div>
                )}

                {/* No operations yet */}
                {access.allowed && balances.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#173251]">
                            <span className="text-2xl">💰</span>
                        </div>
                        <h3 className="mb-2 text-base font-semibold text-[#F5F8FC]">
                            Aucune opération enregistrée
                        </h3>
                        <p className="text-sm text-[#A9B7C8] leading-relaxed">
                            Vous n'avez encore reçu aucun droit WP.
                            <br />
                            Cette page se mettra à jour dès la première rémunération créditée.
                        </p>
                    </div>
                )}

                {/* Balance sections */}
                {access.allowed &&
                    balances.map((balance) => (
                        <section
                            key={balance.currency}
                            aria-label={`Solde en ${balance.currency}`}
                            className="space-y-2"
                        >
                            <p className="px-1 text-xs font-semibold uppercase tracking-widest text-[#A9B7C8]">
                                {balance.currency}
                            </p>
                            <BalanceRow
                                label="Disponibles"
                                value={balance.available}
                                tone="available"
                            />
                            <BalanceRow
                                label="Provisoires"
                                value={balance.provisional}
                                tone="provisional"
                            />
                            <BalanceRow
                                label="Réservés"
                                value={balance.reserved}
                                tone="reserved"
                            />
                        </section>
                    ))}
            </div>
        </MobileLayout>
    );
}

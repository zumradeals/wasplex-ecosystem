import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import DepositInitiationController from '@/actions/App/Modules/Wallet/Deposit/Http/Controllers/DepositInitiationController';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';

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

const TONE_STYLES: Record<
    BalanceTone,
    { value: string; label: string; dot: string }
> = {
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
                <span
                    className={`h-2.5 w-2.5 rounded-full ${styles.dot}`}
                    aria-hidden="true"
                />
                <span className={`text-sm font-medium ${styles.label}`}>
                    {label}
                </span>
            </div>
            <div className="text-right">
                <p className={`text-lg font-bold tabular-nums ${styles.value}`}>
                    {amountFormatter.format(value)}
                    <span className="ml-1 text-sm font-normal text-[#A9B7C8]">
                        WP
                    </span>
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
    const [rechargeOpen, setRechargeOpen] = useState(false);

    return (
        <MobileLayout>
            <Head title="Wallet" />

            <div className="space-y-6 p-4">
                {/* Header */}
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold text-[#F5F8FC]">
                            Votre Wallet
                        </h1>
                        <p className="mt-1 text-xs text-[#A9B7C8]">
                            1 WP = 1 FCFA · solde reconstruit depuis le registre
                            comptable
                        </p>
                    </div>

                    {access.allowed && (
                        <button
                            type="button"
                            onClick={() => setRechargeOpen(true)}
                            className="shrink-0 rounded-full bg-[#4FA3FF] px-4 py-2 text-sm font-semibold text-[#07182D]"
                        >
                            Recharger
                        </button>
                    )}
                </div>

                {/* Access denied */}
                {!access.allowed && (
                    <div className="rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-4">
                        <p className="text-sm font-semibold text-[#FF9A3D]">
                            Consultation indisponible pour le moment
                        </p>
                        <p className="mt-1.5 text-xs leading-relaxed text-[#A9B7C8]">
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
                        <p className="text-sm leading-relaxed text-[#A9B7C8]">
                            Vous n'avez encore reçu aucun droit WP.
                            <br />
                            Cette page se mettra à jour dès la première
                            rémunération créditée.
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
                            <p className="px-1 text-xs font-semibold tracking-widest text-[#A9B7C8] uppercase">
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

            {rechargeOpen && (
                <RechargeSheet onClose={() => setRechargeOpen(false)} />
            )}
        </MobileLayout>
    );
}

/**
 * AMD-0017 : recharge du Wallet via GeniusPay (pilote Côte d'Ivoire).
 * Redirige vers la page de checkout hébergée par GeniusPay — jamais
 * d'affichage d'un solde crédité ici : seul le retour confirmé par webhook
 * ({@see \App\Modules\Wallet\Deposit\Http\Controllers\DepositWebhookController})
 * fait foi (`wallet/deposit-return`).
 */
function RechargeSheet({ onClose }: { onClose: () => void }) {
    const [amount, setAmount] = useState('');
    const [submitting, setSubmitting] = useState(false);

    async function submit() {
        const parsed = Number(amount);

        if (!Number.isInteger(parsed) || parsed < 200) {
            toast.error('Le montant minimum est de 200 FCFA.');

            return;
        }

        setSubmitting(true);

        const result = await postJson<{ checkout_url: string }>(
            DepositInitiationController.store().url,
            {
                amount: parsed,
                idempotency_key: crypto.randomUUID(),
            },
        );

        if (result.ok) {
            window.location.href = result.data.checkout_url;

            return;
        }

        setSubmitting(false);

        toast.error(
            result.status === 503
                ? "Le service de paiement n'est pas disponible pour le moment. Réessayez dans quelques instants."
                : "La recharge n'a pas pu être initiée.",
        );
    }

    return (
        <Sheet onClose={onClose} title="Recharger">
            <label className="block text-sm font-medium text-[#A9B7C8]">
                Montant (FCFA)
                <input
                    type="number"
                    inputMode="numeric"
                    min={200}
                    step={1}
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
                    placeholder="15000"
                    className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                />
            </label>

            <p className="mt-3 text-xs text-[#A9B7C8]">
                Vous serez redirigé vers la page de paiement sécurisée GeniusPay
                (Wave, Orange Money, MTN Money ou carte). Le WP n'est crédité
                qu'après confirmation du paiement.
            </p>

            <button
                type="button"
                onClick={submit}
                disabled={submitting || amount.trim() === ''}
                className="mt-4 w-full rounded-lg bg-[#4FA3FF] py-3 text-sm font-semibold text-[#07182D] disabled:opacity-50"
            >
                {submitting ? 'Redirection...' : 'Continuer vers le paiement'}
            </button>
        </Sheet>
    );
}

function Sheet({
    title,
    onClose,
    children,
}: {
    title: string;
    onClose: () => void;
    children: React.ReactNode;
}) {
    return (
        <div className="fixed inset-0 z-[60] flex items-end bg-black/60">
            <div className="max-h-[85vh] w-full overflow-y-auto rounded-t-2xl bg-[#07182D] p-4 pb-24">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-base font-bold text-white">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fermer"
                        className="text-[#A9B7C8]"
                    >
                        ✕
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

import { Head } from '@inertiajs/react';
import { PiggyBank } from 'lucide-react';
import { useState } from 'react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import {
    amountFormatter,
    displayCampaignStatus,
} from '@/lib/advertising-labels';
import { postJson } from '@/lib/api';

type CampaignBudget = {
    campaign_id: string;
    campaign_code: string;
    currency: string;
    state: string;
    available: number;
    reserved: number;
    consumed: number;
    unit_price: number | null;
    user_share_per_event: number | null;
    events_affordable: number | null;
};

// Véto du dirigeant 2026-07-30 : financement de campagne en libre-service
// via GeniusPay (campaign.fund_self) — même flux que la recharge Wallet
// (resources/js/pages/wallet/overview.tsx) : redirection vers le checkout
// hébergé par GeniusPay, jamais d'affichage d'un budget crédité ici, seul
// le webhook signé fait foi.
function FundCampaignButton({ campaignId }: { campaignId: string }) {
    const [open, setOpen] = useState(false);
    const [amount, setAmount] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        const parsed = Number(amount);

        if (!Number.isInteger(parsed) || parsed < 200) {
            setError('Le montant minimum est de 200.');

            return;
        }

        setSubmitting(true);
        setError(null);

        const result = await postJson<{ checkout_url: string }>(
            `/advertising/campaigns/${campaignId}/self-funding`,
            { amount: parsed, idempotency_key: crypto.randomUUID() },
        );

        if (result.ok) {
            window.location.href = result.data.checkout_url;

            return;
        }

        setSubmitting(false);
        setError(
            result.status === 503
                ? "Le service de paiement n'est pas disponible pour le moment."
                : "Le financement n'a pas pu être initié.",
        );
    }

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="rounded-md border border-[var(--border-default)] px-3 py-1.5 text-xs font-medium text-[var(--text-primary)] hover:bg-[var(--bg-subtle)]"
            >
                Financer
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="flex items-center justify-end gap-2">
            {error && (
                <p className="text-xs text-[var(--status-danger)]">{error}</p>
            )}
            <input
                type="number"
                min={200}
                step={1}
                autoFocus
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                placeholder="Montant"
                className="w-28 rounded-md border border-[var(--border-default)] bg-[var(--bg-canvas)] px-2 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
            />
            <button
                type="submit"
                disabled={submitting}
                className="rounded-md bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90 disabled:opacity-50"
            >
                {submitting ? 'Redirection...' : 'Payer'}
            </button>
        </form>
    );
}

export default function AdvertisingBudget({
    access,
    advertiserProfile,
    campaignBudgets,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    campaignBudgets: CampaignBudget[];
}) {
    return (
        <AdvertiserLayout
            title="Budget"
            description="Ce que chaque campagne peut encore engager, reconstruit depuis le registre comptable en temps réel."
        >
            <Head title="Espace annonceur — Budget" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                {campaignBudgets.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={PiggyBank}
                        title="Aucun budget à afficher"
                        description="Le budget disponible, réservé et consommé de chaque campagne apparaîtra ici."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                    <th className="px-5 py-2.5 font-medium">
                                        Campagne
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Statut
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Disponible
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Réservé
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Consommé
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Prix / vue qualifiée
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Vues finançables
                                    </th>
                                    <th className="px-5 py-2.5 text-right font-medium">
                                        Financement
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--border-default)]">
                                {campaignBudgets.map((row) => (
                                    <tr key={row.campaign_id}>
                                        <td className="px-5 py-3 font-medium text-[var(--text-primary)]">
                                            {row.campaign_code}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--text-secondary)]">
                                            {displayCampaignStatus(
                                                row.state,
                                                null,
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--status-success)] tabular-nums">
                                            {amountFormatter.format(
                                                row.available,
                                            )}{' '}
                                            {row.currency}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--status-warning)] tabular-nums">
                                            {amountFormatter.format(
                                                row.reserved,
                                            )}{' '}
                                            {row.currency}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--text-secondary)] tabular-nums">
                                            {amountFormatter.format(
                                                row.consumed,
                                            )}{' '}
                                            {row.currency}
                                        </td>
                                        <td className="px-5 py-3 tabular-nums">
                                            {row.unit_price !== null ? (
                                                <>
                                                    <span className="text-[var(--text-primary)]">
                                                        {amountFormatter.format(
                                                            row.unit_price,
                                                        )}{' '}
                                                        {row.currency}
                                                    </span>
                                                    <p className="text-xs text-[var(--text-secondary)]">
                                                        Récompense utilisateur :{' '}
                                                        {
                                                            row.user_share_per_event
                                                        }
                                                    </p>
                                                </>
                                            ) : (
                                                <span className="text-[var(--text-secondary)]">
                                                    Non résolvable
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--text-primary)] tabular-nums">
                                            {row.events_affordable ?? '—'}
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <FundCampaignButton
                                                campaignId={row.campaign_id}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </AdvertiserAccessGate>
        </AdvertiserLayout>
    );
}

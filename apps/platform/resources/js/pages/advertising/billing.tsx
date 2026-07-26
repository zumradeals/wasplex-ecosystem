import { Head } from '@inertiajs/react';
import { Banknote, Info } from 'lucide-react';
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

type CampaignFunding = {
    campaign_id: string;
    campaign_code: string;
    currency: string;
    state: string;
    covered_total: number;
    billed_to_date: number;
    available: number;
};

export default function AdvertisingBilling({
    access,
    advertiserProfile,
    campaignFunding,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    campaignFunding: CampaignFunding[];
}) {
    return (
        <AdvertiserLayout
            title="Facturation"
            description="État du financement de vos campagnes."
        >
            <Head title="Espace annonceur — Facturation" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                <div className="mb-4 flex gap-2 rounded-lg border border-[var(--border-default)] bg-[var(--bg-raised)] px-4 py-3 text-xs text-[var(--text-secondary)]">
                    <Info size={14} className="mt-0.5 shrink-0" />
                    <p>
                        Une campagne ne peut être activée sans financement
                        intégral de son budget engagé. Le financement est
                        confirmé par Wasplex après réception ; aucun paiement en
                        libre-service n'est disponible sur cet écran.
                    </p>
                </div>

                {campaignFunding.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={Banknote}
                        title="Aucune campagne à facturer"
                        description="L'état du financement de vos campagnes apparaîtra ici."
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
                                        Couvert à ce jour
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Facturé (consommé)
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Encore disponible
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--border-default)]">
                                {campaignFunding.map((row) => (
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
                                        <td className="px-5 py-3 text-[var(--text-primary)] tabular-nums">
                                            {amountFormatter.format(
                                                row.covered_total,
                                            )}{' '}
                                            {row.currency}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--text-secondary)] tabular-nums">
                                            {amountFormatter.format(
                                                row.billed_to_date,
                                            )}{' '}
                                            {row.currency}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--status-success)] tabular-nums">
                                            {amountFormatter.format(
                                                row.available,
                                            )}{' '}
                                            {row.currency}
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

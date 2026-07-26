import { Head } from '@inertiajs/react';
import { PiggyBank } from 'lucide-react';
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

type CampaignBudget = {
    campaign_id: string;
    campaign_code: string;
    currency: string;
    state: string;
    available: number;
    reserved: number;
    consumed: number;
};

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

import { Head } from '@inertiajs/react';
import { Landmark, PiggyBank, ShieldCheck, Users } from 'lucide-react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import { KpiCard } from '@/components/advertiser/kpi-card';
import AdminLayout from '@/layouts/admin-layout';
import {
    amountFormatter,
    displayCampaignStatus,
} from '@/lib/advertising-labels';

type BudgetTotal = {
    currency: string;
    available: number;
    reserved: number;
    consumed: number;
};

type DistributionTotal = {
    currency: string;
    accepted_total: number;
    user_share: number;
    wasplex_share: number;
};

type CampaignRow = {
    campaign_id: string;
    code: string;
    currency: string;
    state: string;
    advertiser_legal_name: string;
    available: number;
    reserved: number;
    consumed: number;
};

export default function AdminFinance({
    access,
    budgetTotals,
    distributionTotals,
    campaigns,
}: {
    access: AdminAccess;
    budgetTotals: BudgetTotal[];
    distributionTotals: DistributionTotal[];
    campaigns: CampaignRow[];
}) {
    const primaryBudget = budgetTotals[0] ?? null;
    const primaryDistribution = distributionTotals[0] ?? null;

    return (
        <AdminLayout
            title="Finance et rapprochement"
            description="Budget de toutes les campagnes et répartition des publicités qualifiées créditées."
        >
            <Head title="Personnel — Finance et rapprochement" />

            <AdminAccessGate access={access}>
                {campaigns.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={Landmark}
                        title="Aucune campagne financée"
                        description="Les montants disponibles, réservés et consommés apparaîtront ici dès la première campagne."
                    />
                ) : (
                    <div className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <KpiCard
                                icon={PiggyBank}
                                label="Disponible (système)"
                                value={
                                    primaryBudget
                                        ? `${amountFormatter.format(primaryBudget.available)} ${primaryBudget.currency}`
                                        : '—'
                                }
                                hint="Toutes campagnes confondues"
                                tone="info"
                            />
                            <KpiCard
                                icon={Landmark}
                                label="Réservé (en cours)"
                                value={
                                    primaryBudget
                                        ? `${amountFormatter.format(primaryBudget.reserved)} ${primaryBudget.currency}`
                                        : '—'
                                }
                                hint="Publicités qualifiées en attente de décision"
                                tone="warning"
                            />
                            <KpiCard
                                icon={Users}
                                label="Part utilisateurs distribuée"
                                value={
                                    primaryDistribution
                                        ? `${amountFormatter.format(primaryDistribution.user_share)} ${primaryDistribution.currency}`
                                        : '0'
                                }
                                hint="Ratio 50/50 (AMD-0002)"
                                tone="success"
                            />
                            <KpiCard
                                icon={ShieldCheck}
                                label="Part Wasplex"
                                value={
                                    primaryDistribution
                                        ? `${amountFormatter.format(primaryDistribution.wasplex_share)} ${primaryDistribution.currency}`
                                        : '0'
                                }
                                hint="Ratio 50/50 (AMD-0002)"
                            />
                        </div>

                        <div className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                            <div className="border-b border-[var(--border-default)] px-5 py-3">
                                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                    Budget par campagne
                                </h2>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[800px] text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                            <th className="px-5 py-2.5 font-medium">
                                                Campagne
                                            </th>
                                            <th className="px-5 py-2.5 font-medium">
                                                Annonceur
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
                                        {campaigns.map((campaign) => (
                                            <tr key={campaign.campaign_id}>
                                                <td className="px-5 py-3 font-medium text-[var(--text-primary)]">
                                                    {campaign.code}
                                                </td>
                                                <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                    {
                                                        campaign.advertiser_legal_name
                                                    }
                                                </td>
                                                <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                    {displayCampaignStatus(
                                                        campaign.state,
                                                        null,
                                                    )}
                                                </td>
                                                <td className="px-5 py-3 text-[var(--status-success)] tabular-nums">
                                                    {amountFormatter.format(
                                                        campaign.available,
                                                    )}{' '}
                                                    {campaign.currency}
                                                </td>
                                                <td className="px-5 py-3 text-[var(--status-warning)] tabular-nums">
                                                    {amountFormatter.format(
                                                        campaign.reserved,
                                                    )}{' '}
                                                    {campaign.currency}
                                                </td>
                                                <td className="px-5 py-3 text-[var(--text-secondary)] tabular-nums">
                                                    {amountFormatter.format(
                                                        campaign.consumed,
                                                    )}{' '}
                                                    {campaign.currency}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}
            </AdminAccessGate>
        </AdminLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    Landmark,
    ListTree,
    PiggyBank,
    ShieldCheck,
    Users,
    Video,
} from 'lucide-react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import { KpiCard } from '@/components/advertiser/kpi-card';
import AdminLayout from '@/layouts/admin-layout';
import {
    amountFormatter,
    displayCampaignStatus,
} from '@/lib/advertising-labels';
import admin from '@/routes/admin';

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

            <div className="mb-6 space-y-3">
                <Link
                    href={admin.walletDeposits().url}
                    className="flex items-center gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 transition-colors hover:border-[var(--status-warning)]/50"
                >
                    <AlertTriangle
                        size={20}
                        className="shrink-0 text-[var(--status-warning)]"
                    />
                    <div>
                        <p className="text-sm font-semibold text-[var(--text-primary)]">
                            Dépôts Wallet — supervision
                        </p>
                        <p className="text-xs text-[var(--text-secondary)]">
                            Dépôts GeniusPay en litige et webhooks à signature
                            invalide (wallet_deposit.review).
                        </p>
                    </div>
                </Link>
                <Link
                    href="/admin/wallet-deposits/credentials"
                    className="flex items-center gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 transition-colors hover:border-[var(--brand-blue)]/50"
                >
                    <ShieldCheck
                        size={20}
                        className="shrink-0 text-[var(--brand-blue)]"
                    />
                    <div>
                        <p className="text-sm font-semibold text-[var(--text-primary)]">
                            Dépôts Wallet — clés GeniusPay
                        </p>
                        <p className="text-xs text-[var(--text-secondary)]">
                            Configurer les identifiants du prestataire
                            (wallet_deposit.manage_credentials).
                        </p>
                    </div>
                </Link>
                <Link
                    href="/admin/campaign-fundings"
                    className="flex items-center gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 transition-colors hover:border-[var(--status-warning)]/50"
                >
                    <AlertTriangle
                        size={20}
                        className="shrink-0 text-[var(--status-warning)]"
                    />
                    <div>
                        <p className="text-sm font-semibold text-[var(--text-primary)]">
                            Financements de campagne — supervision
                        </p>
                        <p className="text-xs text-[var(--text-secondary)]">
                            Financements GeniusPay en litige et webhooks à
                            signature invalide (campaign_funding.review).
                        </p>
                    </div>
                </Link>
                <Link
                    href="/admin/interest-taxonomy"
                    className="flex items-center gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 transition-colors hover:border-[var(--brand-blue)]/50"
                >
                    <Users
                        size={20}
                        className="shrink-0 text-[var(--brand-blue)]"
                    />
                    <div>
                        <p className="text-sm font-semibold text-[var(--text-primary)]">
                            Centres d'intérêt — profil publicitaire
                        </p>
                        <p className="text-xs text-[var(--text-secondary)]">
                            Ajouter ou retirer une entrée de la référence
                            (advertising.manage_interest_taxonomy).
                        </p>
                    </div>
                </Link>
                <Link
                    href="/admin/video-duration-bounds"
                    className="flex items-center gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 transition-colors hover:border-[var(--brand-blue)]/50"
                >
                    <Video
                        size={20}
                        className="shrink-0 text-[var(--brand-blue)]"
                    />
                    <div>
                        <p className="text-sm font-semibold text-[var(--text-primary)]">
                            Durée des vidéos publicitaires
                        </p>
                        <p className="text-xs text-[var(--text-secondary)]">
                            Régler les bornes min/max en secondes
                            (advertising.manage_video_duration_bounds).
                        </p>
                    </div>
                </Link>
                <Link
                    href="/admin/sector-classifications"
                    className="flex items-center gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-5 py-4 transition-colors hover:border-[var(--brand-blue)]/50"
                >
                    <ListTree
                        size={20}
                        className="shrink-0 text-[var(--brand-blue)]"
                    />
                    <div>
                        <p className="text-sm font-semibold text-[var(--text-primary)]">
                            Secteurs publicitaires
                        </p>
                        <p className="text-xs text-[var(--text-secondary)]">
                            Publier une classification (pays, secteur) — âge
                            minimal, formats, niveau de revue
                            (advertising.manage_sector_classifications).
                        </p>
                    </div>
                </Link>
            </div>

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

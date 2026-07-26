import { Head, Link } from '@inertiajs/react';
import { Clock, Megaphone, PiggyBank, ShieldCheck } from 'lucide-react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import { KpiCard } from '@/components/advertiser/kpi-card';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import {
    amountFormatter,
    displayCampaignStatus,
} from '@/lib/advertising-labels';
import advertising from '@/routes/advertising';

type BudgetTotal = {
    currency: string;
    available: number;
    reserved: number;
    consumed: number;
};

type EventTotal = {
    billing_status: string;
    currency: string;
    event_count: number;
    amount_total: number;
};

type RecentCampaign = {
    id: string;
    code: string;
    currency: string;
    state: string;
    latest_version_state: string | null;
};

export default function AdvertisingOverview({
    access,
    advertiserProfile,
    campaignCounts,
    budgetTotals,
    eventTotals,
    recentCampaigns,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    campaignCounts: Record<string, number>;
    budgetTotals: BudgetTotal[];
    eventTotals: EventTotal[];
    recentCampaigns: RecentCampaign[];
}) {
    const activeCampaigns = campaignCounts.active ?? 0;
    const totalCampaigns = Object.values(campaignCounts).reduce(
        (sum, n) => sum + n,
        0,
    );
    const primaryBudget = budgetTotals[0] ?? null;
    const pending = eventTotals.filter(
        (row) => row.billing_status === 'pending',
    );
    const accepted = eventTotals.filter(
        (row) => row.billing_status === 'accepted',
    );
    const pendingCount = pending.reduce((sum, row) => sum + row.event_count, 0);
    const acceptedAmount = accepted.reduce(
        (sum, row) => sum + row.amount_total,
        0,
    );
    const acceptedCurrency =
        accepted[0]?.currency ?? primaryBudget?.currency ?? '';

    return (
        <AdvertiserLayout
            title="Vue d'ensemble"
            description="Panorama de vos campagnes, de votre budget et des résultats validés."
        >
            <Head title="Espace annonceur — Vue d'ensemble" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                {totalCampaigns === 0 ? (
                    <AdvertiserEmptyState
                        icon={Megaphone}
                        title="Aucune campagne pour le moment"
                        description="Créez votre première campagne pour la voir apparaître ici, avec son budget et ses résultats."
                        action={
                            <Link
                                href={advertising.campaigns.create().url}
                                className="rounded-lg bg-[var(--brand-blue)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90"
                            >
                                + Nouvelle campagne
                            </Link>
                        }
                    />
                ) : (
                    <div className="space-y-6">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <KpiCard
                                icon={Megaphone}
                                label="Campagnes actives"
                                value={`${activeCampaigns}`}
                                hint={`${totalCampaigns} au total`}
                            />
                            <KpiCard
                                icon={PiggyBank}
                                label="Budget disponible"
                                value={
                                    primaryBudget
                                        ? `${amountFormatter.format(primaryBudget.available)} ${primaryBudget.currency}`
                                        : '—'
                                }
                                hint="Reconstruit depuis le registre comptable"
                                tone="info"
                            />
                            <KpiCard
                                icon={Clock}
                                label="En attente de validation"
                                value={`${pendingCount}`}
                                hint="Publicités transmises, pas encore examinées"
                                tone="warning"
                            />
                            <KpiCard
                                icon={ShieldCheck}
                                label="Créditées"
                                value={
                                    acceptedAmount > 0
                                        ? `${amountFormatter.format(acceptedAmount)} ${acceptedCurrency}`
                                        : '0'
                                }
                                hint="Montant validé par Wasplex"
                                tone="success"
                            />
                        </div>

                        <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                            <div className="flex items-center justify-between border-b border-[var(--border-default)] px-5 py-3">
                                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                    Campagnes récentes
                                </h2>
                                <Link
                                    href={advertising.campaigns.index().url}
                                    className="text-xs font-medium text-[var(--brand-blue)] hover:underline"
                                >
                                    Voir toutes les campagnes
                                </Link>
                            </div>
                            <ul className="divide-y divide-[var(--border-default)]">
                                {recentCampaigns.map((campaign) => (
                                    <li
                                        key={campaign.id}
                                        className="flex items-center justify-between px-5 py-3"
                                    >
                                        <span className="text-sm font-medium text-[var(--text-primary)]">
                                            {campaign.code}
                                        </span>
                                        <span className="text-xs text-[var(--text-secondary)]">
                                            {displayCampaignStatus(
                                                campaign.state,
                                                campaign.latest_version_state,
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}
            </AdvertiserAccessGate>
        </AdvertiserLayout>
    );
}

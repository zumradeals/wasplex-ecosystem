import { Head } from '@inertiajs/react';
import { TrendingUp } from 'lucide-react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { amountFormatter } from '@/lib/advertising-labels';

type StatusAmount = { event_count: number; amount_total: number };

type CampaignReport = {
    campaign_id: string;
    campaign_code: string;
    currency: string;
    pending: StatusAmount;
    accepted: StatusAmount;
    rejected: StatusAmount;
};

export default function AdvertisingReports({
    access,
    advertiserProfile,
    campaignReports,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    campaignReports: CampaignReport[];
}) {
    const hasAnyEvent = campaignReports.some(
        (row) =>
            row.pending.event_count +
                row.accepted.event_count +
                row.rejected.event_count >
            0,
    );

    return (
        <AdvertiserLayout
            title="Rapports"
            description="Résultats agrégés par campagne : publicités qualifiées en attente, créditées ou refusées."
        >
            <Head title="Espace annonceur — Rapports" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                {!hasAnyEvent ? (
                    <AdvertiserEmptyState
                        icon={TrendingUp}
                        title="Aucun résultat pour le moment"
                        description="Dès qu'une publicité qualifiée est transmise sur l'une de vos campagnes, elle apparaît ici — en attente, créditée ou refusée."
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
                                        En attente de validation
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Créditées
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Refusées
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--border-default)]">
                                {campaignReports.map((row) => (
                                    <tr key={row.campaign_id}>
                                        <td className="px-5 py-3 font-medium text-[var(--text-primary)]">
                                            {row.campaign_code}
                                        </td>
                                        <td className="px-5 py-3">
                                            <p className="text-[var(--status-warning)] tabular-nums">
                                                {row.pending.event_count}{' '}
                                                publicité
                                                {row.pending.event_count > 1
                                                    ? 's'
                                                    : ''}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3">
                                            <p className="text-[var(--status-success)] tabular-nums">
                                                {row.accepted.event_count}{' '}
                                                publicité
                                                {row.accepted.event_count > 1
                                                    ? 's'
                                                    : ''}
                                            </p>
                                            <p className="text-xs text-[var(--text-secondary)]">
                                                {amountFormatter.format(
                                                    row.accepted.amount_total,
                                                )}{' '}
                                                {row.currency}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3">
                                            <p className="text-[var(--status-danger)] tabular-nums">
                                                {row.rejected.event_count}{' '}
                                                publicité
                                                {row.rejected.event_count > 1
                                                    ? 's'
                                                    : ''}
                                            </p>
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

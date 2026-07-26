import { Head } from '@inertiajs/react';
import { Radar } from 'lucide-react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { amountFormatter } from '@/lib/advertising-labels';

type AudienceRow = {
    campaign_id: string;
    campaign_code: string;
    campaign_version_id: string;
    criteria: Record<string, unknown>;
    estimated_size: number | null;
    below_threshold: boolean;
};

function formatCriteria(criteria: Record<string, unknown>): string {
    const parts = Object.entries(criteria).map(([key, value]) => {
        const label = key === 'country' ? 'Pays' : key;
        const displayValue = Array.isArray(value)
            ? value.join(', ')
            : String(value);

        return `${label} : ${displayValue}`;
    });

    return parts.length > 0 ? parts.join(' · ') : 'Aucun critère';
}

export default function AdvertisingAudiences({
    access,
    advertiserProfile,
    audiences,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    audiences: AudienceRow[];
}) {
    return (
        <AdvertiserLayout
            title="Audiences"
            description="Critères et taille estimée de chaque segment ciblé, jamais une identité individuelle (AMD-0009 §13)."
        >
            <Head title="Espace annonceur — Audiences" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                {audiences.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={Radar}
                        title="Aucune audience définie"
                        description="Chaque campagne créée définit un segment d'audience ; il apparaîtra ici avec sa taille estimée."
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
                                        Critères
                                    </th>
                                    <th className="px-5 py-2.5 font-medium">
                                        Taille estimée
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--border-default)]">
                                {audiences.map((row) => (
                                    <tr key={row.campaign_version_id}>
                                        <td className="px-5 py-3 font-medium text-[var(--text-primary)]">
                                            {row.campaign_code}
                                        </td>
                                        <td className="px-5 py-3 text-[var(--text-secondary)]">
                                            {formatCriteria(row.criteria)}
                                        </td>
                                        <td className="px-5 py-3">
                                            {row.estimated_size !== null ? (
                                                <span className="text-[var(--text-primary)] tabular-nums">
                                                    {amountFormatter.format(
                                                        row.estimated_size,
                                                    )}{' '}
                                                    profils
                                                </span>
                                            ) : (
                                                <span className="text-xs text-[var(--status-warning)]">
                                                    Non communiqué — sous le
                                                    seuil minimal
                                                </span>
                                            )}
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

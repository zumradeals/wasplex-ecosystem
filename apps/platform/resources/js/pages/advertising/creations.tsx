import { Head } from '@inertiajs/react';
import { Image as ImageIcon } from 'lucide-react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import { Badge } from '@/components/ui/badge';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import { FORMAT_LABELS, VERSION_STATE_LABELS } from '@/lib/advertising-labels';

type Creation = {
    campaign_id: string;
    campaign_code: string;
    campaign_version_id: string;
    version: number;
    state: string;
    headline: string | null;
    format: string | null;
    condition: string | null;
    destination_url: string | null;
    territory: string[];
    sector: {
        label: string;
        allowed_formats: string[];
        minimum_age: number | null;
        warnings: string[];
    };
};

export default function AdvertisingCreations({
    access,
    advertiserProfile,
    creations,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    creations: Creation[];
}) {
    return (
        <AdvertiserLayout
            title="Créations"
            description="Contenu publicitaire de chaque version de campagne. Le média réel (vidéo, image) arrive dans un lot ultérieur."
        >
            <Head title="Espace annonceur — Créations" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                {creations.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={ImageIcon}
                        title="Aucune création pour le moment"
                        description="Le titre, le format et la destination de chaque campagne apparaîtront ici."
                    />
                ) : (
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {creations.map((creation) => (
                            <article
                                key={creation.campaign_version_id}
                                className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-4"
                            >
                                <div className="mb-3 flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold text-[var(--text-primary)]">
                                            {creation.campaign_code}
                                        </p>
                                        <p className="text-xs text-[var(--text-secondary)]">
                                            Version {creation.version}
                                        </p>
                                    </div>
                                    <Badge variant="secondary">
                                        {VERSION_STATE_LABELS[creation.state] ??
                                            creation.state}
                                    </Badge>
                                </div>

                                <p className="mb-3 text-sm text-[var(--text-primary)]">
                                    {creation.headline ?? '—'}
                                </p>

                                <dl className="space-y-1.5 text-xs text-[var(--text-secondary)]">
                                    <div className="flex justify-between gap-2">
                                        <dt>Format</dt>
                                        <dd className="text-[var(--text-primary)]">
                                            {creation.format
                                                ? (FORMAT_LABELS[
                                                      creation.format
                                                  ] ?? creation.format)
                                                : '—'}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt>Condition</dt>
                                        <dd className="text-[var(--text-primary)]">
                                            {creation.condition ?? '—'}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt>Territoires</dt>
                                        <dd className="text-[var(--text-primary)]">
                                            {creation.territory.join(', ')}
                                        </dd>
                                    </div>
                                    <div className="flex justify-between gap-2">
                                        <dt>Secteur</dt>
                                        <dd className="text-right text-[var(--text-primary)]">
                                            {creation.sector.label}
                                        </dd>
                                    </div>
                                </dl>

                                {creation.destination_url && (
                                    <a
                                        href={creation.destination_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="mt-3 block truncate text-xs text-[var(--brand-blue)] hover:underline"
                                    >
                                        {creation.destination_url}
                                    </a>
                                )}

                                {creation.sector.warnings.length > 0 && (
                                    <p className="mt-3 text-xs text-[var(--status-warning)]">
                                        {creation.sector.warnings.join(' · ')}
                                    </p>
                                )}
                            </article>
                        ))}
                    </div>
                )}
            </AdvertiserAccessGate>
        </AdvertiserLayout>
    );
}

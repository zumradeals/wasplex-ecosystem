import { Head, Link, router } from '@inertiajs/react';
import { Megaphone } from 'lucide-react';
import { useState } from 'react';
import { AdvertiserAccessGate } from '@/components/advertiser/advertiser-access-gate';
import type {
    AdvertiserAccess,
    AdvertiserProfileSummary,
} from '@/components/advertiser/advertiser-access-gate';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import { Badge } from '@/components/ui/badge';
import AdvertiserLayout from '@/layouts/advertiser-layout';
import {
    amountFormatter,
    displayCampaignStatus,
} from '@/lib/advertising-labels';
import { postJson } from '@/lib/api';
import advertising from '@/routes/advertising';
import campaignVersions from '@/routes/advertising/campaign-versions';

type Campaign = {
    id: string;
    code: string;
    currency: string;
    state: string;
    latest_version_id: string | null;
    latest_version_state: string | null;
    headline: string | null;
    created_at: string;
    budget: { available: number; reserved: number; consumed: number };
    events: { pending: number; accepted: number; rejected: number };
};

const dateFormatter = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' });

function SubmitForReviewButton({
    campaignVersionId,
}: {
    campaignVersionId: string;
}) {
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function handleClick() {
        setSubmitting(true);
        setError(null);

        const result = await postJson(
            campaignVersions.submitForReview.url(campaignVersionId),
            {},
        );

        setSubmitting(false);

        if (!result.ok) {
            setError("La soumission n'a pas abouti.");

            return;
        }

        router.reload();
    }

    return (
        <div className="text-right">
            {error && (
                <p className="mb-1 text-xs text-[var(--status-danger)]">
                    {error}
                </p>
            )}
            <button
                type="button"
                onClick={handleClick}
                disabled={submitting}
                className="rounded-lg border border-[var(--border-default)] px-3 py-1.5 text-xs font-semibold text-[var(--text-primary)] hover:bg-[var(--bg-raised)] disabled:opacity-50"
            >
                {submitting ? 'Envoi...' : 'Soumettre pour revue'}
            </button>
        </div>
    );
}

export default function AdvertisingCampaigns({
    access,
    advertiserProfile,
    campaigns,
}: {
    access: AdvertiserAccess;
    advertiserProfile: AdvertiserProfileSummary | null;
    campaigns: Campaign[];
}) {
    return (
        <AdvertiserLayout
            title="Campagnes"
            description="Toutes vos campagnes, leur budget et l'état de leurs publicités qualifiées."
        >
            <Head title="Espace annonceur — Campagnes" />

            <AdvertiserAccessGate
                access={access}
                advertiserProfile={advertiserProfile}
            >
                {campaigns.length === 0 ? (
                    <AdvertiserEmptyState
                        icon={Megaphone}
                        title="Aucune campagne pour le moment"
                        description="Créez votre première campagne : elle démarre à l'état brouillon, sans diffusion ni budget engagé."
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
                    <div className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                        <div className="flex items-center justify-between border-b border-[var(--border-default)] px-5 py-3">
                            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                {campaigns.length} campagne
                                {campaigns.length > 1 ? 's' : ''}
                            </h2>
                            <Link
                                href={advertising.campaigns.create().url}
                                className="rounded-lg bg-[var(--brand-blue)] px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90"
                            >
                                + Nouvelle campagne
                            </Link>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[900px] text-left text-sm">
                                <thead>
                                    <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                        <th className="px-5 py-2.5 font-medium">
                                            Campagne
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Statut
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Budget disponible
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Réservé
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Consommé
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Publicités
                                        </th>
                                        <th className="px-5 py-2.5 font-medium">
                                            Créée le
                                        </th>
                                        <th className="px-5 py-2.5" />
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--border-default)]">
                                    {campaigns.map((campaign) => (
                                        <tr key={campaign.id}>
                                            <td className="px-5 py-3">
                                                <p className="font-medium text-[var(--text-primary)]">
                                                    {campaign.code}
                                                </p>
                                                {campaign.headline && (
                                                    <p className="text-xs text-[var(--text-secondary)]">
                                                        {campaign.headline}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-5 py-3">
                                                <Badge variant="secondary">
                                                    {displayCampaignStatus(
                                                        campaign.state,
                                                        campaign.latest_version_state,
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="px-5 py-3 text-[var(--text-primary)] tabular-nums">
                                                {amountFormatter.format(
                                                    campaign.budget.available,
                                                )}{' '}
                                                {campaign.currency}
                                            </td>
                                            <td className="px-5 py-3 text-[var(--text-secondary)] tabular-nums">
                                                {amountFormatter.format(
                                                    campaign.budget.reserved,
                                                )}{' '}
                                                {campaign.currency}
                                            </td>
                                            <td className="px-5 py-3 text-[var(--text-secondary)] tabular-nums">
                                                {amountFormatter.format(
                                                    campaign.budget.consumed,
                                                )}{' '}
                                                {campaign.currency}
                                            </td>
                                            <td className="px-5 py-3">
                                                <div className="flex gap-1.5 text-xs">
                                                    <span className="rounded-md bg-[var(--status-warning)]/10 px-1.5 py-0.5 text-[var(--status-warning)]">
                                                        {
                                                            campaign.events
                                                                .pending
                                                        }{' '}
                                                        en attente
                                                    </span>
                                                    <span className="rounded-md bg-[var(--status-success)]/10 px-1.5 py-0.5 text-[var(--status-success)]">
                                                        {
                                                            campaign.events
                                                                .accepted
                                                        }{' '}
                                                        créditées
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap text-[var(--text-secondary)]">
                                                {dateFormatter.format(
                                                    new Date(
                                                        campaign.created_at,
                                                    ),
                                                )}
                                            </td>
                                            <td className="px-5 py-3">
                                                {campaign.latest_version_state ===
                                                    'draft' &&
                                                    campaign.latest_version_id && (
                                                        <SubmitForReviewButton
                                                            campaignVersionId={
                                                                campaign.latest_version_id
                                                            }
                                                        />
                                                    )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </AdvertiserAccessGate>
        </AdvertiserLayout>
    );
}

import { Head, router } from '@inertiajs/react';
import { Megaphone, ShieldAlert } from 'lucide-react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { ModerationCaseDecisionForm } from '@/components/admin/moderation-case-decision-form';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import { Badge } from '@/components/ui/badge';
import AdminLayout from '@/layouts/admin-layout';
import {
    displayCampaignStatus,
    VERSION_STATE_LABELS,
} from '@/lib/advertising-labels';

type CampaignRow = {
    campaign_id: string;
    code: string;
    state: string;
    advertiser_legal_name: string;
    latest_version_state: string | null;
    headline: string | null;
};

type ModerationCaseRow = {
    moderation_case_id: string;
    campaign_id: string;
    campaign_code: string;
    advertiser_legal_name: string;
    reason: string;
    severity: string;
    status: string;
    precautionary_measure: string;
    decision: string | null;
    observed_destination: string | null;
    opened_at: string;
};

const dateFormatter = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' });

const DECISION_LABELS: Record<string, string> = {
    violation_confirmed: 'Violation confirmée',
    no_violation_found: 'Aucune violation constatée',
};

const MEASURE_LABELS: Record<string, string> = {
    none: 'Aucune',
    limited_diffusion: 'Diffusion limitée',
    campaign_suspended: 'Campagne suspendue',
    destination_blocked: 'Destination bloquée',
    advertiser_blocked: 'Annonceur bloqué',
};

export default function AdminAdvertisingModeration({
    access,
    campaigns,
    moderationCases,
}: {
    access: AdminAccess;
    campaigns: CampaignRow[];
    moderationCases: ModerationCaseRow[];
}) {
    const openCases = moderationCases.filter((item) => item.status === 'open');
    const resolvedCases = moderationCases.filter(
        (item) => item.status !== 'open',
    );

    return (
        <AdminLayout
            title="Publicité et modération"
            description="Toutes les campagnes et l'historique complet des dossiers de modération."
        >
            <Head title="Personnel — Publicité et modération" />

            <AdminAccessGate access={access}>
                <div className="space-y-6">
                    <section className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                        <div className="border-b border-[var(--border-default)] px-5 py-3">
                            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                Signalements ouverts
                            </h2>
                        </div>
                        <div className="p-5">
                            {openCases.length === 0 ? (
                                <p className="text-sm text-[var(--text-secondary)]">
                                    Aucun signalement ouvert.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {openCases.map((item) => (
                                        <div
                                            key={item.moderation_case_id}
                                            className="space-y-3 rounded-lg border border-[var(--border-default)] p-4"
                                        >
                                            <div>
                                                <p className="font-medium text-[var(--text-primary)]">
                                                    {item.campaign_code} —{' '}
                                                    {item.advertiser_legal_name}
                                                </p>
                                                <p className="text-sm text-[var(--text-secondary)]">
                                                    {item.reason} · gravité :{' '}
                                                    {item.severity}
                                                </p>
                                            </div>
                                            <ModerationCaseDecisionForm
                                                moderationCaseId={
                                                    item.moderation_case_id
                                                }
                                                onDecided={() =>
                                                    router.reload({
                                                        only: [
                                                            'moderationCases',
                                                        ],
                                                    })
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                        <div className="border-b border-[var(--border-default)] px-5 py-3">
                            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                Historique des dossiers
                            </h2>
                        </div>
                        {resolvedCases.length === 0 ? (
                            <div className="p-5">
                                <AdvertiserEmptyState
                                    icon={ShieldAlert}
                                    title="Aucun dossier résolu"
                                    description="L'historique des décisions de modération apparaîtra ici."
                                />
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[800px] text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-[var(--border-default)] text-xs text-[var(--text-secondary)]">
                                            <th className="px-5 py-2.5 font-medium">
                                                Campagne
                                            </th>
                                            <th className="px-5 py-2.5 font-medium">
                                                Motif
                                            </th>
                                            <th className="px-5 py-2.5 font-medium">
                                                Décision
                                            </th>
                                            <th className="px-5 py-2.5 font-medium">
                                                Mesure conservatoire
                                            </th>
                                            <th className="px-5 py-2.5 font-medium">
                                                Ouvert le
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--border-default)]">
                                        {resolvedCases.map((item) => (
                                            <tr key={item.moderation_case_id}>
                                                <td className="px-5 py-3 font-medium text-[var(--text-primary)]">
                                                    {item.campaign_code}
                                                    <p className="text-xs font-normal text-[var(--text-secondary)]">
                                                        {
                                                            item.advertiser_legal_name
                                                        }
                                                    </p>
                                                </td>
                                                <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                    {item.reason}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <Badge
                                                        variant={
                                                            item.decision ===
                                                            'violation_confirmed'
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {DECISION_LABELS[
                                                            item.decision ?? ''
                                                        ] ?? '—'}
                                                    </Badge>
                                                </td>
                                                <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                    {MEASURE_LABELS[
                                                        item
                                                            .precautionary_measure
                                                    ] ??
                                                        item.precautionary_measure}
                                                </td>
                                                <td className="px-5 py-3 whitespace-nowrap text-[var(--text-secondary)]">
                                                    {dateFormatter.format(
                                                        new Date(
                                                            item.opened_at,
                                                        ),
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <section className="overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
                        <div className="border-b border-[var(--border-default)] px-5 py-3">
                            <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                                Toutes les campagnes
                            </h2>
                        </div>
                        {campaigns.length === 0 ? (
                            <div className="p-5">
                                <AdvertiserEmptyState
                                    icon={Megaphone}
                                    title="Aucune campagne"
                                    description="Les campagnes déclarées par les annonceurs apparaîtront ici."
                                />
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[700px] text-left text-sm">
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
                                                Dernière version
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--border-default)]">
                                        {campaigns.map((campaign) => (
                                            <tr key={campaign.campaign_id}>
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
                                                <td className="px-5 py-3 text-[var(--text-secondary)]">
                                                    {campaign.latest_version_state
                                                        ? (VERSION_STATE_LABELS[
                                                              campaign
                                                                  .latest_version_state
                                                          ] ??
                                                          campaign.latest_version_state)
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </AdminAccessGate>
        </AdminLayout>
    );
}

import { Head, router } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { ModerationCaseDecisionForm } from '@/components/admin/moderation-case-decision-form';
import { AdvertiserEmptyState } from '@/components/advertiser/empty-state';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';
import campaignVersions from '@/routes/advertising/campaign-versions';
import funding from '@/routes/advertising/campaigns/funding';
import qualifiedEvents from '@/routes/advertising/qualified-events';

const amountFormatter = new Intl.NumberFormat('fr-FR');

type Section<T> = {
    access: AdminAccess;
    items: T[];
};

type CampaignApprovalItem = {
    campaign_version_id: string;
    campaign_id: string;
    campaign_code: string;
    advertiser_legal_name: string;
    headline: string;
    submitted_at: string;
};

type CampaignFundingItem = {
    campaign_id: string;
    code: string;
    currency: string;
    advertiser_legal_name: string;
    available: number;
};

type QualifiedEventItem = {
    qualified_event_id: string;
    campaign_code: string;
    headline: string;
    format: string;
    reward_amount: number;
    currency: string;
    submitted_at: string;
};

type ModerationCaseItem = {
    moderation_case_id: string;
    campaign_id: string;
    campaign_code: string;
    advertiser_legal_name: string;
    reason: string;
    severity: string;
    observed_destination: string | null;
    opened_at: string;
};

// `campaign.fund` et `event.accept` sont `risk_class = critical` : leur
// activation exige une session « strong » (step-up mot de passe), en plus
// d'un grant actif — voir CampaignFundingRouteTest/QualifiedEventLifecycle-
// RouteTest. Un refus pour ce motif précis n'est pas un manque de droit :
// c'est une session à renforcer, jamais silencieusement contourné.
function StepUpNotice() {
    return (
        <div className="rounded-lg border border-[var(--status-danger)]/30 bg-[var(--status-danger)]/10 px-4 py-3 text-sm text-[var(--status-danger)]">
            <p className="font-semibold">
                Confirmation de mot de passe requise
            </p>
            <p className="mt-1">
                Cette action exige une session renforcée. Confirmez votre mot de
                passe puis réessayez.{' '}
                <a
                    href="/user/confirm-password"
                    className="underline underline-offset-2"
                >
                    Confirmer mon mot de passe
                </a>
            </p>
        </div>
    );
}

function SectionCard({
    title,
    description,
    section,
    emptyLabel,
    children,
}: {
    title: string;
    description: string;
    section: Section<unknown>;
    emptyLabel: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]">
            <div className="border-b border-[var(--border-default)] px-5 py-4">
                <h2 className="text-sm font-semibold text-[var(--text-primary)]">
                    {title}
                </h2>
                <p className="mt-0.5 text-xs text-[var(--text-secondary)]">
                    {description}
                </p>
            </div>
            <div className="p-5">
                <AdminAccessGate access={section.access}>
                    {section.items.length === 0 ? (
                        <p className="text-sm text-[var(--text-secondary)]">
                            {emptyLabel}
                        </p>
                    ) : (
                        <div className="space-y-3">{children}</div>
                    )}
                </AdminAccessGate>
            </div>
        </section>
    );
}

function CampaignApprovalSection({
    section,
}: {
    section: Section<CampaignApprovalItem>;
}) {
    const [error, setError] = useState<string | null>(null);
    const [pendingId, setPendingId] = useState<string | null>(null);

    async function approve(item: CampaignApprovalItem) {
        setPendingId(item.campaign_version_id);
        setError(null);

        const result = await postJson(
            campaignVersions.approve.url(item.campaign_version_id),
            {},
        );

        setPendingId(null);

        if (!result.ok) {
            setError(
                "L'approbation n'a pas abouti (l'auteur d'une version ne peut pas l'approuver lui-même).",
            );

            return;
        }

        router.reload({ only: ['campaignApproval'] });
    }

    return (
        <SectionCard
            title="Campagnes en revue"
            description="Versions soumises par leur auteur, en attente d'approbation."
            section={section}
            emptyLabel="Aucune campagne en attente de revue."
        >
            {error && (
                <p className="text-sm text-[var(--status-danger)]">{error}</p>
            )}
            {section.items.map((item) => (
                <div
                    key={item.campaign_version_id}
                    className="flex items-center justify-between rounded-lg border border-[var(--border-default)] p-4"
                >
                    <div className="min-w-0">
                        <p className="truncate font-medium text-[var(--text-primary)]">
                            {item.headline}
                        </p>
                        <p className="truncate text-sm text-[var(--text-secondary)]">
                            {item.campaign_code} — {item.advertiser_legal_name}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={() => approve(item)}
                        disabled={pendingId === item.campaign_version_id}
                        className="shrink-0 rounded-lg bg-[var(--brand-blue)] px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                    >
                        {pendingId === item.campaign_version_id
                            ? 'Approbation...'
                            : 'Approuver'}
                    </button>
                </div>
            ))}
        </SectionCard>
    );
}

function FundCampaignForm({
    item,
    onFunded,
}: {
    item: CampaignFundingItem;
    onFunded: () => void;
}) {
    const [amount, setAmount] = useState(10_000);
    const [reference, setReference] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<'step_up' | 'other' | null>(null);

    async function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<{ reason?: string }>(
            funding.store.url(item.campaign_id),
            {
                amount,
                funding_reference:
                    reference || `demo-${item.campaign_id}-${Date.now()}`,
            },
        );

        setSubmitting(false);

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setError(
                data?.reason === 'session_assurance_insufficient'
                    ? 'step_up'
                    : 'other',
            );

            return;
        }

        onFunded();
    }

    return (
        <form
            onSubmit={handleSubmit}
            className="flex flex-wrap items-end gap-2"
        >
            {error === 'step_up' && <StepUpNotice />}
            {error === 'other' && (
                <p className="w-full text-sm text-[var(--status-danger)]">
                    Le financement n'a pas abouti.
                </p>
            )}

            <label className="space-y-1">
                <span className="block text-xs font-medium text-[var(--text-primary)]">
                    Montant
                </span>
                <input
                    type="number"
                    min={1}
                    value={amount}
                    onChange={(event) => setAmount(Number(event.target.value))}
                    className="w-32 rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                />
            </label>

            <label className="space-y-1">
                <span className="block text-xs font-medium text-[var(--text-primary)]">
                    Référence (optionnelle, démo)
                </span>
                <input
                    value={reference}
                    onChange={(event) => setReference(event.target.value)}
                    placeholder="virement-demo-001"
                    className="w-48 rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                />
            </label>

            <button
                type="submit"
                disabled={submitting}
                className="rounded-lg bg-[var(--brand-blue)] px-3 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
            >
                {submitting ? 'Financement...' : 'Financer'}
            </button>
        </form>
    );
}

function CampaignFundingSection({
    section,
}: {
    section: Section<CampaignFundingItem>;
}) {
    return (
        <SectionCard
            title="Financement des campagnes"
            description="Enregistre un encaissement confirmé — démo : aucune passerelle de paiement réelle n'est encore branchée."
            section={section}
            emptyLabel="Aucune campagne à financer."
        >
            {section.items.map((item) => (
                <div
                    key={item.campaign_id}
                    className="space-y-3 rounded-lg border border-[var(--border-default)] p-4"
                >
                    <div>
                        <p className="font-medium text-[var(--text-primary)]">
                            {item.code}
                        </p>
                        <p className="text-sm text-[var(--text-secondary)]">
                            {item.advertiser_legal_name} — disponible :{' '}
                            {amountFormatter.format(item.available)}{' '}
                            {item.currency}
                        </p>
                    </div>
                    <FundCampaignForm
                        item={item}
                        onFunded={() =>
                            router.reload({ only: ['campaignFunding'] })
                        }
                    />
                </div>
            ))}
        </SectionCard>
    );
}

function QualifiedEventsSection({
    section,
}: {
    section: Section<QualifiedEventItem>;
}) {
    const [pendingId, setPendingId] = useState<string | null>(null);
    const [error, setError] = useState<Record<string, 'step_up' | 'other'>>({});

    async function decide(
        item: QualifiedEventItem,
        decision: 'accept' | 'reject',
    ) {
        setPendingId(item.qualified_event_id);
        setError((prev) => {
            const next = { ...prev };
            delete next[item.qualified_event_id];

            return next;
        });

        const result =
            decision === 'accept'
                ? await postJson<{ reason?: string }>(
                      qualifiedEvents.accept.url(item.qualified_event_id),
                      {},
                  )
                : await postJson<{ reason?: string }>(
                      qualifiedEvents.reject.url(item.qualified_event_id),
                      {
                          reason: 'moderation_dashboard_reject',
                      },
                  );

        setPendingId(null);

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setError((prev) => ({
                ...prev,
                [item.qualified_event_id]:
                    data?.reason === 'session_assurance_insufficient'
                        ? 'step_up'
                        : 'other',
            }));

            return;
        }

        router.reload({ only: ['qualifiedEvents'] });
    }

    return (
        <SectionCard
            title="Événements qualifiés en attente"
            description="Preuves d'attention soumises, en attente de décision — c'est ce qui crédite réellement le Wallet."
            section={section}
            emptyLabel="Aucun événement en attente."
        >
            {section.items.map((item) => (
                <div
                    key={item.qualified_event_id}
                    className="space-y-2 rounded-lg border border-[var(--border-default)] p-4"
                >
                    {error[item.qualified_event_id] === 'step_up' && (
                        <StepUpNotice />
                    )}
                    {error[item.qualified_event_id] === 'other' && (
                        <p className="text-sm text-[var(--status-danger)]">
                            La décision n'a pas abouti.
                        </p>
                    )}

                    <div className="flex items-center justify-between gap-3">
                        <div className="min-w-0">
                            <p className="truncate font-medium text-[var(--text-primary)]">
                                {item.headline}
                            </p>
                            <p className="truncate text-sm text-[var(--text-secondary)]">
                                {item.campaign_code} —{' '}
                                {amountFormatter.format(item.reward_amount)}{' '}
                                {item.currency}
                            </p>
                        </div>
                        <div className="flex shrink-0 gap-2">
                            <button
                                type="button"
                                onClick={() => decide(item, 'reject')}
                                disabled={pendingId === item.qualified_event_id}
                                className="rounded-lg border border-[var(--border-default)] px-3 py-1.5 text-sm font-medium text-[var(--text-primary)] hover:bg-[var(--bg-raised)] disabled:opacity-50"
                            >
                                Refuser
                            </button>
                            <button
                                type="button"
                                onClick={() => decide(item, 'accept')}
                                disabled={pendingId === item.qualified_event_id}
                                className="rounded-lg bg-[var(--brand-blue)] px-3 py-1.5 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                            >
                                Accepter
                            </button>
                        </div>
                    </div>
                </div>
            ))}
        </SectionCard>
    );
}

function ModerationCasesSection({
    section,
}: {
    section: Section<ModerationCaseItem>;
}) {
    return (
        <SectionCard
            title="Signalements ouverts"
            description="Dossiers de modération ouverts par signalement — un signalement seul ne prouve jamais la violation."
            section={section}
            emptyLabel="Aucun signalement ouvert."
        >
            {section.items.map((item) => (
                <div
                    key={item.moderation_case_id}
                    className="space-y-3 rounded-lg border border-[var(--border-default)] p-4"
                >
                    <div>
                        <p className="font-medium text-[var(--text-primary)]">
                            {item.campaign_code} — {item.advertiser_legal_name}
                        </p>
                        <p className="text-sm text-[var(--text-secondary)]">
                            {item.reason} · gravité : {item.severity}
                        </p>
                        {item.observed_destination && (
                            <p className="text-xs text-[var(--text-secondary)]">
                                Destination observée :{' '}
                                {item.observed_destination}
                            </p>
                        )}
                    </div>
                    <ModerationCaseDecisionForm
                        moderationCaseId={item.moderation_case_id}
                        onDecided={() =>
                            router.reload({ only: ['moderationCases'] })
                        }
                    />
                </div>
            ))}
        </SectionCard>
    );
}

export default function ModerationOverview({
    campaignApproval,
    campaignFunding,
    qualifiedEvents: qualifiedEventsSection,
    moderationCases,
}: {
    campaignApproval: Section<CampaignApprovalItem>;
    campaignFunding: Section<CampaignFundingItem>;
    qualifiedEvents: Section<QualifiedEventItem>;
    moderationCases: Section<ModerationCaseItem>;
}) {
    const totalPending =
        campaignApproval.items.length +
        campaignFunding.items.length +
        qualifiedEventsSection.items.length +
        moderationCases.items.length;

    return (
        <AdminLayout
            title="À traiter"
            description="Tout ce qui attend une décision de votre part, selon vos droits."
        >
            <Head title="Personnel — À traiter" />

            {totalPending === 0 &&
            campaignApproval.access.allowed &&
            campaignFunding.access.allowed &&
            qualifiedEventsSection.access.allowed &&
            moderationCases.access.allowed ? (
                <AdvertiserEmptyState
                    icon={CheckCircle2}
                    title="Tout est traité"
                    description="Aucune action en attente sur les files auxquelles vous avez accès."
                />
            ) : (
                <div className="space-y-6">
                    <CampaignApprovalSection section={campaignApproval} />
                    <CampaignFundingSection section={campaignFunding} />
                    <QualifiedEventsSection section={qualifiedEventsSection} />
                    <ModerationCasesSection section={moderationCases} />
                </div>
            )}
        </AdminLayout>
    );
}

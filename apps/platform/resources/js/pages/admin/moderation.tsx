import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/api';
import admin from '@/routes/admin';
import campaignVersions from '@/routes/advertising/campaign-versions';
import funding from '@/routes/advertising/campaigns/funding';
import qualifiedEvents from '@/routes/advertising/qualified-events';
import type { BreadcrumbItem } from '@/types';

type Access = {
    allowed: boolean;
    reason: string | null;
};

type Section<T> = {
    access: Access;
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

const amountFormatter = new Intl.NumberFormat('fr-FR');

// Même discipline que les autres écrans (wallet/overview.tsx,
// advertising/overview.tsx) : jamais le motif technique brut d'un refus
// d'autorisation (ADR-0004 §"décision explicable").
const ACCESS_DENIED_MESSAGES: Record<string, string> = {
    no_active_grant: 'Vous ne détenez pas ce droit sur ce compte.',
    subject_not_resolved:
        "Votre session n'a pas pu être confirmée. Reconnectez-vous pour réessayer.",
};

function AccessDenied({ access }: { access: Access }) {
    return (
        <Alert>
            <AlertTitle>Section indisponible</AlertTitle>
            <AlertDescription>
                {ACCESS_DENIED_MESSAGES[access.reason ?? ''] ??
                    "Cette section n'est pas disponible pour votre compte."}
            </AlertDescription>
        </Alert>
    );
}

// `campaign.fund` et `event.accept` sont `risk_class = critical` : leur
// activation exige une session « strong » (step-up mot de passe), en plus
// d'un grant actif — voir CampaignFundingRouteTest/QualifiedEventLifecycle-
// RouteTest. Un refus pour ce motif précis n'est pas un manque de droit :
// c'est une session à renforcer, jamais silencieusement contourné.
function StepUpNotice() {
    return (
        <Alert variant="destructive">
            <AlertTitle>Confirmation de mot de passe requise</AlertTitle>
            <AlertDescription>
                Cette action exige une session renforcée. Confirmez votre mot de
                passe puis réessayez.{' '}
                <a
                    href="/user/confirm-password"
                    className="underline underline-offset-2"
                >
                    Confirmer mon mot de passe
                </a>
            </AlertDescription>
        </Alert>
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
        <Card>
            <CardHeader>
                <CardTitle>Campagnes en revue</CardTitle>
                <CardDescription>
                    Versions soumises par leur auteur, en attente d'approbation.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {!section.access.allowed && (
                    <AccessDenied access={section.access} />
                )}

                {section.access.allowed && error && (
                    <Alert variant="destructive">
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {section.access.allowed && section.items.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Aucune campagne en attente de revue.
                    </p>
                )}

                {section.access.allowed &&
                    section.items.map((item) => (
                        <div
                            key={item.campaign_version_id}
                            className="flex items-center justify-between rounded-lg border p-4"
                        >
                            <div>
                                <p className="font-medium">{item.headline}</p>
                                <p className="text-sm text-muted-foreground">
                                    {item.campaign_code} —{' '}
                                    {item.advertiser_legal_name}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                onClick={() => approve(item)}
                                disabled={
                                    pendingId === item.campaign_version_id
                                }
                            >
                                {pendingId === item.campaign_version_id
                                    ? 'Approbation...'
                                    : 'Approuver'}
                            </Button>
                        </div>
                    ))}
            </CardContent>
        </Card>
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
                <p className="w-full text-sm text-destructive">
                    Le financement n'a pas abouti.
                </p>
            )}

            <div className="space-y-1">
                <Label htmlFor={`amount-${item.campaign_id}`}>Montant</Label>
                <Input
                    id={`amount-${item.campaign_id}`}
                    type="number"
                    min={1}
                    className="w-32"
                    value={amount}
                    onChange={(event) => setAmount(Number(event.target.value))}
                />
            </div>

            <div className="space-y-1">
                <Label htmlFor={`ref-${item.campaign_id}`}>
                    Référence (optionnelle, démo)
                </Label>
                <Input
                    id={`ref-${item.campaign_id}`}
                    className="w-48"
                    value={reference}
                    onChange={(event) => setReference(event.target.value)}
                    placeholder="virement-demo-001"
                />
            </div>

            <Button type="submit" size="sm" disabled={submitting}>
                {submitting ? 'Financement...' : 'Financer'}
            </Button>
        </form>
    );
}

function CampaignFundingSection({
    section,
}: {
    section: Section<CampaignFundingItem>;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Financement des campagnes</CardTitle>
                <CardDescription>
                    Enregistre un encaissement confirmé — démo : aucune
                    passerelle de paiement réelle n'est encore branchée.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {!section.access.allowed && (
                    <AccessDenied access={section.access} />
                )}

                {section.access.allowed && section.items.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Aucune campagne à financer.
                    </p>
                )}

                {section.access.allowed &&
                    section.items.map((item) => (
                        <div
                            key={item.campaign_id}
                            className="space-y-3 rounded-lg border p-4"
                        >
                            <div>
                                <p className="font-medium">{item.code}</p>
                                <p className="text-sm text-muted-foreground">
                                    {item.advertiser_legal_name} — disponible :{' '}
                                    {amountFormatter.format(item.available)}{' '}
                                    {item.currency}
                                </p>
                            </div>
                            <FundCampaignForm
                                item={item}
                                onFunded={() =>
                                    router.reload({
                                        only: ['campaignFunding'],
                                    })
                                }
                            />
                        </div>
                    ))}
            </CardContent>
        </Card>
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
                      { reason: 'moderation_dashboard_reject' },
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
        <Card>
            <CardHeader>
                <CardTitle>Événements qualifiés en attente</CardTitle>
                <CardDescription>
                    Preuves d'attention soumises, en attente de décision — c'est
                    ce qui crédite réellement le Wallet.
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {!section.access.allowed && (
                    <AccessDenied access={section.access} />
                )}

                {section.access.allowed && section.items.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Aucun événement en attente.
                    </p>
                )}

                {section.access.allowed &&
                    section.items.map((item) => (
                        <div
                            key={item.qualified_event_id}
                            className="space-y-2 rounded-lg border p-4"
                        >
                            {error[item.qualified_event_id] === 'step_up' && (
                                <StepUpNotice />
                            )}
                            {error[item.qualified_event_id] === 'other' && (
                                <p className="text-sm text-destructive">
                                    La décision n'a pas abouti.
                                </p>
                            )}

                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">
                                        {item.headline}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {item.campaign_code} —{' '}
                                        {amountFormatter.format(
                                            item.reward_amount,
                                        )}{' '}
                                        {item.currency}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => decide(item, 'reject')}
                                        disabled={
                                            pendingId ===
                                            item.qualified_event_id
                                        }
                                    >
                                        Refuser
                                    </Button>
                                    <Button
                                        size="sm"
                                        onClick={() => decide(item, 'accept')}
                                        disabled={
                                            pendingId ===
                                            item.qualified_event_id
                                        }
                                    >
                                        Accepter
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}
            </CardContent>
        </Card>
    );
}

export default function ModerationOverview({
    campaignApproval,
    campaignFunding,
    qualifiedEvents: qualifiedEventsSection,
}: {
    campaignApproval: Section<CampaignApprovalItem>;
    campaignFunding: Section<CampaignFundingItem>;
    qualifiedEvents: Section<QualifiedEventItem>;
}) {
    return (
        <>
            <Head title="Modération" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Modération Wasplex"
                    description="Les trois files qui ferment la boucle de gain : approbation, financement, acceptation/refus des preuves d'attention."
                />

                <CampaignApprovalSection section={campaignApproval} />
                <CampaignFundingSection section={campaignFunding} />
                <QualifiedEventsSection section={qualifiedEventsSection} />
            </div>
        </>
    );
}

ModerationOverview.layout = {
    breadcrumbs: [
        { title: 'Modération', href: admin.moderation() },
    ] satisfies BreadcrumbItem[],
};

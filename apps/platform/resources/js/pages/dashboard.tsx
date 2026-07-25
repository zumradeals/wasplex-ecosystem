import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import QualifiedEventSelfSubmissionController from '@/actions/App/Modules/Advertising/Http/Controllers/QualifiedEventSelfSubmissionController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { postJson } from '@/lib/api';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type Ad = {
    campaign_version_id: string;
    headline: string;
    format: string;
    condition: string;
    reward_amount: number;
    currency: string;
};

type WatchState =
    | { status: 'idle' }
    | { status: 'watching'; progress: number }
    | { status: 'submitting' }
    | { status: 'submitted'; billingStatus: string }
    | { status: 'denied'; reason: string };

// Aucune vraie lecture vidéo/média n'existe encore (stockage objet et CDN
// hors périmètre, W5) : la barre de progression simule ici la condition
// de « durée minimale » (Constitution art. 3 §1-§4) sur une durée fixe de
// démonstration, jamais un vrai lecteur.
const WATCH_DURATION_MS = 6_000;
const PROGRESS_TICK_MS = 100;

// Wasplex ne présente jamais un motif de refus interne (grant, politique,
// définition de capacité) : seul un texte destiné à la personne (même
// discipline que wallet/overview.tsx et advertising/overview.tsx).
const DENIAL_MESSAGES: Record<string, string> = {
    campaign_version_not_approved: "Cette publicité n'est plus disponible.",
    pricing_not_resolvable:
        'Cette publicité ne peut pas être créditée pour le moment.',
    insufficient_budget: "Cette publicité n'a plus de budget disponible.",
    no_active_grant:
        "Votre compte ne peut pas encore soumettre de preuve d'attention.",
};

const amountFormatter = new Intl.NumberFormat('fr-FR');

function AdCard({ ad }: { ad: Ad }) {
    const [state, setState] = useState<WatchState>({ status: 'idle' });
    const startedAtRef = useRef<number | null>(null);

    async function submit() {
        setState({ status: 'submitting' });

        const result = await postJson(
            QualifiedEventSelfSubmissionController.store.url(
                ad.campaign_version_id,
            ),
            {
                format: ad.format,
                evidence: { condition: ad.condition, completed: true },
                idempotency_key: `${ad.campaign_version_id}-${Date.now()}`,
            },
        );

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setState({ status: 'denied', reason: data?.reason ?? 'unknown' });

            return;
        }

        const data = result.data as { billing_status: string };
        setState({ status: 'submitted', billingStatus: data.billing_status });
    }

    useEffect(() => {
        if (state.status !== 'watching') {
            return;
        }

        const interval = setInterval(() => {
            const elapsed = Date.now() - (startedAtRef.current ?? Date.now());
            const progress = Math.min(100, (elapsed / WATCH_DURATION_MS) * 100);

            if (progress >= 100) {
                clearInterval(interval);
                void submit();
            } else {
                setState({ status: 'watching', progress });
            }
        }, PROGRESS_TICK_MS);

        return () => clearInterval(interval);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [state.status === 'watching']);

    function start() {
        startedAtRef.current = Date.now();
        setState({ status: 'watching', progress: 0 });
    }

    // Interruption (Constitution art. 3 §5-§6) : quitter avant la
    // condition remplie n'envoie aucune preuve et ne crédite rien.
    function leave() {
        setState({ status: 'idle' });
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle className="text-base">{ad.headline}</CardTitle>
                <Badge variant="secondary">
                    {amountFormatter.format(ad.reward_amount)} {ad.currency}
                </Badge>
            </CardHeader>
            <CardContent className="space-y-3">
                {state.status === 'idle' && (
                    <Button onClick={start} className="w-full">
                        Regarder
                    </Button>
                )}

                {state.status === 'watching' && (
                    <div className="space-y-3">
                        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-primary transition-[width]"
                                style={{ width: `${state.progress}%` }}
                            />
                        </div>
                        <Button
                            onClick={leave}
                            variant="outline"
                            className="w-full"
                        >
                            Quitter
                        </Button>
                    </div>
                )}

                {state.status === 'submitting' && (
                    <p className="text-sm text-muted-foreground">
                        Envoi de la preuve...
                    </p>
                )}

                {state.status === 'submitted' && (
                    <Alert>
                        <AlertTitle>Gain provisoire</AlertTitle>
                        <AlertDescription>
                            Preuve soumise — en attente de validation avant
                            crédit sur votre Wallet.
                        </AlertDescription>
                    </Alert>
                )}

                {state.status === 'denied' && (
                    <Alert variant="destructive">
                        <AlertTitle>Indisponible</AlertTitle>
                        <AlertDescription>
                            {DENIAL_MESSAGES[state.reason] ??
                                "Cette publicité n'est pas disponible pour le moment."}
                        </AlertDescription>
                    </Alert>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ ads }: { ads: Ad[] }) {
    return (
        <>
            <Head title="Feed" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Votre Feed"
                    description="Regardez une publicité jusqu'au bout pour qualifier votre attention à une récompense."
                />

                {ads.length === 0 && (
                    <Alert>
                        <AlertTitle>Aucune publicité disponible</AlertTitle>
                        <AlertDescription>
                            Revenez plus tard : aucune campagne éligible pour le
                            moment.
                        </AlertDescription>
                    </Alert>
                )}

                {ads.length > 0 && (
                    <div className="grid gap-4 md:grid-cols-2">
                        {ads.map((ad) => (
                            <AdCard key={ad.campaign_version_id} ad={ad} />
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Feed',
            href: dashboard(),
        },
    ] satisfies BreadcrumbItem[],
};

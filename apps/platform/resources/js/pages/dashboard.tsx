import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';
import QualifiedEventSelfSubmissionController from '@/actions/App/Modules/Advertising/Http/Controllers/QualifiedEventSelfSubmissionController';

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

// No real video/media yet (object storage and CDN deferred to W5).
// The progress bar simulates the minimum-duration condition
// (Constitution art. 3 §1–§4) on a fixed demo duration, not a real player.
const WATCH_DURATION_MS = 6_000;
const PROGRESS_TICK_MS = 100;

// Wasplex never exposes internal denial reasons (grant, policy, capability
// definition). Only user-facing text is shown (ADR-0004 §"décision explicable").
const DENIAL_MESSAGES: Record<string, string> = {
    campaign_version_not_approved: "Cette publicité n'est plus disponible.",
    pricing_not_resolvable:
        'Cette publicité ne peut pas être créditée pour le moment.',
    insufficient_budget: "Cette publicité n'a plus de budget disponible.",
    no_active_grant:
        "Votre compte ne peut pas encore soumettre de preuve d'attention.",
};

const amountFormatter = new Intl.NumberFormat('fr-FR');

type FeedTab = 'pour-toi' | 'alertes' | 'explorer';

const FORMAT_LABELS: Record<string, string> = {
    video: 'Vidéo',
    display: 'Affichage',
    audio: 'Audio',
};

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

    // Leaving before the threshold sends no proof and credits nothing
    // (Constitution art. 3 §5–§6).
    function leave() {
        setState({ status: 'idle' });
    }

    const rewardText = `+${amountFormatter.format(ad.reward_amount)} ${ad.currency}`;

    return (
        <article className="overflow-hidden rounded-2xl border border-[#35506D] bg-[#0E2542]">
            {/* Media placeholder / attention state indicator */}
            <div className="relative flex h-44 select-none items-center justify-center bg-gradient-to-br from-[#173251] to-[#0A1E38]">
                {/* Format badge — top left */}
                <span className="absolute left-3 top-3 rounded-md bg-[#0E2542]/80 px-2 py-0.5 text-[10px] font-medium uppercase tracking-widest text-[#A9B7C8] backdrop-blur-sm">
                    {FORMAT_LABELS[ad.format] ?? ad.format}
                </span>

                {/* Reward pill — top right */}
                <span className="absolute right-3 top-3 flex items-center gap-1 rounded-full bg-[#C75100] px-2.5 py-1 text-xs font-bold text-white shadow-lg">
                    {rewardText}
                </span>

                {state.status === 'idle' && (
                    <div className="text-center">
                        <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#173251] text-xl text-[#4FA3FF]">
                            ▶
                        </div>
                        <p className="text-xs text-[#A9B7C8]">
                            Appuie pour commencer
                        </p>
                    </div>
                )}

                {state.status === 'watching' && (
                    <div className="text-center">
                        <p className="text-4xl font-bold tabular-nums text-[#4FA3FF]">
                            {Math.round(state.progress)}%
                        </p>
                        <p className="mt-1 text-xs text-[#A9B7C8]">
                            Attention en cours…
                        </p>
                    </div>
                )}

                {state.status === 'submitting' && (
                    <div className="flex flex-col items-center gap-3">
                        <span className="h-8 w-8 animate-spin rounded-full border-2 border-[#35506D] border-t-[#4FA3FF]" />
                        <p className="text-xs text-[#A9B7C8]">
                            Envoi de la preuve…
                        </p>
                    </div>
                )}

                {state.status === 'submitted' && (
                    <div className="text-center">
                        <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#42D392]/20 text-xl text-[#42D392]">
                            ✓
                        </div>
                        <p className="text-sm font-semibold text-[#42D392]">
                            Preuve transmise
                        </p>
                    </div>
                )}

                {state.status === 'denied' && (
                    <div className="text-center">
                        <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-[#FF6B61]/20 text-xl text-[#FF6B61]">
                            ✕
                        </div>
                        <p className="text-sm font-semibold text-[#FF6B61]">
                            Indisponible
                        </p>
                    </div>
                )}
            </div>

            {/* Progress bar — only visible while watching */}
            {state.status === 'watching' && (
                <div className="h-1.5 bg-[#173251]">
                    <div
                        className="h-full transition-[width] duration-100 ease-linear"
                        style={{
                            width: `${state.progress}%`,
                            background:
                                'linear-gradient(to right, #4FA3FF, #FF9A3D)',
                        }}
                    />
                </div>
            )}

            {/* Card body */}
            <div className="p-4">
                <h3 className="mb-1 text-base font-semibold leading-snug text-[#F5F8FC]">
                    {ad.headline}
                </h3>
                <p className="mb-4 text-xs text-[#A9B7C8]">
                    Gain potentiel · crédité après validation par Wasplex
                </p>

                {state.status === 'idle' && (
                    <button
                        onClick={start}
                        className="w-full rounded-xl bg-[#075CCF] py-3.5 text-sm font-semibold text-white transition-colors active:bg-[#0A4FAF]"
                    >
                        Regarder · {rewardText}
                    </button>
                )}

                {state.status === 'watching' && (
                    <button
                        onClick={leave}
                        className="w-full rounded-xl border border-[#35506D] py-3.5 text-sm font-medium text-[#A9B7C8] transition-colors active:bg-[#173251]"
                    >
                        Quitter — la progression est perdue
                    </button>
                )}

                {state.status === 'submitting' && (
                    <div className="flex items-center justify-center py-3 text-sm text-[#A9B7C8]">
                        <span className="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-[#35506D] border-t-[#4FA3FF]" />
                        Envoi en cours…
                    </div>
                )}

                {state.status === 'submitted' && (
                    <div className="rounded-xl border border-[#42D392]/25 bg-[#42D392]/10 px-4 py-3.5">
                        <p className="text-xs font-semibold text-[#42D392]">
                            Preuve soumise · en attente de validation
                        </p>
                        <p className="mt-0.5 text-xs text-[#A9B7C8]">
                            Le crédit apparaîtra sur votre Wallet après examen.
                        </p>
                    </div>
                )}

                {state.status === 'denied' && (
                    <div className="rounded-xl border border-[#FF6B61]/25 bg-[#FF6B61]/10 px-4 py-3.5">
                        <p className="text-xs font-semibold text-[#FF6B61]">
                            {DENIAL_MESSAGES[state.reason] ??
                                "Cette publicité n'est pas disponible pour le moment."}
                        </p>
                    </div>
                )}
            </div>
        </article>
    );
}

function EmptyFeed() {
    return (
        <div className="flex flex-col items-center justify-center px-8 py-16 text-center">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[#173251]">
                <span className="text-2xl">📡</span>
            </div>
            <h3 className="mb-2 text-base font-semibold text-[#F5F8FC]">
                Aucune publicité disponible
            </h3>
            <p className="text-sm leading-relaxed text-[#A9B7C8]">
                Aucune campagne éligible pour le moment.
                <br />
                Revenez plus tard.
            </p>
        </div>
    );
}

const FEED_TABS: { key: FeedTab; label: string; available: boolean }[] = [
    { key: 'pour-toi', label: 'Pour toi', available: true },
    { key: 'alertes', label: 'Alertes', available: false },
    { key: 'explorer', label: 'Explorer', available: false },
];

export default function Dashboard({ ads }: { ads: Ad[] }) {
    const [activeTab, setActiveTab] = useState<FeedTab>('pour-toi');

    return (
        <MobileLayout>
            <Head title="Feed" />

            {/* Feed sub-tabs — UX-0001 §9 session structure */}
            <div className="sticky top-14 z-30 flex border-b border-[#35506D] bg-[#07182D]">
                {FEED_TABS.map((tab) => (
                    <button
                        key={tab.key}
                        disabled={!tab.available}
                        onClick={() =>
                            tab.available && setActiveTab(tab.key)
                        }
                        title={
                            !tab.available ? 'Bientôt disponible' : undefined
                        }
                        aria-current={
                            activeTab === tab.key ? 'page' : undefined
                        }
                        className={[
                            'relative flex-1 py-3.5 text-sm font-medium transition-colors',
                            !tab.available
                                ? 'cursor-not-allowed opacity-40'
                                : 'cursor-pointer',
                            activeTab === tab.key && tab.available
                                ? 'text-[#4FA3FF]'
                                : 'text-[#A9B7C8]',
                        ].join(' ')}
                    >
                        {tab.label}
                        {activeTab === tab.key && tab.available && (
                            <span className="absolute bottom-0 left-1/2 h-0.5 w-10 -translate-x-1/2 rounded-full bg-[#4FA3FF]" />
                        )}
                    </button>
                ))}
            </div>

            {/* Ad list */}
            <div className="space-y-4 p-4">
                {ads.length === 0 ? (
                    <EmptyFeed />
                ) : (
                    ads.map((ad) => (
                        <AdCard key={ad.campaign_version_id} ad={ad} />
                    ))
                )}
            </div>
        </MobileLayout>
    );
}

import { Head, usePage } from '@inertiajs/react';
import { Check, ChevronDown, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { CSSProperties, RefObject } from 'react';
import { toast } from 'sonner';
import QualifiedEventSelfSubmissionController from '@/actions/App/Modules/Advertising/Http/Controllers/QualifiedEventSelfSubmissionController';
import WasplexMascot from '@/components/wasplex-mascot';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';

type Ad = {
    campaign_version_id: string;
    advertiser: string;
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

const FORMAT_LABELS: Record<string, string> = {
    video: 'Vidéo',
    display: 'Affichage',
    banner: 'Bannière',
    audio: 'Audio',
};

const CONDITION_LABELS: Record<string, string> = {
    completion: 'Vue complète requise',
};

/**
 * Onglets de session du Feed — UX-0001 §9. Seul « Pour toi » est actif :
 * « Alertes » et « Explorer » annoncent leur indisponibilité au tap
 * (DS-0001 §23 — un onglet indisponible explique la condition).
 */
const FEED_TABS: { key: string; label: string; available: boolean }[] = [
    { key: 'pour-toi', label: 'Pour toi', available: true },
    { key: 'alertes', label: 'Alertes', available: false },
    { key: 'explorer', label: 'Explorer', available: false },
];

function FeedTopBar() {
    const page = usePage<{
        wallet_balance?: { available?: number; currency?: string };
    }>();
    const walletBalance = page.props.wallet_balance;

    return (
        <div className="pointer-events-none absolute inset-x-0 top-0 z-30 bg-gradient-to-b from-[#07182D]/95 via-[#07182D]/50 to-transparent px-3 pt-2 pb-8">
            <div className="flex items-center gap-2">
                <WasplexMascot className="h-9 w-9 shrink-0 drop-shadow" />

                <nav
                    aria-label="Sections du Feed"
                    className="pointer-events-auto flex flex-1 items-center justify-center gap-6"
                >
                    {FEED_TABS.map((tab) => (
                        <button
                            key={tab.key}
                            type="button"
                            aria-disabled={!tab.available}
                            aria-current={tab.available ? 'page' : undefined}
                            onClick={() =>
                                tab.available
                                    ? undefined
                                    : toast(`${tab.label} — bientôt disponible`)
                            }
                            className={[
                                'relative pb-1.5 text-sm font-semibold transition-colors',
                                tab.available
                                    ? 'text-white'
                                    : 'text-[#A9B7C8]/60',
                            ].join(' ')}
                        >
                            {tab.label}
                            {tab.available && (
                                <span className="absolute bottom-0 left-1/2 h-0.5 w-7 -translate-x-1/2 rounded-full bg-white" />
                            )}
                        </button>
                    ))}
                </nav>

                {/* Compteur WP — rendu seulement quand le solde est partagé
                    par le backend : jamais de solde inventé (CLAUDE.md §6). */}
                {walletBalance !== undefined && walletBalance !== null ? (
                    <div
                        className="pointer-events-auto flex shrink-0 items-center gap-1.5 rounded-full bg-[#0E2542]/90 px-3 py-1.5 text-sm font-bold text-white backdrop-blur-sm"
                        aria-label={`Solde disponible : ${walletBalance.available ?? 0} WP`}
                    >
                        <span
                            className="h-2 w-2 rounded-full bg-[#FF9A3D]"
                            aria-hidden="true"
                        />
                        {amountFormatter.format(walletBalance.available ?? 0)}{' '}
                        <span className="text-[10px] font-semibold text-[#F2C14E]">
                            WP
                        </span>
                    </div>
                ) : (
                    <span className="w-9 shrink-0" aria-hidden="true" />
                )}
            </div>
        </div>
    );
}

/**
 * Une publicité plein écran, façon défilement vertical aimanté.
 *
 * L'attention démarre automatiquement quand la publicité occupe l'écran
 * (le geste de scroll vaut « démarrer ») et s'interrompt si l'utilisateur
 * fait défiler avant le seuil — sans punition, aucune preuve envoyée
 * (Constitution art. 3 §5–§6, UX-0001 §10 « interrompue avant seuil »).
 */
function AdScreen({
    ad,
    scrollRoot,
}: {
    ad: Ad;
    scrollRoot: RefObject<HTMLDivElement | null>;
}) {
    const [state, setState] = useState<WatchState>({ status: 'idle' });
    const sectionRef = useRef<HTMLElement>(null);
    const startedAtRef = useRef<number | null>(null);
    // Une fois la preuve partie (soumise, acceptée ou refusée), revenir sur
    // la publicité ne relance jamais une session : le doublon restaurerait
    // le résultat existant côté serveur (UX-0001 §10), autant ne pas mentir
    // côté interface.
    const finishedRef = useRef(false);

    async function submit() {
        finishedRef.current = true;
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

    // Scroll = démarrer / passer : l'attention suit la visibilité. La
    // publicité est « à l'écran » quand elle occupe au moins 60 % de la
    // zone de défilement ; en sortir avant le seuil abandonne la
    // progression — aucune preuve, aucune punition (Constitution art. 3
    // §5–§6, UX-0001 §10).
    useEffect(() => {
        const element = sectionRef.current;

        if (!element) {
            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                const onScreen = entry.intersectionRatio >= 0.6;

                setState((current) => {
                    if (
                        onScreen &&
                        current.status === 'idle' &&
                        !finishedRef.current
                    ) {
                        startedAtRef.current = Date.now();

                        return { status: 'watching', progress: 0 };
                    }

                    if (!onScreen && current.status === 'watching') {
                        return { status: 'idle' };
                    }

                    return current;
                });
            },
            { threshold: [0.6], root: scrollRoot.current },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, [scrollRoot]);

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

    const rewardText = `+${amountFormatter.format(ad.reward_amount)} ${ad.currency}`;
    const formatLabel = FORMAT_LABELS[ad.format] ?? ad.format;
    const conditionLabel = CONDITION_LABELS[ad.condition] ?? ad.condition;

    return (
        <section
            ref={sectionRef}
            className="relative h-full snap-start overflow-hidden bg-gradient-to-br from-[#173251] via-[#0C2340] to-[#0A1E38]"
        >
            {/* Lueurs décoratives du média factice */}
            <div
                aria-hidden="true"
                className="absolute -top-20 -right-24 h-72 w-72 rounded-full bg-[#075CCF]/25 blur-3xl"
            />
            <div
                aria-hidden="true"
                className="absolute -bottom-24 -left-20 h-72 w-72 rounded-full bg-[#C75100]/20 blur-3xl"
            />

            {/* Pilule de gain — le gain reste potentiel jusqu'à validation
                (UX-0001 §9) */}
            {state.status !== 'submitted' && state.status !== 'denied' && (
                <span className="absolute top-16 right-3 z-20 rounded-full bg-[#C75100] px-3 py-1.5 text-xs font-bold text-white shadow-lg">
                    {rewardText}
                </span>
            )}

            {/* « Créa » centrale — placeholder tant que le vrai média
                n'existe pas (W5) */}
            <div className="relative z-10 flex h-full flex-col items-center justify-center px-8 pb-40 text-center">
                {(state.status === 'idle' || state.status === 'watching') && (
                    <>
                        <h2 className="max-w-[320px] text-2xl leading-snug font-bold text-white drop-shadow-lg">
                            {ad.headline}
                        </h2>
                        {state.status === 'watching' && (
                            <p
                                className="mt-6 text-5xl font-bold text-white/90 tabular-nums drop-shadow-lg"
                                aria-live="off"
                            >
                                {Math.round(state.progress)}
                                <span className="text-2xl text-[#4FA3FF]">
                                    %
                                </span>
                            </p>
                        )}
                        {state.status === 'watching' && (
                            <p className="mt-1 text-xs text-[#A9B7C8]">
                                Attention en cours — fais défiler pour passer
                            </p>
                        )}
                    </>
                )}

                {state.status === 'submitting' && (
                    <div className="flex flex-col items-center gap-4">
                        <span className="h-10 w-10 animate-spin rounded-full border-2 border-[#35506D] border-t-[#4FA3FF]" />
                        <p className="text-sm text-[#A9B7C8]">
                            Envoi de la preuve…
                        </p>
                    </div>
                )}

                {state.status === 'submitted' && (
                    <>
                        {/* WasPoints qui s'envolent vers le Wallet (nav
                            inférieure) — célèbre la preuve transmise, jamais
                            un crédit « disponible » (UX-0001 §10). */}
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-0 z-30"
                        >
                            {[0, 1, 2, 3, 4].map((i) => (
                                <span
                                    key={i}
                                    className="wpx-coin"
                                    style={
                                        {
                                            '--dx': `${(i - 2) * 30}px`,
                                            animationDelay: `${i * 100}ms`,
                                        } as CSSProperties
                                    }
                                >
                                    WP
                                </span>
                            ))}
                            <span className="wpx-fly-reward">{rewardText}</span>
                        </div>

                        <div className="wpx-status-in w-full max-w-[320px] rounded-2xl border border-[#42D392]/25 bg-[#07182D]/80 p-5 backdrop-blur-md">
                            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#42D392]/15 text-[#42D392]">
                                <Check size={24} />
                            </div>
                            <p className="text-base font-semibold text-[#42D392]">
                                Preuve transmise
                            </p>
                            <p className="mt-1 text-sm leading-relaxed text-[#A9B7C8]">
                                {rewardText} en validation. Le crédit apparaîtra
                                sur votre Wallet après examen.
                            </p>
                        </div>

                        <p className="wpx-status-in mt-6 flex flex-col items-center gap-0.5 text-xs text-[#A9B7C8]">
                            Fais défiler pour continuer
                            <ChevronDown
                                size={16}
                                className="animate-bounce"
                                aria-hidden="true"
                            />
                        </p>
                    </>
                )}

                {state.status === 'denied' && (
                    <div className="w-full max-w-[320px] rounded-2xl border border-[#FF6B61]/25 bg-[#07182D]/80 p-5 backdrop-blur-md">
                        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#FF6B61]/15 text-[#FF6B61]">
                            <X size={24} />
                        </div>
                        <p className="text-base font-semibold text-[#FF6B61]">
                            Indisponible
                        </p>
                        <p className="mt-1 text-sm leading-relaxed text-[#A9B7C8]">
                            {DENIAL_MESSAGES[state.reason] ??
                                "Cette publicité n'est pas disponible pour le moment."}
                        </p>
                        <p className="mt-4 flex flex-col items-center gap-0.5 text-xs text-[#A9B7C8]">
                            Fais défiler pour continuer
                            <ChevronDown
                                size={16}
                                className="animate-bounce"
                                aria-hidden="true"
                            />
                        </p>
                    </div>
                )}
            </div>

            {/* Identité et transparence — UX-0001 §9 : annonceur, format,
                condition, gain potentiel, raison générale d'éligibilité,
                visibles sur la publicité elle-même. */}
            <div className="pointer-events-none absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-[#07182D]/95 via-[#07182D]/55 to-transparent px-4 pt-14 pb-3">
                <div className="mb-2 flex flex-wrap items-center gap-1.5">
                    <span className="rounded-md bg-[#0E2542]/80 px-2 py-0.5 text-[10px] font-semibold tracking-widest text-[#4FA3FF] uppercase backdrop-blur-sm">
                        {formatLabel}
                    </span>
                    <span className="rounded-md bg-[#0E2542]/80 px-2 py-0.5 text-[10px] font-medium text-[#A9B7C8] backdrop-blur-sm">
                        {conditionLabel} · ≈ {WATCH_DURATION_MS / 1000} s
                    </span>
                </div>

                <div className="flex items-center gap-2.5">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#4FA3FF] to-[#075CCF] text-xs font-bold text-white">
                        {ad.advertiser.charAt(0).toUpperCase()}
                    </span>
                    <div className="min-w-0">
                        <p className="truncate text-sm font-bold text-white">
                            {ad.advertiser}
                        </p>
                        <p className="truncate text-xs text-[#A9B7C8]">
                            {ad.headline}
                        </p>
                    </div>
                </div>

                <p className="mt-1.5 text-[10px] leading-relaxed text-[#53657D]">
                    Gain potentiel {rewardText} · crédité après validation par
                    Wasplex · campagne active, approuvée et financée
                </p>
            </div>

            {/* Barre de progression — bord inférieur, style lecteur */}
            {state.status === 'watching' && (
                <div className="absolute inset-x-0 bottom-0 z-20 h-1 bg-white/10">
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
        </section>
    );
}

function EmptyFeed() {
    return (
        <section className="flex h-full snap-start flex-col items-center justify-center px-8 text-center">
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
        </section>
    );
}

export default function Dashboard({ ads }: { ads: Ad[] }) {
    const scrollRef = useRef<HTMLDivElement>(null);

    return (
        <MobileLayout showHeader={false} fullScreen>
            <Head title="Feed" />

            {/* Animations des WasPoints vers le Wallet */}
            <style>{`
                .wpx-coin {
                    position: absolute;
                    left: 50%;
                    top: 40%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 34px;
                    height: 34px;
                    border-radius: 9999px;
                    background: radial-gradient(circle at 30% 30%, #FFE9A8, #F2C14E 60%, #C78A00);
                    color: #5C3A00;
                    font-size: 11px;
                    font-weight: 800;
                    opacity: 0;
                    transform: translate(-50%, -50%) scale(0.3);
                    animation: wpx-coin-fly 1.1s cubic-bezier(0.45, -0.05, 0.7, 1) forwards;
                }
                @keyframes wpx-coin-fly {
                    0% { opacity: 0; transform: translate(-50%, -50%) scale(0.3); }
                    22% { opacity: 1; transform: translate(calc(-50% + var(--dx)), -62%) scale(1); }
                    100% { opacity: 0; transform: translate(calc(-50% + var(--dx) * 0.25), 46svh) scale(0.4); }
                }
                .wpx-fly-reward {
                    position: absolute;
                    left: 50%;
                    top: 34%;
                    transform: translate(-50%, -50%);
                    border-radius: 9999px;
                    background: #C75100;
                    color: #fff;
                    font-size: 13px;
                    font-weight: 700;
                    padding: 6px 14px;
                    opacity: 0;
                    animation: wpx-coin-fly 1.2s cubic-bezier(0.45, -0.05, 0.7, 1) 0.25s forwards;
                }
                .wpx-status-in {
                    opacity: 0;
                    animation: wpx-fade-up 0.4s ease-out 1s forwards;
                }
                @keyframes wpx-fade-up {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @media (prefers-reduced-motion: reduce) {
                    .wpx-coin, .wpx-fly-reward { animation-duration: 0.01s; animation-delay: 0s; }
                    .wpx-status-in { animation-duration: 0.01s; animation-delay: 0s; }
                }
            `}</style>

            {/* Zone immersive : hauteur de l'écran moins la navigation
                inférieure (h-16). Le défilement s'aimante publicité par
                publicité — jamais un défilement infini sans borne
                (UX-0001 §9). */}
            <div className="relative h-[calc(100svh-4rem)]">
                <FeedTopBar />

                <div
                    ref={scrollRef}
                    className="h-full snap-y snap-mandatory overflow-y-auto overscroll-contain"
                >
                    {ads.length === 0 ? (
                        <EmptyFeed />
                    ) : (
                        ads.map((ad) => (
                            <AdScreen
                                key={ad.campaign_version_id}
                                ad={ad}
                                scrollRoot={scrollRef}
                            />
                        ))
                    )}
                </div>
            </div>
        </MobileLayout>
    );
}

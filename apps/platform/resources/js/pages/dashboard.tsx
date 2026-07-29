import { Head, router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Search, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { toast } from 'sonner';
import CampaignVersionFavoriteController from '@/actions/App/Modules/Advertising/Http/Controllers/CampaignVersionFavoriteController';
import CampaignVersionLikeController from '@/actions/App/Modules/Advertising/Http/Controllers/CampaignVersionLikeController';
import CampaignVersionShareController from '@/actions/App/Modules/Advertising/Http/Controllers/CampaignVersionShareController';
import QualifiedEventSelfSubmissionController from '@/actions/App/Modules/Advertising/Http/Controllers/QualifiedEventSelfSubmissionController';
import { SocialButtons } from '@/components/feed/social-buttons';
import { WingFlight } from '@/components/feed/wing-flight';
import WasplexMascot from '@/components/wasplex-mascot';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';
import alerts from '@/routes/alerts';

type Ad = {
    campaign_version_id: string;
    advertiser: string;
    headline: string;
    format: string;
    condition: string;
    reward_amount: number;
    currency: string;
    likes_count: number;
    favorites_count: number;
    shares_count: number;
    liked: boolean;
    favorited: boolean;
};

// P008-A : jamais une publicité — ni quota, ni WP, ni SocialButtons.
type CommunityAlert = {
    publication_id: string;
    title: string;
    summary: string;
    approximate_zone: string | null;
};

type WalletBalance = { available: number; currency: string | null };

type SubmitResponse = {
    billing_status: string;
    acceptance_mode?: string | null;
    credited_amount?: number;
    credited_currency?: string;
    wallet_available?: number;
    review_reason?: string | null;
};

type LikeResponse = { liked: boolean; likes_count: number };
type FavoriteResponse = { favorited: boolean; favorites_count: number };
type ShareResponse = { shares_count: number };

type WatchState =
    | { status: 'idle' }
    | { status: 'watching'; progress: number }
    | { status: 'submitting' }
    // Crédit réellement comptabilisé dans la même requête (acceptation
    // automatique serveur) — le montant affiché EST au Wallet.
    | { status: 'credited'; amount: number }
    // Preuve maintenue pour examen humain (cas suspects — l'exception,
    // pas le défaut depuis l'arbitrage Koné/SIRR 2026-07-26).
    | { status: 'under-review'; reason: string | null }
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
        "Ton compte ne peut pas encore soumettre de preuve d'attention.",
};

// Raisons d'examen (flux d'exception) — formulées sans promesse de gain
// « disponible » tant que le Wallet n'a rien comptabilisé (UX-0001 §10).
const REVIEW_MESSAGES: Record<string, string> = {
    daily_quota_reached:
        'Plafond du jour atteint : cette preuve sera examinée par Wasplex avant tout crédit.',
    completion_not_attested:
        'Cette preuve doit être examinée par Wasplex avant tout crédit.',
    auto_acceptance_not_configured:
        'Cette preuve sera examinée par Wasplex avant tout crédit.',
};

const DEFAULT_REVIEW_MESSAGE =
    'Cette preuve sera examinée par Wasplex avant tout crédit.';

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
 * Onglets de session du Feed — UX-0001 §9. « Pour toi » reste la
 * destination courante ; « Alertes » navigue réellement vers le module
 * Alertes (P008-A) depuis ce lot — l'onglet Feed lui-même ne réimplémente
 * pas une seconde liste d'alertes, il pointe vers `/alerts`. « Explorer »
 * annonce toujours son indisponibilité au tap (DS-0001 §23).
 */
const FEED_TABS: {
    key: string;
    label: string;
    available: boolean;
    href?: string;
}[] = [
    { key: 'pour-toi', label: 'Pour toi', available: true },
    {
        key: 'alertes',
        label: 'Alertes',
        available: true,
        href: alerts.index().url,
    },
    { key: 'explorer', label: 'Explorer', available: false },
];

/**
 * Compteur animé : rejoint la vraie valeur par interpolation courte
 * (~600 ms) à chaque crédit — l'oiseau livre, le compteur monte. La
 * valeur affichée converge toujours vers le solde renvoyé par le serveur,
 * jamais une extrapolation.
 */
function useCountUp(target: number): number {
    const [display, setDisplay] = useState(target);
    const displayRef = useRef(target);

    useEffect(() => {
        const from = displayRef.current;

        if (from === target) {
            return;
        }

        // Mouvement réduit : rejoindre la valeur en une seule frame.
        const duration = window.matchMedia('(prefers-reduced-motion: reduce)')
            .matches
            ? 0
            : 600;
        const startedAt = performance.now();
        let raf = 0;

        const tick = (now: number) => {
            const t =
                duration === 0 ? 1 : Math.min(1, (now - startedAt) / duration);
            const eased = 1 - (1 - t) ** 3;
            const value = Math.round(from + (target - from) * eased);
            displayRef.current = value;
            setDisplay(value);

            if (t < 1) {
                raf = requestAnimationFrame(tick);
            }
        };

        raf = requestAnimationFrame(tick);

        return () => cancelAnimationFrame(raf);
    }, [target]);

    return display;
}

function FeedTopBar({
    balance,
    walletPulse,
    logoRef,
    walletRef,
    progressRef,
    communityAlerts,
}: {
    balance: WalletBalance | null;
    walletPulse: boolean;
    logoRef: RefObject<HTMLSpanElement | null>;
    walletRef: RefObject<HTMLDivElement | null>;
    progressRef: RefObject<HTMLDivElement | null>;
    communityAlerts: CommunityAlert[];
}) {
    const displayedAvailable = useCountUp(balance?.available ?? 0);

    return (
        <div className="pointer-events-none absolute inset-x-0 top-0 z-30 bg-gradient-to-b from-[#07182D]/95 via-[#07182D]/50 to-transparent px-3 pt-2 pb-8">
            {/* Barre de progression fine du header — pilotée en DOM direct
                par la publicité à l'écran, sans re-render (comportement
                repris de l'ancien Feed wasplexmobile4.0). */}
            <div className="absolute inset-x-0 top-0 h-0.5 bg-transparent">
                <div
                    ref={progressRef}
                    className="h-full w-0 opacity-0"
                    style={{
                        background:
                            'linear-gradient(to right, #4FA3FF, #F2C14E)',
                        transition: 'width 100ms linear',
                    }}
                />
            </div>

            <div className="flex items-center gap-2">
                <span ref={logoRef} className="shrink-0">
                    <WasplexMascot className="h-9 w-9 drop-shadow" />
                </span>

                <nav
                    aria-label="Sections du Feed"
                    className="pointer-events-auto flex flex-1 items-center justify-center gap-6"
                >
                    {FEED_TABS.map((tab) => (
                        <button
                            key={tab.key}
                            type="button"
                            aria-disabled={!tab.available}
                            aria-current={
                                tab.key === 'pour-toi' ? 'page' : undefined
                            }
                            onClick={() => {
                                if (tab.href) {
                                    router.visit(tab.href);

                                    return;
                                }

                                if (!tab.available) {
                                    toast(`${tab.label} — bientôt disponible`);
                                }
                            }}
                            className={[
                                'relative pb-1.5 text-sm font-semibold whitespace-nowrap transition-colors',
                                tab.key === 'pour-toi'
                                    ? 'text-white'
                                    : tab.available
                                      ? 'text-[#A9B7C8]'
                                      : 'text-[#A9B7C8]/60',
                            ].join(' ')}
                        >
                            {tab.label}
                            {tab.key === 'pour-toi' && (
                                <span className="absolute bottom-0 left-1/2 h-0.5 w-7 -translate-x-1/2 rounded-full bg-white" />
                            )}
                        </button>
                    ))}
                </nav>

                {/* Compteur WP — rendu seulement quand le solde est partagé
                    par le backend : jamais de solde inventé (CLAUDE.md §6). */}
                {balance !== null ? (
                    <div
                        ref={walletRef}
                        className={[
                            'pointer-events-auto flex shrink-0 items-center gap-1.5 rounded-full bg-[#0E2542]/90 px-3 py-1.5 text-sm font-bold text-white backdrop-blur-sm',
                            walletPulse ? 'wpx-balance-pulse' : '',
                        ].join(' ')}
                        aria-label={`Solde disponible : ${balance.available} WP`}
                    >
                        <span
                            className="h-2 w-2 rounded-full bg-[#FF9A3D]"
                            aria-hidden="true"
                        />
                        {amountFormatter.format(displayedAvailable)}{' '}
                        <span className="text-[10px] font-semibold text-[#F2C14E]">
                            WP
                        </span>
                    </div>
                ) : (
                    <span className="w-9 shrink-0" aria-hidden="true" />
                )}
            </div>

            {/* Surface compacte d'alertes communautaires (mission P008-A
                §15) : jamais mêlée aux publicités, ne consomme aucun quota
                et ne crédite aucun WP — un simple lien vers `/alerts`.
                L'interleaving à cadence configurable entre publicités reste
                différé (dette technique). */}
            {communityAlerts.length > 0 && (
                <a
                    href={alerts.index().url}
                    className="pointer-events-auto mt-2 flex items-center gap-2 rounded-lg bg-[#0E2542]/90 px-3 py-2 text-xs text-white backdrop-blur-sm"
                >
                    <span
                        className="h-1.5 w-1.5 shrink-0 rounded-full bg-[#4FA3FF]"
                        aria-hidden="true"
                    />
                    <span className="truncate">
                        Alerte communautaire — {communityAlerts[0].title}
                    </span>
                </a>
            )}
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
 *
 * À complétion, la preuve part au serveur ; si les contrôles automatiques
 * passent, la réponse revient avec le crédit réellement comptabilisé et
 * l'animation de livraison se déclenche — au retour serveur seulement,
 * jamais avant (UX-0001 §10 : le gain affiché EST comptabilisé).
 */
function AdScreen({
    ad,
    scrollRoot,
    onWatchProgress,
    onCredited,
}: {
    ad: Ad;
    scrollRoot: RefObject<HTMLDivElement | null>;
    onWatchProgress: (pct: number | null) => void;
    onCredited: (
        amount: number,
        newAvailable: number | null,
        currency: string | null,
    ) => void;
}) {
    const [state, setState] = useState<WatchState>({ status: 'idle' });
    const sectionRef = useRef<HTMLElement>(null);
    const startedAtRef = useRef<number | null>(null);
    // Une même preuve garde la même clé d'idempotence pour toute la vie de
    // cette carte : un nouvel essai (réseau coupé…) rejoue l'événement
    // existant côté serveur au lieu d'en créer un second.
    const idempotencyKeyRef = useRef<string | null>(null);
    // Une fois la preuve partie (créditée, en examen ou refusée), revenir
    // sur la publicité ne relance jamais une session : le doublon
    // restaurerait le résultat existant côté serveur (UX-0001 §10), autant
    // ne pas mentir côté interface.
    const finishedRef = useRef(false);

    // Signaux sociaux (Lot 3 Phase A, décision de Koné 2026-07-26) —
    // initialisés par le backend, mis à jour optimistiquement au tap puis
    // réconciliés avec la réponse serveur ; en cas d'échec (401/403…),
    // l'état revient exactement à ce qu'il était avant le tap, jamais une
    // valeur devinée. Purement social : aucun de ces boutons ne touche au
    // Wallet ni au crédit de la publicité.
    const [social, setSocial] = useState({
        likes: ad.likes_count,
        favorites: ad.favorites_count,
        shares: ad.shares_count,
        liked: ad.liked,
        favorited: ad.favorited,
    });

    async function handleLike() {
        const before = social;
        const optimisticLiked = !before.liked;
        setSocial((s) => ({
            ...s,
            liked: optimisticLiked,
            likes: s.likes + (optimisticLiked ? 1 : -1),
        }));

        const result = await postJson<LikeResponse>(
            CampaignVersionLikeController.store.url(ad.campaign_version_id),
            {},
        );

        if (!result.ok) {
            setSocial(before);

            return;
        }

        setSocial((s) => ({
            ...s,
            liked: result.data.liked,
            likes: result.data.likes_count,
        }));
    }

    async function handleFavorite() {
        const before = social;
        const optimisticFavorited = !before.favorited;
        setSocial((s) => ({
            ...s,
            favorited: optimisticFavorited,
            favorites: s.favorites + (optimisticFavorited ? 1 : -1),
        }));

        const result = await postJson<FavoriteResponse>(
            CampaignVersionFavoriteController.store.url(ad.campaign_version_id),
            {},
        );

        if (!result.ok) {
            setSocial(before);

            return;
        }

        setSocial((s) => ({
            ...s,
            favorited: result.data.favorited,
            favorites: result.data.favorites_count,
        }));
    }

    async function handleShare() {
        const before = social;
        setSocial((s) => ({ ...s, shares: s.shares + 1 }));

        const result = await postJson<ShareResponse>(
            CampaignVersionShareController.store.url(ad.campaign_version_id),
            {},
        );

        if (!result.ok) {
            setSocial(before);

            return;
        }

        setSocial((s) => ({ ...s, shares: result.data.shares_count }));

        // Le partage effectif se produit hors plateforme (partage natif du
        // système d'exploitation) — cette route ne fait que compter le
        // geste (AMD-0001, AMD-0009).
        if (typeof navigator !== 'undefined' && navigator.share) {
            navigator
                .share({ title: ad.headline, url: window.location.href })
                .catch(() => {});
        }
    }

    async function submit() {
        finishedRef.current = true;
        onWatchProgress(null);
        setState({ status: 'submitting' });

        idempotencyKeyRef.current ??=
            typeof crypto.randomUUID === 'function'
                ? `${ad.campaign_version_id}-${crypto.randomUUID()}`
                : `${ad.campaign_version_id}-${Date.now()}-${Math.random().toString(36).slice(2)}`;

        const result = await postJson(
            QualifiedEventSelfSubmissionController.store.url(
                ad.campaign_version_id,
            ),
            {
                format: ad.format,
                evidence: { condition: ad.condition, completed: true },
                idempotency_key: idempotencyKeyRef.current,
            },
        );

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setState({ status: 'denied', reason: data?.reason ?? 'unknown' });

            return;
        }

        const data = result.data as SubmitResponse;

        if (
            data.billing_status === 'accepted' &&
            typeof data.credited_amount === 'number'
        ) {
            setState({ status: 'credited', amount: data.credited_amount });
            onCredited(
                data.credited_amount,
                typeof data.wallet_available === 'number'
                    ? data.wallet_available
                    : null,
                data.credited_currency ?? null,
            );

            return;
        }

        setState({
            status: 'under-review',
            reason: data.review_reason ?? null,
        });
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
                        onWatchProgress(null);

                        return { status: 'idle' };
                    }

                    return current;
                });
            },
            { threshold: [0.6], root: scrollRoot.current },
        );

        observer.observe(element);

        return () => observer.disconnect();
        // eslint-disable-next-line react-hooks/exhaustive-deps
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
                onWatchProgress(progress);
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
            {state.status !== 'credited' &&
                state.status !== 'under-review' &&
                state.status !== 'denied' && (
                    <span className="absolute top-16 right-3 z-20 rounded-full bg-[#C75100] px-3 py-1.5 text-xs font-bold text-white shadow-lg">
                        {rewardText}
                    </span>
                )}

            {/* Menu vertical du Feed — j'aime, favori, intention de partage
                (Lot 3 Phase A, décision de Koné 2026-07-26). Signaux
                sociaux purs, sans effet sur le crédit ci-dessus. */}
            <SocialButtons
                likes={social.likes}
                favorites={social.favorites}
                shares={social.shares}
                liked={social.liked}
                favorited={social.favorited}
                onLike={() => void handleLike()}
                onFavorite={() => void handleFavorite()}
                onShare={() => void handleShare()}
            />

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

                {state.status === 'credited' && (
                    <>
                        {/* La carte apparaît pendant que l'oiseau livre les
                            WP (WingFlight, déclenché par le parent au retour
                            serveur) : le montant annoncé est celui
                            réellement comptabilisé. */}
                        <div className="wpx-status-in w-full max-w-[320px] rounded-2xl border border-[#42D392]/25 bg-[#07182D]/80 p-5 backdrop-blur-md">
                            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#42D392]/15 text-[#42D392]">
                                <Check size={24} />
                            </div>
                            <p className="text-base font-semibold text-[#42D392]">
                                +{amountFormatter.format(state.amount)} WP
                                crédités
                            </p>
                            <p className="mt-1 text-sm leading-relaxed text-[#A9B7C8]">
                                Ton Wallet vient d'être crédité — contrôles
                                automatiques Wasplex passés.
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

                {state.status === 'under-review' && (
                    <>
                        <div className="wpx-status-in w-full max-w-[320px] rounded-2xl border border-[#F2C14E]/25 bg-[#07182D]/80 p-5 backdrop-blur-md">
                            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[#F2C14E]/15 text-[#F2C14E]">
                                <Search size={24} />
                            </div>
                            <p className="text-base font-semibold text-[#F2C14E]">
                                Preuve en examen
                            </p>
                            <p className="mt-1 text-sm leading-relaxed text-[#A9B7C8]">
                                {(state.reason !== null
                                    ? REVIEW_MESSAGES[state.reason]
                                    : undefined) ?? DEFAULT_REVIEW_MESSAGE}
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
                    Gain potentiel {rewardText} · crédité à la vue complète
                    après contrôles automatiques Wasplex · campagne active,
                    approuvée et financée
                </p>
            </div>
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

type Flight = {
    logoRect: DOMRect;
    walletRect: DOMRect;
    amount: number;
    newAvailable: number | null;
    currency: string | null;
};

export default function Dashboard({
    ads,
    community_alerts: communityAlerts,
}: {
    ads: Ad[];
    community_alerts: CommunityAlert[];
}) {
    const page = usePage<{ wallet_balance?: WalletBalance | null }>();
    const scrollRef = useRef<HTMLDivElement>(null);
    const logoRef = useRef<HTMLSpanElement>(null);
    const walletRef = useRef<HTMLDivElement>(null);
    const progressRef = useRef<HTMLDivElement>(null);

    // Solde vivant côté client : initialisé par le backend, puis mis à
    // jour uniquement avec les soldes renvoyés par le serveur à chaque
    // crédit réel — jamais extrapolé.
    const [balance, setBalance] = useState<WalletBalance | null>(
        page.props.wallet_balance ?? null,
    );
    const [flight, setFlight] = useState<Flight | null>(null);
    const [walletPulse, setWalletPulse] = useState(false);

    const setWatchProgress = useCallback((pct: number | null) => {
        const el = progressRef.current;

        if (!el) {
            return;
        }

        el.style.opacity = pct === null ? '0' : '1';
        el.style.width = `${pct ?? 0}%`;
    }, []);

    const applyCredit = useCallback(
        (
            amount: number,
            newAvailable: number | null,
            currency: string | null,
        ) => {
            setBalance((current) => ({
                available: newAvailable ?? (current?.available ?? 0) + amount,
                currency: current?.currency ?? currency,
            }));
            setWalletPulse(true);
            setTimeout(() => setWalletPulse(false), 700);
        },
        [],
    );

    const handleCredited = useCallback(
        (
            amount: number,
            newAvailable: number | null,
            currency: string | null,
        ) => {
            const logoRect = logoRef.current?.getBoundingClientRect();
            const walletRect = walletRef.current?.getBoundingClientRect();
            const reducedMotion = window.matchMedia(
                '(prefers-reduced-motion: reduce)',
            ).matches;

            setFlight((current) => {
                // Sans repères à l'écran, préférence de mouvement réduit,
                // ou vol déjà en cours : créditer le compteur directement.
                if (!logoRect || !walletRect || reducedMotion || current) {
                    applyCredit(amount, newAvailable, currency);

                    return current;
                }

                return {
                    logoRect,
                    walletRect,
                    amount,
                    newAvailable,
                    currency,
                };
            });
        },
        [applyCredit],
    );

    return (
        <MobileLayout showHeader={false} fullScreen>
            <Head title="Feed" />

            <style>{`
                .wpx-status-in {
                    opacity: 0;
                    animation: wpx-fade-up 0.4s ease-out 1s forwards;
                }
                @keyframes wpx-fade-up {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                @keyframes wpx-balance-pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.25); }
                }
                .wpx-balance-pulse {
                    animation: wpx-balance-pulse 600ms ease-out;
                }
                @media (prefers-reduced-motion: reduce) {
                    .wpx-status-in { animation-duration: 0.01s; animation-delay: 0s; }
                    .wpx-balance-pulse { animation: none; }
                }
            `}</style>

            {/* Zone immersive : hauteur de l'écran moins la navigation
                inférieure (h-16). Le défilement s'aimante publicité par
                publicité — jamais un défilement infini sans borne
                (UX-0001 §9). */}
            <div className="relative h-[calc(100svh-4rem)]">
                <FeedTopBar
                    balance={balance}
                    walletPulse={walletPulse}
                    logoRef={logoRef}
                    walletRef={walletRef}
                    progressRef={progressRef}
                    communityAlerts={communityAlerts}
                />

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
                                onWatchProgress={setWatchProgress}
                                onCredited={handleCredited}
                            />
                        ))
                    )}
                </div>
            </div>

            {/* L'oiseau WasPlex livre les WP — déclenché au retour serveur
                d'un crédit réellement comptabilisé, jamais avant
                (UX-0001 §10). */}
            {flight && (
                <WingFlight
                    logoRect={flight.logoRect}
                    walletRect={flight.walletRect}
                    amount={flight.amount}
                    onCredit={() =>
                        applyCredit(
                            flight.amount,
                            flight.newAvailable,
                            flight.currency,
                        )
                    }
                    onDone={() => setFlight(null)}
                />
            )}
        </MobileLayout>
    );
}

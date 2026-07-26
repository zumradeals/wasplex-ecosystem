/**
 * WingFlight — l'oiseau WasPlex ramasse les WasPoints et les livre dans le
 * Wallet de l'utilisateur après un crédit réellement comptabilisé.
 *
 * Portage fidèle de `wasplexmobile4.0/src/components/feed/WingFlight.tsx`
 * (timings, phasé et courbes d'origine, non réinventés — décision Koné
 * 2026-07-26), avec une différence de fond : ici l'animation ne se
 * déclenche qu'au retour serveur d'une acceptation réelle — le montant
 * affiché EST comptabilisé (UX-0001 §10), jamais une promesse.
 *
 * Architecture :
 *  - <canvas> fixed plein écran pour particules + pièces (RAF direct)
 *  - <img>    fixed pour l'oiseau (style RAF direct, aucun re-render)
 *  - <div>    React state pour le texte « +X WP » au centre
 *
 * Phases (ms) :
 *  0–200     TREMBLE : l'oiseau frémit sur son perchoir (logo)
 *  200–900   ARC1    : envol en arc vers le centre de l'écran
 *  900–1250  COLLECT : pièces en burst + texte « +X WP »
 *  1250–2050 ARC2    : vol rapide vers le wallet
 *  2050–2400 DELIVER : l'oiseau plonge dans le wallet, onCredit() déclenché
 *  2400–2800 SPARKLE : dispersion résiduelle, fondu
 */
import { useEffect, useRef, useState } from 'react';

type Pt = { x: number; y: number };

type Particle = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    life: number;
    maxLife: number;
    size: number;
    hue: number;
};

type Coin = {
    x: number;
    y: number;
    vx: number;
    vy: number;
    life: number;
    maxLife: number;
    size: number;
};

export type WingFlightProps = {
    logoRect: DOMRect;
    walletRect: DOMRect;
    amount: number;
    onCredit: () => void;
    onDone: () => void;
};

const cbez = (t: number, p0: Pt, p1: Pt, p2: Pt, p3: Pt): Pt => {
    const u = 1 - t;

    return {
        x:
            u ** 3 * p0.x +
            3 * u ** 2 * t * p1.x +
            3 * u * t ** 2 * p2.x +
            t ** 3 * p3.x,
        y:
            u ** 3 * p0.y +
            3 * u ** 2 * t * p1.y +
            3 * u * t ** 2 * p2.y +
            t ** 3 * p3.y,
    };
};

const clamp = (v: number, lo: number, hi: number) =>
    Math.max(lo, Math.min(hi, v));
const pt = (elapsed: number, s: number, e: number) =>
    clamp((elapsed - s) / (e - s), 0, 1);
const easeOut3 = (t: number) => 1 - (1 - t) ** 3;
const easeInOut = (t: number) => (t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t);

const T = {
    TREMBLE_END: 200,
    ARC1_START: 200,
    ARC1_END: 900,
    COLLECT_START: 900,
    COLLECT_END: 1250,
    ARC2_START: 1250,
    ARC2_END: 2050,
    DELIVER_START: 2050,
    DELIVER_END: 2400,
    DONE: 2800,
} as const;

const BIRD_BASE = 48; // px

const amountFormatter = new Intl.NumberFormat('fr-FR');

export function WingFlight({
    logoRect,
    walletRect,
    amount,
    onCredit,
    onDone,
}: WingFlightProps) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const birdRef = useRef<HTMLImageElement>(null);
    const rafRef = useRef<number>(0);
    const startRef = useRef<number>(0);

    const creditedRef = useRef(false);
    const doneRef = useRef(false);

    const onCreditRef = useRef(onCredit);
    const onDoneRef = useRef(onDone);
    useEffect(() => {
        onCreditRef.current = onCredit;
    }, [onCredit]);
    useEffect(() => {
        onDoneRef.current = onDone;
    }, [onDone]);

    const particles = useRef<Particle[]>([]);
    const coins = useRef<Coin[]>([]);
    const coinsSpawned = useRef(false);

    const [showAmount, setShowAmount] = useState(false);

    const logoCx = logoRect.left + logoRect.width / 2;
    const logoCy = logoRect.top + logoRect.height / 2;
    const walletCx = walletRect.left + walletRect.width / 2;
    const walletCy = walletRect.top + walletRect.height / 2;
    const cx = window.innerWidth / 2;
    const cy = window.innerHeight / 2;

    useEffect(() => {
        const canvas = canvasRef.current;
        const bird = birdRef.current;

        if (!canvas || !bird) {
            return;
        }

        const dpr = window.devicePixelRatio || 1;
        const W = window.innerWidth;
        const H = window.innerHeight;
        canvas.width = W * dpr;
        canvas.height = H * dpr;
        canvas.style.width = `${W}px`;
        canvas.style.height = `${H}px`;
        const ctx = canvas.getContext('2d');

        if (!ctx) {
            return;
        }

        ctx.scale(dpr, dpr);

        const logoPt: Pt = { x: logoCx, y: logoCy };
        const centerPt: Pt = { x: cx, y: cy };
        const walletPt: Pt = { x: walletCx, y: walletCy };

        // Arc1 : logo → centre (arc vers le haut)
        const a1p1: Pt = { x: logoCx + (cx - logoCx) * 0.25, y: logoCy - 160 };
        const a1p2: Pt = { x: cx - 80, y: cy - 90 };

        // Arc2 : centre → wallet (piqué rapide)
        const a2p1: Pt = { x: cx + 70, y: cy - 110 };
        const a2p2: Pt = { x: walletCx - 50, y: walletCy - 30 };

        const spawnTrail = (x: number, y: number, n = 3) => {
            for (let i = 0; i < n; i++) {
                particles.current.push({
                    x: x + (Math.random() - 0.5) * 10,
                    y: y + (Math.random() - 0.5) * 10,
                    vx: (Math.random() - 0.5) * 2.5,
                    vy: Math.random() * 1.8 - 0.4,
                    life: 0,
                    maxLife: 350 + Math.random() * 350,
                    size: 2 + Math.random() * 3.5,
                    hue: 38 + Math.random() * 18,
                });
            }
        };

        const spawnCoins = (x: number, y: number) => {
            for (let i = 0; i < 16; i++) {
                const angle = (i / 16) * Math.PI * 2;
                const speed = 2.5 + Math.random() * 4.5;
                coins.current.push({
                    x,
                    y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed,
                    life: 0,
                    maxLife: 550,
                    size: 3 + Math.random() * 4.5,
                });
            }
        };

        let prevX = logoCx;
        let prevY = logoCy;

        // Tableaux stables pour la vie de l'effet — utilisés aussi par le
        // nettoyage, sans relire les refs après démontage.
        const particleList = particles.current;
        const coinList = coins.current;

        const tick = (now: number) => {
            if (startRef.current === 0) {
                startRef.current = now;
            }

            const elapsed = now - startRef.current;
            const DT = 16;

            ctx.clearRect(0, 0, W, H);

            let bx = logoCx,
                by = logoCy;
            let bScale = 1,
                bAngle = 0,
                bOpacity = 1;
            const flutter = 1 + 0.16 * Math.sin(elapsed * 0.027);

            if (elapsed < T.TREMBLE_END) {
                // Phase 1 : frémissement
                bx = logoCx + Math.sin(elapsed * 0.13) * 4.5;
                by = logoCy + Math.cos(elapsed * 0.19) * 3;
                bScale = 1 + Math.sin(elapsed * 0.016) * 0.09;
                bAngle = Math.sin(elapsed * 0.1) * 14;
            } else if (elapsed < T.ARC1_END) {
                // Phase 2 : envol vers le centre
                const t = easeInOut(pt(elapsed, T.ARC1_START, T.ARC1_END));
                const pos = cbez(t, logoPt, a1p1, a1p2, centerPt);
                bx = pos.x;
                by = pos.y;
                bScale = 1.15 + t * 0.35;
                const dx = bx - prevX,
                    dy = by - prevY;

                if (Math.abs(dx) + Math.abs(dy) > 0.5) {
                    bAngle = clamp(
                        ((Math.atan2(dy, dx) * 180) / Math.PI) * 0.4,
                        -25,
                        25,
                    );
                }

                spawnTrail(bx, by);
            } else if (elapsed < T.COLLECT_END) {
                // Phase 3 : collecte au centre
                bx = cx + Math.sin(elapsed * 0.022) * 5;
                by = cy + Math.cos(elapsed * 0.017) * 4;
                bScale = 1.55 + Math.sin(elapsed * 0.012) * 0.08;
                bAngle = Math.sin(elapsed * 0.016) * 10;

                if (!coinsSpawned.current) {
                    coinsSpawned.current = true;
                    spawnCoins(cx, cy);
                    setShowAmount(true);
                }
            } else if (elapsed < T.ARC2_END) {
                // Phase 4 : vol vers le wallet
                const t = easeInOut(pt(elapsed, T.ARC2_START, T.ARC2_END));
                const pos = cbez(t, centerPt, a2p1, a2p2, walletPt);
                bx = pos.x;
                by = pos.y;
                bScale = 1.3 - t * 0.55;
                const dx = bx - prevX,
                    dy = by - prevY;

                if (Math.abs(dx) + Math.abs(dy) > 0.5) {
                    bAngle = clamp(
                        ((Math.atan2(dy, dx) * 180) / Math.PI) * 0.4,
                        -25,
                        25,
                    );
                }

                spawnTrail(bx, by, 4);
            } else if (elapsed < T.DELIVER_END) {
                // Phase 5 : livraison dans le wallet
                const t = easeOut3(pt(elapsed, T.DELIVER_START, T.DELIVER_END));
                bx = walletCx;
                by = walletCy;
                bScale = 0.75 * (1 - t);
                bOpacity = 1 - t;

                if (!creditedRef.current) {
                    creditedRef.current = true;
                    onCreditRef.current();
                }

                spawnTrail(
                    bx + (Math.random() - 0.5) * 20,
                    by + (Math.random() - 0.5) * 10,
                    5,
                );
            } else {
                // Phase 6 : sparkle résiduel, oiseau invisible
                bOpacity = 0;
            }

            prevX = bx;
            prevY = by;

            const ps = particles.current;

            for (let i = ps.length - 1; i >= 0; i--) {
                const p = ps[i];
                p.life += DT;
                p.x += p.vx;
                p.y += p.vy;
                p.vy += 0.06;
                p.vx *= 0.97;

                if (p.life >= p.maxLife) {
                    ps.splice(i, 1);
                    continue;
                }

                const a = (1 - p.life / p.maxLife) * 0.85;
                ctx.globalAlpha = a;
                ctx.fillStyle = `hsl(${p.hue}, 90%, 60%)`;
                ctx.beginPath();
                ctx.arc(
                    p.x,
                    p.y,
                    p.size * (1 - (p.life / p.maxLife) * 0.4),
                    0,
                    Math.PI * 2,
                );
                ctx.fill();
            }

            const cs = coins.current;

            for (let i = cs.length - 1; i >= 0; i--) {
                const c = cs[i];
                c.life += DT;
                c.x += c.vx;
                c.y += c.vy;
                c.vx *= 0.91;
                c.vy *= 0.91;

                if (c.life >= c.maxLife) {
                    cs.splice(i, 1);
                    continue;
                }

                const lt = c.life / c.maxLife;
                const a = lt < 0.25 ? lt / 0.25 : 1 - (lt - 0.25) / 0.75;
                ctx.globalAlpha = a;
                ctx.shadowBlur = 8;
                ctx.shadowColor = '#F2C14E';
                ctx.fillStyle = '#F2C14E';
                ctx.beginPath();
                ctx.arc(c.x, c.y, c.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.shadowBlur = 0;
            }

            ctx.globalAlpha = 1;

            const bSize = BIRD_BASE * bScale;
            bird.style.cssText = `
                position:fixed;
                pointer-events:none;
                z-index:10000;
                left:${bx - bSize / 2}px;
                top:${by - bSize / 2}px;
                width:${bSize}px;
                height:${bSize}px;
                opacity:${bOpacity};
                transform:rotate(${bAngle}deg) scaleY(${flutter});
                filter:drop-shadow(0 0 14px rgba(242,193,78,0.9));
                will-change:transform,left,top;
            `;

            if (elapsed >= T.DONE) {
                if (!doneRef.current) {
                    doneRef.current = true;
                    ps.length = 0;
                    cs.length = 0;
                    onDoneRef.current();
                }

                return;
            }

            rafRef.current = requestAnimationFrame(tick);
        };

        rafRef.current = requestAnimationFrame(tick);

        return () => {
            cancelAnimationFrame(rafRef.current);
            particleList.length = 0;
            coinList.length = 0;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []); // exécuté une fois au montage — positions capturées par closure

    return (
        <>
            <style>{`
                @keyframes wpx-wp-burst {
                    0%   { opacity: 0;   transform: translate(-50%, -50%) scale(0.35); }
                    18%  { opacity: 1;   transform: translate(-50%, -65%) scale(1.28); }
                    48%  { opacity: 1;   transform: translate(-50%, -82%) scale(1.04); }
                    78%  { opacity: 0.7; transform: translate(-50%, -98%) scale(0.96); }
                    100% { opacity: 0;   transform: translate(-50%, -118%) scale(0.82); }
                }
                .wpx-wp-burst {
                    animation: wpx-wp-burst 1200ms cubic-bezier(0.23, 1, 0.32, 1) forwards;
                }
            `}</style>

            <canvas
                ref={canvasRef}
                style={{
                    position: 'fixed',
                    inset: 0,
                    pointerEvents: 'none',
                    zIndex: 9999,
                }}
            />

            <img
                ref={birdRef}
                src="/brand/wasplex-mascot.png"
                alt=""
                aria-hidden="true"
                style={{
                    position: 'fixed',
                    pointerEvents: 'none',
                    zIndex: 10000,
                    left: logoCx - BIRD_BASE / 2,
                    top: logoCy - BIRD_BASE / 2,
                    width: BIRD_BASE,
                    height: BIRD_BASE,
                    filter: 'drop-shadow(0 0 14px rgba(242,193,78,0.9))',
                    willChange: 'transform, left, top, opacity',
                }}
            />

            {showAmount && (
                <div
                    style={{
                        position: 'fixed',
                        left: '50%',
                        top: '50%',
                        pointerEvents: 'none',
                        zIndex: 10001,
                    }}
                    className="wpx-wp-burst"
                >
                    <span className="text-5xl font-black text-[#F2C14E] drop-shadow-[0_3px_16px_rgba(242,193,78,0.85)]">
                        +{amountFormatter.format(amount)} WP
                    </span>
                </div>
            )}
        </>
    );
}

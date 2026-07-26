import { Heart, Share2, Star } from 'lucide-react';

/**
 * Menu vertical du Feed (Lot 3 Phase A, décision de Koné 2026-07-26) —
 * placement et style repris de `wasplexmobile4.0/src/components/feed/SocialButtons.tsx`
 * (pile verticale, icône circulaire + compteur sous chaque bouton).
 * Comportement adapté à ce qui existe réellement côté backend : j'aime et
 * favori sont des bascules (re-cliquer retire, jamais désactivées après le
 * premier clic — contrairement à l'ancien prototype), le partage
 * n'affiche aucun état actif (chaque tap est un nouvel événement). Pas de
 * commentaires ni de contrôle du son ici : hors périmètre de ce lot
 * (Phase B, obligations de modération).
 */

type Props = {
    likes: number;
    favorites: number;
    shares: number;
    liked: boolean;
    favorited: boolean;
    onLike: () => void;
    onFavorite: () => void;
    onShare: () => void;
};

function formatCount(n: number): string {
    return n > 999 ? `${(n / 1000).toFixed(1)}k` : String(n);
}

function SocialButton({
    Icon,
    label,
    ariaLabel,
    onClick,
    active,
    activeColor,
}: {
    Icon: typeof Heart;
    label: string;
    ariaLabel: string;
    onClick: () => void;
    active?: boolean;
    activeColor?: string;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={ariaLabel}
            aria-pressed={active}
            className="flex flex-col items-center gap-0.5 transition active:scale-90"
        >
            <span
                className={[
                    'flex h-10 w-10 items-center justify-center rounded-full bg-black/40 ring-1 backdrop-blur-sm transition',
                    active ? 'ring-white/20' : 'ring-white/10',
                ].join(' ')}
            >
                <Icon
                    size={20}
                    strokeWidth={2}
                    className={active ? activeColor : 'text-white'}
                    fill={active ? 'currentColor' : 'none'}
                />
            </span>
            <span
                className={[
                    'text-[11px] font-semibold drop-shadow',
                    active && activeColor ? activeColor : 'text-white/90',
                ].join(' ')}
            >
                {label}
            </span>
        </button>
    );
}

export function SocialButtons({
    likes,
    favorites,
    shares,
    liked,
    favorited,
    onLike,
    onFavorite,
    onShare,
}: Props) {
    return (
        <div className="absolute right-3 bottom-36 z-20 flex flex-col gap-3">
            <SocialButton
                Icon={Heart}
                label={formatCount(likes)}
                ariaLabel={liked ? "Retirer j'aime" : "J'aime"}
                active={liked}
                activeColor="text-[#FF6B61]"
                onClick={onLike}
            />
            <SocialButton
                Icon={Star}
                label={formatCount(favorites)}
                ariaLabel={
                    favorited ? 'Retirer des favoris' : 'Mettre en favori'
                }
                active={favorited}
                activeColor="text-[#F2C14E]"
                onClick={onFavorite}
            />
            <SocialButton
                Icon={Share2}
                label={formatCount(shares)}
                ariaLabel="Partager"
                onClick={onShare}
            />
        </div>
    );
}

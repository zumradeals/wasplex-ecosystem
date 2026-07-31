import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    Building2,
    Image,
    LayoutDashboard,
    LifeBuoy,
    Megaphone,
    PiggyBank,
    Radar,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import { toast } from 'sonner';
import WasplexMascot from '@/components/wasplex-mascot';
import { dashboard } from '@/routes';
import advertising from '@/routes/advertising';

/**
 * Ordre et priorité imposés par DS-0001 §19 (« Priorité à campagne,
 * budget, audience autorisée, modération, résultats et facturation ») et
 * UX-0001 §8 (« Navigation professionnelle — Annonceur »). « Assistance »
 * reste désactivée : aucun canal de support annonceur n'existe encore
 * (DS-0001 §23 — un onglet indisponible explique la condition).
 */
const NAV_ITEMS = [
    {
        key: 'overview',
        label: "Vue d'ensemble",
        href: advertising.overview().url,
        icon: LayoutDashboard,
    },
    {
        key: 'campaigns',
        label: 'Campagnes',
        href: advertising.campaigns.index().url,
        icon: Megaphone,
    },
    {
        key: 'audiences',
        label: 'Audiences',
        href: advertising.audiences().url,
        icon: Radar,
    },
    {
        key: 'creations',
        label: 'Créations',
        href: advertising.creations().url,
        icon: Image,
    },
    {
        key: 'wallet',
        label: 'Wallet',
        href: advertising.wallet().url,
        icon: Wallet,
    },
    {
        key: 'budget',
        label: 'Budget',
        href: advertising.budget().url,
        icon: PiggyBank,
    },
    {
        key: 'reports',
        label: 'Rapports',
        href: advertising.reports().url,
        icon: TrendingUp,
    },
    {
        key: 'billing',
        label: 'Facturation',
        href: advertising.billing().url,
        icon: Banknote,
    },
    {
        key: 'organization',
        label: 'Organisation et accès',
        href: advertising.organization().url,
        icon: Building2,
    },
] as const;

function isActive(href: string, currentUrl: string): boolean {
    return (
        currentUrl === href ||
        currentUrl.startsWith(`${href}/`) ||
        currentUrl.startsWith(`${href}?`)
    );
}

export function AdvertiserSidebarNav({
    onNavigate,
}: {
    onNavigate?: () => void;
}) {
    const { url } = usePage();

    return (
        <div className="flex h-full flex-col">
            <Link
                href={dashboard()}
                onClick={onNavigate}
                className="flex items-center gap-2.5 px-5 py-5"
                aria-label="Retour à Wasplex"
            >
                <WasplexMascot className="h-11 w-11 shrink-0" />
                <p className="min-w-0 truncate text-xs font-medium text-[#8FA3BC]">
                    Espace annonceur
                </p>
            </Link>

            <div className="px-3">
                <Link
                    href={advertising.campaigns.create().url}
                    onClick={onNavigate}
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-[#C75100] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#A84300]"
                >
                    + Nouvelle campagne
                </Link>
            </div>

            <nav
                aria-label="Navigation annonceur"
                className="mt-4 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4"
            >
                {NAV_ITEMS.map((item) => {
                    const active = isActive(item.href, url);
                    const Icon = item.icon;

                    return (
                        <Link
                            key={item.key}
                            href={item.href}
                            onClick={onNavigate}
                            aria-current={active ? 'page' : undefined}
                            className={[
                                'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                active
                                    ? 'bg-white/10 text-white'
                                    : 'text-[#B7C4D6] hover:bg-white/5 hover:text-white',
                            ].join(' ')}
                        >
                            <Icon size={18} className="shrink-0" />
                            <span className="truncate">{item.label}</span>
                        </Link>
                    );
                })}

                <button
                    type="button"
                    onClick={() =>
                        toast('Assistance annonceur — bientôt disponible')
                    }
                    className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-[#B7C4D6]/50"
                >
                    <LifeBuoy size={18} className="shrink-0" />
                    <span className="truncate">Assistance</span>
                    <span className="ml-auto shrink-0 text-[10px] font-normal text-[#B7C4D6]/40">
                        Bientôt
                    </span>
                </button>
            </nav>

            <div className="border-t border-white/10 px-3 py-3">
                <Link
                    href={dashboard()}
                    onClick={onNavigate}
                    className="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-[#8FA3BC] transition-colors hover:bg-white/5 hover:text-white"
                >
                    ← Retour à mon espace Wasplex
                </Link>
            </div>
        </div>
    );
}

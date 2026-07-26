import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    FileWarning,
    Inbox,
    KeyRound,
    Landmark,
    Megaphone,
    ScrollText,
    Settings,
    ShieldAlert,
    Wallet,
} from 'lucide-react';
import { toast } from 'sonner';
import WasplexMascot from '@/components/wasplex-mascot';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';

/**
 * Navigation par files de responsabilité (UX-0001 §8 « Administration
 * Wasplex »), dans l'ordre du texte. Deux écrans réellement construits au
 * jour de ce lot : « À traiter » et « Finance et rapprochement » et
 * « Publicité et modération ». Les autres destinations que le texte
 * décide n'ont aujourd'hui aucun module ni capacité de lecture déclarée
 * (Fonds Social, Cartes, Institutions, Alertes, Configurations, Accès,
 * Audit) : elles restent visibles — la navigation ne cache jamais ce qui
 * est prévu — mais désactivées et annoncées comme telles (DS-0001 §23 « un
 * onglet indisponible explique la condition »), jamais simulées avec de
 * fausses données.
 */
const AVAILABLE_ITEMS = [
    {
        key: 'todo',
        label: 'À traiter',
        href: admin.moderation().url,
        icon: Inbox,
    },
    {
        key: 'finance',
        label: 'Finance et rapprochement',
        href: admin.finance().url,
        icon: Landmark,
    },
    {
        key: 'advertising',
        label: 'Publicité et modération',
        href: admin.advertisingModeration().url,
        icon: Megaphone,
    },
] as const;

const UNAVAILABLE_ITEMS = [
    { key: 'risks', label: 'Risques et incidents', icon: ShieldAlert },
    { key: 'alerts', label: 'Alertes et institutions', icon: FileWarning },
    { key: 'social-fund', label: 'Fonds Social', icon: Building2 },
    { key: 'cards', label: 'Cartes et partenaires', icon: Wallet },
    { key: 'settings', label: 'Configurations', icon: Settings },
    { key: 'access', label: 'Accès', icon: KeyRound },
    { key: 'audit', label: 'Audit', icon: ScrollText },
] as const;

function isActive(href: string, currentUrl: string): boolean {
    return (
        currentUrl === href ||
        currentUrl.startsWith(`${href}/`) ||
        currentUrl.startsWith(`${href}?`)
    );
}

export function AdminSidebarNav({ onNavigate }: { onNavigate?: () => void }) {
    const { url } = usePage();

    return (
        <div className="flex h-full flex-col">
            <Link
                href={dashboard()}
                onClick={onNavigate}
                className="flex items-center gap-2.5 px-5 py-5"
                aria-label="Retour à Wasplex"
            >
                <WasplexMascot className="h-8 w-8 shrink-0" />
                <div className="min-w-0 leading-tight">
                    <p className="truncate text-sm font-bold text-white">
                        Wasplex
                    </p>
                    <p className="truncate text-[11px] font-medium text-[#8FA3BC]">
                        Personnel Wasplex
                    </p>
                </div>
            </Link>

            <nav
                aria-label="Files de responsabilité"
                className="mt-2 flex-1 space-y-0.5 overflow-y-auto px-3 pb-4"
            >
                {AVAILABLE_ITEMS.map((item) => {
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

                <div className="mt-3 border-t border-white/10 pt-3">
                    {UNAVAILABLE_ITEMS.map((item) => {
                        const Icon = item.icon;

                        return (
                            <button
                                key={item.key}
                                type="button"
                                onClick={() =>
                                    toast(`${item.label} — bientôt disponible`)
                                }
                                className="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium text-[#B7C4D6]/50"
                            >
                                <Icon size={18} className="shrink-0" />
                                <span className="truncate">{item.label}</span>
                                <span className="ml-auto shrink-0 text-[10px] font-normal text-[#B7C4D6]/40">
                                    Bientôt
                                </span>
                            </button>
                        );
                    })}
                </div>
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

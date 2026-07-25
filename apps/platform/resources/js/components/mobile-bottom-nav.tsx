import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    HandHeart,
    Tv2,
    User,
    Wallet,
} from 'lucide-react';
import { dashboard } from '@/routes';
import wallet from '@/routes/wallet';

/**
 * Navigation mobile principale — DS-0001 §14, UX-0001 §7.
 * Cinq destinations : Feed / Fonds / Wallet (central élevé) / Alertes / Mon espace.
 * « Fonds » remplace « Social » (UX-0001 §7 : éviter confusion réseau social).
 * Wallet central en orange — DS-0001 §14.
 * Les onglets sans route active affichent leur état indisponible (DS-0001 §23 :
 * « un onglet indisponible explique la condition »).
 */

type NavItem = {
    key: string;
    label: string;
    href?: string;
    icon: React.FC<{ size?: number; className?: string }>;
    disabled?: boolean;
    isWallet?: boolean;
};

const items: NavItem[] = [
    {
        key: 'feed',
        label: 'Feed',
        href: dashboard(),
        icon: Tv2,
    },
    {
        key: 'fonds',
        label: 'Fonds',
        icon: HandHeart,
        disabled: true,
    },
    {
        key: 'wallet',
        label: 'Wallet',
        href: wallet.show(),
        icon: Wallet,
        isWallet: true,
    },
    {
        key: 'alertes',
        label: 'Alertes',
        icon: Bell,
        disabled: true,
    },
    {
        key: 'espace',
        label: 'Mon espace',
        icon: User,
        disabled: true,
    },
];

function isActive(href: string | undefined, currentUrl: string): boolean {
    if (!href) {
return false;
}

    return currentUrl.startsWith(href);
}

export default function MobileBottomNav() {
    const { url } = usePage();

    return (
        <nav
            aria-label="Navigation principale"
            className="fixed bottom-0 left-0 right-0 z-50 border-t border-[#35506D] bg-[#0E2542] pb-safe"
            style={{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }}
        >
            <ul className="flex h-16 items-stretch">
                {items.map((item) => {
                    const active = isActive(item.href, url);
                    const Icon = item.icon;

                    if (item.isWallet) {
                        return (
                            <li key={item.key} className="flex flex-1 items-center justify-center">
                                {item.href ? (
                                    <Link
                                        href={item.href}
                                        aria-label={item.label}
                                        aria-current={active ? 'page' : undefined}
                                        className="flex flex-col items-center gap-0.5 -mt-5"
                                    >
                                        <span
                                            className={[
                                                'flex h-12 w-12 items-center justify-center rounded-full shadow-lg',
                                                active
                                                    ? 'bg-[#C75100]'
                                                    : 'bg-[#C75100]',
                                            ].join(' ')}
                                            style={{
                                                boxShadow: '0 0 0 3px #0E2542, 0 4px 12px rgba(199,81,0,0.5)',
                                            }}
                                        >
                                            <Icon size={22} className="text-white" />
                                        </span>
                                        <span className="text-[10px] font-medium text-[#A9B7C8] mt-0.5">
                                            {item.label}
                                        </span>
                                    </Link>
                                ) : null}
                            </li>
                        );
                    }

                    if (item.disabled || !item.href) {
                        return (
                            <li key={item.key} className="flex flex-1 items-center justify-center">
                                <button
                                    disabled
                                    aria-label={`${item.label} — bientôt disponible`}
                                    title="Bientôt disponible"
                                    className="flex flex-col items-center gap-0.5 py-2 opacity-40 cursor-not-allowed"
                                >
                                    <Icon size={22} className="text-[#A9B7C8]" />
                                    <span className="text-[10px] font-medium text-[#A9B7C8]">
                                        {item.label}
                                    </span>
                                </button>
                            </li>
                        );
                    }

                    return (
                        <li key={item.key} className="flex flex-1 items-center justify-center">
                            <Link
                                href={item.href}
                                aria-label={item.label}
                                aria-current={active ? 'page' : undefined}
                                className="flex flex-col items-center gap-0.5 py-2"
                            >
                                <Icon
                                    size={22}
                                    className={
                                        active
                                            ? 'text-[#4FA3FF]'
                                            : 'text-[#A9B7C8]'
                                    }
                                />
                                <span
                                    className={[
                                        'text-[10px] font-medium',
                                        active
                                            ? 'text-[#4FA3FF]'
                                            : 'text-[#A9B7C8]',
                                    ].join(' ')}
                                >
                                    {item.label}
                                </span>
                                {active && (
                                    <span className="absolute top-0 h-0.5 w-8 rounded-full bg-[#4FA3FF]" />
                                )}
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

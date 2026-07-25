import { Link, usePage } from '@inertiajs/react';
import MobileBottomNav from '@/components/mobile-bottom-nav';
import WasplexMascot from '@/components/wasplex-mascot';
import { dashboard } from '@/routes';

/**
 * Layout mobile principal pour les utilisateurs authentifiés.
 * DS-0001 §14 : navigation à cinq destinations maximum.
 * DS-0001 §13 : responsive, mobile-first à partir de 320 px.
 * UX-0001 §7 : espace utilisateur mobile-first.
 */
export default function MobileLayout({
    children,
    showHeader = true,
    fullScreen = false,
}: {
    children: React.ReactNode;
    showHeader?: boolean;
    fullScreen?: boolean;
}) {
    const page = usePage<{ auth?: { user?: { name?: string } }; wallet_balance?: { available?: number; currency?: string } }>();
    const walletBalance = page.props.wallet_balance;

    return (
        <div className="flex min-h-svh flex-col bg-[#07182D] text-[#F5F8FC]">
            {showHeader && (
                <header className="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-[#35506D] bg-[#07182D]/95 px-4 backdrop-blur-sm">
                    <Link
                        href={dashboard()}
                        className="flex items-center gap-2"
                        aria-label="Accueil Wasplex"
                    >
                        <WasplexMascot className="h-8 w-8" />
                        <span className="text-base font-bold tracking-tight text-white">
                            Wasplex
                        </span>
                    </Link>

                    {/* Compteur WP — DS-0001 §17, UX-0001 §10 */}
                    {walletBalance !== undefined && walletBalance !== null && (
                        <div
                            className="flex items-center gap-1.5 rounded-full bg-[#173251] px-3 py-1.5 text-sm font-semibold"
                            aria-label={`Solde disponible : ${walletBalance.available ?? 0} WP`}
                        >
                            <span className="h-2 w-2 rounded-full bg-[#FF9A3D]" aria-hidden="true" />
                            <span className="text-white">
                                {new Intl.NumberFormat('fr-FR').format(
                                    walletBalance.available ?? 0,
                                )}{' '}
                                <span className="text-[#A9B7C8]">WP</span>
                            </span>
                        </div>
                    )}
                </header>
            )}

            <main className={['flex-1', fullScreen ? '' : 'pb-20'].join(' ')}>
                {children}
            </main>

            <MobileBottomNav />
        </div>
    );
}

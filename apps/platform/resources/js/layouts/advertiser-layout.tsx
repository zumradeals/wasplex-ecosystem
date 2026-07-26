import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { AdvertiserSidebarNav } from '@/components/advertiser/advertiser-sidebar-nav';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import type { User } from '@/types/auth';

/**
 * Portail professionnel annonceur (DS-0001 §19, UX-0001 §8/§9). Desktop
 * primaire — navigation latérale, tableaux et panneaux, jamais un mobile
 * étiré (DS-0001 §13) : la navigation se replie dans un tiroir sous
 * `lg`, seule concession mobile pour rester consultable, pas la
 * référence.
 *
 * Vouvoiement obligatoire (DS-0001 §28 « Les portails annonceurs [...]
 * utilisent vous ») — tout le texte de ce layout et des écrans qu'il
 * enveloppe s'adresse à « vous », jamais à « tu ».
 */
export default function AdvertiserLayout({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    const [drawerOpen, setDrawerOpen] = useState(false);
    const page = usePage<{ auth: { user: User } }>();
    const user = page.props.auth.user;
    const getInitials = useInitials();

    function handleLogout() {
        router.post(logout().url);
    }

    return (
        <div className="flex min-h-svh bg-[var(--bg-canvas)] text-[var(--text-primary)]">
            {/* Navigation latérale — desktop */}
            <aside className="hidden w-64 shrink-0 bg-[var(--brand-navy)] lg:block">
                <div className="sticky top-0 h-svh">
                    <AdvertiserSidebarNav />
                </div>
            </aside>

            {/* Tiroir de navigation — mobile/tablette */}
            <Sheet open={drawerOpen} onOpenChange={setDrawerOpen}>
                <SheetContent
                    side="left"
                    className="w-72 border-none bg-[var(--brand-navy)] p-0 [&>button]:text-white"
                >
                    <SheetTitle className="sr-only">
                        Navigation annonceur
                    </SheetTitle>
                    <AdvertiserSidebarNav
                        onNavigate={() => setDrawerOpen(false)}
                    />
                </SheetContent>
            </Sheet>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-[var(--border-default)] bg-[var(--bg-surface)] px-4 py-3 sm:px-6">
                    <button
                        type="button"
                        onClick={() => setDrawerOpen(true)}
                        aria-label="Ouvrir la navigation"
                        className="-ml-1 flex h-9 w-9 items-center justify-center rounded-lg text-[var(--text-secondary)] hover:bg-[var(--bg-raised)] lg:hidden"
                    >
                        <Menu size={20} />
                    </button>

                    <div className="min-w-0 flex-1">
                        <h1 className="truncate text-lg font-bold text-[var(--text-primary)]">
                            {title}
                        </h1>
                        {description && (
                            <p className="truncate text-xs text-[var(--text-secondary)]">
                                {description}
                            </p>
                        )}
                    </div>

                    <div className="flex shrink-0 items-center gap-3">
                        <Link
                            href="/me"
                            className="hidden items-center gap-2 rounded-full py-1 pr-1 pl-2 text-sm font-medium text-[var(--text-primary)] hover:bg-[var(--bg-raised)] sm:flex"
                        >
                            <span className="max-w-[10rem] truncate">
                                {user.name}
                            </span>
                            <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-blue)] text-xs font-bold text-white">
                                {getInitials(user.name)}
                            </span>
                        </Link>

                        <button
                            type="button"
                            onClick={handleLogout}
                            aria-label="Se déconnecter"
                            title="Se déconnecter"
                            className="flex h-9 w-9 items-center justify-center rounded-lg text-[var(--text-secondary)] hover:bg-[var(--bg-raised)]"
                        >
                            <LogOut size={18} />
                        </button>
                    </div>
                </header>

                <main className="flex-1 p-4 sm:p-6">{children}</main>
            </div>
        </div>
    );
}

import { router, usePage } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import type { ReactNode } from 'react';
import WasplexMascot from '@/components/wasplex-mascot';
import { logout } from '@/routes';
import type { User } from '@/types/auth';

/**
 * Portail des institutions Wasplex (ecosystem/institutions/01 §10),
 * desktop-first — jamais « portail agents » ni « espace agents »
 * (§10 : libellés admis « Portail des institutions Wasplex »,
 * « Espace institutionnel »). Distinct du portail personnel Wasplex
 * (`AdminLayout`) : une organisation différente, des capacités
 * différentes, aucun rôle générique.
 */
export default function InstitutionalLayout({
    organizationName,
    children,
}: {
    organizationName?: string;
    children: ReactNode;
}) {
    const page = usePage<{ auth: { user: User } }>();
    const user = page.props.auth.user;

    function handleLogout() {
        router.post(logout().url);
    }

    return (
        <div className="min-h-svh bg-[var(--bg-canvas)] text-[var(--text-primary)]">
            <header className="flex items-center justify-between border-b border-[var(--border-default)] bg-[var(--bg-surface)] px-6 py-3">
                <div className="flex items-center gap-3">
                    <WasplexMascot className="h-9 w-9" />
                    <div>
                        <p className="text-sm font-bold text-[var(--text-primary)]">
                            Espace institutionnel
                        </p>
                        {organizationName && (
                            <p className="text-xs text-[var(--text-secondary)]">
                                {organizationName}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <span className="text-sm text-[var(--text-secondary)]">
                        {user.name}
                    </span>
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

            <main className="mx-auto max-w-5xl p-6">{children}</main>
        </div>
    );
}

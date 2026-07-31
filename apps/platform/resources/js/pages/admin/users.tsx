import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';

type AccountState = 'invited' | 'active' | 'suspended' | 'closed';

type AdminUser = {
    id: number;
    public_id: string;
    name: string;
    email: string;
    email_verified: boolean;
    account_state: AccountState;
    created_at: string | null;
};

type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    previous_url: string | null;
    next_url: string | null;
};

const inputClass =
    'w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none';

const STATE_LABELS: Record<AccountState, string> = {
    invited: 'invité',
    active: 'actif',
    suspended: 'suspendu',
    closed: 'clôturé',
};

function CreateUserForm({
    onCreated,
}: {
    onCreated: (user: AdminUser) => void;
}) {
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<AdminUser>('/admin/users', {
            name,
            email,
            password,
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "La création n'a pas abouti. Vérifiez les champs (e-mail valide et non déjà utilisé, mot de passe d'au moins 8 caractères).",
            );

            return;
        }

        onCreated(result.data);
        setName('');
        setEmail('');
        setPassword('');
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            {error && (
                <p className="text-sm text-[var(--status-danger)]">{error}</p>
            )}

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Nom complet
                    </Label>
                    <input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        className={inputClass}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Adresse e-mail
                    </Label>
                    <input
                        type="email"
                        value={email}
                        onChange={(event) => setEmail(event.target.value)}
                        className={inputClass}
                    />
                </div>
                <div className="space-y-1.5">
                    <Label className="text-xs font-medium text-[var(--text-primary)]">
                        Mot de passe initial
                    </Label>
                    <input
                        type="text"
                        value={password}
                        onChange={(event) => setPassword(event.target.value)}
                        className={inputClass}
                    />
                </div>
            </div>

            <Button type="submit" disabled={submitting}>
                {submitting ? 'Création...' : 'Créer le compte'}
            </Button>
        </form>
    );
}

function AccountStateActions({
    user,
    onChanged,
}: {
    user: AdminUser;
    onChanged: (user: AdminUser) => void;
}) {
    const [submitting, setSubmitting] = useState(false);

    async function setState(accountState: AccountState) {
        setSubmitting(true);

        const result = await postJson<AdminUser>(
            `/admin/users/${user.id}/state`,
            {
                account_state: accountState,
            },
        );

        setSubmitting(false);

        if (result.ok) {
            onChanged(result.data);
        }
    }

    return (
        <div className="flex gap-2">
            {user.account_state !== 'active' && (
                <Button
                    variant="outline"
                    size="sm"
                    disabled={submitting}
                    onClick={() => setState('active')}
                >
                    Réactiver
                </Button>
            )}
            {user.account_state !== 'suspended' && (
                <Button
                    variant="outline"
                    size="sm"
                    disabled={submitting}
                    onClick={() => setState('suspended')}
                >
                    Suspendre
                </Button>
            )}
            {user.account_state !== 'closed' && (
                <Button
                    variant="outline"
                    size="sm"
                    disabled={submitting}
                    onClick={() => setState('closed')}
                >
                    Clôturer
                </Button>
            )}
        </div>
    );
}

function UserRow({
    user,
    onChanged,
}: {
    user: AdminUser;
    onChanged: (user: AdminUser) => void;
}) {
    const stateVariant =
        user.account_state === 'active'
            ? 'default'
            : user.account_state === 'suspended'
              ? 'outline'
              : 'outline';

    return (
        <div className="flex flex-col gap-3 rounded-lg border border-[var(--border-default)] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <p className="text-sm font-medium text-[var(--text-primary)]">
                    {user.name}
                </p>
                <p className="text-xs text-[var(--text-secondary)]">
                    {user.email}
                    {!user.email_verified && ' · e-mail non vérifié'}
                </p>
            </div>
            <div className="flex items-center gap-3">
                <Badge variant={stateVariant}>
                    {STATE_LABELS[user.account_state]}
                </Badge>
                <AccountStateActions user={user} onChanged={onChanged} />
            </div>
        </div>
    );
}

export default function AdminUsers({
    access,
    users,
    pagination,
    search,
}: {
    access: AdminAccess;
    users: AdminUser[];
    pagination: Pagination;
    search: string | null;
}) {
    const [list, setList] = useState(users);
    const [searchInput, setSearchInput] = useState(search ?? '');

    function upsert(updated: AdminUser) {
        setList((current) => {
            const withoutUpdated = current.filter(
                (entry) => entry.id !== updated.id,
            );

            return [updated, ...withoutUpdated];
        });
    }

    function replace(updated: AdminUser) {
        setList((current) =>
            current.map((entry) => (entry.id === updated.id ? updated : entry)),
        );
    }

    function submitSearch(event: React.FormEvent) {
        event.preventDefault();
        router.get('/admin/users', searchInput ? { q: searchInput } : {}, {
            preserveState: true,
        });
    }

    return (
        <AdminLayout
            title="Utilisateurs"
            description="Créer un compte et gérer son état — actif, suspendu, clôturé (identity.manage_users)."
        >
            <Head title="Personnel — Utilisateurs" />

            <div className="space-y-6">
                <AdminAccessGate access={access}>
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Créer un compte</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <CreateUserForm onCreated={upsert} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Comptes existants</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <form
                                    onSubmit={submitSearch}
                                    className="flex gap-2"
                                >
                                    <input
                                        value={searchInput}
                                        onChange={(event) =>
                                            setSearchInput(event.target.value)
                                        }
                                        placeholder="Rechercher par nom ou e-mail"
                                        className={inputClass}
                                    />
                                    <Button type="submit" variant="outline">
                                        Rechercher
                                    </Button>
                                </form>

                                {list.length === 0 ? (
                                    <p className="text-sm text-[var(--text-secondary)]">
                                        Aucun compte trouvé.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {list.map((user) => (
                                            <UserRow
                                                key={user.id}
                                                user={user}
                                                onChanged={replace}
                                            />
                                        ))}
                                    </div>
                                )}

                                {pagination.last_page > 1 && (
                                    <div className="flex items-center justify-between border-t border-[var(--border-default)] pt-3">
                                        <p className="text-xs text-[var(--text-secondary)]">
                                            Page {pagination.current_page} sur{' '}
                                            {pagination.last_page} —{' '}
                                            {pagination.total} comptes
                                        </p>
                                        <div className="flex gap-2">
                                            {pagination.previous_url && (
                                                <Link
                                                    href={
                                                        pagination.previous_url
                                                    }
                                                    className="text-xs text-[var(--brand-blue)] underline underline-offset-2"
                                                >
                                                    Précédent
                                                </Link>
                                            )}
                                            {pagination.next_url && (
                                                <Link
                                                    href={pagination.next_url}
                                                    className="text-xs text-[var(--brand-blue)] underline underline-offset-2"
                                                >
                                                    Suivant
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </>
                </AdminAccessGate>
            </div>
        </AdminLayout>
    );
}

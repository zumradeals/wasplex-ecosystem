import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { AdminAccessGate } from '@/components/admin/admin-access-gate';
import type { AdminAccess } from '@/components/admin/admin-access-gate';
import AdminLayout from '@/layouts/admin-layout';
import { postJson } from '@/lib/api';

const CREDENTIALS_URL = '/admin/wallet-deposits/credentials';

type Credentials = {
    base_url: string | null;
    api_key_configured: boolean;
    api_secret_configured: boolean;
    webhook_secret_configured: boolean;
    updated_at: string | null;
};

// `wallet_deposit.manage_credentials` est `risk_class = critical` : son
// activation exige une session « strong » (step-up mot de passe), même
// palier que `campaign.fund`/`event.accept` (voir moderation.tsx).
function StepUpNotice() {
    return (
        <div className="rounded-lg border border-[var(--status-danger)]/30 bg-[var(--status-danger)]/10 px-4 py-3 text-sm text-[var(--status-danger)]">
            <p className="font-semibold">
                Confirmation de mot de passe requise
            </p>
            <p className="mt-1">
                Cette action exige une session renforcée. Confirmez votre mot
                de passe puis réessayez.{' '}
                <a
                    href="/user/confirm-password"
                    className="underline underline-offset-2"
                >
                    Confirmer mon mot de passe
                </a>
            </p>
        </div>
    );
}

function ConfiguredBadge({ configured }: { configured: boolean }) {
    return (
        <span
            className={
                configured
                    ? 'rounded-full bg-[var(--status-success)]/20 px-2 py-0.5 text-[10px] font-semibold text-[var(--status-success)]'
                    : 'rounded-full bg-[var(--status-warning)]/20 px-2 py-0.5 text-[10px] font-semibold text-[var(--status-warning)]'
            }
        >
            {configured ? 'configurée' : 'non configurée'}
        </span>
    );
}

function CredentialsForm({ credentials }: { credentials: Credentials }) {
    const [baseUrl, setBaseUrl] = useState(credentials.base_url ?? '');
    const [apiKey, setApiKey] = useState('');
    const [apiSecret, setApiSecret] = useState('');
    const [webhookSecret, setWebhookSecret] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<'step_up' | 'other' | null>(null);
    const [saved, setSaved] = useState<Credentials | null>(null);

    const current = saved ?? credentials;

    async function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<Credentials & { reason?: string }>(
            CREDENTIALS_URL,
            {
                base_url: baseUrl,
                api_key: apiKey || null,
                api_secret: apiSecret || null,
                webhook_secret: webhookSecret || null,
            },
        );

        setSubmitting(false);

        if (!result.ok) {
            const data = result.data as { reason?: string } | null;
            setError(
                data?.reason === 'session_assurance_insufficient'
                    ? 'step_up'
                    : 'other',
            );

            return;
        }

        setSaved(result.data);
        setApiKey('');
        setApiSecret('');
        setWebhookSecret('');
    }

    return (
        <div className="space-y-6">
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className="rounded-lg border border-[var(--border-default)] p-3">
                    <dt className="text-xs text-[var(--text-secondary)]">
                        Clé API
                    </dt>
                    <dd className="mt-1">
                        <ConfiguredBadge
                            configured={current.api_key_configured}
                        />
                    </dd>
                </div>
                <div className="rounded-lg border border-[var(--border-default)] p-3">
                    <dt className="text-xs text-[var(--text-secondary)]">
                        Secret API
                    </dt>
                    <dd className="mt-1">
                        <ConfiguredBadge
                            configured={current.api_secret_configured}
                        />
                    </dd>
                </div>
                <div className="rounded-lg border border-[var(--border-default)] p-3">
                    <dt className="text-xs text-[var(--text-secondary)]">
                        Secret webhook
                    </dt>
                    <dd className="mt-1">
                        <ConfiguredBadge
                            configured={current.webhook_secret_configured}
                        />
                    </dd>
                </div>
            </dl>

            {current.updated_at && (
                <p className="text-xs text-[var(--text-secondary)]">
                    Dernière mise à jour le{' '}
                    {new Date(current.updated_at).toLocaleString('fr-FR')}
                </p>
            )}

            <form onSubmit={handleSubmit} className="space-y-4">
                {error === 'step_up' && <StepUpNotice />}
                {error === 'other' && (
                    <p className="text-sm text-[var(--status-danger)]">
                        L'enregistrement n'a pas abouti. Vérifiez les valeurs
                        saisies puis réessayez.
                    </p>
                )}
                {saved && !error && (
                    <p className="text-sm text-[var(--status-success)]">
                        Clés enregistrées.
                    </p>
                )}

                <label className="block space-y-1">
                    <span className="text-xs font-medium text-[var(--text-primary)]">
                        URL de base GeniusPay
                    </span>
                    <input
                        type="text"
                        required
                        value={baseUrl}
                        onChange={(event) => setBaseUrl(event.target.value)}
                        className="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                    />
                </label>

                <label className="block space-y-1">
                    <span className="text-xs font-medium text-[var(--text-primary)]">
                        Clé API
                    </span>
                    <input
                        type="password"
                        autoComplete="off"
                        placeholder={
                            current.api_key_configured
                                ? 'Laisser vide pour conserver la valeur actuelle'
                                : 'Aucune valeur configurée'
                        }
                        value={apiKey}
                        onChange={(event) => setApiKey(event.target.value)}
                        className="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                    />
                </label>

                <label className="block space-y-1">
                    <span className="text-xs font-medium text-[var(--text-primary)]">
                        Secret API
                    </span>
                    <input
                        type="password"
                        autoComplete="off"
                        placeholder={
                            current.api_secret_configured
                                ? 'Laisser vide pour conserver la valeur actuelle'
                                : 'Aucune valeur configurée'
                        }
                        value={apiSecret}
                        onChange={(event) => setApiSecret(event.target.value)}
                        className="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                    />
                </label>

                <label className="block space-y-1">
                    <span className="text-xs font-medium text-[var(--text-primary)]">
                        Secret webhook
                    </span>
                    <input
                        type="password"
                        autoComplete="off"
                        placeholder={
                            current.webhook_secret_configured
                                ? 'Laisser vide pour conserver la valeur actuelle'
                                : 'Aucune valeur configurée'
                        }
                        value={webhookSecret}
                        onChange={(event) =>
                            setWebhookSecret(event.target.value)
                        }
                        className="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-canvas)] px-3 py-2 text-sm text-[var(--text-primary)] focus:border-[var(--brand-blue)] focus:outline-none"
                    />
                </label>

                <button
                    type="submit"
                    disabled={submitting}
                    className="rounded-lg bg-[var(--brand-blue)] px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50"
                >
                    {submitting ? 'Enregistrement...' : 'Enregistrer'}
                </button>
            </form>
        </div>
    );
}

export default function AdminWalletDepositCredentials({
    access,
    credentials,
}: {
    access: AdminAccess;
    credentials: Credentials | null;
}) {
    return (
        <AdminLayout
            title="Dépôts Wallet — clés GeniusPay"
            description="Configuration des identifiants du prestataire de dépôt Wallet (wallet_deposit.manage_credentials)."
        >
            <Head title="Personnel — Clés GeniusPay" />

            <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-5">
                <AdminAccessGate access={access}>
                    {credentials && <CredentialsForm credentials={credentials} />}
                </AdminAccessGate>
            </div>
        </AdminLayout>
    );
}

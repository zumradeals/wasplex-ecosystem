import { Head, Link, usePage } from '@inertiajs/react';
import { LogOut, Pencil } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';
import { logout } from '@/routes';
import accountRoutes from '@/routes/account';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

/**
 * « Mon espace » (W4, demande Koné 2026-07-26) — cinquième destination de
 * la navigation mobile (mobile-bottom-nav.tsx), restée désactivée depuis
 * la refonte W4 faute d'écran. Modifie uniquement nom/e-mail, déjà
 * légitimement collectés à l'inscription : aucun nouveau champ de profil
 * (pays, âge, téléphone…) — voir AccountOverviewController pour le
 * raisonnement AMD-0009.
 */

type PageProps = {
    auth: Auth;
};

type UpdateErrors = {
    name?: string;
    email?: string;
};

function ProfileForm({
    name,
    email,
    onSaved,
    onCancel,
}: {
    name: string;
    email: string;
    onSaved: (values: { name: string; email: string }) => void;
    onCancel: () => void;
}) {
    const [nameValue, setNameValue] = useState(name);
    const [emailValue, setEmailValue] = useState(email);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState<UpdateErrors>({});

    async function handleSubmit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});

        const result = await postJson<{ name: string; email: string }>(
            accountRoutes.profile.update.url(),
            { name: nameValue, email: emailValue },
        );

        setSubmitting(false);

        // Une session expirée pendant la saisie fait suivre le fetch
        // jusqu'à la page de connexion (redirection HTML, jamais un 401
        // JSON — ce point de terminaison vit dans le même groupe 'auth' que
        // l'écran) : `result.ok` serait alors vrai (200) sans que `name`
        // n'existe réellement. Ne jamais traiter ce cas comme un succès.
        if (!result.ok || typeof result.data?.name !== 'string') {
            const data = result.data as {
                errors?: Record<string, string[]>;
            } | null;
            setErrors({
                name: data?.errors?.name?.[0],
                email: data?.errors?.email?.[0],
            });

            return;
        }

        onSaved({ name: result.data.name, email: result.data.email });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1.5">
                <label
                    htmlFor="account-name"
                    className="text-sm font-medium text-[#A9B7C8]"
                >
                    Nom complet
                </label>
                <input
                    id="account-name"
                    value={nameValue}
                    onChange={(event) => setNameValue(event.target.value)}
                    required
                    className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                />
                <InputError message={errors.name} />
            </div>

            <div className="space-y-1.5">
                <label
                    htmlFor="account-email"
                    className="text-sm font-medium text-[#A9B7C8]"
                >
                    Adresse e-mail
                </label>
                <input
                    id="account-email"
                    type="email"
                    value={emailValue}
                    onChange={(event) => setEmailValue(event.target.value)}
                    required
                    className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                />
                <InputError message={errors.email} />
            </div>

            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={onCancel}
                    className="flex-1 rounded-xl border border-[#35506D] py-3 text-sm font-medium text-[#A9B7C8] transition-colors active:bg-[#173251]"
                >
                    Annuler
                </button>
                <button
                    type="submit"
                    disabled={submitting}
                    className="flex flex-1 items-center justify-center gap-2 rounded-xl bg-[#075CCF] py-3 text-sm font-semibold text-white transition-colors active:bg-[#0A4FAF] disabled:opacity-60"
                >
                    {submitting && <Spinner />}
                    Enregistrer
                </button>
            </div>
        </form>
    );
}

export default function AccountOverview({
    mustVerifyEmail,
}: {
    mustVerifyEmail: boolean;
}) {
    const { auth } = usePage<PageProps>().props;
    const [name, setName] = useState(auth.user.name);
    const [email, setEmail] = useState(auth.user.email);
    const [emailVerifiedAt, setEmailVerifiedAt] = useState(
        auth.user.email_verified_at,
    );
    const [editing, setEditing] = useState(false);
    const [verificationSent, setVerificationSent] = useState(false);

    async function resendVerification() {
        const result = await postJson(send().url, {});

        if (result.ok) {
            setVerificationSent(true);
        }
    }

    return (
        <MobileLayout>
            <Head title="Mon espace" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-xl font-bold text-[#F5F8FC]">
                        Mon espace
                    </h1>
                    <p className="mt-1 text-xs text-[#A9B7C8]">
                        Vos informations de compte
                    </p>
                </div>

                <section className="rounded-xl border border-[#35506D] bg-[#0E2542] p-4">
                    {editing ? (
                        <ProfileForm
                            name={name}
                            email={email}
                            onCancel={() => setEditing(false)}
                            onSaved={(values) => {
                                setName(values.name);
                                setEmail(values.email);

                                if (values.email !== email) {
                                    setEmailVerifiedAt(null);
                                }

                                setEditing(false);
                            }}
                        />
                    ) : (
                        <div className="space-y-4">
                            <div className="flex items-start justify-between">
                                <div>
                                    <p className="text-base font-semibold text-[#F5F8FC]">
                                        {name}
                                    </p>
                                    <p className="mt-0.5 text-sm text-[#A9B7C8]">
                                        {email}
                                    </p>
                                </div>
                                <button
                                    onClick={() => setEditing(true)}
                                    aria-label="Modifier mon profil"
                                    className="flex h-9 w-9 items-center justify-center rounded-full bg-[#173251] text-[#4FA3FF]"
                                >
                                    <Pencil size={16} />
                                </button>
                            </div>

                            {mustVerifyEmail && emailVerifiedAt === null && (
                                <div className="rounded-xl border border-[#FF9A3D]/25 bg-[#FF9A3D]/10 px-4 py-3">
                                    <p className="text-xs font-semibold text-[#FF9A3D]">
                                        Adresse e-mail non vérifiée
                                    </p>
                                    {verificationSent ? (
                                        <p className="mt-1 text-xs text-[#A9B7C8]">
                                            Un lien de vérification vient de
                                            vous être envoyé.
                                        </p>
                                    ) : (
                                        <button
                                            onClick={resendVerification}
                                            className="mt-1 text-xs text-[#4FA3FF] underline underline-offset-2"
                                        >
                                            Renvoyer le lien de vérification
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </section>

                <Link
                    href="/me/advertising-profile"
                    className="flex items-center justify-between rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-3.5 text-sm font-medium text-[#F5F8FC] transition-colors active:bg-[#173251]"
                >
                    Intérêts publicitaires
                    <span className="text-xs text-[#A9B7C8]">Facultatif</span>
                </Link>

                <Link
                    href="/subscriptions"
                    className="flex items-center justify-between rounded-xl border border-[#35506D] bg-[#0E2542] px-4 py-3.5 text-sm font-medium text-[#F5F8FC] transition-colors active:bg-[#173251]"
                >
                    Abonnements
                </Link>

                <Link
                    href={logout()}
                    as="button"
                    data-test="logout-button"
                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-[#35506D] py-3.5 text-sm font-medium text-[#A9B7C8] transition-colors active:bg-[#173251]"
                >
                    <LogOut size={16} />
                    Se déconnecter
                </Link>
            </div>
        </MobileLayout>
    );
}

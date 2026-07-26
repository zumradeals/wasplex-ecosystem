import { Form, Head, Link } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronUp,
    Eye,
    EyeOff,
    Megaphone,
    Shield,
    Smartphone,
    Wallet,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Spinner } from '@/components/ui/spinner';
import WasplexMascot from '@/components/wasplex-mascot';
import { store as loginStore } from '@/routes/login';
import { store as registerStore } from '@/routes/register';

/**
 * Page d'entrée publique — UX-0001 §4.
 * Textes référencés : Constitution art. 1 (raison d'être), art. 3 (attention
 * rémunérée), art. 6 §3 (accès gratuit réel), AMD-0001 (données jamais
 * vendues), AMD-0002 (partage 50/50), AMD-0003 (1 WP = 1 FCFA).
 * DS-0001 §27 : ton « tu », direct, digne, sans promesse de gain.
 * UX-0001 §39 : aucun dark pattern, aucune case précochée.
 */

type AccordionKey = 'user' | 'advertiser' | null;
type AuthTab = 'login' | 'register';

/* ─── Feature cards ───────────────────────────────────────────── */

const features = [
    {
        icon: Megaphone,
        color: '#4FA3FF',
        title: 'Regarde et gagne',
        body: "Chaque publicité vue jusqu'au bout crédite ton Wallet en WasPoints — 1 WP = 1 FCFA.",
    },
    {
        icon: Wallet,
        color: '#FF9A3D',
        title: 'Retire sur Mobile Money',
        body: "Tes WP disponibles se convertissent en argent sur Orange Money, Wave et d'autres canaux.",
    },
    {
        icon: Shield,
        color: '#2BC4DE',
        title: "Tes données t'appartiennent",
        body: 'Tes données ne sont jamais vendues ni exposées à un annonceur. Tu restes propriétaire de ton attention.',
    },
    {
        icon: Smartphone,
        color: '#42D392',
        title: 'Gratuit pour tout le monde',
        body: 'Inscription simple. Le niveau gratuit conserve tous les droits essentiels — Wallet, gains et retrait.',
    },
] as const;

/* ─── Formulaire connexion ────────────────────────────────────── */

function LoginForm() {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <Form
            {...loginStore.form()}
            resetOnSuccess={['password']}
            className="flex flex-col gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="login-email"
                            className="text-sm font-medium text-[#A9B7C8]"
                        >
                            Adresse e-mail
                        </label>
                        <input
                            id="login-email"
                            type="email"
                            name="email"
                            required
                            autoComplete="email"
                            placeholder="ton@adresse.com"
                            className="h-12 rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white placeholder-[#53657D] focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="login-password"
                            className="text-sm font-medium text-[#A9B7C8]"
                        >
                            Mot de passe
                        </label>
                        <div className="relative">
                            <input
                                id="login-password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                required
                                autoComplete="current-password"
                                placeholder="Minimum 8 caractères"
                                className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 pr-12 text-white placeholder-[#53657D] focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((v) => !v)}
                                aria-label={
                                    showPassword
                                        ? 'Masquer le mot de passe'
                                        : 'Afficher le mot de passe'
                                }
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-[#53657D] hover:text-[#A9B7C8]"
                            >
                                {showPassword ? (
                                    <EyeOff size={18} />
                                ) : (
                                    <Eye size={18} />
                                )}
                            </button>
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        data-test="login-button"
                        className="mt-1 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#075CCF] font-semibold text-white transition hover:bg-[#0552BB] active:scale-[0.98] disabled:opacity-60"
                    >
                        {processing && <Spinner />}
                        Se connecter →
                    </button>

                    <div className="text-center">
                        <Link
                            href="/password/reset"
                            className="text-sm text-[#A9B7C8] hover:text-white"
                        >
                            Mot de passe oublié ?
                        </Link>
                    </div>
                </>
            )}
        </Form>
    );
}

/* ─── Formulaire inscription ──────────────────────────────────── */

function RegisterForm({ passwordRules }: { passwordRules?: string }) {
    const [showPassword, setShowPassword] = useState(false);

    return (
        <Form
            {...registerStore.form()}
            resetOnSuccess={['password', 'password_confirmation']}
            disableWhileProcessing
            className="flex flex-col gap-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="reg-name"
                            className="text-sm font-medium text-[#A9B7C8]"
                        >
                            Ton prénom ou nom
                        </label>
                        <input
                            id="reg-name"
                            type="text"
                            name="name"
                            required
                            autoComplete="name"
                            placeholder="Ton nom"
                            className="h-12 rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white placeholder-[#53657D] focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="reg-email"
                            className="text-sm font-medium text-[#A9B7C8]"
                        >
                            Adresse e-mail
                        </label>
                        <input
                            id="reg-email"
                            type="email"
                            name="email"
                            required
                            autoComplete="email"
                            placeholder="ton@adresse.com"
                            className="h-12 rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white placeholder-[#53657D] focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="reg-password"
                            className="text-sm font-medium text-[#A9B7C8]"
                        >
                            Mot de passe
                        </label>
                        <div className="relative">
                            <input
                                id="reg-password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                required
                                autoComplete="new-password"
                                placeholder="Minimum 8 caractères"
                                passwordrules={passwordRules}
                                className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 pr-12 text-white placeholder-[#53657D] focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword((v) => !v)}
                                aria-label={
                                    showPassword
                                        ? 'Masquer le mot de passe'
                                        : 'Afficher le mot de passe'
                                }
                                className="absolute top-1/2 right-3 -translate-y-1/2 text-[#53657D] hover:text-[#A9B7C8]"
                            >
                                {showPassword ? (
                                    <EyeOff size={18} />
                                ) : (
                                    <Eye size={18} />
                                )}
                            </button>
                        </div>
                        <InputError message={errors.password} />
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label
                            htmlFor="reg-password-confirm"
                            className="text-sm font-medium text-[#A9B7C8]"
                        >
                            Confirmer le mot de passe
                        </label>
                        <input
                            id="reg-password-confirm"
                            type={showPassword ? 'text' : 'password'}
                            name="password_confirmation"
                            required
                            autoComplete="new-password"
                            placeholder="Répète ton mot de passe"
                            className="h-12 rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white placeholder-[#53657D] focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                        />
                        <InputError message={errors.password_confirmation} />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        data-test="register-user-button"
                        className="mt-1 flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#075CCF] font-semibold text-white transition hover:bg-[#0552BB] active:scale-[0.98] disabled:opacity-60"
                    >
                        {processing && <Spinner />}
                        Créer mon compte →
                    </button>
                </>
            )}
        </Form>
    );
}

/* ─── Page principale ─────────────────────────────────────────── */

export default function Welcome({ passwordRules }: { passwordRules?: string }) {
    const [open, setOpen] = useState<AccordionKey>(null);
    const [tab, setTab] = useState<AuthTab>('login');

    function toggle(key: AccordionKey) {
        setOpen((prev) => (prev === key ? null : key));
    }

    return (
        <>
            <Head title="Wasplex — La publicité qui te paie" />

            <div className="min-h-svh bg-[#07182D]">
                {/* DS-0001 §13 : desktop n'étire pas le mobile — colonne
                    contenue, centrée, fond identique au reste de l'écran. */}
                <div className="mx-auto flex min-h-svh w-full max-w-[480px] flex-col text-white">
                    {/* ── Héros ─────────────────────────────────────────── */}
                    <div className="flex flex-col items-center px-5 pt-10 pb-6 text-center">
                        {/* Mascotte + marque */}
                        <div className="mb-5 flex flex-col items-center gap-2">
                            <WasplexMascot className="h-28 w-28 drop-shadow-lg" />
                        </div>

                        {/* Accroche — fidèle à la Constitution art. 1 */}
                        <p className="max-w-xs text-base leading-snug font-medium text-[#4FA3FF]">
                            La publicité qui te paie directement sur ton mobile
                        </p>
                    </div>

                    {/* ── 3 badges clés ─────────────────────────────────── */}
                    <div className="mb-6 grid grid-cols-3 gap-2 px-5">
                        {[
                            { label: 'Gratuit', sub: 'Inscription simple' },
                            { label: '1 WP', sub: '= 1 FCFA garanti' },
                            { label: 'Cash', sub: 'Mobile Money' },
                        ].map(({ label, sub }) => (
                            <div
                                key={label}
                                className="flex flex-col items-center rounded-2xl border border-[#35506D] bg-[#0E2542] p-3 text-center"
                            >
                                <span className="text-sm font-bold text-white">
                                    {label}
                                </span>
                                <span className="mt-0.5 text-[11px] leading-tight text-[#A9B7C8]">
                                    {sub}
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* ── Grille 2×2 features ───────────────────────────── */}
                    <div className="mb-8 grid grid-cols-2 gap-3 px-5">
                        {features.map((f) => {
                            const Icon = f.icon;

                            return (
                                <div
                                    key={f.title}
                                    className="flex flex-col gap-2 rounded-2xl border border-[#35506D] bg-[#0E2542] p-4"
                                >
                                    <span
                                        className="flex h-9 w-9 items-center justify-center rounded-xl"
                                        style={{
                                            backgroundColor: `${f.color}18`,
                                        }}
                                    >
                                        <Icon
                                            size={18}
                                            style={{ color: f.color }}
                                        />
                                    </span>
                                    <p className="text-sm leading-snug font-semibold text-white">
                                        {f.title}
                                    </p>
                                    <p className="text-[12px] leading-relaxed text-[#A9B7C8]">
                                        {f.body}
                                    </p>
                                </div>
                            );
                        })}
                    </div>

                    {/* ── Connexion rapide ───────────────────────────────── */}
                    <div className="px-5 pb-10">
                        <p className="mb-3 text-center text-[11px] font-semibold tracking-widest text-[#FF9A3D] uppercase">
                            Connexion rapide
                        </p>

                        {/* Accordéon Utilisateur */}
                        <div className="mb-3 overflow-hidden rounded-2xl border border-[#35506D]">
                            <button
                                onClick={() => toggle('user')}
                                aria-expanded={open === 'user'}
                                className="flex w-full items-center justify-between bg-[#075CCF] px-5 py-4 text-left"
                            >
                                <span className="flex items-center gap-3 font-semibold text-white">
                                    <Smartphone size={20} />
                                    Connexion Utilisateur
                                </span>
                                {open === 'user' ? (
                                    <ChevronUp
                                        size={20}
                                        className="text-white/70"
                                    />
                                ) : (
                                    <ChevronDown
                                        size={20}
                                        className="text-white/70"
                                    />
                                )}
                            </button>

                            {open === 'user' && (
                                <div className="bg-[#0E2542] px-5 py-5">
                                    {/* Onglets Connexion / Inscription */}
                                    <div className="mb-5 flex border-b border-[#35506D]">
                                        <button
                                            onClick={() => setTab('login')}
                                            className={[
                                                'flex-1 pb-2.5 text-sm font-semibold transition',
                                                tab === 'login'
                                                    ? 'border-b-2 border-white text-white'
                                                    : 'text-[#A9B7C8] hover:text-white',
                                            ].join(' ')}
                                        >
                                            Connexion
                                        </button>
                                        <button
                                            onClick={() => setTab('register')}
                                            className={[
                                                'flex-1 pb-2.5 text-sm font-semibold transition',
                                                tab === 'register'
                                                    ? 'border-b-2 border-white text-white'
                                                    : 'text-[#A9B7C8] hover:text-white',
                                            ].join(' ')}
                                        >
                                            Inscription
                                        </button>
                                    </div>

                                    {tab === 'login' ? (
                                        <LoginForm />
                                    ) : (
                                        <RegisterForm
                                            passwordRules={passwordRules}
                                        />
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Accordéon Annonceur */}
                        <div className="overflow-hidden rounded-2xl border border-[#35506D]">
                            <Link
                                href="/advertiser"
                                className="flex w-full items-center justify-between bg-[#C75100] px-5 py-4 text-left"
                            >
                                <span className="flex items-center gap-3 font-semibold text-white">
                                    <Megaphone size={20} />
                                    Espace Annonceur
                                </span>
                                <ChevronDown
                                    size={20}
                                    className="text-white/70"
                                />
                            </Link>
                        </div>

                        {/* Footer légal — UX-0001 §4 */}
                        <p className="mt-6 text-center text-[12px] text-[#53657D]">
                            En continuant, tu acceptes les{' '}
                            <span className="text-[#4FA3FF]">
                                Conditions Wasplex
                            </span>{' '}
                            et la{' '}
                            <span className="text-[#4FA3FF]">
                                Politique de confidentialité
                            </span>
                            .
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

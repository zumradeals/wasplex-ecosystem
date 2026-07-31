import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';

/**
 * « Intérêts publicitaires » (véto du dirigeant, 2026-07-30 ; AMD-0009,
 * UX-0001 §11) — section distincte de « Mon espace », consentement
 * facultatif, spécifique, versionné et révocable. Chaque section indique
 * finalité, caractère facultatif, bénéfice, retrait possible et dernière
 * modification (UX-0001 §11) ; aucun score ni valeur agrégée n'est affiché.
 */

const AGE_BRACKETS = ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'];

const GENDERS: Record<string, string> = {
    woman: 'Femme',
    man: 'Homme',
    other: 'Autre',
    prefer_not_to_say: 'Je préfère ne pas préciser',
};

type InterestOption = { code: string; label: string };

type Profile = {
    consented: boolean;
    country_code: string | null;
    city: string | null;
    neighborhood: string | null;
    age_bracket: string | null;
    gender: string | null;
    interests: string[];
    consent_given_at: string | null;
};

function ProfileForm({
    interestTaxonomy,
    initial,
    onSaved,
}: {
    interestTaxonomy: InterestOption[];
    initial: Profile | null;
    onSaved: (profile: Profile) => void;
}) {
    const [countryCode, setCountryCode] = useState(initial?.country_code ?? '');
    const [city, setCity] = useState(initial?.city ?? '');
    const [neighborhood, setNeighborhood] = useState(
        initial?.neighborhood ?? '',
    );
    const [ageBracket, setAgeBracket] = useState(initial?.age_bracket ?? '');
    const [gender, setGender] = useState(initial?.gender ?? '');
    const [interests, setInterests] = useState<string[]>(
        initial?.interests ?? [],
    );
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function toggleInterest(code: string) {
        setInterests((current) =>
            current.includes(code)
                ? current.filter((value) => value !== code)
                : [...current, code],
        );
    }

    async function submit(event: React.FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        const result = await postJson<Profile>('/me/advertising-profile', {
            country_code: countryCode || null,
            city: city || null,
            neighborhood: neighborhood || null,
            age_bracket: ageBracket || null,
            gender: gender || null,
            interests,
        });

        setSubmitting(false);

        if (!result.ok) {
            setError(
                "L'enregistrement n'a pas abouti. Vérifiez les valeurs saisies.",
            );

            return;
        }

        onSaved(result.data);
    }

    return (
        <form onSubmit={submit} className="space-y-5">
            {error && <p className="text-sm text-[#FF6B6B]">{error}</p>}

            <div className="space-y-1.5">
                <span className="text-sm font-medium text-[#A9B7C8]">
                    Pays (facultatif, code à 2 lettres — ex. CI)
                </span>
                <input
                    type="text"
                    maxLength={2}
                    value={countryCode}
                    onChange={(event) =>
                        setCountryCode(event.target.value.toUpperCase())
                    }
                    placeholder="CI"
                    className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                />
            </div>

            <div className="space-y-1.5">
                <span className="text-sm font-medium text-[#A9B7C8]">
                    Ville (facultatif)
                </span>
                <input
                    type="text"
                    value={city}
                    onChange={(event) => setCity(event.target.value)}
                    placeholder="Abidjan"
                    className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                />
            </div>

            <div className="space-y-1.5">
                <span className="text-sm font-medium text-[#A9B7C8]">
                    Quartier (facultatif)
                </span>
                <input
                    type="text"
                    value={neighborhood}
                    onChange={(event) => setNeighborhood(event.target.value)}
                    placeholder="Abobo"
                    className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                />
            </div>

            <div className="space-y-1.5">
                <span className="text-sm font-medium text-[#A9B7C8]">
                    Tranche d'âge (facultatif)
                </span>
                <select
                    value={ageBracket}
                    onChange={(event) => setAgeBracket(event.target.value)}
                    className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                >
                    <option value="">Ne pas préciser</option>
                    {AGE_BRACKETS.map((bracket) => (
                        <option key={bracket} value={bracket}>
                            {bracket}
                        </option>
                    ))}
                </select>
            </div>

            <div className="space-y-1.5">
                <span className="text-sm font-medium text-[#A9B7C8]">
                    Genre (facultatif)
                </span>
                <select
                    value={gender}
                    onChange={(event) => setGender(event.target.value)}
                    className="h-12 w-full rounded-xl border border-[#35506D] bg-[#173251] px-4 text-white focus:border-[#4FA3FF] focus:ring-2 focus:ring-[#4FA3FF]/30 focus:outline-none"
                >
                    <option value="">Ne pas préciser</option>
                    {Object.entries(GENDERS).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="space-y-1.5">
                <span className="text-sm font-medium text-[#A9B7C8]">
                    Centres d'intérêt (facultatif)
                </span>
                {interestTaxonomy.length === 0 ? (
                    <p className="text-xs text-[#A9B7C8]">
                        Aucun centre d'intérêt n'est encore proposé.
                    </p>
                ) : (
                    <div className="flex flex-wrap gap-2">
                        {interestTaxonomy.map((option) => {
                            const active = interests.includes(option.code);

                            return (
                                <button
                                    key={option.code}
                                    type="button"
                                    onClick={() => toggleInterest(option.code)}
                                    className={
                                        active
                                            ? 'rounded-full bg-[#075CCF] px-3 py-1.5 text-xs font-medium text-white'
                                            : 'rounded-full border border-[#35506D] px-3 py-1.5 text-xs font-medium text-[#A9B7C8]'
                                    }
                                >
                                    {option.label}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            <button
                type="submit"
                disabled={submitting}
                className="w-full rounded-xl bg-[#075CCF] py-3 text-sm font-semibold text-white transition-colors active:bg-[#0A4FAF] disabled:opacity-60"
            >
                {submitting ? 'Enregistrement...' : 'Enregistrer'}
            </button>
        </form>
    );
}

function WithdrawButton({ onWithdrawn }: { onWithdrawn: () => void }) {
    const [confirming, setConfirming] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    async function confirmWithdraw() {
        setSubmitting(true);

        const result = await postJson('/me/advertising-profile/withdraw', {});

        setSubmitting(false);

        if (result.ok) {
            onWithdrawn();
        }
    }

    if (!confirming) {
        return (
            <button
                type="button"
                onClick={() => setConfirming(true)}
                className="w-full rounded-xl border border-[#FF6B6B]/40 py-3 text-sm font-medium text-[#FF6B6B]"
            >
                Retirer mon consentement
            </button>
        );
    }

    return (
        <div className="space-y-3 rounded-xl border border-[#FF6B6B]/40 bg-[#FF6B6B]/10 p-4">
            <p className="text-sm text-[#F5F8FC]">
                Vos publicités redeviendront génériques. La tranche d'âge, le
                genre et les centres d'intérêt déjà enregistrés seront effacés —
                vous pourrez les ressaisir plus tard si vous le souhaitez.
            </p>
            <div className="flex gap-2">
                <button
                    type="button"
                    onClick={() => setConfirming(false)}
                    className="flex-1 rounded-xl border border-[#35506D] py-2.5 text-sm font-medium text-[#A9B7C8]"
                >
                    Annuler
                </button>
                <button
                    type="button"
                    onClick={confirmWithdraw}
                    disabled={submitting}
                    className="flex-1 rounded-xl bg-[#FF6B6B] py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                >
                    {submitting ? 'Retrait...' : 'Confirmer le retrait'}
                </button>
            </div>
        </div>
    );
}

export default function AdvertisingProfile({
    interestTaxonomy,
    profile,
}: {
    interestTaxonomy: InterestOption[];
    profile: Profile | null;
}) {
    const [current, setCurrent] = useState(profile);
    const [editing, setEditing] = useState(false);

    return (
        <MobileLayout>
            <Head title="Mon espace — Intérêts publicitaires" />

            <div className="space-y-6 p-4">
                <div>
                    <h1 className="text-xl font-bold text-[#F5F8FC]">
                        Intérêts publicitaires
                    </h1>
                    <p className="mt-1 text-xs leading-relaxed text-[#A9B7C8]">
                        Utilisé uniquement pour vous proposer des publicités
                        plus pertinentes. Jamais vendu, jamais transmis à un
                        annonceur sous une forme qui vous identifie. Entièrement
                        facultatif — vous pouvez retirer ce consentement à tout
                        moment.
                    </p>
                </div>

                <section className="rounded-xl border border-[#35506D] bg-[#0E2542] p-4">
                    {editing ? (
                        <ProfileForm
                            interestTaxonomy={interestTaxonomy}
                            initial={current}
                            onSaved={(saved) => {
                                setCurrent(saved);
                                setEditing(false);
                            }}
                        />
                    ) : current === null ? (
                        <button
                            type="button"
                            onClick={() => setEditing(true)}
                            className="w-full rounded-xl bg-[#075CCF] py-3 text-sm font-semibold text-white transition-colors active:bg-[#0A4FAF]"
                        >
                            Activer
                        </button>
                    ) : (
                        <div className="space-y-4">
                            <div className="space-y-1 text-sm text-[#A9B7C8]">
                                <p>
                                    Pays :{' '}
                                    <span className="text-[#F5F8FC]">
                                        {current?.country_code ?? 'non précisé'}
                                    </span>
                                </p>
                                <p>
                                    Ville :{' '}
                                    <span className="text-[#F5F8FC]">
                                        {current?.city ?? 'non précisée'}
                                    </span>
                                </p>
                                <p>
                                    Quartier :{' '}
                                    <span className="text-[#F5F8FC]">
                                        {current?.neighborhood ?? 'non précisé'}
                                    </span>
                                </p>
                                <p>
                                    Tranche d'âge :{' '}
                                    <span className="text-[#F5F8FC]">
                                        {current?.age_bracket ?? 'non précisée'}
                                    </span>
                                </p>
                                <p>
                                    Genre :{' '}
                                    <span className="text-[#F5F8FC]">
                                        {current?.gender
                                            ? GENDERS[current.gender]
                                            : 'non précisé'}
                                    </span>
                                </p>
                                <p>
                                    Centres d'intérêt :{' '}
                                    <span className="text-[#F5F8FC]">
                                        {current && current.interests.length > 0
                                            ? current.interests.join(', ')
                                            : 'aucun'}
                                    </span>
                                </p>
                                {current?.consent_given_at && (
                                    <p className="text-xs text-[#A9B7C8]/70">
                                        Dernière modification le{' '}
                                        {new Date(
                                            current.consent_given_at,
                                        ).toLocaleString('fr-FR')}
                                    </p>
                                )}
                            </div>

                            <button
                                type="button"
                                onClick={() => setEditing(true)}
                                className="w-full rounded-xl border border-[#35506D] py-2.5 text-sm font-medium text-[#F5F8FC]"
                            >
                                Modifier
                            </button>

                            <WithdrawButton
                                onWithdrawn={() => setCurrent(null)}
                            />
                        </div>
                    )}
                </section>

                <Link
                    href="/me"
                    className="block w-full rounded-xl border border-[#35506D] py-3 text-center text-sm font-medium text-[#A9B7C8]"
                >
                    Retour à Mon espace
                </Link>
            </div>
        </MobileLayout>
    );
}

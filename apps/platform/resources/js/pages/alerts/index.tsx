import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, MapPin, Megaphone, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import AlertCaseSubmissionController from '@/actions/App/Modules/Alerts/Http/Controllers/AlertCaseSubmissionController';
import SosReportController from '@/actions/App/Modules/Alerts/Http/Controllers/SosReportController';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';
import { login } from '@/routes';
import alerts from '@/routes/alerts';

type PublishedAlert = {
    publication_id: string;
    title: string;
    summary: string;
    approximate_zone: string | null;
    category: string;
    published_at: string | null;
};

type MyDeclaration = {
    case_id: string;
    nature: string;
    category: string;
    state: string;
    created_at: string;
};

const COMMUNITY_CATEGORIES: Record<string, string> = {
    lost_item: 'Objet perdu',
    found_item: 'Objet trouvé',
    lost_document: 'Document perdu',
    found_document: 'Document trouvé',
    stolen_vehicle: 'Véhicule volé',
    found_vehicle: 'Véhicule retrouvé',
    missing_person: 'Personne disparue',
    found_person: 'Personne retrouvée',
};

const SOS_CATEGORIES: Record<string, string> = {
    fire: 'Incendie',
    accident: 'Accident',
    medical_emergency: 'Urgence médicale',
    robbery_in_progress: 'Vol ou braquage en cours',
};

const STATE_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    submitted: 'Soumis',
    under_review: 'En revue',
    published: 'Publié',
    restricted: 'Diffusion restreinte',
    rejected: 'Refusé',
    matched: 'Correspondance trouvée',
    restitution_scheduled: 'Restitution en cours',
    resolved: 'Résolu',
    disputed: 'Contesté',
    expired: 'Expiré',
    withdrawn: 'Retiré',
    created: 'Enregistré',
    transmitted: 'Transmis — non confirmé',
    received: 'Reçu par une institution',
    accepted: 'Pris en charge',
    processing: 'En cours de traitement',
    unanswered: 'Sans réponse',
    refused: 'Refusé par le destinataire',
    transferred: 'Transféré',
    cancelled: 'Annulé',
    impossible: 'Transmission impossible',
    closed_unresolved: 'Clos sans résolution',
};

function stateLabel(state: string): string {
    return STATE_LABELS[state] ?? state;
}

export default function AlertsIndex({
    published,
    my_declarations: myDeclarations,
}: {
    published: PublishedAlert[];
    my_declarations: MyDeclaration[];
}) {
    const { auth } = usePage<{ auth?: { user?: { name?: string } | null } }>()
        .props;
    const isAuthenticated = Boolean(auth?.user);

    const [tab, setTab] = useState<'published' | 'mine'>('published');
    const [declareOpen, setDeclareOpen] = useState(false);
    const [sosOpen, setSosOpen] = useState(false);

    return (
        <MobileLayout>
            <Head title="Alertes" />

            <div className="space-y-4 p-4">
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-lg font-bold text-white">Alertes</h1>

                    <button
                        type="button"
                        onClick={() => setSosOpen(true)}
                        className="flex items-center gap-1.5 rounded-full bg-[#D92D20] px-3 py-2 text-sm font-semibold text-white"
                        aria-label="Envoyer un SOS"
                    >
                        <AlertTriangle size={16} />
                        SOS
                    </button>
                </div>

                <div className="flex gap-2 rounded-full bg-[#0E2542] p-1">
                    <button
                        type="button"
                        onClick={() => setTab('published')}
                        className={[
                            'flex-1 rounded-full py-2 text-sm font-medium',
                            tab === 'published'
                                ? 'bg-[#173251] text-white'
                                : 'text-[#A9B7C8]',
                        ].join(' ')}
                    >
                        Alertes actives
                    </button>
                    <button
                        type="button"
                        onClick={() => setTab('mine')}
                        className={[
                            'flex-1 rounded-full py-2 text-sm font-medium',
                            tab === 'mine'
                                ? 'bg-[#173251] text-white'
                                : 'text-[#A9B7C8]',
                        ].join(' ')}
                    >
                        Mes déclarations
                    </button>
                </div>

                {tab === 'published' ? (
                    published.length === 0 ? (
                        <EmptyState
                            icon={Megaphone}
                            title="Aucune alerte active pour l'instant"
                            description="Les alertes communautaires publiées et vérifiées apparaîtront ici."
                        />
                    ) : (
                        <ul className="space-y-3">
                            {published.map((item) => (
                                <li
                                    key={item.publication_id}
                                    className="rounded-xl border border-[#35506D] bg-[#0E2542] p-4"
                                >
                                    <p className="text-xs font-medium text-[#4FA3FF]">
                                        {COMMUNITY_CATEGORIES[item.category] ??
                                            item.category}
                                    </p>
                                    <h2 className="mt-1 font-semibold text-white">
                                        {item.title}
                                    </h2>
                                    <p className="mt-1 text-sm text-[#A9B7C8]">
                                        {item.summary}
                                    </p>
                                    {item.approximate_zone && (
                                        <p className="mt-2 flex items-center gap-1 text-xs text-[#A9B7C8]">
                                            <MapPin size={12} />
                                            {item.approximate_zone}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )
                ) : myDeclarations.length === 0 ? (
                    <EmptyState
                        icon={Plus}
                        title="Vous n'avez encore rien déclaré"
                        description="Vos déclarations communautaires et SOS apparaîtront ici avec leur statut réel."
                    />
                ) : (
                    <ul className="space-y-3">
                        {myDeclarations.map((item) => (
                            <li key={item.case_id}>
                                <a
                                    href={
                                        alerts.show({ case: item.case_id }).url
                                    }
                                    className="block rounded-xl border border-[#35506D] bg-[#0E2542] p-4"
                                >
                                    <p className="text-xs font-medium text-[#4FA3FF]">
                                        {(item.nature === 'sos'
                                            ? SOS_CATEGORIES
                                            : COMMUNITY_CATEGORIES)[
                                            item.category
                                        ] ?? item.category}
                                    </p>
                                    <p className="mt-1 text-sm text-white">
                                        {stateLabel(item.state)}
                                    </p>
                                </a>
                            </li>
                        ))}
                    </ul>
                )}

                <button
                    type="button"
                    onClick={() =>
                        isAuthenticated
                            ? setDeclareOpen(true)
                            : router.visit(login().url)
                    }
                    className="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-[#35506D] py-3 text-sm font-medium text-[#A9B7C8]"
                >
                    <Plus size={16} />
                    {isAuthenticated
                        ? 'Déclarer un objet, document ou véhicule'
                        : 'Se connecter pour déclarer un objet, document ou véhicule'}
                </button>
            </div>

            {declareOpen && (
                <DeclareCommunityCaseSheet
                    onClose={() => setDeclareOpen(false)}
                />
            )}
            {sosOpen && <SosSheet onClose={() => setSosOpen(false)} />}
        </MobileLayout>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
}: {
    icon: React.FC<{ size?: number; className?: string }>;
    title: string;
    description: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-[#35506D] px-6 py-12 text-center">
            <Icon size={28} className="mb-3 text-[#A9B7C8]" />
            <p className="text-sm font-semibold text-white">{title}</p>
            <p className="mt-1 text-sm text-[#A9B7C8]">{description}</p>
        </div>
    );
}

function DeclareCommunityCaseSheet({ onClose }: { onClose: () => void }) {
    const [category, setCategory] = useState('lost_item');
    const [description, setDescription] = useState('');
    const [submitting, setSubmitting] = useState(false);

    async function submit() {
        setSubmitting(true);

        const result = await postJson(
            AlertCaseSubmissionController.store().url,
            {
                category,
                source_description: description,
                country_code: 'CI',
                locale: 'fr',
            },
        );

        setSubmitting(false);

        if (result.ok) {
            toast.success('Déclaration soumise pour revue.');
            router.reload({ only: ['my_declarations'] });
            onClose();
        } else {
            toast.error("La déclaration n'a pas pu être envoyée.");
        }
    }

    return (
        <Sheet onClose={onClose} title="Déclarer">
            <label className="block text-sm font-medium text-[#A9B7C8]">
                Catégorie
                <select
                    value={category}
                    onChange={(event) => setCategory(event.target.value)}
                    className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                >
                    {Object.entries(COMMUNITY_CATEGORIES).map(
                        ([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ),
                    )}
                </select>
            </label>

            <label className="mt-4 block text-sm font-medium text-[#A9B7C8]">
                Description
                <textarea
                    value={description}
                    onChange={(event) => setDescription(event.target.value)}
                    rows={4}
                    className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                    placeholder="Décrivez l'objet, le lieu et la période, sans informations sensibles."
                />
            </label>

            <p className="mt-3 text-xs text-[#A9B7C8]">
                Votre déclaration sera revue avant toute publication. Les
                données sensibles restent hors de la vue publique.
            </p>

            <button
                type="button"
                onClick={submit}
                disabled={submitting || description.trim() === ''}
                className="mt-4 w-full rounded-lg bg-[#4FA3FF] py-3 text-sm font-semibold text-[#07182D] disabled:opacity-50"
            >
                {submitting ? 'Envoi...' : 'Soumettre'}
            </button>
        </Sheet>
    );
}

function SosSheet({ onClose }: { onClose: () => void }) {
    const [category, setCategory] = useState('fire');
    const [description, setDescription] = useState('');
    const [phone, setPhone] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [result, setResult] = useState<
        'created' | 'routed' | 'not_routed' | null
    >(null);

    async function submit() {
        setSubmitting(true);

        const response = await postJson<{ routed: boolean }>(
            SosReportController.store().url,
            {
                category,
                source_description: description || category,
                country_code: 'CI',
                locale: 'fr',
                recall_phone: phone || undefined,
                idempotency_key: crypto.randomUUID(),
            },
        );

        setSubmitting(false);

        if (response.ok) {
            setResult(response.data.routed ? 'routed' : 'not_routed');
        } else {
            toast.error(
                "Le SOS n'a pas pu être envoyé. Réessayez ou appelez les numéros officiels.",
            );
        }
    }

    return (
        <Sheet onClose={onClose} title="SOS">
            {result ? (
                <div className="space-y-3">
                    <p className="text-sm font-semibold text-white">
                        {result === 'routed'
                            ? 'Enregistré et transmis à une institution habilitée.'
                            : 'Demande enregistrée — transmission institutionnelle non confirmée.'}
                    </p>
                    <p className="text-xs text-[#A9B7C8]">
                        En cas de danger immédiat, appelez directement les
                        numéros officiels d'urgence de votre pays.
                    </p>
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-full rounded-lg bg-[#173251] py-3 text-sm font-semibold text-white"
                    >
                        Fermer
                    </button>
                </div>
            ) : (
                <>
                    <label className="block text-sm font-medium text-[#A9B7C8]">
                        Nature
                        <select
                            value={category}
                            onChange={(event) =>
                                setCategory(event.target.value)
                            }
                            className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                        >
                            {Object.entries(SOS_CATEGORIES).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                    </label>

                    <label className="mt-4 block text-sm font-medium text-[#A9B7C8]">
                        Description courte
                        <textarea
                            value={description}
                            onChange={(event) =>
                                setDescription(event.target.value)
                            }
                            rows={3}
                            className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                        />
                    </label>

                    <label className="mt-4 block text-sm font-medium text-[#A9B7C8]">
                        Numéro de rappel (facultatif)
                        <input
                            type="tel"
                            value={phone}
                            onChange={(event) => setPhone(event.target.value)}
                            className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                        />
                    </label>

                    <button
                        type="button"
                        onClick={submit}
                        disabled={submitting}
                        className="mt-4 w-full rounded-lg bg-[#D92D20] py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {submitting ? 'Envoi...' : 'Envoyer le SOS'}
                    </button>
                </>
            )}
        </Sheet>
    );
}

function Sheet({
    title,
    onClose,
    children,
}: {
    title: string;
    onClose: () => void;
    children: React.ReactNode;
}) {
    return (
        <div className="fixed inset-0 z-[60] flex items-end bg-black/60">
            <div className="max-h-[85vh] w-full overflow-y-auto rounded-t-2xl bg-[#07182D] p-4 pb-24">
                <div className="mb-3 flex items-center justify-between">
                    <h2 className="text-base font-bold text-white">{title}</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fermer"
                        className="text-[#A9B7C8]"
                    >
                        <X size={20} />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

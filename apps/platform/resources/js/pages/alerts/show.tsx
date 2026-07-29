import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import CorrespondenceReportController from '@/actions/App/Modules/Alerts/Http/Controllers/CorrespondenceReportController';
import MobileLayout from '@/layouts/mobile-layout';
import { postJson } from '@/lib/api';
import { dashboard } from '@/routes';

type CaseDetail = {
    case_id: string;
    nature: string;
    category: string;
    state: string;
    created_at: string;
    closed_at: string | null;
    is_owner: boolean;
};

type Publication = {
    title: string;
    summary: string;
    approximate_zone: string | null;
    published_at: string | null;
} | null;

type HistoryEvent = {
    event_type: string;
    to_state: string | null;
    occurred_at: string;
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

export default function AlertCaseShow({
    case: alertCase,
    publication,
    history,
}: {
    case: CaseDetail;
    publication: Publication;
    history: HistoryEvent[];
}) {
    const [correspondenceOpen, setCorrespondenceOpen] = useState(false);

    const canProposeCorrespondence =
        publication !== null && alertCase.state === 'published';

    return (
        <MobileLayout>
            <Head title="Détail de l'alerte" />

            <div className="space-y-4 p-4">
                <a
                    href={dashboard().url}
                    className="inline-flex items-center gap-1 text-sm text-[#A9B7C8]"
                >
                    <ArrowLeft size={16} />
                    Retour
                </a>

                <div className="rounded-xl border border-[#35506D] bg-[#0E2542] p-4">
                    <p className="text-xs font-medium text-[#4FA3FF]">
                        {STATE_LABELS[alertCase.state] ?? alertCase.state}
                    </p>

                    {publication ? (
                        <>
                            <h1 className="mt-1 text-lg font-bold text-white">
                                {publication.title}
                            </h1>
                            <p className="mt-2 text-sm text-[#A9B7C8]">
                                {publication.summary}
                            </p>
                            {publication.approximate_zone && (
                                <p className="mt-2 text-xs text-[#A9B7C8]">
                                    Zone : {publication.approximate_zone}
                                </p>
                            )}
                        </>
                    ) : (
                        <p className="mt-2 text-sm text-[#A9B7C8]">
                            {alertCase.is_owner
                                ? 'Votre déclaration est en cours de revue, aucune diffusion publique pour le moment.'
                                : "Ce dossier n'a pas de diffusion publique disponible."}
                        </p>
                    )}
                </div>

                {canProposeCorrespondence && (
                    <button
                        type="button"
                        onClick={() => setCorrespondenceOpen(true)}
                        className="w-full rounded-lg bg-[#4FA3FF] py-3 text-sm font-semibold text-[#07182D]"
                    >
                        Je pense reconnaître ce dossier
                    </button>
                )}

                {alertCase.is_owner && history.length > 0 && (
                    <div>
                        <h2 className="mb-2 text-sm font-semibold text-white">
                            Historique
                        </h2>
                        <ul className="space-y-2">
                            {history.map((event, index) => (
                                <li
                                    key={index}
                                    className="rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-xs text-[#A9B7C8]"
                                >
                                    {event.to_state
                                        ? (STATE_LABELS[event.to_state] ??
                                          event.to_state)
                                        : event.event_type}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            {correspondenceOpen && (
                <CorrespondenceSheet
                    caseId={alertCase.case_id}
                    onClose={() => setCorrespondenceOpen(false)}
                />
            )}
        </MobileLayout>
    );
}

function CorrespondenceSheet({
    caseId,
    onClose,
}: {
    caseId: string;
    onClose: () => void;
}) {
    const [description, setDescription] = useState('');
    const [answer, setAnswer] = useState('');
    const [submitting, setSubmitting] = useState(false);

    async function submit() {
        setSubmitting(true);

        const result = await postJson(
            CorrespondenceReportController.store({ case: caseId }).url,
            {
                non_public_description: description,
                verification_response: { reponse: answer },
            },
        );

        setSubmitting(false);

        if (result.ok) {
            toast.success(
                'Signalement envoyé — une personne habilitée va vérifier votre proposition.',
            );
            onClose();
        } else {
            toast.error("Le signalement n'a pas pu être envoyé.");
        }
    }

    return (
        <div className="fixed inset-0 z-50 flex items-end bg-black/60">
            <div className="max-h-[85vh] w-full overflow-y-auto rounded-t-2xl bg-[#07182D] p-4">
                <h2 className="mb-3 text-base font-bold text-white">
                    Proposer une correspondance
                </h2>

                <p className="mb-3 text-xs text-[#A9B7C8]">
                    Votre proposition est un candidat, pas une décision — une
                    personne habilitée la vérifiera avant toute restitution.
                </p>

                <label className="block text-sm font-medium text-[#A9B7C8]">
                    Description non publique
                    <textarea
                        value={description}
                        onChange={(event) => setDescription(event.target.value)}
                        rows={3}
                        className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                    />
                </label>

                <label className="mt-3 block text-sm font-medium text-[#A9B7C8]">
                    Réponse à la caractéristique demandée
                    <textarea
                        value={answer}
                        onChange={(event) => setAnswer(event.target.value)}
                        rows={2}
                        className="mt-1 w-full rounded-lg border border-[#35506D] bg-[#0E2542] px-3 py-2 text-white"
                    />
                </label>

                <div className="mt-4 flex gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="flex-1 rounded-lg border border-[#35506D] py-3 text-sm font-semibold text-[#A9B7C8]"
                    >
                        Annuler
                    </button>
                    <button
                        type="button"
                        onClick={submit}
                        disabled={submitting || description.trim() === ''}
                        className="flex-1 rounded-lg bg-[#4FA3FF] py-3 text-sm font-semibold text-[#07182D] disabled:opacity-50"
                    >
                        {submitting ? 'Envoi...' : 'Envoyer'}
                    </button>
                </div>
            </div>
        </div>
    );
}

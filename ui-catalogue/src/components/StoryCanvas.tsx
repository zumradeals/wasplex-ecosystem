import type { FixtureId, Story } from '../catalogue';
import { Status, type StatusTone } from './Status';
import { WalletTruth } from './WalletTruth';

function StatusStory({ fixture }: { fixture: FixtureId }) {
  const tones: StatusTone[] = fixture === 'confirmed'
    ? ['success']
    : fixture === 'unknown-result'
      ? ['unknown', 'pending']
      : ['success', 'warning', 'danger', 'info', 'pending', 'unknown'];

  return (
    <section className="component-demo" aria-labelledby="status-title">
      <span className="eyebrow">CMP-013</span>
      <h2 id="status-title">Un statut dit ce qui est vrai</h2>
      <p className="lead">Couleur, symbole et texte agissent ensemble. « Inconnu » n'est ni un succès ni un échec.</p>
      <div className="status-grid">{tones.map((tone) => <Status key={tone} tone={tone} />)}</div>
    </section>
  );
}

function SystemStates({ fixture }: { fixture: FixtureId }) {
  const content = {
    loading: ['Chargement en cours', 'Nous recherchons la dernière information confirmée.', '…'],
    empty: ['Rien à afficher pour le moment', 'Aucune opération ne correspond à ces filtres.', '○'],
    offline: ['Connexion indisponible', "Cette action nécessite le réseau. Vos données saisies restent sur cet appareil.", '⌁'],
    'unknown-result': ["Le résultat n'est pas encore établi", 'La valeur reste protégée pendant la vérification.', '?'],
    default: ['Information disponible', 'Choisissez une fixture pour vérifier un état.', 'i'],
    confirmed: ['Action confirmée', 'La preuve a été enregistrée.', '✓'],
  }[fixture];

  return (
    <section className="state-demo" aria-labelledby="state-title">
      <div className="state-demo__symbol" aria-hidden="true">{content[2]}</div>
      <span className="eyebrow">État système · {fixture}</span>
      <h2 id="state-title">{content[0]}</h2>
      <p>{content[1]}</p>
      <div className="button-row"><button className="button">Action sûre</button><button className="button button--ghost">Obtenir de l'aide</button></div>
    </section>
  );
}

export function StoryCanvas({ story, fixture }: { story: Story; fixture: FixtureId }) {
  if (story.id === 'foundations/wallet') return <WalletTruth fixture={fixture} />;
  if (story.id === 'foundations/status') return <StatusStory fixture={fixture} />;
  return <SystemStates fixture={fixture} />;
}

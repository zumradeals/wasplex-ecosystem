import { Status } from './Status';

type WalletAmounts = {
  available: number;
  provisional: number;
  reserved: number;
};

const formatter = new Intl.NumberFormat('fr-FR');

function Amount({ value, label, tone }: { value: number; label: string; tone: 'available' | 'provisional' | 'reserved' }) {
  return (
    <div className={`amount amount--${tone}`}>
      <span className="amount__label">{label}</span>
      <strong className="amount__value">{formatter.format(value)} <small>WP</small></strong>
      <span className="amount__equivalent">≈ {formatter.format(value)} FCFA</span>
    </div>
  );
}

export function WalletTruth({ fixture }: { fixture: string }) {
  const amounts: WalletAmounts = fixture === 'empty'
    ? { available: 0, provisional: 0, reserved: 0 }
    : { available: 275, provisional: 90, reserved: 50 };

  if (fixture === 'loading') {
    return <div className="skeleton-stack" aria-label="Chargement des soldes"><span /><span /><span /></div>;
  }

  return (
    <section className="wallet-demo" aria-labelledby="wallet-title">
      <header className="demo-heading">
        <div>
          <span className="eyebrow">Vérité financière</span>
          <h2 id="wallet-title">Votre Wallet</h2>
        </div>
        <Status tone={fixture === 'offline' ? 'unknown' : 'success'} label={fixture === 'offline' ? 'Données anciennes' : 'À jour'} />
      </header>

      {fixture === 'offline' && (
        <div className="system-message system-message--unknown" role="status">
          <span className="system-message__icon" aria-hidden="true">⌁</span>
          <div><strong>Vous êtes hors ligne</strong><p>Soldes synchronisés le 22 juillet à 14:32. Les mouvements sont temporairement bloqués.</p></div>
        </div>
      )}

      <div className="amount-grid">
        <Amount value={amounts.available} label="Disponibles" tone="available" />
        <Amount value={amounts.provisional} label="Provisoires" tone="provisional" />
        <Amount value={amounts.reserved} label="Réservés" tone="reserved" />
      </div>

      {fixture === 'unknown-result' && (
        <div className="unknown-card" role="status">
          <div className="unknown-card__mark" aria-hidden="true">?</div>
          <div>
            <span className="eyebrow">Retrait WP-240722-0186</span>
            <h3>Le résultat n'est pas encore établi</h3>
            <p>50 WP restent réservés pendant la vérification. Ne relancez pas le retrait.</p>
            <button className="button button--secondary">Consulter le dossier</button>
          </div>
        </div>
      )}

      {fixture === 'confirmed' && (
        <div className="system-message system-message--success" role="status">
          <span className="system-message__icon" aria-hidden="true">✓</span>
          <div><strong>Retrait confirmé</strong><p>225 FCFA nets ont été remis. Référence : WP-240722-0185.</p></div>
        </div>
      )}

      <p className="truth-note">1 WP = 1 FCFA · Démonstration — données fictives</p>
    </section>
  );
}

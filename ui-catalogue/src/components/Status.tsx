export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'pending' | 'unknown';

const labels: Record<StatusTone, string> = {
  success: 'Confirmé',
  warning: 'Action requise',
  danger: 'Échec grave',
  info: 'Information',
  pending: 'En attente',
  unknown: 'Résultat inconnu',
};

const symbols: Record<StatusTone, string> = {
  success: '✓',
  warning: '!',
  danger: '×',
  info: 'i',
  pending: '…',
  unknown: '?',
};

export function Status({ tone, label = labels[tone] }: { tone: StatusTone; label?: string }) {
  return (
    <span className={`status status--${tone}`} data-testid={`status-${tone}`}>
      <span className="status__symbol" aria-hidden="true">{symbols[tone]}</span>
      <span>{label}</span>
    </span>
  );
}

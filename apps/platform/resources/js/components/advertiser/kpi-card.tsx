import type { LucideIcon } from 'lucide-react';

export function KpiCard({
    icon: Icon,
    label,
    value,
    hint,
    tone = 'neutral',
}: {
    icon: LucideIcon;
    label: string;
    value: string;
    hint?: string;
    tone?: 'neutral' | 'success' | 'warning' | 'info';
}) {
    const toneColor = {
        neutral: 'text-[var(--text-primary)]',
        success: 'text-[var(--status-success)]',
        warning: 'text-[var(--status-warning)]',
        info: 'text-[var(--brand-blue)]',
    }[tone];

    return (
        <div className="rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-4">
            <div className="mb-2 flex items-center gap-2 text-[var(--text-secondary)]">
                <Icon size={16} />
                <span className="text-xs font-medium">{label}</span>
            </div>
            <p className={`text-2xl font-bold tabular-nums ${toneColor}`}>
                {value}
            </p>
            {hint && (
                <p className="mt-1 text-xs text-[var(--text-secondary)]">
                    {hint}
                </p>
            )}
        </div>
    );
}

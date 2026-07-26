import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export function AdvertiserEmptyState({
    icon: Icon,
    title,
    description,
    action,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-[var(--border-default)] px-6 py-14 text-center">
            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--bg-raised)] text-[var(--text-secondary)]">
                <Icon size={22} />
            </div>
            <h3 className="mb-1 text-sm font-semibold text-[var(--text-primary)]">
                {title}
            </h3>
            <p className="max-w-sm text-sm text-[var(--text-secondary)]">
                {description}
            </p>
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

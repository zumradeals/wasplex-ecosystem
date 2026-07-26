import { usePage } from '@inertiajs/react';

import WasplexMascot from '@/components/wasplex-mascot';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <WasplexMascot className="size-8 shrink-0" />
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    {name}
                </span>
            </div>
        </>
    );
}

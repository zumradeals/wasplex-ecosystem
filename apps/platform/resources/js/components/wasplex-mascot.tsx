import type { ImgHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * Logo officiel Wasplex (DS-0001 §3/§4) : martin-pêcheur perché, poisson
 * au bec, mot-symbole « WasPlex » intégré à l'image — déposé dans
 * `public/brand/`. `object-contain` préserve le ratio réel du fichier
 * (266×305, pas carré) même quand l'appelant impose une boîte carrée
 * (`h-8 w-8` etc.) : l'image est toujours entière, jamais étirée.
 */
export default function WasplexMascot({
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/brand/wasplex-mascot.png"
            alt="Wasplex"
            className={cn('object-contain', className)}
            {...props}
        />
    );
}

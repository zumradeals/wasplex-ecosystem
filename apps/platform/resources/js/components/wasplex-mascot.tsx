import type { SVGAttributes } from 'react';

/**
 * Martin-pêcheur Wasplex — DS-0001 §3/§4.
 * Référence directionnelle en attendant la redessination vectorielle officielle.
 * Couleurs : brand.blue #075CCF, brand.orange #C75100, brand.navy #10233F.
 * Préserve : crête, bec horizontal, contraste bleu/orange, poisson, énergie.
 */
export default function WasplexMascot({
    className,
    ...props
}: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 160 160"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className={className}
            {...props}
        >
            {/* Queue pointée bas-gauche */}
            <path
                d="M 46 112 L 18 138 L 38 128 L 22 148 L 50 130"
                fill="#0A4FAF"
            />

            {/* Corps principal (ovale bleu, légèrement incliné) */}
            <ellipse
                cx="62"
                cy="100"
                rx="26"
                ry="36"
                fill="#075CCF"
                transform="rotate(-8 62 100)"
            />

            {/* Poitrine orangée — Constitution art. 1, énergie de marque */}
            <path
                d="M 68 72 Q 82 82 78 108 Q 64 116 52 104 Q 48 86 60 72 Z"
                fill="#C75100"
            />

            {/* Cou reliant tête et corps */}
            <path
                d="M 70 76 Q 72 68 80 64"
                stroke="#075CCF"
                strokeWidth="14"
                strokeLinecap="round"
                fill="none"
            />

            {/* Tête (grand cercle — proportion martin-pêcheur) */}
            <circle cx="88" cy="58" r="30" fill="#075CCF" />

            {/* Crête — DS-0001 §4, trois pointes ascendantes */}
            <path d="M 74 34 L 79 16 L 84 34" fill="#10233F" />
            <path d="M 82 31 L 88 10 L 94 31" fill="#10233F" />
            <path d="M 90 34 L 96 18 L 102 34" fill="#10233F" />

            {/* Tache jugulaire blanche */}
            <ellipse
                cx="100"
                cy="68"
                rx="8"
                ry="6"
                fill="#E8F0FC"
                opacity="0.85"
            />

            {/* Œil — blanc */}
            <circle cx="96" cy="52" r="7" fill="white" />
            {/* Pupille */}
            <circle cx="97" cy="52" r="3.5" fill="#10233F" />
            {/* Point spéculaire */}
            <circle cx="95.5" cy="50.5" r="1.2" fill="white" />

            {/* Bec long pointant droite-bas — DS-0001 §4 bec horizontal */}
            <path d="M 114 60 L 148 68 L 114 66 Z" fill="#10233F" />
            {/* Mandibule inférieure légèrement plus claire */}
            <path d="M 114 63 L 145 70 L 114 66 Z" fill="#1A3050" />

            {/* Poisson au bout du bec — DS-0001 §3, valeur économique captée */}
            <ellipse cx="152" cy="65" rx="7" ry="4" fill="#CBD5E1" />
            <path d="M 156 62 L 162 58 L 162 70 Z" fill="#CBD5E1" />
            {/* Œil du poisson */}
            <circle cx="149" cy="64" r="1.2" fill="#10233F" />

            {/* Détail aile — légère variation de ton */}
            <path
                d="M 44 88 Q 36 100 40 118"
                stroke="#0A4FAF"
                strokeWidth="3"
                strokeLinecap="round"
                fill="none"
            />
        </svg>
    );
}

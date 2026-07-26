import type { SVGAttributes } from 'react';
import { useId } from 'react';

/**
 * Martin-pêcheur Wasplex — DS-0001 §3/§4.
 * Vectorisation directionnelle du logo officiel (oiseau perché sur une
 * branche, poisson tenu en travers du long bec, crête balayée vers
 * l'arrière, poitrine orange, dos et ailes bleus, pattes orange).
 * Couleurs : brand.blue #075CCF / #4FA3FF, brand.orange #C75100,
 * brand.navy #10233F. Remplaçable par l'asset officiel dès qu'il est
 * versionné dans `public/brand/`.
 */
export default function WasplexMascot({
    className,
    ...props
}: SVGAttributes<SVGElement>) {
    // Des identifiants uniques par instance : plusieurs mascottes peuvent
    // coexister sur un écran sans collision d'IDs de dégradés.
    const uid = useId();
    const blue = `wpx-blue-${uid}`;
    const deep = `wpx-deep-${uid}`;
    const orange = `wpx-orange-${uid}`;

    return (
        <svg
            viewBox="0 0 160 150"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
            className={className}
            {...props}
        >
            <defs>
                <linearGradient id={blue} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#4FA3FF" />
                    <stop offset="1" stopColor="#075CCF" />
                </linearGradient>
                <linearGradient id={deep} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#0A4FAF" />
                    <stop offset="1" stopColor="#072F66" />
                </linearGradient>
                <linearGradient id={orange} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" stopColor="#FFB25C" />
                    <stop offset="1" stopColor="#C75100" />
                </linearGradient>
            </defs>

            {/* Queue : plumes sombres pointant bas-droite, derrière la branche */}
            <path
                d="M 96 90 L 140 124 L 122 122 L 144 140 L 100 116 Z"
                fill={`url(#${deep})`}
            />

            {/* Branche */}
            <path
                d="M 6 132 Q 80 118 154 126 L 153 137 Q 80 129 7 143 Z"
                fill="#8A5A33"
            />
            <path
                d="M 6 138 Q 80 125 154 132 L 153 137 Q 80 129 7 143 Z"
                fill="#6E4522"
            />

            {/* Corps */}
            <path
                d="M 48 60 Q 32 90 48 112 Q 62 128 84 126 Q 108 122 110 94 Q 112 66 86 52 Z"
                fill={`url(#${blue})`}
            />

            {/* Poitrine orange (avant, descend vers le ventre) */}
            <path
                d="M 48 64 Q 36 92 50 110 Q 62 124 82 124 Q 88 110 80 92 Q 70 72 64 62 Z"
                fill={`url(#${orange})`}
            />

            {/* Aile repliée (droite), plumes sombres */}
            <path
                d="M 78 66 Q 106 70 106 94 Q 104 114 88 122 Q 98 98 80 74 Z"
                fill={`url(#${deep})`}
            />
            <path
                d="M 86 78 Q 100 86 96 106"
                stroke="#4FA3FF"
                strokeWidth="2.5"
                strokeLinecap="round"
                fill="none"
                opacity="0.5"
            />

            {/* Crête : plumes balayées vers l'arrière (sous la tête) */}
            <path
                d="M 44 34 Q 38 14 50 4 Q 54 18 60 24 Z"
                fill={`url(#${blue})`}
            />
            <path
                d="M 54 26 Q 56 4 72 0 Q 68 16 72 22 Z"
                fill={`url(#${blue})`}
            />
            <path
                d="M 66 24 Q 76 4 92 4 Q 84 18 84 26 Z"
                fill={`url(#${blue})`}
            />
            <path
                d="M 80 30 Q 90 20 98 22 Q 92 28 90 33 Z"
                fill={`url(#${deep})`}
            />

            {/* Bec : long poignard sombre, base cachée sous la tête */}
            <path d="M 54 38 L 0 48 L 54 50 Z" fill="#10233F" />
            <path d="M 54 52 L 4 53 L 54 60 Z" fill="#1A3050" />

            {/* Tête */}
            <circle cx="66" cy="46" r="27" fill={`url(#${blue})`} />

            {/* Tache blanche du cou (arrière de la joue) */}
            <ellipse
                cx="84"
                cy="60"
                rx="6.5"
                ry="9.5"
                fill="#F5F8FC"
                transform="rotate(25 84 60)"
            />

            {/* Œil */}
            <circle cx="56" cy="44" r="8.5" fill="#F5F8FC" />
            <circle cx="54" cy="45" r="5.2" fill="#10233F" />
            <circle cx="52.5" cy="43" r="1.8" fill="#FFFFFF" />

            {/* Poisson tenu en travers du bec — valeur économique captée */}
            <g transform="rotate(-30 11 49)">
                <ellipse cx="11" cy="49" rx="5" ry="10" fill="#C8D3DE" />
                <path d="M 7 41 L 11 28 L 16 41 Z" fill="#AFBDCB" />
                <circle cx="9" cy="55" r="1.4" fill="#10233F" />
                <path
                    d="M 6 47 Q 11 51 16 47"
                    stroke="#93A5B6"
                    strokeWidth="1.2"
                    fill="none"
                />
            </g>

            {/* Pattes orange agrippées sur le dessus de la branche */}
            <path
                d="M 62 118 L 57 130 M 62 118 L 63 131 M 62 118 L 69 129"
                stroke="#FF9A3D"
                strokeWidth="4.5"
                strokeLinecap="round"
            />
            <path
                d="M 84 116 L 80 128 M 84 116 L 88 128"
                stroke="#FF9A3D"
                strokeWidth="4.5"
                strokeLinecap="round"
            />
        </svg>
    );
}

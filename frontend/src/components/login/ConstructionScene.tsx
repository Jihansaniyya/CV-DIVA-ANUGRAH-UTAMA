import type { CSSProperties } from 'react'

/**
 * Ilustrasi latar halaman login: gedung dalam tahap konstruksi, tower crane,
 * dan cahaya matahari sore. Seluruh warna memakai token tema (navy, warning, primary).
 */

const LANTAI_A = [270, 325, 380, 435, 490, 545]
const KOLOM_A = [8, 64, 120, 176, 232, 282]
const LANTAI_B = [150, 205, 260, 315]
const KOLOM_B = [104, 150, 196, 236]
const LANTAI_C = [405, 455, 505, 555]
const KOLOM_C = [338, 392, 446, 500, 536]

/** Kisi rangka (lattice) crane: garis zig-zag di antara dua garis sejajar. */
function kisiVertikal(x1: number, x2: number, atas: number, bawah: number, langkah: number): string {
  const titik: string[] = []

  for (let y = bawah, kiri = true; y >= atas; y -= langkah, kiri = !kiri) {
    titik.push(`${kiri ? x1 : x2},${y}`)
  }

  return titik.join(' ')
}

function kisiHorizontal(kiri: number, kanan: number, y1: number, y2: number, langkah: number): string {
  const titik: string[] = []

  for (let x = kiri, atas = true; x <= kanan; x += langkah, atas = !atas) {
    titik.push(`${x},${atas ? y1 : y2}`)
  }

  return titik.join(' ')
}

export function ConstructionScene({ className, style }: { className?: string; style?: CSSProperties }) {
  return (
    <svg viewBox="0 0 1000 640" preserveAspectRatio="xMinYMax slice" className={className} style={style} aria-hidden focusable="false">
      <defs>
        <radialGradient id="login-sun" cx="0.6" cy="0.82" r="0.45">
          <stop offset="0" stopColor="var(--color-warning)" stopOpacity="0.85" />
          <stop offset="0.35" stopColor="var(--color-warning)" stopOpacity="0.28" />
          <stop offset="1" stopColor="var(--color-warning)" stopOpacity="0" />
        </radialGradient>
        <linearGradient id="login-ground" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stopColor="var(--color-navy)" stopOpacity="0.55" />
          <stop offset="1" stopColor="var(--color-navy-dark)" stopOpacity="0.95" />
        </linearGradient>
        <filter id="login-blur" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur stdDeviation="8" />
        </filter>
      </defs>

      {/* Cahaya matahari sore di dekat cakrawala */}
      <rect width="1000" height="640" fill="url(#login-sun)" />
      <circle cx="600" cy="528" r="30" fill="var(--color-warning)" opacity="0.9" filter="url(#login-blur)" />

      {/* Perbukitan dan bangunan jauh */}
      <path
        d="M0 545 C 150 512 270 528 390 520 S 640 500 770 518 S 920 508 1000 524 L1000 640 L0 640 Z"
        fill="var(--color-navy)"
        opacity="0.16"
      />
      <g fill="var(--color-navy)" opacity="0.28">
        <rect x="660" y="548" width="46" height="52" />
        <rect x="712" y="560" width="30" height="40" />
        <rect x="752" y="540" width="22" height="60" />
        <rect x="786" y="566" width="60" height="34" />
        <rect x="858" y="552" width="34" height="48" />
        <rect x="904" y="570" width="70" height="30" />
      </g>

      {/* Gedung B (paling belakang, lebih tinggi) */}
      <g fill="var(--color-navy)" opacity="0.45">
        {LANTAI_B.map((y) => (
          <rect key={y} x="96" y={y} width="152" height="6" />
        ))}
        {KOLOM_B.map((x) => (
          <rect key={x} x={x} y="150" width="8" height="450" />
        ))}
      </g>
      <g stroke="var(--color-navy)" strokeWidth="1.5" opacity="0.45">
        {KOLOM_B.map((x) => (
          <line key={x} x1={x + 4} y1="150" x2={x + 4} y2="118" />
        ))}
      </g>

      {/* Gedung C (kanan, lebih rendah, dengan scaffolding) */}
      <g fill="var(--color-navy)" opacity="0.7">
        {LANTAI_C.map((y) => (
          <rect key={y} x="330" y={y} width="216" height="7" />
        ))}
        {KOLOM_C.map((x) => (
          <rect key={x} x={x} y="405" width="9" height="195" />
        ))}
      </g>
      <g stroke="var(--color-navy)" strokeWidth="1.2" opacity="0.4" fill="none">
        <path d="M330 455 L392 405 M392 455 L446 405 M446 455 L500 405 M330 505 L392 455 M446 505 L500 455 M392 555 L446 505" />
      </g>

      {/* Gedung A (utama, depan) */}
      <g fill="var(--color-navy)" opacity="0.88">
        {LANTAI_A.map((y) => (
          <rect key={y} x="0" y={y} width="300" height="8" />
        ))}
        {KOLOM_A.map((x) => (
          <rect key={x} x={x} y="270" width="10" height="330" />
        ))}
        {/* Dinding bata yang sudah terpasang di lantai bawah */}
        <rect x="18" y="498" width="46" height="47" opacity="0.55" />
        <rect x="74" y="498" width="46" height="47" opacity="0.55" />
        <rect x="186" y="553" width="46" height="47" opacity="0.55" />
        <rect x="130" y="443" width="46" height="47" opacity="0.4" />
      </g>
      <g stroke="var(--color-navy)" strokeWidth="1.6" opacity="0.8">
        {KOLOM_A.flatMap((x) => [
          <line key={`${x}-a`} x1={x + 3} y1="270" x2={x + 2} y2="226" />,
          <line key={`${x}-b`} x1={x + 7} y1="270" x2={x + 8} y2="232" />,
        ])}
      </g>

      {/* Tower crane */}
      <g stroke="var(--color-navy-dark)" fill="none" strokeLinejoin="round">
        <line x1="402" y1="600" x2="402" y2="126" strokeWidth="2.5" />
        <line x1="420" y1="600" x2="420" y2="126" strokeWidth="2.5" />
        <polyline points={kisiVertikal(402, 420, 126, 600, 18)} strokeWidth="1.2" />

        <line x1="330" y1="118" x2="840" y2="118" strokeWidth="2.5" />
        <line x1="330" y1="132" x2="840" y2="132" strokeWidth="2.5" />
        <polyline points={kisiHorizontal(330, 840, 118, 132, 14)} strokeWidth="1" />

        <polyline points="411,62 402,118 420,118 411,62" strokeWidth="2" />
        <line x1="411" y1="62" x2="836" y2="118" strokeWidth="1.2" />
        <line x1="411" y1="62" x2="336" y2="118" strokeWidth="1.2" />

        <line x1="566" y1="132" x2="566" y2="318" strokeWidth="1.2" />
        <path d="M566 318 v8 a5 5 0 1 1 -5 5" strokeWidth="2" />
        <line x1="530" y1="346" x2="602" y2="346" strokeWidth="1" />
        <line x1="566" y1="336" x2="530" y2="346" strokeWidth="1" />
        <line x1="566" y1="336" x2="602" y2="346" strokeWidth="1" />
      </g>
      <g fill="var(--color-navy-dark)">
        <rect x="338" y="132" width="38" height="20" />
        <rect x="396" y="132" width="30" height="22" rx="2" />
        <rect x="560" y="132" width="12" height="6" />
        <rect x="528" y="346" width="76" height="8" />
        <rect x="394" y="596" width="34" height="8" />
      </g>
      <rect x="400" y="137" width="11" height="8" fill="var(--color-warning)" opacity="0.7" />

      {/* Tanah dan tumpukan material */}
      <rect x="0" y="600" width="1000" height="40" fill="url(#login-ground)" />
      <g fill="var(--color-navy-dark)" opacity="0.75">
        <rect x="610" y="584" width="120" height="8" />
        <rect x="618" y="576" width="104" height="8" />
        <rect x="626" y="568" width="88" height="8" />
        <rect x="760" y="588" width="70" height="12" />
        <rect x="540" y="590" width="44" height="10" />
      </g>
    </svg>
  )
}

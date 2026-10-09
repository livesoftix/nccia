/** Animated NCCIA guide mascot (anime-style SVG) */
export default function SoftwareGuideCharacter({ speaking = false, wave = false, size = 240 }) {
  const height = Math.round(size * 1.4);
  return (
    <svg viewBox="0 0 200 280" width={size} height={height} aria-hidden="true" className="sg-character">
      <defs>
        <linearGradient id="sg-uniform" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stopColor="#1e4d8c" />
          <stop offset="100%" stopColor="#0f2d54" />
        </linearGradient>
        <linearGradient id="sg-skin" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#fdd9b5" />
          <stop offset="100%" stopColor="#e8b88a" />
        </linearGradient>
      </defs>

      {/* shadow */}
      <ellipse cx="100" cy="268" rx="52" ry="8" fill="rgba(0,0,0,0.12)" />

      {/* legs */}
      <rect x="78" y="210" width="18" height="48" rx="6" fill="#1a365d" />
      <rect x="104" y="210" width="18" height="48" rx="6" fill="#1a365d" />
      <rect x="74" y="252" width="26" height="10" rx="4" fill="#0f172a" />
      <rect x="100" y="252" width="26" height="10" rx="4" fill="#0f172a" />

      {/* body */}
      <path d="M62 118 Q100 108 138 118 L145 210 Q100 220 55 210 Z" fill="url(#sg-uniform)" />
      <path d="M88 128 L112 128 L108 148 L92 148 Z" fill="#fff" opacity="0.9" />
      <text x="100" y="143" textAnchor="middle" fontSize="8" fontWeight="700" fill="#1e4d8c">NCCIA</text>
      <rect x="94" y="152" width="12" height="14" rx="2" fill="#c9a227" />
      <circle cx="100" cy="159" r="3" fill="#fbbf24" />

      {/* left arm */}
      <g className={wave ? 'sg-arm-wave' : ''} style={{ transformOrigin: '68px 130px' }}>
        <path d="M62 125 Q40 140 36 168 Q34 178 42 182" stroke="url(#sg-uniform)" strokeWidth="14" fill="none" strokeLinecap="round" />
        <circle cx="42" cy="182" r="10" fill="url(#sg-skin)" />
      </g>

      {/* right arm */}
      <path d="M138 125 Q160 138 164 165 Q166 175 158 178" stroke="url(#sg-uniform)" strokeWidth="14" fill="none" strokeLinecap="round" />
      <circle cx="158" cy="178" r="10" fill="url(#sg-skin)" />

      {/* neck */}
      <rect x="90" y="98" width="20" height="22" rx="6" fill="url(#sg-skin)" />

      {/* head */}
      <ellipse cx="100" cy="72" rx="42" ry="46" fill="url(#sg-skin)" />
      <path d="M58 58 Q68 28 100 24 Q132 28 142 58 Q138 42 100 38 Q62 42 58 58" fill="#1e293b" />
      <path d="M58 58 Q55 72 60 82 Q72 68 88 64 Q78 52 58 58" fill="#1e293b" />
      <path d="M142 58 Q145 72 140 82 Q128 68 112 64 Q122 52 142 58" fill="#1e293b" />

      {/* eyes */}
      <g className="sg-eyes">
        <ellipse cx="84" cy="74" rx="7" ry="9" fill="#fff" />
        <ellipse cx="116" cy="74" rx="7" ry="9" fill="#fff" />
        <circle cx="86" cy="76" r="4" fill="#1e293b" />
        <circle cx="118" cy="76" r="4" fill="#1e293b" />
        <circle cx="87" cy="75" r="1.2" fill="#fff" />
        <circle cx="119" cy="75" r="1.2" fill="#fff" />
      </g>

      {/* mouth */}
      <ellipse
        cx="100"
        cy="92"
        rx={speaking ? 9 : 6}
        ry={speaking ? 7 : 3}
        fill="#c45c5c"
        className={speaking ? 'sg-mouth-talk' : ''}
      />

      {/* badge cap */}
      <path d="M70 48 L100 34 L130 48 L125 56 Q100 50 75 56 Z" fill="#1e4d8c" />
      <circle cx="100" cy="44" r="5" fill="#fbbf24" stroke="#fff" strokeWidth="1" />
    </svg>
  );
}

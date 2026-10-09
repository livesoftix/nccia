export default function ProgressBar({ value, color, height = 8, showLabel = false, label }) {
  const v = Math.max(0, Math.min(100, Number(value) || 0));
  const autoColor = v >= 100 ? '#38a169' : v >= 60 ? '#015C94' : v >= 40 ? '#e5a100' : '#e53e3e';
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, width: '100%' }}>
      <div className="progress-bar-bg" style={{ height }}>
        <div className="progress-bar-fill" style={{ width: `${v}%`, background: color || autoColor }}></div>
      </div>
      {showLabel && (
        <span style={{ fontSize: 12, fontWeight: 700, minWidth: 38, textAlign: 'right', color: '#2b2b2b', fontVariantNumeric: 'tabular-nums' }}>
          {label ?? `${v}%`}
        </span>
      )}
    </div>
  );
}

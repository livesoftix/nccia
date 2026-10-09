import { useState, useRef, useEffect } from 'react';

const getLabel = (option, labelKey, formatLabel) => {
  if (formatLabel) return formatLabel(option);
  if (typeof labelKey === 'function') return labelKey(option);
  return option[labelKey] ?? option;
};

const getValue = (option, valueKey) => {
  return option[valueKey] ?? option;
};

export default function SearchableSelect({ value, onChange, options, placeholder, labelKey = 'name', valueKey = 'value', formatLabel, style }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const ref = useRef(null);

  const selected = options.find(o => String(getValue(o, valueKey)) === String(value ?? ''));

  const filtered = search
    ? options.filter(o => {
        const label = getLabel(o, labelKey, formatLabel).toString().toLowerCase();
        const val = String(getValue(o, valueKey)).toLowerCase();
        return label.includes(search.toLowerCase()) || val.includes(search.toLowerCase());
      })
    : options;

  useEffect(() => {
    const handleClick = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false);
    };
    document.addEventListener('mousedown', handleClick);
    return () => document.removeEventListener('mousedown', handleClick);
  }, []);

  const handleSelect = (option) => {
    onChange(getValue(option, valueKey));
    setOpen(false);
    setSearch('');
  };

  return (
    <div ref={ref} style={{ position: 'relative', ...style }}>
      <div
        onClick={() => setOpen(!open)}
        style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          padding: '9px 14px', borderRadius: 'var(--border-radius-sm)',
          border: '1.5px solid var(--border)', background: '#fff',
          fontSize: 13, color: '#2b2b2b', cursor: 'pointer', minHeight: '40px',
        }}
      >
        <span style={{ color: selected ? '#2b2b2b' : '#aaa' }}>
          {selected ? getLabel(selected, labelKey, formatLabel) : (placeholder || 'Select...')}
        </span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ transform: open ? 'rotate(180deg)' : 'none', transition: 'transform 0.2s' }}>
          <polyline points="6 9 12 15 18 9" />
        </svg>
      </div>

      {open && (
        <div style={{
          position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 1000,
          marginTop: 4, background: '#fff', borderRadius: '8px',
          boxShadow: '0 8px 24px rgba(0,0,0,0.12), 0 0 0 1px rgba(0,0,0,0.05)',
          maxHeight: '280px', display: 'flex', flexDirection: 'column',
        }}>
          <div style={{ padding: '8px', borderBottom: '1px solid #e5e5e5' }}>
            <input
              autoFocus
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Type to search..."
              style={{
                width: '100%', padding: '8px 12px', border: '1.5px solid #264078',
                borderRadius: '6px', fontSize: 13, outline: 'none', boxSizing: 'border-box',
              }}
              onClick={e => e.stopPropagation()}
            />
          </div>
          <div style={{ overflowY: 'auto', maxHeight: '220px' }}>
            {filtered.length === 0 ? (
              <div style={{ padding: '12px 16px', color: '#999', fontSize: 13, textAlign: 'center' }}>
                No results found
              </div>
            ) : (
              filtered.map((option, i) => {
                const optValue = getValue(option, valueKey);
                const optLabel = getLabel(option, labelKey, formatLabel);
                const isSelected = optValue === value;
                return (
                  <div
                    key={i}
                    onClick={() => handleSelect(option)}
                    style={{
                      padding: '9px 16px', fontSize: 13, cursor: 'pointer',
                      background: isSelected ? 'rgba(1,92,148,0.08)' : 'transparent',
                      color: isSelected ? '#015C94' : '#2b2b2b',
                      fontWeight: isSelected ? 600 : 400,
                      borderBottom: '1px solid #f0f0f0',
                    }}
                    onMouseEnter={e => e.target.style.background = '#f5f7fa'}
                    onMouseLeave={e => e.target.style.background = isSelected ? 'rgba(1,92,148,0.08)' : 'transparent'}
                  >
                    {optLabel}
                  </div>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
}

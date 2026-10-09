export default function ReferenceViewModal({ open, title, item, fields, onClose }) {
  if (!open || !item) return null;

  const formatField = (key, value) => {
    if (!value) return '—';
    if (key === 'effective_date' || key.endsWith('_date')) {
      const d = new Date(value);
      if (!Number.isNaN(d.getTime())) {
        return d.toLocaleDateString('en-GB');
      }
    }
    return value;
  };

  const body = item.description || item.content;

  return (
    <div
      style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.5)', padding: 20 }}
      onClick={onClose}
    >
      <div
        style={{ background: '#fff', borderRadius: 12, width: '100%', maxWidth: 780, maxHeight: '90vh', overflow: 'auto', boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }}
        onClick={e => e.stopPropagation()}
      >
        <div style={{ padding: '18px 24px', borderBottom: '1px solid #f0f0f0', position: 'sticky', top: 0, background: '#fff', zIndex: 1 }}>
          <h3 style={{ margin: 0, fontSize: 16, fontWeight: 700, color: '#1e293b' }}>{title || item.title}</h3>
        </div>
        <div style={{ padding: '18px 24px 24px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 14, marginBottom: 16 }}>
            {fields.filter(f => f.key !== 'description').map(f => (
              <div key={f.key}>
                <div style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 700 }}>{f.label}</div>
                <div style={{ marginTop: 4, fontSize: 13, fontWeight: 600 }}>{formatField(f.key, item[f.key])}</div>
              </div>
            ))}
          </div>
          {body && (
            <>
              <div style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 700, marginBottom: 8 }}>Full Document</div>
              <div style={{ padding: 16, background: '#f8fafc', borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 13, lineHeight: 1.65, whiteSpace: 'pre-wrap', fontFamily: 'inherit' }}>
                {body}
              </div>
            </>
          )}
          <div style={{ marginTop: 16, textAlign: 'right' }}>
            <button type="button" className="btn btn-outline btn-sm" onClick={onClose}>Close</button>
          </div>
        </div>
      </div>
    </div>
  );
}

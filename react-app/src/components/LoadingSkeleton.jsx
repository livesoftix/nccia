export default function LoadingSkeleton({ type, rows, columns }) {
  if (type === 'table') {
    const cols = columns || 5;
    const rCount = rows || 6;
    return (
      <div className="card">
        <div className="card-body" style={{ padding: 0 }}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>{Array.from({ length: cols }).map((_, i) => (
                  <th key={i}><div style={{ height: 14, width: '55%', background: '#e9ecef', borderRadius: 4 }} /></th>
                ))}</tr>
              </thead>
              <tbody>{Array.from({ length: rCount }).map((_, r) => (
                <tr key={r}>{Array.from({ length: cols }).map((_, c) => (
                  <td key={c}><div style={{ height: 12, width: c === 0 ? '40%' : c === 1 ? '70%' : '55%', background: '#f3f4f6', borderRadius: 4 }} /></td>
                ))}</tr>
              ))}</tbody>
            </table>
          </div>
        </div>
      </div>
    );
  }

  if (type === 'stats') {
    const count = rows || 4;
    return (
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 16, marginBottom: 24 }}>
        {Array.from({ length: count }).map((_, i) => (
          <div key={i} style={{ background: '#fff', borderRadius: 12, padding: 20, border: '1px solid #f0f0f0' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
              <div style={{ width: 36, height: 36, borderRadius: 10, background: '#f3f4f6' }} />
              <div style={{ width: 50, height: 16, borderRadius: 4, background: '#f3f4f6' }} />
            </div>
            <div style={{ height: 28, width: '60%', background: '#e9ecef', borderRadius: 6, marginBottom: 6 }} />
            <div style={{ height: 12, width: '40%', background: '#f3f4f6', borderRadius: 4, marginBottom: 8 }} />
            <div style={{ height: 10, width: '70%', background: '#f8f9fa', borderRadius: 4 }} />
          </div>
        ))}
      </div>
    );
  }

  if (type === 'card') {
    return (
      <div className="card">
        <div className="card-header">
          <div style={{ height: 16, width: '35%', background: '#e9ecef', borderRadius: 4 }} />
        </div>
        <div className="card-body" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          {Array.from({ length: 4 }).map((_, i) => (
            <div key={i} style={{ height: 12, width: `${70 + i * 5}%`, background: '#f3f4f6', borderRadius: 4 }} />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12, padding: 40 }}>
      {Array.from({ length: 5 }).map((_, i) => (
        <div key={i} style={{ height: 12, width: `${60 + i * 6}%`, background: '#f3f4f6', borderRadius: 4 }} />
      ))}
    </div>
  );
}

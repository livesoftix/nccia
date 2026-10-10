export default function Pagination({ page, lastPage, onChange, disabled = false }) {
  return <nav aria-label="Pagination" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 12, padding: 16 }}>
    <button type="button" className="btn btn-outline btn-sm" disabled={disabled || page <= 1} onClick={() => onChange(page - 1)}>Previous</button>
    <span>Page {page} of {lastPage} · 10 per page</span>
    <button type="button" className="btn btn-outline btn-sm" disabled={disabled || page >= lastPage} onClick={() => onChange(page + 1)}>Next</button>
  </nav>;
}

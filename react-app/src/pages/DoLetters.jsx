import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { canCompileAdminReports } from '../utils/adminReports';
import { REPORT_STATUS_LABELS } from '../utils/adminReports';

export default function DoLetters() {
  const { user } = useAuth();
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ month_from: '', month_to: '' });

  const load = () => {
    setLoading(true);
    const params = {};
    if (filters.month_from) params.month_from = filters.month_from + '-01';
    if (filters.month_to) params.month_to = filters.month_to + '-01';
    api.get('/do-letters', { params }).then(r => {
      setList(r.data.data || r.data);
      setLoading(false);
    }).catch(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, []);

  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label">Administration</div>
          <h1 className="page-title">D.O. Letter (Monthly)</h1>
          <p className="page-subtitle">Monthly circle report — same approval workflow as DSR</p>
          <div className="title-underline"></div>
        </div>
        {canCompileAdminReports(user) && (
          <div className="page-actions">
            <Link to="/do-letters/create" className="btn btn-primary">+ New D.O. Letter</Link>
          </div>
        )}
      </div>

      <div className="card" style={{ marginBottom: 16 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12, alignItems: 'end' }}>
          <div>
            <label>From Month</label>
            <input type="month" value={filters.month_from} onChange={e => setFilters(f => ({ ...f, month_from: e.target.value }))} />
          </div>
          <div>
            <label>To Month</label>
            <input type="month" value={filters.month_to} onChange={e => setFilters(f => ({ ...f, month_to: e.target.value }))} />
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button type="button" className="btn btn-primary btn-sm" onClick={load}>Filter</button>
            <button type="button" className="btn btn-sm" onClick={() => {
              setFilters({ month_from: '', month_to: '' });
              setLoading(true);
              api.get('/do-letters').then(r => {
                setList(r.data.data || r.data);
                setLoading(false);
              }).catch(() => setLoading(false));
            }}>Clear</button>
          </div>
        </div>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden' }}>
        <table className="data-table" style={{ width: '100%' }}>
          <thead>
            <tr>
              <th>Month</th>
              <th>Circle</th>
              <th>Status</th>
              <th>Compiled By</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {list.length === 0 && (
              <tr><td colSpan={5} style={{ textAlign: 'center', padding: 24, color: '#888' }}>No D.O. letters yet</td></tr>
            )}
            {list.map(row => (
              <tr key={row.id}>
                <td>{row.month_label}</td>
                <td>{row.circle?.name || '—'}</td>
                <td><span className="badge">{REPORT_STATUS_LABELS[row.status] || row.status}</span></td>
                <td>{row.compiler?.name || '—'}</td>
                <td><Link to={`/do-letters/${row.id}`}>Open</Link></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

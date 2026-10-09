import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import { canCompileAdminReports } from '../utils/adminReports';
import { REPORT_STATUS_LABELS } from '../utils/adminReports';

export default function DsrReports() {
  const { user } = useAuth();
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ date_from: '', date_to: '' });

  const load = () => {
    setLoading(true);
    const params = {};
    if (filters.date_from) params.date_from = filters.date_from;
    if (filters.date_to) params.date_to = filters.date_to;
    api.get('/dsr-reports', { params }).then(r => {
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
          <h1 className="page-title">Daily Situation Report (DSR)</h1>
          <p className="page-subtitle">ADA compile → Circle Incharge review → Islamabad HQ</p>
          <div className="title-underline"></div>
        </div>
        {canCompileAdminReports(user) && (
          <div className="page-actions">
            <Link to="/dsr-reports/create" className="btn btn-primary">+ New DSR</Link>
          </div>
        )}
      </div>

      <div className="card" style={{ marginBottom: 16 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12, alignItems: 'end' }}>
          <div>
            <label>From Date</label>
            <input type="date" value={filters.date_from} onChange={e => setFilters(f => ({ ...f, date_from: e.target.value }))} />
          </div>
          <div>
            <label>To Date</label>
            <input type="date" value={filters.date_to} onChange={e => setFilters(f => ({ ...f, date_to: e.target.value }))} />
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button type="button" className="btn btn-primary btn-sm" onClick={load}>Filter</button>
            <button type="button" className="btn btn-sm" onClick={() => {
              setFilters({ date_from: '', date_to: '' });
              setLoading(true);
              api.get('/dsr-reports').then(r => {
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
              <th>Date</th>
              <th>Unit</th>
              <th>Circle</th>
              <th>Status</th>
              <th>Compiled By</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {list.length === 0 && (
              <tr><td colSpan={6} style={{ textAlign: 'center', padding: 24, color: '#888' }}>No DSR reports yet</td></tr>
            )}
            {list.map(row => (
              <tr key={row.id}>
                <td>{row.report_date}</td>
                <td>{row.unit_name}</td>
                <td>{row.circle?.name || '—'}</td>
                <td><span className="badge">{REPORT_STATUS_LABELS[row.status] || row.status}</span></td>
                <td>{row.compiler?.name || '—'}</td>
                <td><Link to={`/dsr-reports/${row.id}`}>Open</Link></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

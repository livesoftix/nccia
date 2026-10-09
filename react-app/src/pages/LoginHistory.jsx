import React, { useEffect, useState } from 'react';
import api from '../api';
import { useNavigate } from 'react-router-dom';

export default function LoginHistory() {
  const navigate = useNavigate();
  const [history, setHistory] = useState([]);
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [filters, setFilters] = useState({ user_id: '', ip: '', spoofed: '', from: '', to: '' });
  const [sortBy, setSortBy] = useState({ key: 'logged_in_at', direction: 'desc' });

  useEffect(() => {
    fetchHistory();
    fetchStats();
  }, [page, filters]);

  const fetchHistory = async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({ page, per_page: 25 });
      Object.entries(filters).forEach(([k, v]) => v && params.append(k, v));
      const res = await api.get('/login-history', { params });
      setHistory(res.data.data || res.data);
      setTotalPages(res.data.last_page || 1);
    } catch (e) {
      console.error(e);
    } finally {
      setLoading(false);
    }
  };

  const fetchStats = async () => {
    try {
      const res = await api.get('/login-history/stats');
      setStats(res.data);
    } catch (e) {
      console.error(e);
    }
  };

  const handleSort = (key) => {
    if (sortBy.key === key) {
      setSortBy({ key, direction: sortBy.direction === 'asc' ? 'desc' : 'asc' });
    } else {
      setSortBy({ key, direction: 'asc' });
    }
  };

  const formatIp = (ip, realIp, isSpoofed) => {
    if (isSpoofed) {
      return (
        <span className="d-flex align-items-center gap-1">
          <span className="text-danger fw-bold">{ip}</span>
          {realIp && <span className="badge bg-warning text-dark ms-1" title="Detected real IP">{realIp}</span>}
          <span className="badge bg-danger">Spoofed</span>
        </span>
      );
    }
    return <span style={{ fontFamily: 'monospace', fontWeight: 600 }}>{ip}</span>;
  };

  return (
    <div className="cf-page">
      <div className="cf-header">
        <h2 className="mb-0">Login & IP Security History</h2>
        <p className="text-muted mb-0">Track all user logins, client IP addresses, real proxy IPs, and spoofing attempts</p>
      </div>

      {/* Stats Cards */}
      {stats && (
        <div className="row g-3 mb-4">
          <div className="col-md-3">
            <div className="card cf-card">
              <div className="card-body">
                <div className="text-muted small">Total Logins</div>
                <div className="h3 mb-0">{stats.total_logins}</div>
              </div>
            </div>
          </div>
          <div className="col-md-3">
            <div className="card cf-card border-danger">
              <div className="card-body">
                <div className="text-muted small">Spoofed Attempts</div>
                <div className="h3 mb-0 text-danger">{stats.spoofed_count}</div>
              </div>
            </div>
          </div>
          <div className="col-md-3">
            <div className="card cf-card">
              <div className="card-body">
                <div className="text-muted small">Unique Users</div>
                <div className="h3 mb-0">{stats.unique_users}</div>
              </div>
            </div>
          </div>
          <div className="col-md-3">
            <div className="card cf-card">
              <div className="card-body">
                <div className="text-muted small">Unique IPs</div>
                <div className="h3 mb-0">{stats.unique_ips}</div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Filters */}
      <div className="card cf-card mb-4">
        <div className="card-body">
          <div className="row g-3 align-items-end">
            <div className="col-md-3">
              <label className="form-label small">Search IP Address</label>
              <input
                type="text"
                className="form-control form-control-sm"
                value={filters.ip}
                onChange={(e) => setFilters(f => ({ ...f, ip: e.target.value }))}
                placeholder="e.g. 192.168.1.1 or 39.40."
              />
            </div>
            <div className="col-md-2">
              <label className="form-label small">User ID</label>
              <input
                type="number"
                className="form-control form-control-sm"
                value={filters.user_id}
                onChange={(e) => setFilters(f => ({ ...f, user_id: e.target.value }))}
                placeholder="Filter user ID"
              />
            </div>
            <div className="col-md-2">
              <label className="form-label small">Spoofed IP</label>
              <select
                className="form-select form-select-sm"
                value={filters.spoofed}
                onChange={(e) => setFilters(f => ({ ...f, spoofed: e.target.value }))}
              >
                <option value="">All</option>
                <option value="true">Yes (Spoofed)</option>
                <option value="false">No (Normal)</option>
              </select>
            </div>
            <div className="col-md-2">
              <label className="form-label small">From Date</label>
              <input
                type="date"
                className="form-control form-control-sm"
                value={filters.from}
                onChange={(e) => setFilters(f => ({ ...f, from: e.target.value }))}
              />
            </div>
            <div className="col-md-2">
              <label className="form-label small">To Date</label>
              <input
                type="date"
                className="form-control form-control-sm"
                value={filters.to}
                onChange={(e) => setFilters(f => ({ ...f, to: e.target.value }))}
              />
            </div>
            <div className="col-md-1">
              <button className="btn btn-primary btn-sm w-100" onClick={() => { setPage(1); fetchHistory(); }}>
                Filter
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* History Table */}
      <div className="card cf-card">
        <div className="card-body p-0">
          {loading ? (
            <div className="text-center py-5">
              <div className="spinner-border text-primary" role="status">
                <span className="visually-hidden">Loading...</span>
              </div>
            </div>
          ) : history.length === 0 ? (
            <div className="text-center py-5 text-muted">No login history found</div>
          ) : (
            <div className="table-responsive">
              <table className="table table-hover mb-0 cf-table">
                <thead className="cf-table-head">
                  <tr>
                    {[
                      { key: 'user.name', label: 'User' },
                      { key: 'ip_address', label: 'Client IP' },
                      { key: 'real_ip', label: 'Real IP' },
                      { key: 'is_spoofed', label: 'Spoofed' },
                      { key: 'login_method', label: 'Method' },
                      { key: 'user_agent', label: 'User Agent' },
                      { key: 'logged_in_at', label: 'Login Time' },
                      { key: 'logged_out_at', label: 'Logout Time' },
                    ].map(col => (
                      <th key={col.key} onClick={() => handleSort(col.key)} style={{ cursor: 'pointer' }}>
                        {col.label}
                        {sortBy.key === col.key && (
                          <span className="ms-1">{sortBy.direction === 'asc' ? '▲' : '▼'}</span>
                        )}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody>
                  {history.map(item => (
                    <tr key={item.id} className={item.is_spoofed ? 'table-danger' : ''}>
                      <td>
                        <div className="fw-medium">{item.user?.name}</div>
                        <div className="text-muted small">{item.user?.email}</div>
                        <div className="text-muted small">
                          <span className="badge bg-light text-dark">{item.user?.role}</span>
                          {item.user?.designation && <span className="badge bg-light text-dark ms-1">{item.user.designation}</span>}
                        </div>
                      </td>
                      <td>{formatIp(item.ip_address, item.real_ip, item.is_spoofed)}</td>
                      <td>{item.real_ip || <span className="text-muted">—</span>}</td>
                      <td>
                        {item.is_spoofed ? (
                          <span className="badge bg-danger">Yes</span>
                        ) : (
                          <span className="badge bg-success">No</span>
                        )}
                      </td>
                      <td>
                        <span className="badge bg-info text-dark">{item.login_method}</span>
                      </td>
                      <td className="text-truncate" style={{ maxWidth: 200 }} title={item.user_agent}>
                        {item.user_agent || <span className="text-muted">—</span>}
                      </td>
                      <td>{item.logged_in_at ? new Date(item.logged_in_at).toLocaleString() : '—'}</td>
                      <td>{item.logged_out_at ? new Date(item.logged_out_at).toLocaleString() : <span className="text-muted">Active</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Pagination */}
          {totalPages > 1 && (
            <nav className="px-3 py-2 border-top">
              <ul className="pagination pagination-sm mb-0 justify-content-center">
                <li className={`page-item ${page === 1 ? 'disabled' : ''}`}>
                  <button className="page-link" onClick={() => setPage(p => p - 1)} disabled={page === 1}>Previous</button>
                </li>
                {Array.from({ length: totalPages }, (_, i) => i + 1)
                  .filter(p => p === 1 || p === totalPages || (p >= page - 2 && p <= page + 2))
                  .map((p, i, arr) => (
                    <li key={p} className={`page-item ${p === page ? 'active' : ''} ${i > 0 && arr[i - 1] !== p - 1 ? 'disabled d-none' : ''}`}>
                      {i > 0 && arr[i - 1] !== p - 1 ? (
                        <span className="page-link">...</span>
                      ) : (
                        <button className="page-link" onClick={() => setPage(p)}>{p}</button>
                      )}
                    </li>
                  ))}
                <li className={`page-item ${page === totalPages ? 'disabled' : ''}`}>
                  <button className="page-link" onClick={() => setPage(p => p + 1)} disabled={page === totalPages}>Next</button>
                </li>
              </ul>
            </nav>
          )}
        </div>
      </div>
    </div>
  );
}
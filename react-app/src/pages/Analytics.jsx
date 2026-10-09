import { useState, useEffect } from 'react';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ProgressBar from '../components/ProgressBar';

export default function Analytics() {
  const [year, setYear] = useState(new Date().getFullYear());
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api.get('/analytics', { params: { year } }).then(r => setData(r.data)).catch(() => {}).finally(() => setLoading(false));
  }, [year]);

  if (loading) return <div className="page-content"><LoadingSkeleton type="stats" rows={4} /><LoadingSkeleton type="chart" /><LoadingSkeleton type="chart" /></div>;
  if (!data) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Failed to load analytics</div>;

  const {
    performanceMetrics = {},
    monthlyTrends = [],
    categoryBreakdown = [],
    circlePerformance = [],
    officerWorkload = [],
    topOfficers = [],
    caseOutcomes = []
  } = data;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label">Analytics</div>
          <h1 className="page-title">System Analytics</h1>
          <p className="page-subtitle">Key performance indicators & trend analysis</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <div className="filters-bar" style={{ margin: 0, padding: '10px 14px' }}>
            <span className="filter-label">Year</span>
            <select className="filter-select" value={year} onChange={e => setYear(Number(e.target.value))} style={{ width: 140 }}>
              {[...Array(5)].map((_, i) => new Date().getFullYear() - i).map(y => <option key={y} value={y}>{y}</option>)}
            </select>
          </div>
        </div>
      </div>

      <div className="stats-grid">
        <div className="stat-card blue"><div className="stat-value">{performanceMetrics.totalComplaints || 0}</div><div className="stat-label">Total Complaints</div></div>
        <div className="stat-card green"><div className="stat-value">{performanceMetrics.resolvedComplaints || 0}</div><div className="stat-label">Resolved</div></div>
        <div className="stat-card orange"><div className="stat-value">{performanceMetrics.pendingComplaints || 0}</div><div className="stat-label">Pending</div></div>
        <div className="stat-card teal"><div className="stat-value">{performanceMetrics.avgResolutionDays || 0} days</div><div className="stat-label">Avg Resolution Time</div></div>
        <div className="stat-card purple"><div className="stat-value">{performanceMetrics.convictionRate || 0}%</div><div className="stat-label">Conviction Rate</div></div>
        <div className="stat-card gold"><div className="stat-value">{performanceMetrics.officerEfficiency || 0}%</div><div className="stat-label">Officer Efficiency</div></div>
      </div>

      <div className="dashboard-grid">
        <div className="card">
          <div className="card-header"><div className="card-title">Monthly Trends</div></div>
          <div className="card-body" style={{ height: 300 }}>
            <svg viewBox="0 0 600 280" preserveAspectRatio="none" style={{ width: '100%', height: '100%' }}>
              {monthlyTrends && monthlyTrends.length > 0 && (
                <>
                  <defs>
                    <linearGradient id="receivedGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#2563eb" stopOpacity="0.3" />
                      <stop offset="100%" stopColor="#2563eb" stopOpacity="0" />
                    </linearGradient>
                    <linearGradient id="resolvedGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#38a169" stopOpacity="0.3" />
                      <stop offset="100%" stopColor="#38a169" stopOpacity="0" />
                    </linearGradient>
                  </defs>
                  <polyline fill="none" stroke="#2563eb" strokeWidth="2" points={monthlyTrends.map((m, i) => `${i * 50 + 40},${260 - (m.received / (Math.max(...monthlyTrends.map(x => x.received)) || 1)) * 200}`).join(' ')} />
                  <polyline fill="none" stroke="#38a169" strokeWidth="2" points={monthlyTrends.map((m, i) => `${i * 50 + 40},${260 - (m.resolved / (Math.max(...monthlyTrends.map(x => x.resolved)) || 1)) * 200}`).join(' ')} />
                  {monthlyTrends.map((m, i) => (
                    <g key={i}>
                      <circle cx={i * 50 + 40} cy={260 - (m.received / (Math.max(...monthlyTrends.map(x => x.received)) || 1)) * 200} r={4} fill="#2563eb" />
                      <circle cx={i * 50 + 40} cy={260 - (m.resolved / (Math.max(...monthlyTrends.map(x => x.resolved)) || 1)) * 200} r={4} fill="#38a169" />
                      <text x={i * 50 + 40} y={275} textAnchor="middle" fontSize="10" fill="#6c757d">{m.month}</text>
                    </g>
                  ))}
                </>
              )}
            </svg>
            <div style={{ display: 'flex', justifyContent: 'center', gap: 16, marginTop: 10, fontSize: 12 }}>
              <span style={{ display: 'flex', alignItems: 'center', gap: 4, color: '#2563eb' }}><span style={{ width: 12, height: 12, borderRadius: 2, background: '#2563eb' }}></span>Received</span>
              <span style={{ display: 'flex', alignItems: 'center', gap: 4, color: '#38a169' }}><span style={{ width: 12, height: 12, borderRadius: 2, background: '#38a169' }}></span>Resolved</span>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title">Category Breakdown</div></div>
          <div className="card-body" style={{ height: 300 }}>
            <svg viewBox="0 0 400 280" preserveAspectRatio="none" style={{ width: '100%', height: '100%' }}>
              {categoryBreakdown && categoryBreakdown.length > 0 && (
                <>
                  {categoryBreakdown.map((c, i) => (
                    <g key={c.name}>
                      <rect x={20} y={30 + i * 35} width={Math.max(1, (c.percentage / 100) * 320)} height={24} fill={c.color || '#2563eb'} rx={4} />
                      <text x={15} y={48 + i * 35} fontSize="11" fill="#2b2b2b" textAnchor="end">{c.name}</text>
                      <text x={40 + (c.percentage / 100) * 320} y={48 + i * 35} fontSize="11" fill="#2b2b2b" fontWeight="600">{c.count} ({c.percentage}%)</text>
                    </g>
                  ))}
                </>
              )}
            </svg>
          </div>
        </div>
      </div>

      <div className="dashboard-grid" style={{ marginTop: 24 }}>
        <div className="card">
          <div className="card-header"><div className="card-title"><span>Top Performing Officers</span></div></div>
          <div className="card-body" style={{ padding: 0 }}>
            <div className="table-responsive">
              <table className="data-table">
                <thead><tr><th>Rank</th><th>Officer</th><th>Role</th><th>Cases Handled</th><th>Resolution Rate</th></tr></thead>
                <tbody>
                  {topOfficers && topOfficers.map((o, i) => (
                    <tr key={o.id}><td><span className="table-id">#{i + 1}</span></td><td>{o.name}</td><td>{o.role}</td><td>{o.casesHandled}</td><td>{o.resolutionRate}%</td></tr>
                  ))}
                  {!topOfficers?.length && <tr><td colSpan={5} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No data</td></tr>}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title"><span>Case Outcomes</span></div></div>
          <div className="card-body" style={{ height: 300 }}>
            <svg viewBox="0 0 400 280" preserveAspectRatio="none" style={{ width: '100%', height: '100%' }}>
              {caseOutcomes && caseOutcomes.length > 0 && (
                caseOutcomes.map((c, i) => (
                  <g key={c.outcome}>
                    <rect x={20} y={30 + i * 35} width={Math.max(1, (c.count / Math.max(...caseOutcomes.map(x => x.count))) * 320)} height={24} fill="#e53e3e" rx={4} />
                    <text x={15} y={48 + i * 35} fontSize="11" fill="#2b2b2b" textAnchor="end">{c.outcome}</text>
                    <text x={40 + (c.count / Math.max(...caseOutcomes.map(x => x.count))) * 320} y={48 + i * 35} fontSize="11" fill="#2b2b2b" fontWeight="600">{c.count} ({c.percentage}%)</text>
                  </g>
                ))
              )}
            </svg>
          </div>
        </div>
      </div>

      <div className="dashboard-grid" style={{ marginTop: 24 }}>
        <div className="card">
          <div className="card-header"><div className="card-title"><span>Officer Workload & Completion</span></div></div>
          <div className="card-body" style={{ padding: 0 }}>
            <div className="table-responsive">
              <table className="data-table">
                <thead><tr><th>Officer</th><th>Role</th><th>Assigned</th><th>Completed</th><th>Pending</th><th style={{ width: 220 }}>Progress</th></tr></thead>
                <tbody>
                  {officerWorkload.map(o => (
                    <tr key={o.id}>
                      <td style={{ fontWeight: 600 }}>{o.name}</td>
                      <td style={{ fontSize: 12.5, color: '#6c757d' }}>{o.role}</td>
                      <td>{o.assigned}</td>
                      <td style={{ color: '#38a169' }}>{o.completed}</td>
                      <td style={{ color: o.pending > 0 ? '#e5a100' : '#6c757d' }}>{o.pending}</td>
                      <td><ProgressBar value={o.completionRate} showLabel /></td>
                    </tr>
                  ))}
                  {!officerWorkload.length && <tr><td colSpan={6} style={{ textAlign: 'center', padding: '24px', color: '#6c757d' }}>No officers with assignments</td></tr>}
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div className="card">
          <div className="card-header"><div className="card-title"><span>Circle Performance</span></div></div>
          <div className="card-body" style={{ padding: 16 }}>
            <div className="progress-list">
              {circlePerformance.map(c => (
                <div className="progress-item" key={c.id}>
                  <div className="progress-header">
                    <span className="progress-name">{c.circle}</span>
                    <span className="progress-count">{c.resolved || 0} / {c.total || 0} resolved ({c.percentage}%)</span>
                  </div>
                  <ProgressBar value={c.percentage} />
                </div>
              ))}
              {!circlePerformance.length && <div style={{ textAlign: 'center', padding: '24px', color: '#6c757d' }}>No circles</div>}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
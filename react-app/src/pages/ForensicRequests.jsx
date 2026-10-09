import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ExternalForensicRequestModal from '../components/ExternalForensicRequestModal';

const STATUS_LABEL = {
  submitted:       'Pending AD Assignment',
  assigned:        'Assigned to AD Forensic',
  in_progress:     'Lab Examination Active',
  submitted_to_ad: 'Submitted for Approval',
  report_ready:    'Report Ready (EO Notified)',
  handed_over:     'Handed Over to EO',
};

const STATUS_COLOR = {
  submitted:       { bg: '#fef3c7', text: '#92400e' },
  assigned:        { bg: '#dbeafe', text: '#1e40af' },
  in_progress:     { bg: '#ede9fe', text: '#6b21a8' },
  submitted_to_ad: { bg: '#fed7aa', text: '#9a3412' },
  report_ready:    { bg: '#d1fae5', text: '#065f46' },
  handed_over:     { bg: '#f1f5f9', text: '#475569' },
};

export default function ForensicRequests() {
  const { user } = useAuth();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [status, setStatus] = useState('');
  const [priority, setPriority] = useState('');
  const [search, setSearch] = useState('');
  const [showDirectModal, setShowDirectModal] = useState(false);

  const isDesk = user?.role === 'desk_forensic' || (user?.roles || []).some(r => r.name === 'desk_forensic');
  const isFo   = user?.role === 'forensic_team' || (user?.roles || []).some(r => r.name === 'forensic_team');
  const isAd   = user?.role === 'ad_forensic' || user?.role === 'admin_forensic' || user?.role === 'admin'
    || (user?.roles || []).some(r => ['ad_forensic', 'admin_forensic', 'admin', 'dd_forensic'].includes(r.name));

  const load = () => {
    setLoading(true);
    setError(null);
    const params = {};
    if (status) params.status = status;
    if (priority) params.priority = priority;
    if (search.trim()) params.search = search.trim();

    api.get('/forensic/requests', { params })
      .then(r => setRows(r.data.data?.data || r.data.data || []))
      .catch(e => setError(e.response?.data?.message || 'Failed to load seizure requests'))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    load();
  }, [status, priority]);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    load();
  };

  const title = isDesk
    ? 'Forensic Desk — Evidence Handover Register'
    : isFo && !isAd
      ? 'My Forensic Examination Queue'
      : 'Seizure Evidence & Forensic Register';

  return (
    <div className="page-content">
      <div className="page-header" style={{ marginBottom: 16 }}>
        <div className="page-title-group">
          <div className="page-label">NCCIA Forensic Lab</div>
          <h1 className="page-title">{title}</h1>
          <p className="page-subtitle">Master Seizure Evidence &amp; Lab Examination Log</p>
          <div className="title-underline"></div>
        </div>

        <div className="page-actions" style={{ display: 'flex', gap: 8 }}>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => setShowDirectModal(true)}
            style={{ background: '#0284c7', borderColor: '#0284c7' }}
          >
            ➕ Direct Forensic (External Seizure)
          </button>
          <Link to="/forensic" className="btn btn-outline">
            Dashboard
          </Link>
          <button type="button" className="btn btn-outline" onClick={load}>
            Refresh
          </button>
        </div>
      </div>

      <form onSubmit={handleSearchSubmit} style={{ display: 'flex', gap: 10, marginBottom: 16, flexWrap: 'wrap', alignItems: 'center' }}>
        <input
          type="text"
          className="cf-input"
          style={{ minWidth: 260, maxWidth: 380 }}
          placeholder="🔍 Search by IMEI, Serial, Officer, Enquiry #..."
          value={search}
          onChange={e => setSearch(e.target.value)}
        />

        <select className="cf-input" style={{ width: 'auto' }} value={status} onChange={e => setStatus(e.target.value)}>
          <option value="">All Statuses</option>
          {Object.entries(STATUS_LABEL).map(([k, v]) => (
            <option key={k} value={k}>{v}</option>
          ))}
        </select>

        <select className="cf-input" style={{ width: 'auto' }} value={priority} onChange={e => setPriority(e.target.value)}>
          <option value="">All Priorities</option>
          <option value="urgent">⚡ Urgent Priority</option>
          <option value="high">🔥 High Priority</option>
          <option value="normal">Normal</option>
        </select>

        <button type="submit" className="btn btn-outline btn-sm">Filter</button>
        {(status || priority || search) && (
          <button
            type="button"
            className="btn btn-outline btn-sm"
            onClick={() => { setStatus(''); setPriority(''); setSearch(''); setTimeout(load, 50); }}
          >
            Clear
          </button>
        )}
      </form>

      {loading && <LoadingSkeleton type="table" rows={8} />}
      {error && <div style={{ color: '#e53e3e', marginBottom: 12 }}>⚠️ {error}</div>}

      {!loading && !error && (
        <div className="card">
          <div className="card-body" style={{ padding: 0 }}>
            <div style={{ overflowX: 'auto' }}>
              <table className="data-table" style={{ width: '100%', minWidth: 900 }}>
                <thead>
                  <tr>
                    <th style={{ width: 140 }}>Request No</th>
                    <th style={{ width: 180 }}>Kis nay Seize kiya (Officer)</th>
                    <th style={{ width: 180 }}>Kahan say Bheja (Origin)</th>
                    <th>Seized Items</th>
                    <th style={{ width: 150 }}>Status / Code</th>
                    <th style={{ width: 120 }}>Date Seized</th>
                    <th style={{ width: 80, textAlign: 'right' }}></th>
                  </tr>
                </thead>
                <tbody>
                  {rows.length === 0 ? (
                    <tr>
                      <td colSpan={7} style={{ textAlign: 'center', padding: 36, color: '#94a3b8' }}>
                        🔬 No seizure requests found
                      </td>
                    </tr>
                  ) : (
                    rows.map(r => {
                      const sc = STATUS_COLOR[r.status] || { bg: '#f1f5f9', text: '#64748b' };
                      const items = r.items || [];
                      const isExt = Boolean(r.is_external || (!r.enquiry_id && !r.case_id && (r.external_organization || r.external_ref)));
                      const circle = isExt
                        ? (r.external_organization || 'External Dept')
                        : (r.submitter?.circle?.name || r.enquiry?.complaint?.circle?.name || 'NCCIA');
                      const caseRef = isExt
                        ? (r.external_ref ? `Ext: ${r.external_ref}` : (r.external_category || 'External Seizure'))
                        : (r.enquiry?.enquiry_number ? `Enquiry ${r.enquiry.enquiry_number}` : (r.caseFile?.fir_no ? `FIR ${r.caseFile.fir_no}` : 'Case'));

                      return (
                        <tr key={r.id}>
                          <td>
                            <strong style={{ color: '#015C94', fontSize: 13 }}>{r.request_no}</strong>
                            {isExt && (
                              <span style={{ display: 'inline-block', fontSize: 9.5, fontWeight: 800, background: '#fef3c7', color: '#b45309', padding: '1px 5px', borderRadius: 4, marginLeft: 4 }}>
                                EXTERNAL
                              </span>
                            )}
                            {r.priority === 'urgent' && (
                              <div style={{ fontSize: 10, color: '#b91c1c', fontWeight: 800 }}>⚡ URGENT</div>
                            )}
                            {r.priority === 'high' && (
                              <div style={{ fontSize: 10, color: '#c2410c', fontWeight: 700 }}>🔥 HIGH</div>
                            )}
                          </td>

                          <td>
                            <div style={{ fontWeight: 700, color: '#0f172a' }}>
                              {isExt ? (r.external_person_name || r.submitter?.name || 'External') : (r.submitter?.name || 'EO')}
                            </div>
                            <div style={{ fontSize: 11, color: '#64748b' }}>
                              {isExt ? (r.external_person_contact || circle) : `${r.submitter?.designation || 'Officer'} · ${circle}`}
                            </div>
                          </td>

                          <td>
                            <div style={{ fontWeight: 700, color: isExt ? '#059669' : '#015C94', fontSize: 12.5 }}>
                              {caseRef}
                            </div>
                            <div style={{ fontSize: 11, color: '#64748b' }}>📍 {circle}</div>
                          </td>

                          <td>
                            <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
                              <span style={{ fontSize: 11, fontWeight: 700, background: '#e0f2fe', color: '#0369a1', padding: '1px 6px', borderRadius: 4 }}>
                                {items.length} {items.length === 1 ? 'Device' : 'Devices'}
                              </span>
                              {items.slice(0, 2).map((it, idx) => (
                                <span key={idx} style={{ fontSize: 11, background: '#f8fafc', border: '1px solid #e2e8f0', padding: '1px 5px', borderRadius: 4, color: '#334155' }}>
                                  {it.item_type === 'phone' ? '📱' : it.item_type === 'laptop' ? '💻' : '📦'} {it.make_model || it.item_type}
                                </span>
                              ))}
                            </div>
                          </td>

                          <td>
                            <span style={{
                              fontSize: 11, fontWeight: 700, background: sc.bg, color: sc.text,
                              padding: '2px 8px', borderRadius: 12, display: 'inline-block', marginBottom: 2,
                            }}>
                              {STATUS_LABEL[r.status] || r.status}
                            </span>
                            {r.report_code && (
                              <div style={{ fontSize: 11, fontWeight: 800, color: '#059669', letterSpacing: 0.5 }}>
                                {r.report_code}
                              </div>
                            )}
                          </td>

                          <td style={{ fontSize: 11.5, color: '#64748b' }}>
                            {r.created_at ? new Date(r.created_at).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                          </td>

                          <td style={{ textAlign: 'right' }}>
                            <Link to={`/forensic/requests/${r.id}`} className="btn btn-outline btn-sm" style={{ padding: '3px 8px', fontSize: 11 }}>
                              View
                            </Link>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      <ExternalForensicRequestModal
        isOpen={showDirectModal}
        onClose={() => setShowDirectModal(false)}
        onSuccess={() => {
          load();
        }}
      />
    </div>
  );
}

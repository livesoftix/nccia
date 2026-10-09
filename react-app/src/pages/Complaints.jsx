import { useState, useEffect } from 'react';
import { Link, useParams, useNavigate } from 'react-router-dom';
import api from '../api';
import ConfirmModal from '../components/ConfirmModal';
import LoadingSkeleton from '../components/LoadingSkeleton';
import WorkflowProgress from '../components/WorkflowProgress';
import { preparePrintWindow, writePrintWindow, closePrintWindow } from '../utils/print';
import { useAuth } from '../contexts/AuthContext';
import { canAssignVerification, canCreateComplaint, hasRole } from '../utils/permissions';
import { useAutoRefresh } from '../utils/useAutoRefresh';

const CLOSURE_REASON_LABELS = {
  non_pursuance: 'Non-Pursuance',
  irrelevant: 'Irrelevant',
  invalid: 'Invalid',
  lack_of_evidence: 'Lack of Evidence',
};

const STATUS_COLORS = {
  complete: 'badge-finalized',
  incomplete: 'badge-pending',
  closed: 'badge-urgent',
  merged: 'badge-merge',
  transferred: 'badge-warning',
  enquiry_registered: 'badge-active',
};

const STATUS_LABELS = {
  complete: 'Complete',
  incomplete: 'Incomplete',
  closed: 'Closed',
  merged: 'Merged',
  transferred: 'Transferred',
  enquiry_registered: 'Enquiry Registered',
};

const ENQ_STATUS_COLORS = {
  registered: 'badge-pending',
  assigned: 'badge-active',
  in_progress: 'badge-info',
  cfr_submitted: 'badge-pending',
  approved: 'badge-finalized',
  closed: 'badge-urgent',
};

export default function Complaints() {
  const { user } = useAuth();
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [offenceMap, setOffenceMap] = useState({});
  const [assignTarget, setAssignTarget] = useState(null);
  const [assignForm, setAssignForm] = useState({ verification_officer_id: '', priority_type: 'normal' });
  const [officers, setOfficers] = useState([]);

  // Assign VO for operator is on Complete Registration form only
  const canAssign = canAssignVerification(user) && !hasRole(user, 'operator');
  const canCreate = canCreateComplaint(user);
  const isOperatorOnly = hasRole(user, 'operator') && !hasRole(user, 'admin') && !hasRole(user, 'circle_incharge');
  const canDelete = hasRole(user, 'admin');
  const canEdit = canCreate || hasRole(user, 'circle_incharge') || hasRole(user, 'admin');

  const fetchList = (p = page) => {
    setLoading(true);
    api.get(`/complaints?page=${p}&per_page=15`).then(r => {
      setList(r.data.data || r.data || []);
      setMeta({ ...(r.data.meta || { current_page: p }), links: r.data.links || {} });
    }).finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchList(page);
  }, [page]);

  useAutoRefresh(() => fetchList(page), [page], 30000);

  useEffect(() => {
    api.get('/offence-types').then(r => {
      const d = r.data.data || r.data;
      const arr = Array.isArray(d) ? d : (d.data || []);
      setOffenceMap(Object.fromEntries(arr.map(o => [o.value, o.name])));
    }).catch(() => {});
  }, []);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    await api.delete(`/complaints/${deleteTarget.id}`);
    setList(list.filter(c => c.id !== deleteTarget.id));
    setDeleteTarget(null);
  };

  const printSlip = async (c) => {
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/complaints/${c.id}/slip`);
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || 'Could not generate slip.');
    }
  };

  const printReport = async (c) => {
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/complaints/${c.id}/report`);
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || 'Could not generate report.');
    }
  };

  // ── Direct Assign ──
  const openDirectAssign = (complaint) => {
    setAssignTarget(complaint);
    setAssignForm({ verification_officer_id: '', priority_type: 'normal' });
    api.get('/lookup/verification-officers').then(r => {
      const all = r.data.data || r.data;
      setOfficers(Array.isArray(all) ? all : []);
    }).catch(() => {});
  };

  const handleDirectAssign = async () => {
    if (!assignTarget) return;
    try {
      await api.post(`/complaints/${assignTarget.id}/direct-assign`, assignForm);
      setAssignTarget(null);
      setAssignForm({ verification_officer_id: '', priority_type: 'normal' });
      fetchList(page);
      setOfficers([]);
    } catch (e) {
      alert(e.response?.data?.message || 'Failed to assign verification');
    }
  };

  if (loading) return <div className="complaint-page"><LoadingSkeleton type="table" columns={8} rows={10} /></div>;

  return (
    <div className="complaint-page">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">{hasRole(user, 'operator') && !hasRole(user, 'admin') ? 'My Complaints' : 'Complaints'}</h1>
          <p className="page-subtitle">{hasRole(user, 'operator') && !hasRole(user, 'admin') ? 'Aap ki registered complaints aur unki progress' : 'All registered complaints'}</p>
          <div className="title-underline"></div>
        </div>
        {canCreate && (
          <div className="page-actions" style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <Link to="/complaints/import-pdf" className="btn btn-outline" style={{ height: 40, padding: '0 16px', fontSize: 13.5, fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: 8, borderRadius: 8 }}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span>Import PDF</span>
            </Link>
            <Link to="/complaints/create" className="btn btn-primary" style={{ height: 40, padding: '0 18px', fontSize: 13.5, fontWeight: 700, display: 'inline-flex', alignItems: 'center', gap: 8, borderRadius: 8 }}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
              <span>{hasRole(user, 'operator') && !hasRole(user, 'admin') ? 'Complete Registration' : 'New Complaint'}</span>
            </Link>
          </div>
        )}
      </div>

      <div className="card" style={{ border: '1px solid #e2e8f0', borderRadius: 12, overflow: 'hidden' }}>
        <div className="card-body" style={{ padding: 0 }}>
          <div className="table-responsive" style={{ width: '100%', margin: 0 }}>
            <table className="data-table" style={{ width: '100%', tableLayout: 'auto' }}>
              <thead>
                <tr>
                  <th style={{ width: '12%', whiteSpace: 'nowrap' }}>Tracking / Date</th>
                  <th style={{ width: '20%' }}>Complainant & CNIC</th>
                  <th style={{ width: '16%' }}>Crime Category</th>
                  <th style={{ width: '11%', whiteSpace: 'nowrap' }}>Status</th>
                  {!isOperatorOnly && <th style={{ width: '15%' }}>Progress</th>}
                  {!isOperatorOnly && <th style={{ width: '10%' }}>Enquiry</th>}
                  <th style={{ width: '16%', textAlign: 'right', whiteSpace: 'nowrap' }}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {list.map((c, i) => (
                  <tr key={c.id}>
                    <td>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <span className="table-id" style={{ fontWeight: 700, color: '#0284c7', fontSize: 13 }}>#{c.tracking_no || c.id}</span>
                        <small style={{ fontSize: 11, color: '#64748b' }}>{new Date(c.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}</small>
                      </div>
                    </td>
                    <td>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <span style={{ fontSize: '13.5px', fontWeight: 700, color: '#0f172a' }}>{c.complainant_name}</span>
                        <span style={{ fontSize: '12px', color: '#64748b', fontFamily: 'monospace' }}>{c.cnic || '—'}</span>
                      </div>
                    </td>
                    <td>
                      <span style={{ fontSize: '12.5px', color: '#334155', display: 'block', lineHeight: 1.3 }}>{offenceMap[c.offence_type] || c.offence_type || '—'}</span>
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      <span className={`badge ${STATUS_COLORS[c.final_status || c.status] || 'badge-pending'}`} style={{ fontSize: 11.5, padding: '4px 10px', borderRadius: 20 }}>
                        {STATUS_LABELS[c.final_status || c.status] || c.status}
                      </span>
                    </td>
                    {!isOperatorOnly && (
                      <td>
                        <WorkflowProgress workflow={c.workflow} percent={c.progress_percent} stage={c.progress_stage} compact />
                      </td>
                    )}
                    {!isOperatorOnly && (
                      <td>
                        {c.enquiry ? (
                          <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            <Link to="/enquiries" style={{ fontSize: '12.5px', fontWeight: 700, color: '#0284c7' }}>#{c.enquiry.enquiry_number || c.enquiry.id}</Link>
                            <span className={`badge ${ENQ_STATUS_COLORS[c.enquiry.status] || 'badge-pending'}`} style={{ fontSize: 10 }}>{c.enquiry.status?.replace('_', ' ')}</span>
                          </div>
                        ) : c.final_status === 'closed' && CLOSURE_REASON_LABELS[c.closure_reason] ? (
                          <span style={{ fontSize: 11.5, color: '#e53e3e', fontWeight: 600 }}>{CLOSURE_REASON_LABELS[c.closure_reason]}</span>
                        ) : c.final_status === 'merged' && c.merged_with_id ? (
                          <span style={{ fontSize: 11.5, color: '#64748b' }}>Merged #{c.merged_with_id}</span>
                        ) : c.final_status === 'transferred' ? (
                          <span style={{ fontSize: 11.5, color: '#d97706' }}>{c.transfer_to_department || 'Transferred'}</span>
                        ) : (
                          <span style={{ color: '#94a3b8' }}>—</span>
                        )}
                      </td>
                    )}
                    <td style={{ textAlign: 'right', whiteSpace: 'nowrap' }}>
                      <div style={{ display: 'inline-flex', gap: 6, alignItems: 'center', justifyContent: 'flex-end' }}>
                        {canAssign && !c.verification && (
                          <button onClick={() => openDirectAssign(c)} className="btn btn-sm" style={{ height: 34, padding: '0 10px', background: 'rgba(99, 102, 241, 0.12)', color: '#4f46e5', border: '1px solid rgba(99, 102, 241, 0.25)', borderRadius: 7, fontSize: 12, fontWeight: 700, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 4 }} title="Assign Verification Officer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                            <span>Assign</span>
                          </button>
                        )}
                        { (canEdit && !(hasRole(user, 'operator') && !hasRole(user, 'admin') && (c.status === 'complete' || c.verification))) && (
                          <Link to={`/complaints/${c.id}/edit`} className="btn btn-outline btn-sm" style={{ height: 34, width: 34, padding: 0, borderRadius: 7, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', border: '1.5px solid #cbd5e1', background: '#fff', color: '#475569' }} title="Edit Complaint">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          </Link>
                        )}
                        {c.tracking_no && canCreate && (
                          <button
                            type="button"
                            onClick={async () => {
                              try {
                                const r = await api.post(`/complaints/${c.id}/notify-complainant`);
                                const wa = r.data?.complainant_notify?.whatsapp_url;
                                if (wa) window.open(wa, '_blank');
                                else alert('Phone missing — WhatsApp message nahi ban saka.');
                              } catch (e) {
                                alert(e.response?.data?.message || 'Notify failed');
                              }
                            }}
                            className="btn btn-sm"
                            style={{ height: 34, width: 34, padding: 0, background: '#25D366', color: '#fff', border: 'none', borderRadius: 7, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', justifyContent: 'center' }}
                            title="Send WhatsApp Notification"
                          >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                          </button>
                        )}
                        <button onClick={() => printSlip(c)} className="btn btn-sm" style={{ height: 34, padding: '0 10px', background: 'rgba(16, 185, 129, 0.12)', color: '#059669', border: '1px solid rgba(16, 185, 129, 0.3)', borderRadius: 7, fontSize: 12, fontWeight: 700, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 5 }} title="Print 80mm Thermal Slip">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                          <span>Slip</span>
                        </button>
                        <button onClick={() => printReport(c)} className="btn btn-sm" style={{ height: 34, padding: '0 10px', background: 'rgba(2, 132, 199, 0.12)', color: '#0284c7', border: '1px solid rgba(2, 132, 199, 0.3)', borderRadius: 7, fontSize: 12, fontWeight: 700, cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: 5 }} title="Print Full A4 Report">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                          <span>Report</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {list.length === 0 && <tr><td colSpan={isOperatorOnly ? 5 : 7} style={{ textAlign: 'center', padding: '32px 16px', color: '#64748b' }}>No complaints found</td></tr>}
              </tbody>
            </table>
          </div>
          {(meta.links?.next || meta.links?.prev || page > 1) && (
            <div style={{display:'flex',justifyContent:'space-between',alignItems:'center',padding:'12px 16px',borderTop:'1px solid #e2e8f0',fontSize:13}}>
              <span style={{color:'#64748b'}}>Page {meta.current_page || page} {meta.total ? `(Total ${meta.total})` : ''}</span>
              <div style={{display:'flex',gap:8}}>
                <button type="button" className="btn btn-outline btn-sm" disabled={!meta.links?.prev && page <= 1} onClick={() => setPage(p => Math.max(1, p - 1))}>Prev</button>
                <button type="button" className="btn btn-outline btn-sm" disabled={!meta.links?.next} onClick={() => setPage(p => p + 1)}>Next</button>
              </div>
            </div>
          )}
        </div>
      </div>

      <ConfirmModal
        open={!!deleteTarget}
        title="Delete Complaint"
        message={`Delete complaint #${deleteTarget?.tracking_no || deleteTarget?.id}? This action cannot be undone.`}
        confirmLabel="Delete"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
      />

      {/* ── Direct Assign Modal ── */}
      {assignTarget && (
        <div className="modal-overlay" onClick={() => setAssignTarget(null)}>
          <div className="modal-container" style={{maxWidth:'480px'}} onClick={e => e.stopPropagation()}>
            <div className="modal-header">
              <h3>Direct Assign Verification Officer</h3>
              <button className="modal-close" onClick={() => setAssignTarget(null)}>&times;</button>
            </div>
            <div className="modal-body">
              <p style={{marginBottom:'12px',fontSize:'13px',color:'#555'}}>
                Assign a verification officer for complaint <strong>#{assignTarget.tracking_no || assignTarget.id}</strong>.
              </p>
              <div className="cf-group">
                <label className="cf-label">Verification Officer <span className="required">*</span></label>
                <select className="cf-input" value={assignForm.verification_officer_id} onChange={e => setAssignForm({...assignForm, verification_officer_id: e.target.value})} required>
                  <option value="">Select Officer</option>
                  {officers.map(o => (
                    <option key={o.id} value={o.id}>{o.name}{o.designation ? ' (' + o.designation + ')' : ''}</option>
                  ))}
                </select>
              </div>
              <div className="cf-group">
                <label className="cf-label">Priority <span className="required">*</span></label>
                <select className="cf-input" value={assignForm.priority_type} onChange={e => setAssignForm({...assignForm, priority_type: e.target.value})} required>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="critical">Critical</option>
                </select>
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setAssignTarget(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleDirectAssign} disabled={!assignForm.verification_officer_id}>Assign Officer</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

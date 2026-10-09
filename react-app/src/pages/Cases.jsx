import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import WorkflowProgress, { caseProgress } from '../components/WorkflowProgress';
import CaseChatModal from '../components/CaseChatModal';
import { useAutoRefresh } from '../utils/useAutoRefresh';
import { useAuth } from '../contexts/AuthContext';
import { canFillLegalAndApprove, CASE_CFR_REVIEW_STATUSES } from '../utils/permissions';

export default function Cases() {
  const { user } = useAuth();
  const canApprove = canFillLegalAndApprove(user);
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [chatTarget, setChatTarget] = useState(null);
  const [approveTarget, setApproveTarget] = useState(null);
  const [approveForm, setApproveForm] = useState({ decision: 'agree', recommendation: '', remarks: '' });
  const [approveSaving, setApproveSaving] = useState(false);

  const fetchData = useCallback((p = page) => {
    setLoading(true);
    api.get('/cases', { params: { page: p } }).then(r => {
      const d = r.data.data || r.data;
      if (Array.isArray(d)) {
        setList(d);
      } else if (d?.data) {
        setList(d.data);
        setLastPage(d.last_page || 1);
        setPage(d.current_page || 1);
      } else {
        setList([]);
      }
    }).finally(() => setLoading(false));
  }, [page]);

  useEffect(() => { fetchData(); }, [fetchData]);
  useAutoRefresh(() => fetchData(page), [page], 30000);

  const openApprove = (c) => {
    setApproveTarget(c);
    setApproveForm({ decision: 'agree', recommendation: c.recommendation || '', remarks: '' });
  };

  const handleApprove = async () => {
    if (!approveTarget) return;
    setApproveSaving(true);
    try {
      await api.post(`/cases/${approveTarget.id}/approve`, {
        decision: approveForm.decision,
        remarks: approveForm.remarks || undefined,
        recommendation: approveForm.recommendation || undefined,
      });
      fetchData();
      setApproveTarget(null);
    } catch (e) {
      alert(e.response?.data?.message || 'Approval failed');
    } finally {
      setApproveSaving(false);
    }
  };

  if (loading) return <div className="page-content"><LoadingSkeleton type="table" columns={5} rows={8} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">DAC Cases</h1>
          <p className="page-subtitle">Manage DAC cases / FIRs</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <Link to="/cases/create" className="btn btn-primary btn-sm">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> New Case
          </Link>
        </div>
      </div>

      <div className="card">
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr><th>#</th><th>FIR No</th><th>Enquiry / Ref</th><th>IO</th><th>Status</th><th>Progress</th><th>Created</th><th style={{textAlign:'center'}}>Actions</th></tr>
              </thead>
              <tbody>
                {list.map((c) => {
                  const prog = caseProgress(c.status);
                  return (
                  <tr key={c.id}>
                    <td><span className="table-id">#{c.id}</span></td>
                    <td><span style={{fontSize:'13px',fontWeight:500}}>{c.fir_no || `CASE-${c.id}`}</span></td>
                    <td>
                      <span style={{fontSize:12}}>
                        {c.direct_info?.complainant_name ? c.direct_info.complainant_name + ' · ' : ''}
                        {c.enquiry?.enquiry_number || c.direct_info?.reference_no || c.enquiry_id || '-'}
                        {c.direct_info?.high_profile_type ? ` · ${String(c.direct_info.high_profile_type).replace(/_/g, ' ')}` : ''}
                      </span>
                    </td>
                    <td><span style={{fontSize:12}}>{c.investigation_officer?.name || '-'}</span></td>
                    <td><span className={`badge ${c.status === 'closed' || c.status === 'approved' ? 'badge-finalized' : 'badge-pending'}`}>{c.status}</span></td>
                    <td style={{ minWidth: 180 }}>
                      <WorkflowProgress percent={prog.percent} stage={prog.stage} compact />
                    </td>
                    <td><span style={{fontSize:'12px',color:'#6c757d'}}>{new Date(c.created_at).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'})}</span></td>
                    <td style={{textAlign:'center'}}>
                      <div style={{display:'flex',gap:6,justifyContent:'center',alignItems:'center'}}>
                        <button
                          type="button"
                          onClick={() => setChatTarget(c)}
                          className="btn btn-sm"
                          style={{background:'rgba(1,92,148,0.12)',color:'#015C94',border:'none',borderRadius:8,width:36,height:36,display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer',fontSize:14}}
                          title="Case Discussion & Team Chat"
                        >
                          💬
                        </button>
                        <Link to={`/cases/${c.id}/edit`} className="btn btn-outline btn-sm btn-icon" title="Edit">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </Link>
                        {canApprove && CASE_CFR_REVIEW_STATUSES.includes(c.status) && (
                          <button
                            type="button"
                            onClick={() => openApprove(c)}
                            className="btn btn-sm"
                            style={{background:'rgba(214,158,46,0.15)',color:'#b7791f',border:'1px solid #d69e2e',borderRadius:8,height:36,display:'inline-flex',alignItems:'center',gap:5,padding:'0 10px',cursor:'pointer',fontSize:12,fontWeight:700}}
                            title="Review & Approve CFR"
                          >
                            Approve
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                  );
                })}
                {list.length === 0 && <tr><td colSpan={8} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No cases found</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
        {lastPage > 1 && (
          <div style={{display:'flex',justifyContent:'center',alignItems:'center',gap:8,padding:'12px 16px',borderTop:'1px solid #f0f0f0'}}>
            <button disabled={page <= 1} onClick={() => setPage(p => Math.max(1, p - 1))} style={{padding:'6px 14px',borderRadius6:6,border:'1px solid #dee2e6',background: page <= 1 ? '#f8f9fa' : '#fff',color: page <= 1 ? '#adb5bd' : '#495057',cursor: page <= 1 ? 'default' : 'pointer',fontSize:13}}>Prev</button>
            <span style={{fontSize:13,color:'#6c757d'}}>Page {page} of {lastPage}</span>
            <button disabled={page >= lastPage} onClick={() => setPage(p => Math.min(lastPage, p + 1))} style={{padding:'6px 14px',borderRadius:6,border:'1px solid #dee2e6',background: page >= lastPage ? '#f8f9fa' : '#fff',color: page >= lastPage ? '#adb5bd' : '#495057',cursor: page >= lastPage ? 'default' : 'pointer',fontSize:13}}>Next</button>
          </div>
        )}
      </div>

      {/* Case Chat Modal */}
      <CaseChatModal
        open={!!chatTarget}
        onClose={() => setChatTarget(null)}
        type="case"
        id={chatTarget?.id}
        caseNumber={chatTarget?.fir_no || (chatTarget?.id ? `CASE-${chatTarget.id}` : '')}
        title={chatTarget?.direct_info?.complainant_name || chatTarget?.enquiry?.complaint?.complainant_name || ''}
        officers={chatTarget?.investigation_officer ? [{ name: chatTarget.investigation_officer.name, role_label: 'Investigation Officer' }] : []}
      />

      {approveTarget && (
        <div className="modal-overlay" onClick={() => setApproveTarget(null)}>
          <div className="modal-container" style={{maxWidth:560}} onClick={e => e.stopPropagation()}>
            <div className="modal-header"><h3>Review Case CFR</h3><button className="modal-close" onClick={() => setApproveTarget(null)}>&times;</button></div>
            <div className="modal-body">
              <p style={{marginBottom:12,fontSize:13,color:'#666'}}>
                FIR <strong>{approveTarget.fir_no || `CASE-${approveTarget.id}`}</strong>
              </p>
              <div className="cf-group">
                <label className="cf-label">Decision <span className="required">*</span></label>
                <select className="cf-input" value={approveForm.decision} onChange={e => setApproveForm({...approveForm, decision: e.target.value})}>
                  <option value="agree">Agree — Approve</option>
                  <option value="review">Review — Send Back to IO</option>
                  <option value="disagree">Disagree — Send Back to IO</option>
                </select>
              </div>
              <div className="cf-group">
                <label className="cf-label">Recommendation</label>
                <select className="cf-input" value={approveForm.recommendation} onChange={e => setApproveForm({...approveForm, recommendation: e.target.value})}>
                  <option value="">Keep current</option>
                  <option value="challan_submission">Challan U/S 173 CrPC</option>
                  <option value="transfer">Transfer</option>
                  <option value="merge">Merge</option>
                </select>
              </div>
              <div className="cf-group">
                <label className="cf-label">Remarks</label>
                <textarea className="cf-input" rows={3} value={approveForm.remarks} onChange={e => setApproveForm({...approveForm, remarks: e.target.value})} placeholder="Approval or send-back remarks..." />
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setApproveTarget(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleApprove} disabled={approveSaving || !approveForm.decision}>
                {approveSaving ? 'Processing...' : (approveForm.decision === 'agree' ? 'Approve' : 'Send Back')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

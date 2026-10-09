import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import ConfirmModal from '../components/ConfirmModal';

const JUDGE_OPTIONS = [
  { value: 'conviction', name: 'Conviction' },
  { value: 'acquittal', name: 'Acquittal' },
  { value: 'discharge', name: 'Discharge' },
  { value: 'dismissed', name: 'Dismissed' },
  { value: 'pending', name: 'Pending/Final' },
];

export default function CourtCases() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [showDetail, setShowDetail] = useState(null);
  const [showCreate, setShowCreate] = useState(false);
  const [showEdit, setShowEdit] = useState(null);
  const [showVerdict, setShowVerdict] = useState(null);
  const [cases, setCases] = useState([]);
  const [createForm, setCreateForm] = useState({ case_id: '', court_name: '', judge_name: '', filing_date: '' });
  const [editForm, setEditForm] = useState({ court_name: '', judge_name: '', filing_date: '', status: '' });
  const [verdictForm, setVerdictForm] = useState({ verdict: '', verdict_date: '', details: '' });
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});

  const fetchData = useCallback((p = page) => {
    setLoading(true);
    api.get('/court-cases', { params: { page: p } }).then(r => {
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

  const openDetail = async (item) => {
    try {
      const r = await api.get(`/court-cases/${item.id}`);
      setShowDetail(r.data.data || r.data);
    } catch (err) {
      setShowDetail(item);
    }
  };

  const openCreate = () => {
    api.get('/cases?status=approved').then(r => setCases(r.data.data || r.data)).catch(() => {});
    setCreateForm({ case_id: '', court_name: '', judge_name: '', filing_date: '' });
    setShowCreate(true);
  };

  const handleCreate = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await api.post('/court-cases', createForm);
      setShowCreate(false);
      fetchData();
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) setErrors(res.errors);
    } finally {
      setSaving(false);
    }
  };

  const openEditModal = (item) => {
    setShowEdit(item);
    setEditForm({
      court_name: item.court_name || '',
      judge_name: item.judge_name || '',
      filing_date: item.filing_date ? item.filing_date.split('T')[0] : '',
      status: item.status || 'pending',
    });
  };

  const handleEdit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      await api.put(`/court-cases/${showEdit.id}`, editForm);
      setShowEdit(null);
      fetchData();
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) setErrors(res.errors);
    } finally {
      setSaving(false);
    }
  };

  const openVerdict = (item) => {
    setShowVerdict(item);
    setVerdictForm({ verdict: '', verdict_date: new Date().toISOString().split('T')[0], details: '' });
  };

  const handleVerdict = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      await api.post(`/court-cases/${showVerdict.id}/verdict`, verdictForm);
      setShowVerdict(null);
      fetchData();
      if (showDetail?.id === showVerdict.id) openDetail(showVerdict);
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) setErrors(res.errors);
    } finally {
      setSaving(false);
    }
  };

  if (loading && list.length === 0) return <div className="page-content"><LoadingSkeleton type="table" columns={5} rows={8} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Court Cases</h1>
          <p className="page-subtitle">Manage court filings, hearings, and verdicts</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <button onClick={openCreate} className="btn btn-primary btn-sm">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> File New Case
          </button>
        </div>
      </div>

      <div className="card">
        <div className="card-body" style={{ padding: 0 }}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr><th>#</th><th>Court Name</th><th>Case/FIR</th><th>Status</th><th>Filed</th><th style={{ textAlign: 'center' }}>Actions</th></tr>
              </thead>
              <tbody>
                {list.map(cc => (
                  <tr key={cc.id}>
                    <td><span className="table-id">#{cc.id}</span></td>
                    <td><span style={{ fontSize: 13, fontWeight: 500 }}>{cc.court_name}</span></td>
                    <td><span style={{ fontSize: 12 }}>{cc.case_file?.fir_no || cc.case_id}</span></td>
                    <td><span className={`badge ${cc.status === 'verdict_given' ? 'badge-finalized' : 'badge-pending'}`}>{cc.status}</span></td>
                    <td><span style={{ fontSize: 12, color: '#6c757d' }}>{cc.filing_date ? new Date(cc.filing_date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '-'}</span></td>
                    <td style={{ textAlign: 'center' }}>
                      <div style={{ display: 'flex', gap: 6, justifyContent: 'center' }}>
                        <button onClick={() => openDetail(cc)} className="btn btn-outline btn-sm btn-icon" title="View Details">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button onClick={() => openEditModal(cc)} className="btn btn-outline btn-sm btn-icon" title="Edit Court Case">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button onClick={() => openVerdict(cc)} className="btn btn-sm" style={{ background: 'rgba(1,92,148,0.15)', color: '#015C94', border: 'none', borderRadius: 8, width: 36, height: 36, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }} title="Add Verdict">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {list.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center', padding: 24, color: '#6c757d' }}>No court cases found</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
        {lastPage > 1 && (
          <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 8, padding: '12px 16px', borderTop: '1px solid #f0f0f0' }}>
            <button disabled={page <= 1} onClick={() => setPage(p => Math.max(1, p - 1))} style={{ padding: '6px 14px', borderRadius: 6, border: '1px solid #dee2e6', background: page <= 1 ? '#f8f9fa' : '#fff', color: page <= 1 ? '#adb5bd' : '#495057', cursor: page <= 1 ? 'default' : 'pointer', fontSize: 13 }}>Prev</button>
            <span style={{ fontSize: 13, color: '#6c757d' }}>Page {page} of {lastPage}</span>
            <button disabled={page >= lastPage} onClick={() => setPage(p => Math.min(lastPage, p + 1))} style={{ padding: '6px 14px', borderRadius: 6, border: '1px solid #dee2e6', background: page >= lastPage ? '#f8f9fa' : '#fff', color: page >= lastPage ? '#adb5bd' : '#495057', cursor: page >= lastPage ? 'default' : 'pointer', fontSize: 13 }}>Next</button>
          </div>
        )}
      </div>

      {/* Create Modal */}
      {showCreate && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.5)', padding: 20 }} onClick={() => setShowCreate(false)}>
          <div style={{ background: '#fff', borderRadius: 12, width: '100%', maxWidth: 480, boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }} onClick={e => e.stopPropagation()}>
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #f0f0f0' }}>
              <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600 }}>File New Court Case</h3>
            </div>
            <form onSubmit={handleCreate} style={{ padding: '16px 24px 24px' }}>
              <div className="cf-field">
                <label className="cf-label required">Case/FIR</label>
                <select className="cf-input" value={createForm.case_id} onChange={e => setCreateForm({ ...createForm, case_id: e.target.value })} required>
                  <option value="">— Select Case —</option>
                  {cases.map(c => <option key={c.id} value={c.id}>{c.fir_no || `CASE-${c.id}`}</option>)}
                </select>
              </div>
              <div className="cf-field">
                <label className="cf-label required">Court Name</label>
                <input type="text" className="cf-input" value={createForm.court_name} onChange={e => setCreateForm({ ...createForm, court_name: e.target.value })} placeholder="e.g. Sessions Court Lahore" required />
              </div>
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label">Judge Name</label>
                  <input type="text" className="cf-input" value={createForm.judge_name} onChange={e => setCreateForm({ ...createForm, judge_name: e.target.value })} placeholder="e.g. Mr. Justice" />
                </div>
                <div className="cf-field">
                  <label className="cf-label required">Filing Date</label>
                  <input type="date" className="cf-input" value={createForm.filing_date} onChange={e => setCreateForm({ ...createForm, filing_date: e.target.value })} required />
                </div>
              </div>
              <div style={{ display: 'flex', gap: 10, marginTop: 20 }}>
                <button type="submit" className="btn btn-primary btn-sm" disabled={saving} style={{ flex: 1 }}>{saving ? 'Saving...' : 'File Case'}</button>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => setShowCreate(false)}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Modal */}
      {showEdit && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.5)', padding: 20 }} onClick={() => setShowEdit(null)}>
          <div style={{ background: '#fff', borderRadius: 12, width: '100%', maxWidth: 480, boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }} onClick={e => e.stopPropagation()}>
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #f0f0f0' }}>
              <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600 }}>Edit Court Case #{showEdit.id}</h3>
            </div>
            <form onSubmit={handleEdit} style={{ padding: '16px 24px 24px' }}>
              <div className="cf-field">
                <label className="cf-label required">Court Name</label>
                <input type="text" className="cf-input" value={editForm.court_name} onChange={e => setEditForm({ ...editForm, court_name: e.target.value })} placeholder="e.g. Sessions Court Lahore" required />
              </div>
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label">Judge Name</label>
                  <input type="text" className="cf-input" value={editForm.judge_name} onChange={e => setEditForm({ ...editForm, judge_name: e.target.value })} placeholder="e.g. Mr. Justice" />
                </div>
                <div className="cf-field">
                  <label className="cf-label required">Filing Date</label>
                  <input type="date" className="cf-input" value={editForm.filing_date} onChange={e => setEditForm({ ...editForm, filing_date: e.target.value })} required />
                </div>
              </div>
              <div className="cf-field">
                <label className="cf-label">Status</label>
                <select className="cf-input" value={editForm.status} onChange={e => setEditForm({ ...editForm, status: e.target.value })}>
                  <option value="pending">Pending</option>
                  <option value="in_trial">In Trial</option>
                  <option value="verdict_given">Verdict Given</option>
                  <option value="dismissed">Dismissed</option>
                  <option value="closed">Closed</option>
                </select>
              </div>
              <div style={{ display: 'flex', gap: 10, marginTop: 20 }}>
                <button type="submit" className="btn btn-primary btn-sm" disabled={saving} style={{ flex: 1 }}>{saving ? 'Updating...' : 'Update Court Case'}</button>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => setShowEdit(null)}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Detail View Modal */}
      {showDetail && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.5)', padding: 20 }} onClick={() => setShowDetail(null)}>
          <div style={{ background: '#fff', borderRadius: 12, width: '100%', maxWidth: 600, maxHeight: '90vh', overflow: 'auto', boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }} onClick={e => e.stopPropagation()}>
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #f0f0f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600 }}>Court Case #{showDetail.id}</h3>
              <button onClick={() => setShowDetail(null)} style={{ background: 'none', border: 'none', cursor: 'pointer', fontSize: 18, color: '#999' }}>×</button>
            </div>
            <div style={{ padding: '16px 24px 24px' }}>
              <div className="cf-row-2">
                <div><strong>Court:</strong> {showDetail.court_name}</div>
                <div><strong>Judge:</strong> {showDetail.judge_name || '-'}</div>
              </div>
              <div className="cf-row-2">
                <div><strong>Status:</strong> <span className={`badge ${showDetail.status === 'verdict_given' ? 'badge-finalized' : 'badge-pending'}`}>{showDetail.status}</span></div>
                <div><strong>Filed:</strong> {showDetail.filing_date ? new Date(showDetail.filing_date).toLocaleDateString('en-GB') : '-'}</div>
              </div>
              <hr style={{ margin: '16px 0', border: 'none', borderTop: '1px solid #f0f0f0' }} />

              <h4 style={{ fontSize: 13, fontWeight: 600, margin: '0 0 8px' }}>Hearings ({showDetail.hearings?.length || 0})</h4>
              {showDetail.hearings?.length > 0 ? (
                <table className="data-table" style={{ fontSize: 12 }}>
                  <thead><tr><th>Date</th><th>Type</th><th>Notes</th><th>Next Hearing</th></tr></thead>
                  <tbody>
                    {showDetail.hearings.map(h => (
                      <tr key={h.id}>
                        <td>{new Date(h.hearing_date).toLocaleDateString('en-GB')}</td>
                        <td>{h.type}</td>
                        <td>{h.notes || '-'}</td>
                        <td>{h.next_hearing_date ? new Date(h.next_hearing_date).toLocaleDateString('en-GB') : '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : <p style={{ color: '#999', fontSize: 12 }}>No hearings recorded yet.</p>}

              <hr style={{ margin: '16px 0', border: 'none', borderTop: '1px solid #f0f0f0' }} />

              <h4 style={{ fontSize: 13, fontWeight: 600, margin: '0 0 8px' }}>Verdicts ({showDetail.verdicts?.length || 0})</h4>
              {showDetail.verdicts?.length > 0 ? (
                <table className="data-table" style={{ fontSize: 12 }}>
                  <thead><tr><th>Date</th><th>Verdict</th><th>Details</th></tr></thead>
                  <tbody>
                    {showDetail.verdicts.map(v => (
                      <tr key={v.id}>
                        <td>{new Date(v.verdict_date).toLocaleDateString('en-GB')}</td>
                        <td><span className={`badge ${v.verdict === 'conviction' ? 'badge-finalized' : 'badge-closed'}`}>{v.verdict}</span></td>
                        <td>{v.details || '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : <p style={{ color: '#999', fontSize: 12 }}>No verdicts recorded yet.</p>}
            </div>
          </div>
        </div>
      )}

      {/* Verdict Modal */}
      {showVerdict && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'rgba(0,0,0,0.5)', padding: 20 }} onClick={() => setShowVerdict(null)}>
          <div style={{ background: '#fff', borderRadius: 12, width: '100%', maxWidth: 480, boxShadow: '0 20px 60px rgba(0,0,0,0.2)' }} onClick={e => e.stopPropagation()}>
            <div style={{ padding: '20px 24px', borderBottom: '1px solid #f0f0f0' }}>
              <h3 style={{ margin: 0, fontSize: 15, fontWeight: 600 }}>Add Verdict</h3>
              <p style={{ margin: '4px 0 0', fontSize: 12, color: '#6c757d' }}>Court Case #{showVerdict.id} — {showVerdict.court_name}</p>
            </div>
            <form onSubmit={handleVerdict} style={{ padding: '16px 24px 24px' }}>
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label required">Verdict</label>
                  <select className="cf-input" value={verdictForm.verdict} onChange={e => setVerdictForm({ ...verdictForm, verdict: e.target.value })} required>
                    <option value="">— Select Verdict —</option>
                    {JUDGE_OPTIONS.filter(o => o.value !== 'pending').map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                  </select>
                </div>
                <div className="cf-field">
                  <label className="cf-label required">Verdict Date</label>
                  <input type="date" className="cf-input" value={verdictForm.verdict_date} onChange={e => setVerdictForm({ ...verdictForm, verdict_date: e.target.value })} required />
                </div>
              </div>
              <div className="cf-field">
                <label className="cf-label">Details</label>
                <textarea className="cf-input" rows={4} value={verdictForm.details} onChange={e => setVerdictForm({ ...verdictForm, details: e.target.value })} placeholder="Verdict details, sentence duration, fine, etc."></textarea>
              </div>
              <div style={{ display: 'flex', gap: 10, marginTop: 20 }}>
                <button type="submit" className="btn btn-primary btn-sm" disabled={saving} style={{ flex: 1 }}>{saving ? 'Saving...' : 'Submit Verdict'}</button>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => setShowVerdict(null)}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
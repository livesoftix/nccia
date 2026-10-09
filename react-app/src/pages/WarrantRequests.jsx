import { useState, useEffect } from 'react';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import { useAuth } from '../contexts/AuthContext';
import { hasAnyRole } from '../utils/permissions';

const ACTIONS = [
  { key: 'arrest_warrant', label: 'Arrest Warrant' },
  { key: 'search_warrant', label: 'Search Warrant' },
  { key: 'raid_permission', label: 'Raid Permission' },
  { key: 'proclamation', label: 'Proclamation (u/s 87)' },
  { key: 'attachment', label: 'Property Attachment (u/s 88)' },
];
const ACTION_LABEL = Object.fromEntries(ACTIONS.map(a => [a.key, a.label]));

const STATUS_STYLE = {
  pending: { background: 'rgba(234,179,8,0.15)', color: '#b45309' },
  approved: { background: 'rgba(22,163,74,0.15)', color: '#15803d' },
  rejected: { background: 'rgba(229,62,62,0.15)', color: '#b91c1c' },
};

function StatusBadge({ status }) {
  const s = STATUS_STYLE[status] || { background: '#eee', color: '#555' };
  return <span style={{ ...s, padding: '3px 10px', borderRadius: 20, fontSize: 12, fontWeight: 600, textTransform: 'capitalize' }}>{status}</span>;
}

export default function WarrantRequests() {
  const { user } = useAuth();
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ action_key: 'arrest_warrant', enquiry_id: '', case_file_id: '', remarks: '' });
  const [enquiries, setEnquiries] = useState([]);
  const [cases, setCases] = useState([]);
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [rejectTarget, setRejectTarget] = useState(null);
  const [rejectRemarks, setRejectRemarks] = useState('');

  const canApprove = hasAnyRole(user, ['admin', 'circle_incharge', 'director_general']);
  const canRequest = hasAnyRole(user, ['admin', 'circle_incharge', 'enquiry_officer', 'investigation_officer', 'director_general']);

  const fetchData = () => {
    setLoading(true);
    api.get('/warrant-requests', { params: { per_page: 50 } })
      .then(r => setList(r.data.data || r.data))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchData();
    api.get('/enquiries', { params: { page: 1 } }).then(r => {
      const d = r.data.data || r.data; setEnquiries(Array.isArray(d) ? d : (d?.data || []));
    }).catch(() => {});
    api.get('/cases', { params: { page: 1 } }).then(r => {
      const d = r.data.data || r.data; setCases(Array.isArray(d) ? d : (d?.data || []));
    }).catch(() => {});
  }, []);

  const resetForm = () => {
    setForm({ action_key: 'arrest_warrant', enquiry_id: '', case_file_id: '', remarks: '' });
    setShowForm(false);
  };

  const submit = async (e) => {
    e.preventDefault();
    if (!form.enquiry_id && !form.case_file_id) {
      setNotice('Please link an enquiry or a case to the request.');
      return;
    }
    setBusy(true); setNotice('');
    try {
      const payload = {
        action_key: form.action_key,
        remarks: form.remarks || null,
        enquiry_id: form.enquiry_id || null,
        case_file_id: form.case_file_id || null,
      };
      const r = await api.post('/warrant-requests', payload);
      setNotice(r.data.message || 'Request submitted.');
      resetForm();
      fetchData();
    } catch (err) {
      setNotice(err?.response?.data?.message || 'Could not submit the request.');
    } finally {
      setBusy(false);
    }
  };

  const approve = async (row) => {
    setBusy(true); setNotice('');
    try {
      const r = await api.post(`/warrant-requests/${row.id}/approve`, {});
      setNotice(r.data.message || 'Approved.');
      fetchData();
    } catch (err) {
      setNotice(err?.response?.data?.message || 'Could not approve.');
    } finally {
      setBusy(false);
    }
  };

  const doReject = async () => {
    if (!rejectTarget) return;
    if (!rejectRemarks.trim()) { setNotice('A reason is required to reject.'); return; }
    setBusy(true); setNotice('');
    try {
      const r = await api.post(`/warrant-requests/${rejectTarget.id}/reject`, { remarks: rejectRemarks });
      setNotice(r.data.message || 'Rejected.');
      setRejectTarget(null); setRejectRemarks('');
      fetchData();
    } catch (err) {
      setNotice(err?.response?.data?.message || 'Could not reject.');
    } finally {
      setBusy(false);
    }
  };

  const linkedLabel = (row) => {
    if (row.case_file_id) return `Case: ${row.case_file?.fir_no || `#${row.case_file_id}`}`;
    if (row.enquiry_id) return `Enquiry: ${row.enquiry?.enquiry_number || `#${row.enquiry_id}`}`;
    return '-';
  };

  if (loading) return <div className="page-content"><LoadingSkeleton type="table" columns={6} rows={8} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Warrant & Approval Requests</h1>
          <p className="page-subtitle">Arrest / search warrants, raids and 87/88 CrPC orders routed to the Circle Incharge for approval</p>
          <div className="title-underline"></div>
        </div>
        {canRequest && (
          <div className="page-actions">
            <button className="btn btn-primary btn-sm" onClick={() => { setShowForm(!showForm); setNotice(''); }}>
              {showForm ? 'Cancel' : 'New Request'}
            </button>
          </div>
        )}
      </div>

      {notice && <div className="card" style={{marginBottom:16}}><div className="card-body" style={{color:'#2563eb'}}>{notice}</div></div>}

      {showForm && canRequest && (
        <div className="card" style={{marginBottom:20}}>
          <div className="card-header"><div className="card-title">New Approval Request</div></div>
          <div className="card-body">
            <form onSubmit={submit}>
              <div className="cf-field">
                <label className="cf-label required">Action</label>
                <select className="cf-input" value={form.action_key} onChange={e => setForm({...form, action_key: e.target.value})}>
                  {ACTIONS.map(a => <option key={a.key} value={a.key}>{a.label}</option>)}
                </select>
              </div>
              <div className="cf-field">
                <label className="cf-label">Link to Case (FIR)</label>
                <select className="cf-input" value={form.case_file_id} onChange={e => setForm({...form, case_file_id: e.target.value})}>
                  <option value="">— none —</option>
                  {cases.map(c => <option key={c.id} value={c.id}>{c.fir_no || `Case #${c.id}`}</option>)}
                </select>
              </div>
              <div className="cf-field">
                <label className="cf-label">Link to Enquiry</label>
                <select className="cf-input" value={form.enquiry_id} onChange={e => setForm({...form, enquiry_id: e.target.value})}>
                  <option value="">— none —</option>
                  {enquiries.map(en => <option key={en.id} value={en.id}>{en.enquiry_number || `Enquiry #${en.id}`}</option>)}
                </select>
              </div>
              <div className="cf-field">
                <label className="cf-label">Remarks / justification</label>
                <textarea className="cf-input" rows={3} value={form.remarks} onChange={e => setForm({...form, remarks: e.target.value})} />
              </div>
              <p style={{color:'#6c757d',fontSize:13,marginTop:0}}>Link at least one — a case or an enquiry.</p>
              <div style={{display:'flex',gap:10}}>
                <button type="submit" className="btn btn-primary btn-sm" disabled={busy}>Submit for Approval</button>
                <button type="button" className="btn btn-outline btn-sm" onClick={resetForm}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="card">
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead><tr><th>#</th><th>Action</th><th>Linked to</th><th>Requested by</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                {list.map((row) => (
                  <tr key={row.id}>
                    <td><span className="table-id">#{row.id}</span></td>
                    <td>{ACTION_LABEL[row.action_key] || row.action_key}</td>
                    <td style={{fontSize:13}}>{linkedLabel(row)}</td>
                    <td style={{fontSize:13}}>{row.requester?.name || '-'}</td>
                    <td><StatusBadge status={row.status} /></td>
                    <td>
                      {canApprove && row.status === 'pending' ? (
                        <div style={{display:'flex',gap:6}}>
                          <button className="btn btn-primary btn-sm" disabled={busy} onClick={() => approve(row)}>Approve</button>
                          <button className="btn btn-outline btn-sm" disabled={busy} onClick={() => { setRejectTarget(row); setRejectRemarks(''); }}>Reject</button>
                        </div>
                      ) : (
                        <span style={{fontSize:12,color:'#6c757d'}}>
                          {row.approver?.name ? `${row.status} by ${row.approver.name}` : '-'}
                          {row.remarks && row.status === 'rejected' ? ` — ${row.remarks}` : ''}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
                {list.length === 0 && <tr><td colSpan={6} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No requests yet.</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {rejectTarget && (
        <div style={{position:'fixed',inset:0,zIndex:9999,display:'flex',alignItems:'center',justifyContent:'center',background:'rgba(0,0,0,0.5)',padding:20}} onClick={() => { setRejectTarget(null); setRejectRemarks(''); }}>
          <div style={{background:'#fff',borderRadius:12,width:'100%',maxWidth:420,boxShadow:'0 20px 60px rgba(0,0,0,0.2)'}} onClick={e => e.stopPropagation()}>
            <div style={{padding:'22px 24px'}}>
              <h3 style={{margin:'0 0 10px',fontSize:16,fontWeight:600}}>Reject Request</h3>
              <p style={{marginTop:0,color:'#6c757d',fontSize:14}}>Rejecting request #{rejectTarget.id} ({ACTION_LABEL[rejectTarget.action_key] || ''}). A reason is required.</p>
              <label className="cf-label required">Reason</label>
              <textarea className="cf-input" rows={3} value={rejectRemarks} onChange={e => setRejectRemarks(e.target.value)} />
              <div style={{display:'flex',gap:10,justifyContent:'flex-end',marginTop:16}}>
                <button className="btn btn-outline btn-sm" onClick={() => { setRejectTarget(null); setRejectRemarks(''); }}>Cancel</button>
                <button className="btn btn-sm" style={{background:'#e53e3e',color:'#fff',border:'none'}} disabled={busy} onClick={doReject}>Reject</button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

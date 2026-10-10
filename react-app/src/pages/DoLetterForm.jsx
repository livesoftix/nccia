import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import api from '../api';
import { printApiDocument } from '../utils/print';
import { useAuth } from '../contexts/AuthContext';
import {
  REPORT_STATUS_LABELS,
  canCompileAdminReports,
  canReviewAdminReports,
  canAckHqReports,
} from '../utils/adminReports';

function StatRow({ title, cells }) {
  return (
    <div className="card" style={{ marginBottom: 16, padding: 0, overflow: 'hidden' }}>
      <div style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
        <h3 style={{ margin: 0, fontSize: 15 }}>{title}</h3>
      </div>
      <div style={{ overflowX: 'auto' }}>
        <table className="data-table" style={{ width: '100%', margin: 0 }}>
          <thead>
            <tr>{cells.map(c => <th key={c.label}>{c.label}</th>)}</tr>
          </thead>
          <tbody>
            <tr>{cells.map(c => <td key={c.label}><strong>{c.value ?? 0}</strong></td>)}</tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default function DoLetterForm() {
  const { id } = useParams();
  const isCreate = !id || id === 'create';
  const navigate = useNavigate();
  const { user } = useAuth();
  const [circles, setCircles] = useState([]);
  const [letter, setLetter] = useState(null);
  const [form, setForm] = useState({
    circle_id: user?.circle_id || '',
    report_month: new Date().toISOString().slice(0, 7) + '-01',
    notes: '',
  });
  const [loading, setLoading] = useState(!isCreate);
  const [busy, setBusy] = useState(false);
  const [loadError, setLoadError] = useState('');
  const [remarks, setRemarks] = useState('');
  const [previewData, setPreviewData] = useState(null);

  const canEditMeta = isCreate || (canCompileAdminReports(user) && ['draft', 'sent_back'].includes(letter?.status));

  useEffect(() => {
    api.get('/lookup/circles').then(r => {
      const circles = Array.isArray(r.data) ? r.data : r.data?.data;
      if (!Array.isArray(circles)) throw new Error('Invalid circle list response.');
      setCircles(circles);
    }).catch(() => setLoadError('Circle list could not load. Please refresh and try again.'));
  }, []);

  useEffect(() => {
    if (isCreate) return;
    setLoading(true);
    setLoadError('');
    api.get(`/do-letters/${id}`).then(r => {
      setLetter(r.data);
      setForm({
        circle_id: r.data.circle_id,
        report_month: r.data.report_month?.slice?.(0, 10) || r.data.report_month,
        notes: r.data.notes || '',
      });
      setLoading(false);
    }).catch(() => { setLoadError('This report could not load. Please refresh or return to the report list.'); setLoading(false); });
  }, [id, isCreate]);

  const save = async (recompile = false) => {
    setBusy(true);
    try {
      if (isCreate) {
        const r = await api.post('/do-letters', { ...form, auto_compile: true });
        navigate(`/do-letters/${r.data.letter.id}`);
      } else {
        const originalMonth = letter?.report_month?.slice?.(0, 7);
        const selectedMonth = form.report_month?.slice?.(0, 7);
        const monthChanged = selectedMonth && originalMonth && selectedMonth !== originalMonth;
        const r = await api.put(`/do-letters/${id}`, { ...form, recompile: recompile || monthChanged });
        setLetter(r.data.letter);
        setPreviewData(null);
        alert(monthChanged ? 'Month updated and recompiled' : 'D.O. Letter saved');
      }
    } catch (e) {
      alert(e.response?.data?.message || 'Save failed');
    } finally {
      setBusy(false);
    }
  };

  const previewCompile = async () => {
    if (!form.circle_id || !form.report_month) {
      alert('Pehle circle aur month select karein.');
      return;
    }
    setBusy(true);
    try {
      const r = await api.post('/do-letters/preview', {
        circle_id: form.circle_id,
        report_month: form.report_month,
      });
      if (isCreate) {
        setPreviewData(r.data);
      } else {
        setLetter(prev => ({
          ...prev,
          payload: r.data.payload,
          month_label: r.data.month_label,
        }));
        alert('Preview loaded — Save dabayein ya Recompile from System use karein.');
      }
    } catch (e) {
      alert(e.response?.data?.message || 'Preview failed');
    } finally {
      setBusy(false);
    }
  };

  const action = async (path, body = {}) => {
    setBusy(true);
    try {
      const r = await api.post(`/do-letters/${id}/${path}`, body);
      setLetter(r.data.letter);
      alert(r.data.message);
    } catch (e) {
      alert(e.response?.data?.message || 'Action failed');
    } finally {
      setBusy(false);
    }
  };

  const exportLetter = () => {
    window.open(`/api/do-letters/${id}/export`, '_blank');
  };

  const exportLetterHtml = () => {
    printApiDocument(`/do-letters/${id}/export?format=html`);
  };

  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;

  const status = letter?.status;
  const payload = (isCreate ? previewData?.payload : letter?.payload) || {};
  const arrested = payload.accused_arrested || [];
  const complaints = payload.complaints_registered || [];
  const showStats = isCreate ? Boolean(previewData) : !isCreate;

  return (
    <div className="page-content">
      {loadError && <p role="alert" style={{color: '#b91c1c'}}>{loadError}</p>}
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label"><Link to="/do-letters">D.O. Letter</Link></div>
          <h1 className="page-title">
            {isCreate ? 'New Monthly D.O. Letter' : `D.O. Letter — ${letter?.month_label || ''}`}
          </h1>
          {!isCreate && (
            <p className="page-subtitle">
              {letter?.circle?.name || 'Circle'} · Status: {REPORT_STATUS_LABELS[status] || status}
            </p>
          )}
          <div className="title-underline"></div>
        </div>
        <div className="page-actions" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {!isCreate && <button type="button" className="btn btn-sm" onClick={exportLetter}>Download Excel (.xlsx)</button>}
          {!isCreate && <button type="button" className="btn btn-sm" onClick={exportLetterHtml}>Print HTML</button>}
          {canCompileAdminReports(user) && ['draft', 'sent_back'].includes(status) && !isCreate && (
            <>
              <button type="button" className="btn btn-sm" disabled={busy} onClick={() => save(true)}>Recompile from System</button>
              <button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={() => action('submit-ci')}>Mark to Circle Incharge</button>
            </>
          )}
          {canReviewAdminReports(user) && status === 'submitted_to_ci' && (
            <>
              <button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={() => action('approve-ci', { ci_remarks: remarks })}>Approve</button>
              <button type="button" className="btn btn-sm" disabled={busy} onClick={() => action('send-back', { send_back_remarks: remarks || 'Please revise' })}>Send Back</button>
            </>
          )}
          {canReviewAdminReports(user) && status === 'ci_approved' && (
            <button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={() => action('forward-hq')}>Forward to Islamabad HQ</button>
          )}
          {canAckHqReports(user) && status === 'forwarded_to_hq' && (
            <button type="button" className="btn btn-primary btn-sm" disabled={busy} onClick={() => action('ack-hq')}>HQ Acknowledge</button>
          )}
        </div>
      </div>

      {(canReviewAdminReports(user) || canAckHqReports(user)) && !isCreate && (
        <div className="card" style={{ marginBottom: 16 }}>
          <label>Remarks (CI / HQ)</label>
          <textarea value={remarks} onChange={e => setRemarks(e.target.value)} rows={2} style={{ width: '100%' }} />
        </div>
      )}

      <div className="card" style={{ marginBottom: 16 }}>
        <div className="form-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
          <div>
            <label>Circle</label>
            <select value={form.circle_id} disabled={!canEditMeta} onChange={e => {
              setForm(f => ({ ...f, circle_id: e.target.value }));
              setPreviewData(null);
            }}>
              <option value="">Select circle</option>
              {circles.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
            </select>
          </div>
          <div>
            <label>Month</label>
            <input
              type="month"
              value={form.report_month?.slice(0, 7)}
              disabled={!canEditMeta}
              onChange={e => {
                setForm(f => ({ ...f, report_month: e.target.value + '-01' }));
                setPreviewData(null);
              }}
            />
            <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>Koi bhi purana month select kar sakte hain.</div>
          </div>
        </div>
        <div style={{ marginTop: 12 }}>
          <label>Notes</label>
          <textarea value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} rows={2} style={{ width: '100%' }} />
        </div>
        {canCompileAdminReports(user) && (
          <div style={{ marginTop: 12, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            {canEditMeta && (
              <button type="button" className="btn btn-outline" disabled={busy} onClick={previewCompile}>
                Preview for Month
              </button>
            )}
            <button type="button" className="btn btn-primary" disabled={busy} onClick={() => save(false)}>
              {isCreate ? 'Create & Auto-Compile' : 'Save'}
            </button>
          </div>
        )}
      </div>

      {showStats && (
        <>
          <StatRow title="1. Cases" cells={[
            { label: 'At beginning', value: payload.cases?.at_beginning },
            { label: 'Added', value: payload.cases?.added },
            { label: 'Total', value: payload.cases?.total },
            { label: 'Interim Challan', value: payload.cases?.interim_challan },
            { label: 'Final Challan', value: payload.cases?.final_challan },
            { label: 'Discharge', value: payload.cases?.discharge },
            { label: 'Transferred', value: payload.cases?.transferred },
            { label: 'Pending', value: payload.cases?.pending },
          ]} />

          <StatRow title="2. Cases (CFRs) and Pendency" cells={[
            { label: 'Total CFRs', value: payload.cfr_pendency?.total_cfrs },
            { label: 'Pending Legal', value: payload.cfr_pendency?.pending_legal },
            { label: 'Pending Forensic', value: payload.cfr_pendency?.pending_forensic },
            { label: 'Under Investigation', value: payload.cfr_pendency?.pending_investigation },
            { label: 'Total Pending', value: payload.cfr_pendency?.total_pending },
          ]} />

          <StatRow title="3. Enquiries" cells={[
            { label: 'At beginning', value: payload.enquiries?.at_beginning },
            { label: 'Added', value: payload.enquiries?.added },
            { label: 'Closed', value: payload.enquiries?.closed },
            { label: 'Pending', value: payload.enquiries?.pending },
          ]} />

          <StatRow title="4. Raids" cells={[
            { label: 'Total raids', value: payload.raid?.total_raid },
            { label: 'Cases after raid', value: payload.raid?.cases_after_raid },
            { label: 'Enquiries after raid', value: payload.raid?.enquiries_after_raid },
          ]} />

          <StatRow title="5. Proclaimed Offenders (PO)" cells={[
            { label: 'Previous', value: payload.pos?.previous },
            { label: 'Added', value: payload.pos?.added },
            { label: 'Arrested', value: payload.pos?.arrested },
            { label: 'Pending', value: payload.pos?.pending },
          ]} />

          <StatRow title="6. Court Absconders (CA)" cells={[
            { label: 'Previous', value: payload.cas?.previous },
            { label: 'Added', value: payload.cas?.added },
            { label: 'Arrested', value: payload.cas?.arrested },
            { label: 'Pending', value: payload.cas?.pending },
          ]} />

          <div className="card" style={{ marginBottom: 16, padding: 0, overflow: 'hidden' }}>
            <div style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
              <h3 style={{ margin: 0, fontSize: 15 }}>Accused Arrested ({arrested.length})</h3>
            </div>
            <div style={{ overflowX: 'auto' }}>
              {arrested.length === 0 ? (
                <p style={{ padding: 16, margin: 0, color: '#64748b', fontWeight: 600 }}>NIL</p>
              ) : (
                <table className="data-table" style={{ width: '100%', margin: 0 }}>
                  <thead>
                    <tr>
                      <th>Sr.#</th>
                      <th>FIR #</th>
                      <th>Accused Name</th>
                      <th>Date of Arrest</th>
                      <th>Address</th>
                    </tr>
                  </thead>
                  <tbody>
                    {arrested.map((row, i) => (
                      <tr key={i}>
                        <td>{row.sr || i + 1}</td>
                        <td>{row.fir_no || '—'}</td>
                        <td>{row.accused_name || '—'}</td>
                        <td>{row.arrest_date || '—'}</td>
                        <td>{row.address || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>

          <div className="card" style={{ marginBottom: 16, padding: 0, overflow: 'hidden' }}>
            <div style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
              <h3 style={{ margin: 0, fontSize: 15 }}>Complaints Registered ({complaints.length})</h3>
            </div>
            <div style={{ overflowX: 'auto' }}>
              {complaints.length === 0 ? (
                <p style={{ padding: 16, margin: 0, color: '#64748b', fontWeight: 600 }}>NIL</p>
              ) : (
                <table className="data-table" style={{ width: '100%', margin: 0 }}>
                  <thead>
                    <tr>
                      <th>Sr.#</th>
                      <th>Tracking #</th>
                      <th>Diary #</th>
                      <th>Dated</th>
                      <th>Complainant</th>
                      <th>CNIC</th>
                      <th>Contact</th>
                      <th>Offence Type</th>
                      <th>U/S</th>
                      <th>Amount</th>
                      <th>Received Via</th>
                      <th>Operator</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {complaints.map((row, i) => (
                      <tr key={i}>
                        <td>{i + 1}</td>
                        <td>{row.tracking_no || '—'}</td>
                        <td>{row.diary_no || '—'}</td>
                        <td>{row.dated || '—'}</td>
                        <td>{row.complainant || '—'}</td>
                        <td>{row.cnic || '—'}</td>
                        <td>{row.contact_no || '—'}</td>
                        <td>{row.offence_type || '—'}</td>
                        <td>{row.laws || '—'}</td>
                        <td>{row.amount_involved || '—'}</td>
                        <td>{row.received_via || '—'}</td>
                        <td>{row.operator_name || '—'}</td>
                        <td>{row.status || '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}

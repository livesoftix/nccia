import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import {
  REPORT_STATUS_LABELS,
  canCompileAdminReports,
  canReviewAdminReports,
  canAckHqReports,
} from '../utils/adminReports';

const HIGHLIGHT_ORDER = [
  ['complaints', 'Complaints Registered'],
  ['verifications', 'Verifications Done'],
  ['cases', 'Cases'],
  ['enquiries_registered', 'Enquiries Registered'],
  ['enquiries_closed', 'Enquiries Closed'],
  ['accused_in_lockup', 'Accused in Lockup'],
  ['fresh_arrests', 'Fresh Accused Arrested'],
  ['bail', 'Bail (Pre/Post)'],
  ['old_accused_arrested', 'Old Accused Arrested'],
  ['conviction', 'Conviction'],
  ['raids', 'Raids (today)'],
];

function SectionTable({ title, rows, columns }) {
  const list = Array.isArray(rows) ? rows : [];
  return (
    <div className="card" style={{ marginBottom: 16, padding: 0, overflow: 'hidden' }}>
      <div style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
        <h3 style={{ margin: 0, fontSize: 15 }}>{title} ({list.length})</h3>
      </div>
      <div style={{ overflowX: 'auto' }}>
        {list.length === 0 ? (
          <p style={{ padding: 16, margin: 0, color: '#64748b', fontWeight: 600 }}>NIL</p>
        ) : (
          <table className="data-table" style={{ width: '100%', margin: 0 }}>
            <thead>
              <tr>
                <th>Sr.#</th>
                {columns.map(c => <th key={c.key}>{c.label}</th>)}
              </tr>
            </thead>
            <tbody>
              {list.map((row, i) => (
                <tr key={i}>
                  <td>{i + 1}</td>
                  {columns.map(c => (
                    <td key={c.key}>{row?.[c.key] ?? '—'}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

function ProgressTable({ title, rows }) {
  const list = Array.isArray(rows) ? rows : [];
  return (
    <div className="card" style={{ marginBottom: 16, padding: 0, overflow: 'hidden' }}>
      <div style={{ padding: '12px 16px', borderBottom: '1px solid #e2e8f0', background: '#f8fafc' }}>
        <h3 style={{ margin: 0, fontSize: 15 }}>{title}</h3>
      </div>
      <div style={{ overflowX: 'auto' }}>
        <table className="data-table" style={{ width: '100%', margin: 0 }}>
          <thead>
            <tr>
              <th>Unit</th>
              <th>Previous</th>
              <th>Added</th>
              <th>Pending</th>
              <th>As on</th>
            </tr>
          </thead>
          <tbody>
            {list.length === 0 ? (
              <tr><td colSpan={5} style={{ textAlign: 'center' }}>NIL</td></tr>
            ) : list.map((row, i) => (
              <tr key={i}>
                <td>{row.unit || '—'}</td>
                <td>{row.previous ?? 0}</td>
                <td>{row.added ?? 0}</td>
                <td>{row.pending ?? 0}</td>
                <td>{row.as_on || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default function DsrReportForm() {
  const { id } = useParams();
  const isCreate = !id || id === 'create';
  const navigate = useNavigate();
  const { user } = useAuth();
  const [circles, setCircles] = useState([]);
  const [report, setReport] = useState(null);
  const [form, setForm] = useState({
    circle_id: user?.circle_id || '',
    report_date: new Date().toISOString().slice(0, 10),
    unit_name: 'CCRC-Lahore',
    notes: '',
  });
  const [loading, setLoading] = useState(!isCreate);
  const [busy, setBusy] = useState(false);
  const [remarks, setRemarks] = useState('');
  const [previewData, setPreviewData] = useState(null);

  const canEditMeta = isCreate || (canCompileAdminReports(user) && ['draft', 'sent_back'].includes(report?.status));

  useEffect(() => {
    api.get('/lookup/circles').then(r => setCircles(r.data || [])).catch(() => {});
  }, []);

  useEffect(() => {
    if (isCreate) return;
    api.get(`/dsr-reports/${id}`).then(r => {
      setReport(r.data);
      setForm({
        circle_id: r.data.circle_id,
        report_date: r.data.report_date?.slice?.(0, 10) || r.data.report_date,
        unit_name: r.data.unit_name,
        notes: r.data.notes || '',
      });
      setLoading(false);
    }).catch(() => setLoading(false));
  }, [id, isCreate]);

  const save = async (recompile = false) => {
    setBusy(true);
    try {
      if (isCreate) {
        const r = await api.post('/dsr-reports', { ...form, auto_compile: true });
        navigate(`/dsr-reports/${r.data.report.id}`);
      } else {
        const originalDate = report?.report_date?.slice?.(0, 10) || report?.report_date;
        const dateChanged = form.report_date && originalDate && form.report_date !== originalDate;
        const r = await api.put(`/dsr-reports/${id}`, { ...form, recompile: recompile || dateChanged });
        setReport(r.data.report);
        setPreviewData(null);
        alert(dateChanged ? 'DSR date updated and recompiled' : 'DSR saved');
      }
        } catch (e) {
      const data = e.response?.data;
      const msg = data?.message
        || (data?.errors && Object.values(data.errors).flat()[0])
        || e.message
        || 'Save failed';
      alert(msg);
    } finally {
      setBusy(false);
    }
  };

  const previewCompile = async () => {
    if (!form.circle_id || !form.report_date) {
      alert('Pehle circle aur report date select karein.');
      return;
    }
    setBusy(true);
    try {
      const r = await api.post('/dsr-reports/preview', {
        circle_id: form.circle_id,
        report_date: form.report_date,
        unit_name: form.unit_name,
      });
      if (isCreate) {
        setPreviewData(r.data);
      } else {
        setReport(prev => ({
          ...prev,
          highlights: r.data.highlights,
          payload: r.data.payload,
          unit_name: r.data.unit_name || form.unit_name,
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
      const r = await api.post(`/dsr-reports/${id}/${path}`, body);
      setReport(r.data.report);
      alert(r.data.message);
    } catch (e) {
      alert(e.response?.data?.message || 'Action failed');
    } finally {
      setBusy(false);
    }
  };

  const exportReport = () => {
    window.open(`/api/dsr-reports/${id}/export`, '_blank');
  };

  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;

  const status = report?.status;
  const highlights = (isCreate ? previewData?.highlights : report?.highlights) || {};
  const payload = (isCreate ? previewData?.payload : report?.payload) || {};
  const dated = form.report_date
    ? form.report_date.split('-').reverse().join('.')
    : '';

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label"><Link to="/dsr-reports">DSR</Link></div>
          <h1 className="page-title">
            {isCreate ? 'New Daily Situation Report' : `Daily Situation Report — ${form.unit_name || 'CCRC'}`}
          </h1>
          {!isCreate && (
            <p className="page-subtitle">
              Dated {dated} · Status: {REPORT_STATUS_LABELS[status] || status}
            </p>
          )}
          <div className="title-underline"></div>
        </div>
        <div className="page-actions" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {!isCreate && <button type="button" className="btn btn-sm" onClick={exportReport}>Print Official DSR</button>}
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

      {report?.send_back_remarks && (
        <div className="card" style={{ marginBottom: 16, borderColor: '#e53e3e' }}>
          <strong>Sent back for correction:</strong> {report.send_back_remarks}
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
            <label>Report Date</label>
            <input
              type="date"
              value={form.report_date}
              disabled={!canEditMeta}
              onChange={e => {
                setForm(f => ({ ...f, report_date: e.target.value }));
                setPreviewData(null);
              }}
            />
            <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>Koi bhi purani ya aaj ki date select kar sakte hain.</div>
          </div>
          <div>
            <label>Unit Name</label>
            <input value={form.unit_name} onChange={e => setForm(f => ({ ...f, unit_name: e.target.value }))} />
          </div>
        </div>
        <div style={{ marginTop: 12 }}>
          <label>Highlights / Notes (optional)</label>
          <textarea value={form.notes} onChange={e => setForm(f => ({ ...f, notes: e.target.value }))} rows={2} style={{ width: '100%' }} placeholder="e.g. NIL / important remarks for HQ" />
        </div>
        {canCompileAdminReports(user) && (
          <div style={{ marginTop: 12, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            {canEditMeta && (
              <button type="button" className="btn btn-outline" disabled={busy} onClick={previewCompile}>
                Preview for Date
              </button>
            )}
            <button type="button" className="btn btn-primary" disabled={busy} onClick={() => save(false)}>
              {isCreate ? 'Create & Auto-Compile' : 'Save'}
            </button>
          </div>
        )}
      </div>

      {(isCreate ? previewData : !isCreate) && (
        <>
          <div className="card" style={{ marginBottom: 16 }}>
            <h3 style={{ marginTop: 0 }}>Highlights</h3>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10 }}>
              {HIGHLIGHT_ORDER.map(([key, label]) => (
                <div key={key} style={{ background: '#f1f5f9', padding: '12px 14px', borderRadius: 10, border: '1px solid #e2e8f0' }}>
                  <div style={{ fontSize: 12, color: '#64748b', marginBottom: 4 }}>{label}</div>
                  <div style={{ fontSize: 22, fontWeight: 700, color: '#0f172a' }}>{highlights[key] ?? 0}</div>
                </div>
              ))}
            </div>
          </div>

          <SectionTable
            title="Complaints Registered"
            rows={payload.complaints_registered}
            columns={[
              { key: 'tracking_no', label: 'Tracking No.' },
              { key: 'diary_no', label: 'Diary No.' },
              { key: 'dated', label: 'Dated' },
              { key: 'complainant', label: 'Complainant' },
              { key: 'cnic', label: 'CNIC' },
              { key: 'circle', label: 'Circle' },
              { key: 'offence_type', label: 'Offence Type' },
              { key: 'laws', label: 'U/S' },
              { key: 'amount_involved', label: 'Amount involved' },
              { key: 'received_via', label: 'Received Via' },
              { key: 'operator_name', label: 'Operator' },
              { key: 'status', label: 'Status' },
            ]}
          />

          <SectionTable
            title="Verifications Done"
            rows={payload.verifications_registered}
            columns={[
              { key: 'tracking_no', label: 'Tracking No.' },
              { key: 'dated', label: 'Dated' },
              { key: 'complainant', label: 'Complainant' },
              { key: 'circle', label: 'Circle' },
              { key: 'victim_name', label: 'Victim' },
              { key: 'victim_cnic', label: 'CNIC' },
              { key: 'city', label: 'City' },
              { key: 'crime_category', label: 'Crime Category' },
              { key: 'accused_names', label: 'Accused' },
              { key: 'verifier', label: 'Verified By' },
              { key: 'recommendation', label: 'Recommendation' },
            ]}
          />

          <SectionTable
            title="Enquiries Registered"
            rows={payload.enquiries_registered}
            columns={[
              { key: 'enquiry_number', label: 'Enquiry No. & Dated' },
              { key: 'dated', label: 'Dated' },
              { key: 'complainant', label: 'Complainant' },
              { key: 'circle', label: 'Circle' },
              { key: 'eo_name', label: 'Name of E.O.' },
              { key: 'gist', label: 'Gist of allegations' },
              { key: 'accused', label: 'Name of Accused' },
              { key: 'amount_involved', label: 'Amount involved' },
              { key: 'amount_recovered', label: 'Amount recovered' },
              { key: 'cms_entered', label: 'Entered in CMS' },
            ]}
          />

          <SectionTable
            title="Enquiries Closed"
            rows={payload.enquiries_closed}
            columns={[
              { key: 'enquiry_number', label: 'Enquiry No.' },
              { key: 'dated', label: 'Dated' },
              { key: 'complainant', label: 'Complainant' },
              { key: 'circle', label: 'Circle' },
              { key: 'eo_name', label: 'Name of E.O.' },
              { key: 'closure_reason', label: 'Reason of Closure' },
              { key: 'cms_entered', label: 'Entered in CMS' },
            ]}
          />

          <SectionTable
            title="FIR / Case Registered"
            rows={payload.cases_registered}
            columns={[
              { key: 'enquiry_no', label: 'Enquiry No. / Raid' },
              { key: 'fir_no', label: 'FIR No. & Dated' },
              { key: 'dated', label: 'Dated' },
              { key: 'circle', label: 'Circle' },
              { key: 'io_name', label: 'Name of I.O.' },
              { key: 'gist', label: 'Gist of allegations' },
              { key: 'accused', label: 'Name of Accused' },
              { key: 'amount_involved', label: 'Amount involved' },
              { key: 'cms_entered', label: 'Entered in CMS' },
            ]}
          />

          <SectionTable
            title="FIR Disposed Off"
            rows={payload.cases_disposed}
            columns={[
              { key: 'fir_no', label: 'FIR No.' },
              { key: 'dated', label: 'Dated' },
              { key: 'circle', label: 'Circle' },
              { key: 'io_name', label: 'Name of I.O.' },
              { key: 'reason', label: 'Reason of Disposal' },
            ]}
          />

          <SectionTable
            title="Accused / PO / CA Arrested"
            rows={payload.arrests}
            columns={[
              { key: 'circle', label: 'Circle' },
              { key: 'accused_name', label: 'Name of accused' },
              { key: 'cnic', label: 'CNIC / PP No.' },
              { key: 'fir_no', label: 'FIR No & date' },
              { key: 'arrest_date', label: 'Arrest Date' },
            ]}
          />

          <ProgressTable title={`Progress of Cases (As on ${dated})`} rows={(payload.progress_cases || []).map(r => ({ ...r, unit: form.unit_name || r.unit }))} />
          <ProgressTable title={`Progress of Enquiries (As on ${dated})`} rows={(payload.progress_enquiries || []).map(r => ({ ...r, unit: form.unit_name || r.unit }))} />

          <div className="card" style={{ marginBottom: 16 }}>
            <h3 style={{ marginTop: 0 }}>Summary — {form.unit_name}</h3>
            <table className="data-table" style={{ width: '100%' }}>
              <tbody>
                <tr><td>Total Complaints Registered (today)</td><td><strong>{payload.summary?.total_complaints_registered ?? 0}</strong></td></tr>
                <tr><td>Total Verifications Done (today)</td><td><strong>{payload.summary?.total_verifications_registered ?? 0}</strong></td></tr>
                <tr><td>Total Cases Registered (today)</td><td><strong>{payload.summary?.total_cases_registered ?? 0}</strong></td></tr>
                <tr><td>Total Enquiries Registered (today)</td><td><strong>{payload.summary?.total_enquiries_registered ?? 0}</strong></td></tr>
                <tr><td>Total Arrests (today)</td><td><strong>{payload.summary?.total_arrests ?? 0}</strong></td></tr>
                <tr><td>Total Amount Recovered (today enquiries)</td><td><strong>{payload.summary?.total_amount_recovered || 'NIL'}</strong></td></tr>
                <tr><td>Raids (today)</td><td><strong>{payload.raid?.total_raid ?? 0}</strong></td></tr>
                <tr><td>Highlights / Remarks</td><td><strong>{form.notes?.trim() || 'NIL'}</strong></td></tr>
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

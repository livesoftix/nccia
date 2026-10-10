import { useCallback, useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';

const STATUS = {
  queued: { label: 'Queued', color: '#64748b' },
  processing: { label: 'Processing', color: '#3b82f6' },
  needs_review: { label: 'Needs review', color: '#f59e0b' },
  imported: { label: 'Imported', color: '#10b981' },
  failed: { label: 'Failed', color: '#ef4444' },
};

const FIELD_LABELS = {
  victim_full_name: 'Complainant name', victim_father_name: 'Father / husband / guardian', victim_gender: 'Gender',
  victim_cnic: 'CNIC', victim_phone: 'Mobile number', victim_email: 'Email', victim_occupation: 'Occupation',
  victim_address: 'Current address', victim_permanent_address: 'Permanent address', tracking_no: 'Tracking no',
  inquiry_no: 'Inquiry / enquiry no', file_no: 'File / reference no', verification_date: 'Verification date',
  assignment_date: 'Assignment date', report_date: 'Report date', crime_category: 'Crime category',
  crime_description: 'Description', city: 'City of occurrence', amount_involved: 'Amount involved',
  recommendation: 'Recommendation', reporting_officer: 'Reporting officer',
};

function Badge({ status }) {
  const s = STATUS[status] || { label: status, color: '#64748b' };
  return <span style={{ background: s.color, color: '#fff', borderRadius: 10, padding: '2px 10px', fontSize: 12, fontWeight: 600 }}>{s.label}</span>;
}

function apiError(err, fallback) {
  const data = err?.response?.data;
  if (data?.errors) return Object.values(data.errors).flat().join(' ');
  return data?.message || fallback;
}

function ReviewPanel({ id, onClose, onChanged }) {
  const [detail, setDetail] = useState(null);
  const [edits, setEdits] = useState({});
  const [linkId, setLinkId] = useState('');
  const [pageText, setPageText] = useState(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    const r = await api.get(`/ocr-imports/${id}`);
    setDetail(r.data);
  }, [id]);

  useEffect(() => { load().catch(e => setError(apiError(e, 'Could not load import.'))); }, [load]);

  const showPage = async (page) => {
    try {
      const r = await api.get(`/ocr-imports/${id}/pages/${page}`);
      setPageText(r.data);
    } catch (e) { setError(apiError(e, 'Could not load page text.')); }
  };

  const openPdf = async () => {
    try {
      const r = await api.get(`/ocr-imports/${id}/file`, { responseType: 'blob' });
      const url = URL.createObjectURL(new Blob([r.data], { type: 'application/pdf' }));
      window.open(url, '_blank', 'noopener');
      setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) { setError(apiError(e, 'Could not open PDF.')); }
  };

  const submit = async (approve) => {
    setBusy(true);
    setError('');
    try {
      const payload = { fields: edits, approve };
      if (approve && linkId) payload.link_complaint_id = Number(linkId);
      await api.put(`/ocr-imports/${id}/review`, payload);
      setEdits({});
      await load();
      onChanged();
    } catch (e) {
      setError(apiError(e, 'Could not save review.'));
    } finally {
      setBusy(false);
    }
  };

  const retry = async () => {
    setBusy(true);
    try { await api.post(`/ocr-imports/${id}/retry`); await load(); onChanged(); }
    catch (e) { setError(apiError(e, 'Retry failed.')); }
    finally { setBusy(false); }
  };

  if (!detail) {
    return <div className="card" style={{ padding: 20 }}>{error || 'Loading…'}</div>;
  }
  const imp = detail.import;
  const fields = imp.field_results || {};
  const editable = imp.status === 'needs_review';
  const names = detail.editable_fields.filter(n => fields[n] || editable);

  return (
    <div className="card" style={{ padding: 20 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <div>
          <h3 style={{ margin: 0 }}>{imp.original_filename}</h3>
          <div style={{ fontSize: 13, color: '#64748b', marginTop: 4 }}>
            Circle: <strong>{imp.circle?.name}</strong> · Pages {imp.pages_done}/{imp.page_count ?? '?'} · Uploaded by {imp.uploaded_by}
            {imp.mean_confidence != null && <> · Mean confidence {(imp.mean_confidence * 100).toFixed(0)}%</>}
          </div>
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          <Badge status={imp.status} />
          <button type="button" className="btn btn-sm btn-outline" onClick={openPdf}>View PDF</button>
          <button type="button" className="btn btn-sm btn-outline" onClick={onClose}>Close</button>
        </div>
      </div>

      {error && <div style={{ color: '#b91c1c', marginTop: 12 }}>{error}</div>}
      {imp.error_message && <div style={{ color: '#b91c1c', marginTop: 12 }}>Error: {imp.error_message}</div>}
      {imp.complaint_id && (
        <div style={{ marginTop: 12 }}>Imported as complaint <Link to={`/complaints/${imp.complaint_id}/edit`}>#{imp.complaint_id}</Link></div>
      )}
      {(imp.review_reasons || []).length > 0 && (
        <div style={{ background: '#fffbeb', border: '1px solid #fcd34d', borderRadius: 8, padding: 12, marginTop: 12 }}>
          <strong>Why this needs review</strong>
          <ul style={{ margin: '6px 0 0 18px' }}>{imp.review_reasons.map(r => <li key={r}>{r}</li>)}</ul>
        </div>
      )}

      <div style={{ overflowX: 'auto', marginTop: 16 }}>
        <table className="table" style={{ width: '100%', fontSize: 13 }}>
          <thead><tr><th>Field</th><th>Value</th><th>OCR text</th><th>Page</th><th>Confidence</th><th>Issues</th></tr></thead>
          <tbody>
            {names.map(name => {
              const f = fields[name] || {};
              const value = edits[name] ?? f.value ?? '';
              const bad = f.valid === false || (f.issues || []).some(i => /conflict|corrected|missing|not found/.test(i));
              return (
                <tr key={name} style={{ background: bad ? '#fef2f2' : undefined }}>
                  <td style={{ fontWeight: 600, whiteSpace: 'nowrap' }}>{FIELD_LABELS[name] || name}{f.required && ' *'}</td>
                  <td style={{ minWidth: 220 }}>
                    {editable
                      ? <input className="form-control" value={value ?? ''} onChange={e => setEdits(prev => ({ ...prev, [name]: e.target.value }))} dir="auto" />
                      : <span dir="auto">{String(f.value ?? '—')}</span>}
                  </td>
                  <td style={{ color: '#64748b', maxWidth: 220, overflow: 'hidden', textOverflow: 'ellipsis' }} dir="auto">{f.raw || ''}</td>
                  <td>{f.page ? <button type="button" className="btn btn-sm btn-outline" onClick={() => showPage(f.page)}>p.{f.page}</button> : '—'}</td>
                  <td>{f.confidence != null ? `${Math.round(f.confidence * 100)}%` : '—'}</td>
                  <td style={{ color: '#b45309' }}>{(f.issues || []).join('; ')}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {editable && (detail.possible_duplicates || []).length > 0 && (
        <div style={{ marginTop: 12 }}>
          <strong>Possible existing complaints in this circle</strong>
          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', marginTop: 6 }}>
            <label><input type="radio" name="link" checked={!linkId} onChange={() => setLinkId('')} /> Create new complaint</label>
            {detail.possible_duplicates.map(c => (
              <label key={c.id}>
                <input type="radio" name="link" checked={String(linkId) === String(c.id)} onChange={() => setLinkId(c.id)} />
                {' '}Link to #{c.id} {c.tracking_no} — {c.complainant_name} ({c.cnic})
              </label>
            ))}
          </div>
        </div>
      )}

      <div style={{ display: 'flex', gap: 8, marginTop: 16, flexWrap: 'wrap' }}>
        {editable && <button type="button" className="btn btn-outline" disabled={busy} onClick={() => submit(false)}>Save corrections</button>}
        {editable && <button type="button" className="btn btn-primary" disabled={busy} onClick={() => submit(true)}>Approve &amp; import</button>}
        {['failed', 'needs_review'].includes(imp.status) && <button type="button" className="btn btn-outline" disabled={busy} onClick={retry}>Reprocess</button>}
      </div>

      <details style={{ marginTop: 16 }}>
        <summary>Pages ({detail.pages.length})</summary>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginTop: 8 }}>
          {detail.pages.map(p => (
            <button key={p.page_no} type="button" className="btn btn-sm btn-outline" title={p.error || `${p.method} ${Math.round(p.confidence * 100)}%`}
              style={{ borderColor: p.error ? '#ef4444' : p.confidence < 0.7 ? '#f59e0b' : undefined }} onClick={() => showPage(p.page_no)}>
              {p.page_no}{p.method === 'ocr' ? '·ocr' : ''}
            </button>
          ))}
        </div>
      </details>

      {pageText && (
        <div style={{ marginTop: 12 }}>
          <strong>Page {pageText.page_no}</strong> ({pageText.method}, {Math.round(pageText.confidence * 100)}%)
          <pre dir="auto" style={{ whiteSpace: 'pre-wrap', background: '#f8fafc', padding: 12, borderRadius: 8, maxHeight: 360, overflow: 'auto', fontSize: 12 }}>{pageText.text}</pre>
        </div>
      )}
    </div>
  );
}

export default function OcrImports() {
  const fileRef = useRef(null);
  const [circles, setCircles] = useState([]);
  const [circleId, setCircleId] = useState('');
  const [rows, setRows] = useState([]);
  const [stats, setStats] = useState({});
  const [filter, setFilter] = useState({ status: '', search: '' });
  const [uploading, setUploading] = useState(false);
  const [message, setMessage] = useState('');
  const [selected, setSelected] = useState(null);

  const load = useCallback(async () => {
    const params = { per_page: 50, ...(filter.status && { status: filter.status }), ...(filter.search && { search: filter.search }) };
    const [list, s] = await Promise.all([api.get('/ocr-imports', { params }), api.get('/ocr-imports/stats')]);
    setRows(list.data.data || []);
    setStats(s.data || {});
  }, [filter]);

  useEffect(() => {
    api.get('/ocr-imports/circles').then(r => {
      setCircles(r.data || []);
      if ((r.data || []).length === 1) setCircleId(String(r.data[0].id));
    }).catch(() => setCircles([]));
  }, []);

  useEffect(() => {
    load().catch(() => {});
    const timer = setInterval(() => load().catch(() => {}), 5000);
    return () => clearInterval(timer);
  }, [load]);

  const upload = async (e) => {
    e.preventDefault();
    const files = Array.from(fileRef.current?.files || []);
    if (!circleId || files.length === 0) {
      setMessage('Choose a circle and at least one PDF.');
      return;
    }
    setUploading(true);
    setMessage('');
    try {
      let queued = 0, dupes = 0, failed = 0;
      for (let i = 0; i < files.length; i += 10) {
        const fd = new FormData();
        fd.append('circle_id', circleId);
        files.slice(i, i + 10).forEach(f => fd.append('files[]', f));
        const r = await api.post('/ocr-imports', fd, { timeout: 600000 });
        (r.data.imports || []).forEach(x => { if (x.duplicate) dupes++; else if (x.id) queued++; else failed++; });
      }
      setMessage(`${queued} queued, ${dupes} already uploaded to this circle, ${failed} failed.`);
      fileRef.current.value = '';
      load();
    } catch (err) {
      setMessage(apiError(err, 'Upload failed.'));
    } finally {
      setUploading(false);
    }
  };

  return (
    <div className="page-container">
      <div className="page-header" style={{ marginBottom: 16 }}>
        <h1 className="page-title">OCR Document Import</h1>
        <p style={{ color: '#64748b', margin: 0 }}>Upload PDFs into a circle. They are processed in the background; uncertain fields are sent for review.</p>
      </div>

      <form className="card" style={{ padding: 20, marginBottom: 16, display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'flex-end' }} onSubmit={upload}>
        <div>
          <label style={{ display: 'block', fontWeight: 600, fontSize: 13 }}>Circle</label>
          <select className="form-control" value={circleId} onChange={e => setCircleId(e.target.value)} required>
            <option value="">Select circle…</option>
            {circles.map(c => <option key={c.id} value={c.id}>{c.name} ({c.code})</option>)}
          </select>
        </div>
        <div>
          <label style={{ display: 'block', fontWeight: 600, fontSize: 13 }}>PDF files</label>
          <input ref={fileRef} type="file" accept="application/pdf,.pdf" multiple className="form-control" />
        </div>
        <button type="submit" className="btn btn-primary" disabled={uploading}>{uploading ? 'Uploading…' : 'Upload & process'}</button>
        {message && <div style={{ width: '100%', fontSize: 13 }}>{message}</div>}
      </form>

      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginBottom: 16 }}>
        {Object.keys(STATUS).map(k => (
          <button key={k} type="button" className="card" onClick={() => setFilter(f => ({ ...f, status: f.status === k ? '' : k }))}
            style={{ padding: '8px 14px', cursor: 'pointer', border: filter.status === k ? `2px solid ${STATUS[k].color}` : undefined }}>
            <div style={{ fontSize: 12, color: '#64748b' }}>{STATUS[k].label}</div>
            <div style={{ fontSize: 20, fontWeight: 700 }}>{stats[k] || 0}</div>
          </button>
        ))}
        <input className="form-control" placeholder="Search file or inquiry no…" style={{ maxWidth: 260 }}
          value={filter.search} onChange={e => setFilter(f => ({ ...f, search: e.target.value }))} />
      </div>

      {selected && (
        <div style={{ marginBottom: 16 }}>
          <ReviewPanel id={selected} onClose={() => setSelected(null)} onChanged={() => load()} />
        </div>
      )}

      <div className="card" style={{ overflowX: 'auto' }}>
        <table className="table" style={{ width: '100%', fontSize: 13 }}>
          <thead><tr><th>#</th><th>File</th><th>Circle</th><th>Status</th><th>Progress</th><th>Confidence</th><th>Inquiry</th><th></th></tr></thead>
          <tbody>
            {rows.length === 0 && <tr><td colSpan={8} style={{ textAlign: 'center', color: '#94a3b8', padding: 24 }}>No imports yet.</td></tr>}
            {rows.map(r => (
              <tr key={r.id}>
                <td>{r.id}</td>
                <td style={{ maxWidth: 260, overflow: 'hidden', textOverflow: 'ellipsis' }} title={r.original_filename}>{r.original_filename}</td>
                <td>{r.circle?.name}</td>
                <td><Badge status={r.status} /></td>
                <td>{r.page_count ? `${r.pages_done}/${r.page_count}` : '—'}</td>
                <td>{r.mean_confidence != null ? `${Math.round(r.mean_confidence * 100)}%` : '—'}</td>
                <td>{r.inquiry_ref || '—'}</td>
                <td><button type="button" className="btn btn-sm btn-outline" onClick={() => setSelected(r.id)}>{r.status === 'needs_review' ? 'Review' : 'Open'}</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

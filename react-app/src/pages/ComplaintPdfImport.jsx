import { useState, useEffect, useCallback, useRef } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import { useAuth } from '../contexts/AuthContext';
import { canCreateComplaint } from '../utils/permissions';
import { extractTextFromPdf, pickPdfFile } from '../utils/pdfOcr';

const STATUS_COLORS = {
  pending: '#f59e0b',
  processing: '#3b82f6',
  extracted: '#8b5cf6',
  imported: '#10b981',
  failed: '#ef4444',
};

function FieldRow({ label, value }) {
  if (!value && value !== 0) return null;
  return (
    <div style={{ display: 'grid', gridTemplateColumns: '160px 1fr', gap: 8, fontSize: 13, padding: '4px 0' }}>
      <span style={{ color: '#64748b', fontWeight: 600 }}>{label}</span>
      <span style={{ color: '#111827', wordBreak: 'break-word' }}>{String(value)}</span>
    </div>
  );
}

export default function ComplaintPdfImport() {
  const { user } = useAuth();
  const fileRef = useRef(null);
  const ocrCacheRef = useRef(new Map());
  const [imports, setImports] = useState([]);
  const [stats, setStats] = useState({});
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [preview, setPreview] = useState(null);
  const [previewFile, setPreviewFile] = useState(null);
  const [previewError, setPreviewError] = useState('');
  const [previewLoading, setPreviewLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [selected, setSelected] = useState(null);
  const [processingId, setProcessingId] = useState(null);
  const [serverCaps, setServerCaps] = useState(null);

  const canUpload = canCreateComplaint(user);
  const previewReady = Boolean(preview?.victim_cnic && preview?.victim_name);

  const fetchData = useCallback(() => {
    Promise.all([
      api.get('/complaint-pdf-imports?per_page=30'),
      api.get('/complaint-pdf-imports/stats'),
    ]).then(([listRes, statsRes]) => {
      setImports(listRes.data?.data || listRes.data || []);
      setStats(statsRes.data || {});
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  useEffect(() => { fetchData(); }, [fetchData]);
  useEffect(() => {
    api.get('/complaint-pdf-imports/capabilities').then(r => setServerCaps(r.data || null)).catch(() => {});
  }, []);
  useEffect(() => {
    // Soft poll for status only — pause while OCR/upload is busy so UI doesn't jump
    if (uploading || previewLoading || processingId) return undefined;
    const t = setInterval(fetchData, 15000);
    return () => clearInterval(t);
  }, [fetchData, uploading, previewLoading, processingId]);

  const runBrowserOcr = async (file) => {
    const text = await extractTextFromPdf(file, (s) => setMessage(s), {
      serverRenderPage: async (pageIndex) => {
        const fd = new FormData();
        fd.append('file', file);
        fd.append('page', String(pageIndex));
        try {
          const r = await api.post('/complaint-pdf-imports/render-page', fd, { timeout: 120000 });
          return r.data?.image || null;
        } catch (err) {
          const caps = err.response?.data?.capabilities;
          if (caps && !caps.imagick && !caps.ghostscript && !caps.pdftoppm && !caps.mutool) {
            throw new Error('Server par Imagick/Ghostscript nahi. Hosting panel se Imagick PHP extension enable karwain.');
          }
          throw err;
        }
      },
    });
    ocrCacheRef.current.set(file.name, text);
    return text;
  };

  const parseOcrText = async (text, filename) => {
    const r = await api.post('/complaint-pdf-imports/preview', {
      ocr_text: text,
      filename,
    });
    return {
      extracted: r.data?.extracted || null,
      fieldsOk: r.data?.fields_ok !== false,
      error: r.data?.error || null,
    };
  };

  const ensureOcrForFile = async (filename) => {
    let ocrText = ocrCacheRef.current.get(filename);
    if (ocrText) return ocrText;

    const fromInput = Array.from(fileRef.current?.files || []).find(f => f.name === filename);
    if (fromInput) {
      setMessage(`OCR: ${filename}…`);
      return runBrowserOcr(fromInput);
    }

    setMessage(`Select ${filename} for browser OCR…`);
    const picked = await pickPdfFile(filename);
    setMessage(`OCR: ${filename}…`);
    return runBrowserOcr(picked);
  };

  const handlePreview = async (file) => {
    if (!file) return;
    setPreview(null);
    setPreviewFile(file);
    setPreviewError('');
    setPreviewLoading(true);
    setMessage('Reading PDF in browser…');
    try {
      const text = await runBrowserOcr(file);
      if (!text || text.trim().length < 20) {
        setPreviewError('OCR ne kuch text nahi parha — Re-run OCR try karein.');
        return;
      }
      const { extracted, fieldsOk, error } = await parseOcrText(text, file.name);
      setPreview(extracted);
      if (!fieldsOk) {
        setPreviewError(error || 'CNIC/name not detected — Re-run OCR try karein.');
      }
    } catch (err) {
      const partial = err.response?.data?.extracted;
      if (partial) setPreview(partial);
      const detail = err.response?.data?.error
        || err.response?.data?.message
        || err.message
        || String(err)
        || 'Unknown error';
      setPreviewError(detail.startsWith('OCR') ? detail : `OCR error: ${detail}`);
    } finally {
      setPreviewLoading(false);
      setMessage('');
    }
  };

  const processWithOcr = async (importId, filename) => {
    const ocrText = await ensureOcrForFile(filename);
    if (!ocrText || ocrText.length < 20) {
      throw new Error('OCR returned empty text. Select the PDF and try again.');
    }
    return api.post(`/complaint-pdf-imports/${importId}/process`, {
      auto_apply: 1,
      ocr_text: ocrText,
    }, { timeout: 300000 });
  };

  const handleUpload = async (e) => {
    e.preventDefault();
    const files = fileRef.current?.files;
    if (!files?.length) return;

    if (!previewReady) {
      setMessage('Pehle preview mein CNIC/name check karein — OCR complete hone ka wait karein.');
      return;
    }

    setUploading(true);
    setMessage('');
    try {
      for (const f of Array.from(files)) {
        if (!ocrCacheRef.current.has(f.name)) {
          setMessage(`OCR: ${f.name}…`);
          await runBrowserOcr(f);
        }
      }

      const fd = new FormData();
      Array.from(files).forEach(f => fd.append('files[]', f));
      Array.from(files).forEach(f => {
        const text = ocrCacheRef.current.get(f.name);
        if (text) fd.append(`ocr_texts[${f.name}]`, text);
      });
      fd.append('auto_apply', '1');
      fd.append('sync', '0');

      const r = await api.post('/complaint-pdf-imports', fd, { timeout: 300000 });
      const uploaded = r.data?.imports || [];

      for (const imp of uploaded) {
        if (imp.status === 'imported' || imp.status === 'extracted') continue;
        if (imp.status === 'failed') {
          setMessage(`Retrying ${imp.original_filename}…`);
          await processWithOcr(imp.id, imp.original_filename);
        }
      }

      setMessage('Import complete — complaint mein CNIC & PDF attachment check karein.');
      if (fileRef.current) fileRef.current.value = '';
      setPreview(null);
      setPreviewFile(null);
      ocrCacheRef.current.clear();
      fetchData();
    } catch (err) {
      const msg = err.response?.data?.message
        || err.response?.data?.import?.error_message
        || err.message
        || 'Upload/process failed';
      setMessage(msg);
      fetchData();
    } finally {
      setUploading(false);
    }
  };

  const processImport = async (row) => {
    setProcessingId(row.id);
    setMessage(`Re-processing ${row.original_filename}…`);
    try {
      await processWithOcr(row.id, row.original_filename);
      fetchData();
      setMessage('Done — complaint updated.');
    } catch (err) {
      alert(err.response?.data?.message || err.response?.data?.import?.error_message || err.message || 'Process failed');
      fetchData();
    } finally {
      setProcessingId(null);
    }
  };

  const applyImport = async (id) => {
    try {
      await api.post(`/complaint-pdf-imports/${id}/apply`);
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || 'Apply failed');
    }
  };

  if (!canUpload) {
    return <div className="page-header"><h1>PDF Import</h1><p>Access denied.</p></div>;
  }

  return (
    <div>
      <div className="page-header">
        <h1>Complaint PDF Import</h1>
        <p style={{ color: '#64748b', fontSize: 13, marginTop: 4 }}>
          PDF select karein → browser OCR (30–90 sec) → preview mein CNIC check → phir upload.
          Filename <code>261-26.PDF</code> → inquiry <code>E/261/26</code>.
        </p>
        {serverCaps && !serverCaps.imagick && !serverCaps.ghostscript && !serverCaps.pdftoppm && (
          <p style={{ color: '#b45309', fontSize: 12, marginTop: 8 }}>
            Server par Imagick/Ghostscript missing — scanned PDF ke liye hosting se <strong>Imagick PHP extension</strong> enable karwain.
          </p>
        )}
      </div>

      <div className="card" style={{ padding: 20, marginBottom: 20 }}>
        <h3 style={{ margin: '0 0 12px', fontSize: 16 }}>Upload PDF(s)</h3>
        <form onSubmit={handleUpload}>
          <input
            ref={fileRef}
            type="file"
            accept=".pdf,application/pdf"
            multiple
            className="cf-input"
            style={{ marginBottom: 12 }}
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) handlePreview(f);
            }}
          />
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
            <button
              type="submit"
              className="btn btn-primary"
              disabled={uploading || previewLoading || !previewReady}
              title={!previewReady ? 'Wait for OCR preview with CNIC' : ''}
            >
              {uploading ? 'Importing…' : previewLoading ? 'OCR running…' : 'Upload & Import'}
            </button>
            {previewFile && !previewLoading && (
              <button
                type="button"
                className="btn btn-outline"
                onClick={() => handlePreview(previewFile)}
              >
                Re-run OCR
              </button>
            )}
          </div>
        </form>
        {message && <div style={{ marginTop: 12, color: '#015C94', fontSize: 13 }}>{message}</div>}
      </div>

      {(preview || previewError || previewLoading) && (
        <div className="card" style={{ padding: 20, marginBottom: 20 }}>
          <h3 style={{ margin: '0 0 12px', fontSize: 16 }}>Preview — browser OCR</h3>
          {previewLoading && <div style={{ color: '#64748b', fontSize: 13 }}>OCR chal raha hai… 30–90 sec (page band na karein)</div>}
          {previewError && <div style={{ color: '#ef4444', fontSize: 13 }}>{previewError}</div>}
          {preview && (
            <div style={{ background: previewReady ? '#ecfdf5' : '#fef2f2', borderRadius: 10, padding: 16 }}>
              <FieldRow label="Tracking No" value={preview.tracking_no} />
              <FieldRow label="Inquiry No" value={preview.inquiry_no} />
              <FieldRow label="Complainant" value={preview.complainant_full_name || preview.victim_name} />
              <FieldRow label="CNIC" value={preview.victim_cnic} />
              <FieldRow label="Phone" value={preview.victim_phone} />
              <FieldRow label="Occupation" value={preview.victim_occupation} />
              <FieldRow label="Address" value={preview.victim_address} />
              <FieldRow label="Crime" value={preview.crime_category} />
              <FieldRow label="Amount" value={preview.amount_involved} />
              <FieldRow label="City" value={preview.city} />
            </div>
          )}
        </div>
      )}

      <div style={{ display: 'flex', gap: 12, marginBottom: 16, flexWrap: 'wrap' }}>
        {Object.entries(stats).map(([k, v]) => (
          <div key={k} className="card" style={{ padding: '10px 16px', minWidth: 100 }}>
            <div style={{ fontSize: 11, color: '#64748b', textTransform: 'capitalize' }}>{k}</div>
            <div style={{ fontSize: 20, fontWeight: 700 }}>{v}</div>
          </div>
        ))}
      </div>

      {loading ? (
        <LoadingSkeleton rows={8} />
      ) : (
        <div className="card" style={{ overflow: 'auto' }}>
          <table className="data-table" style={{ width: '100%' }}>
            <thead>
              <tr>
                <th>File</th>
                <th>Inquiry Ref</th>
                <th>Status</th>
                <th>Tracking</th>
                <th>Complainant</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {imports.length === 0 && (
                <tr><td colSpan={6} style={{ textAlign: 'center', padding: 24, color: '#9ca3af' }}>No imports yet</td></tr>
              )}
              {imports.map(row => {
                const ex = row.extracted_data || {};
                return (
                  <tr key={row.id}>
                    <td style={{ fontSize: 13 }}>{row.original_filename}</td>
                    <td>{row.inquiry_ref || ex.inquiry_no || '—'}</td>
                    <td>
                      <span style={{
                        background: (STATUS_COLORS[row.status] || '#94a3b8') + '22',
                        color: STATUS_COLORS[row.status] || '#64748b',
                        padding: '3px 8px', borderRadius: 6, fontSize: 12, fontWeight: 600,
                      }}>{row.status}</span>
                    </td>
                    <td>{ex.tracking_no || row.complaint?.tracking_no || '—'}</td>
                    <td>{ex.victim_name || ex.complainant_full_name || row.complaint?.complainant_name || '—'}</td>
                    <td>
                      <button type="button" className="btn btn-sm btn-outline" onClick={() => setSelected(row)} style={{ marginRight: 6 }}>View</button>
                      {(row.status === 'pending' || row.status === 'failed' || row.status === 'processing' || row.status === 'imported') && (
                        <button type="button" className="btn btn-sm btn-primary" disabled={processingId === row.id} onClick={() => processImport(row)} style={{ marginRight: 6 }}>
                          {processingId === row.id ? 'OCR…' : 'Re-process'}
                        </button>
                      )}
                      {row.status === 'extracted' && (
                        <button type="button" className="btn btn-sm btn-primary" onClick={() => applyImport(row.id)} style={{ marginRight: 6 }}>Apply</button>
                      )}
                      {row.complaint_id && (
                        <Link to={`/complaints/${row.complaint_id}/edit`} className="btn btn-sm btn-outline" style={{ marginLeft: 6 }}>Complaint</Link>
                      )}
                      {row.error_message && (
                        <div style={{ fontSize: 11, color: '#ef4444', marginTop: 4, maxWidth: 260 }}>
                          {row.error_message}
                          {row.status === 'failed' && (
                            <div style={{ color: '#64748b', marginTop: 2 }}>Re-process → PDF select karein → OCR</div>
                          )}
                        </div>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}

      {selected && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.45)', zIndex: 2000,
          display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 20,
        }} onClick={() => setSelected(null)}>
          <div className="card" style={{ maxWidth: 640, width: '100%', maxHeight: '80vh', overflow: 'auto', padding: 24 }} onClick={e => e.stopPropagation()}>
            <h3 style={{ marginTop: 0 }}>{selected.original_filename}</h3>
            {selected.error_message && <div style={{ color: '#ef4444', marginBottom: 12 }}>{selected.error_message}</div>}
            {selected.extracted_data && Object.entries(selected.extracted_data).map(([k, v]) => (
              k !== 'raw_text_preview' && <FieldRow key={k} label={k.replace(/_/g, ' ')} value={v} />
            ))}
            <button type="button" className="btn btn-outline" onClick={() => setSelected(null)} style={{ marginTop: 16 }}>Close</button>
          </div>
        </div>
      )}
    </div>
  );
}

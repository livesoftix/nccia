import { useRef, useState } from 'react';
import { ocrAndParsePdf } from '../utils/fillFromPdf';

export default function PdfAutoFillBar({ onFilled, hint }) {
  const inputRef = useRef(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const handleFile = async (file) => {
    if (!file) return;
    setBusy(true);
    setError('');
    setMessage('Reading PDF…');
    try {
      const { extracted, fieldsOk, error: parseErr } = await ocrAndParsePdf(file, setMessage);
      await onFilled(extracted, file);
      if (!fieldsOk) {
        setError(parseErr || 'Kuch fields nahi mili — form check karke complete karein.');
      } else {
        setMessage('PDF se information fill ho gayi. Save se pehle check kar lein.');
      }
    } catch (err) {
      setError(err.response?.data?.error || err.response?.data?.message || err.message || 'PDF read failed');
      setMessage('');
    } finally {
      setBusy(false);
      if (inputRef.current) inputRef.current.value = '';
    }
  };

  return (
    <div
      className="cf-section pdf-autofill-box"
      style={{
        marginBottom: 20,
        border: '2px dashed #0284c7',
        background: busy ? 'rgba(2, 132, 199, 0.04)' : '#f8fafc',
        borderRadius: 12,
        transition: 'all 0.2s ease',
      }}
    >
      <div className="cf-section-header" style={{ flexWrap: 'wrap', gap: 12, padding: '14px 18px' }}>
        <div className="cf-section-icon" style={{ background: 'linear-gradient(135deg, #0284c7, #0369a1)', width: 40, height: 40, minWidth: 40, borderRadius: 10 }}>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div style={{ flex: 1, minWidth: 200 }}>
          <div className="cf-section-title" style={{ fontSize: 15, display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
            <span>⚡ Fill from PDF (AI Auto-Fill)</span>
            <span style={{ fontSize: 11, background: '#0284c7', color: '#fff', padding: '2px 8px', borderRadius: 12, fontWeight: 700 }}>AI Active</span>
          </div>
          <div className="cf-section-sub" style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>
            {hint || 'Upload verification report PDF — Complainant, CNIC, Phone, Address, Offence, Amount, & Accused auto-fill.'}
          </div>
        </div>
      </div>
      <div className="cf-body" style={{ padding: '14px 18px' }}>
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
          <input
            ref={inputRef}
            type="file"
            className="cf-input"
            accept="application/pdf,.pdf"
            disabled={busy}
            onChange={(e) => handleFile(e.target.files?.[0])}
            style={{
              flex: 1,
              minWidth: 220,
              padding: '8px 12px',
              borderRadius: 8,
              border: '1.5px solid #cbd5e1',
              background: '#fff',
              cursor: busy ? 'not-allowed' : 'pointer',
            }}
          />
        </div>
        {busy && (
          <div style={{ marginTop: 10, display: 'flex', alignItems: 'center', gap: 8, color: '#0284c7', fontWeight: 600, fontSize: 13 }}>
            <span className="spinner" style={{ display: 'inline-block', width: 14, height: 14, border: '2px solid #0284c7', borderTopColor: 'transparent', borderRadius: '50%', animation: 'spin 0.8s linear infinite' }} />
            <span>{message || 'Scanning PDF document…'}</span>
          </div>
        )}
        {!busy && message && (
          <div style={{ marginTop: 10, display: 'flex', alignItems: 'center', gap: 6, color: '#16a34a', fontWeight: 600, fontSize: 13, background: '#f0fdf4', padding: '8px 12px', borderRadius: 8, border: '1px solid #bbf7d0' }}>
            <span>✓</span> {message}
          </div>
        )}
        {error && (
          <div className="cf-error" style={{ marginTop: 10, color: '#dc2626', background: '#fef2f2', padding: '8px 12px', borderRadius: 8, border: '1px solid #fecaca', fontSize: 13 }}>
            ⚠️ {error}
          </div>
        )}
      </div>
    </div>
  );
}

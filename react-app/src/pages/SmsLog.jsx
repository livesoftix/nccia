import { useState, useEffect, useCallback } from 'react';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import { useAuth } from '../contexts/AuthContext';
import { canSendManualSms } from '../utils/permissions';

const STATUS_STYLE = {
  pending: { bg: '#fff3cd', color: '#856404' },
  sent: { bg: '#e6f7e6', color: '#2e7d32' },
  failed: { bg: '#fdecec', color: '#c0392b' },
  disabled: { bg: '#ececec', color: '#6c757d' },
};

export default function SmsLog() {
  const { user } = useAuth();
  const canSend = canSendManualSms(user);
  const [data, setData] = useState({ data: [], links: null });
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({ trigger: '', status: '', phone: '' });
  const [page, setPage] = useState(1);

  const [sendOpen, setSendOpen] = useState(false);
  const [form, setForm] = useState({ phone: '', message: '', lang: 'both' });
  const [sending, setSending] = useState(false);
  const [msg, setMsg] = useState(null);

  const fetchLogs = useCallback(() => {
    setLoading(true);
    const params = new URLSearchParams({ per_page: 25, page });
    if (filters.trigger) params.set('trigger', filters.trigger);
    if (filters.status) params.set('status', filters.status);
    if (filters.phone) params.set('phone', filters.phone);
    api.get(`/sms?${params.toString()}`).then(r => setData(r.data)).catch(() => {}).finally(() => setLoading(false));
  }, [page, filters]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  const submit = async (e) => {
    e.preventDefault();
    setSending(true);
    setMsg(null);
    try {
      await api.post('/sms', form);
      setMsg({ type: 'success', text: 'SMS sent successfully' });
      setForm({ phone: '', message: '', lang: 'both' });
      setSendOpen(false);
      fetchLogs();
    } catch (err) {
      setMsg({ type: 'error', text: err.response?.data?.message || 'Failed to send SMS' });
    } finally {
      setSending(false);
    }
  };

  const filterRow = {
    display: 'flex', gap: 12, flexWrap: 'wrap',
  };
  const filterBox = {
    flex: '1 1 200px', minWidth: 180,
  };
  const inputStyle = {
    width: '100%', padding: '9px 12px', border: '1px solid #d5d9e0',
    borderRadius: 8, fontSize: 13.5, background: '#fff', color: '#1f2937',
  };
  const successBox = {
    background: '#e6f7e6', border: '1px solid #b3e6b3', color: '#2e7d32',
    padding: '10px 16px', borderRadius: 8, marginBottom: 16, fontWeight: 600, fontSize: 13,
  };
  const errorBox = {
    background: '#fdecec', border: '1px solid #f5b5b5', color: '#c0392b',
    padding: '10px 16px', borderRadius: 8, marginBottom: 16, fontWeight: 600, fontSize: 13,
  };

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">SMS Log</h1>
          <p className="page-subtitle">Bilingual SMS notifications & delivery history</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          {canSend && (
          <button className="btn btn-primary btn-sm" onClick={() => { setSendOpen(!sendOpen); setMsg(null); }}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg> Send SMS
          </button>
          )}
        </div>
      </div>

      {!canSend && (
        <div style={{ marginBottom: 16, padding: '10px 14px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13, color: '#475569' }}>
          Live SMS delivery history from complaints, verifications, and system notifications.
        </div>
      )}

      {canSend && sendOpen && (
        <div className="card" style={{ marginBottom: 20 }}>
          <div className="card-header"><span className="card-title">Manual SMS</span></div>
          <div className="card-body">
            {msg && (
              <div style={msg.type === 'success' ? successBox : errorBox}>
                {msg.text}
              </div>
            )}
            <form onSubmit={submit}>
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label required">Phone Number</label>
                  <input
                    className="cf-input"
                    type="text"
                    required
                    placeholder="+923001234567"
                    value={form.phone}
                    onChange={e => setForm({ ...form, phone: e.target.value })}
                  />
                </div>
                <div className="cf-field">
                  <label className="cf-label">Language</label>
                  <select className="cf-input" value={form.lang} onChange={e => setForm({ ...form, lang: e.target.value })}>
                    <option value="both">Both (EN + UR)</option>
                    <option value="en">English</option>
                    <option value="ur">Roman Urdu</option>
                  </select>
                </div>
              </div>
              <div className="cf-field" style={{ marginTop: 12 }}>
                <label className="cf-label required">Message</label>
                <textarea
                  className="cf-input"
                  rows={3}
                  required
                  maxLength={1600}
                  value={form.message}
                  onChange={e => setForm({ ...form, message: e.target.value })}
                  placeholder="Type the message to send..."
                />
              </div>
              <div style={{ marginTop: 14, display: 'flex', gap: 8 }}>
                <button className="btn btn-primary btn-sm" disabled={sending}>{sending ? 'Sending...' : 'Send SMS'}</button>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => setSendOpen(false)}>Cancel</button>
              </div>
            </form>
          </div>
        </div>
      )}

      <div className="card">
        <div className="card-body" style={{ padding: '16px 20px', borderBottom: '1px solid #eef0f3' }}>
          <div style={filterRow}>
            <div style={filterBox}>
              <input
                style={inputStyle}
                type="text"
                placeholder="Filter by phone..."
                value={filters.phone}
                onChange={e => { setPage(1); setFilters({ ...filters, phone: e.target.value }); }}
              />
            </div>
            <div style={filterBox}>
              <select style={inputStyle} value={filters.trigger} onChange={e => { setPage(1); setFilters({ ...filters, trigger: e.target.value }); }}>
                <option value="">All Triggers</option>
                {(data.triggers || []).map(t => <option key={t} value={t}>{t.replace(/_/g, ' ')}</option>)}
              </select>
            </div>
            <div style={filterBox}>
              <select style={inputStyle} value={filters.status} onChange={e => { setPage(1); setFilters({ ...filters, status: e.target.value }); }}>
                <option value="">All Statuses</option>
                {(data.statuses || []).map(s => <option key={s} value={s}>{s}</option>)}
              </select>
            </div>
          </div>
        </div>

        <div className="card-body" style={{ padding: 0 }}>
          {loading ? <LoadingSkeleton type="table" columns={5} rows={10} /> : (
            <div className="table-responsive">
              <table className="data-table">
                <thead>
                  <tr><th>#</th><th>Phone</th><th>Message</th><th>Trigger</th><th>Status</th><th>Sent At</th></tr>
                </thead>
                <tbody>
                  {data.data.map((l, i) => {
                    const st = STATUS_STYLE[l.status] || STATUS_STYLE.pending;
                    return (
                      <tr key={l.id}>
                        <td><span className="table-id">#{l.id}</span></td>
                        <td>{l.phone}</td>
                        <td style={{ fontSize: 12, maxWidth: 320 }}>
                          <div style={{ maxHeight: 60, overflow: 'hidden' }}>{l.message}</div>
                          <span style={{ display: 'inline-block', marginTop: 4, padding: '1px 7px', borderRadius: 10, background: '#ececec', color: '#6c757d', fontSize: 10, fontWeight: 700 }}>
                            {l.lang === 'ur' ? 'UR' : 'EN'}
                          </span>
                        </td>
                        <td><span className="badge badge-info">{l.trigger?.replace(/_/g, ' ')}</span></td>
                        <td>
                          <span style={{ display: 'inline-block', padding: '3px 10px', borderRadius: 12, fontSize: 11, fontWeight: 700, background: st.bg, color: st.color }}>
                            {l.status}
                          </span>
                        </td>
                        <td style={{ fontSize: 12, color: '#6c757d' }}>
                          {l.sent_at ? new Date(l.sent_at).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '-'}
                        </td>
                      </tr>
                    );
                  })}
                  {data.data.length === 0 && <tr><td colSpan={6} style={{ textAlign: 'center', padding: 24, color: '#6c757d' }}>No SMS logs yet</td></tr>}
                </tbody>
              </table>
            </div>
          )}
        </div>

        {data.links && (
          <div className="card-body" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <span style={{ fontSize: 13, color: '#6c757d' }}>
              Page {data.current_page || page} of {data.last_page || 1}
            </span>
            <div style={{ display: 'flex', gap: 8 }}>
              <button className="btn btn-outline btn-sm" disabled={!data.prev_page_url || page <= 1} onClick={() => setPage(p => p - 1)}>Previous</button>
              <button className="btn btn-outline btn-sm" disabled={!data.next_page_url} onClick={() => setPage(p => p + 1)}>Next</button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

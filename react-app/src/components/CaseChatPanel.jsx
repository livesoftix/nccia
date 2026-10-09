import { useState, useEffect, useRef, useCallback } from 'react';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';

const PRIMARY = '#015C94';

function formatTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDay(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const today = new Date();
  const yest = new Date();
  yest.setDate(today.getDate() - 1);
  if (d.toDateString() === today.toDateString()) return 'Today';
  if (d.toDateString() === yest.toDateString()) return 'Yesterday';
  return d.toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
}

function initialsOf(name) {
  return (name || '?').split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

const ROLE_BADGES = {
  admin: { label: 'Admin', color: '#9b2226' },
  circle_incharge: { label: 'Circle Incharge', color: '#B7791F' },
  enquiry_officer: { label: 'Enquiry Officer', color: '#015C94' },
  investigation_officer: { label: 'Investigation Officer', color: '#2d6a4f' },
  operator: { label: 'Front Desk Officer', color: '#4a5568' },
  verification_officer: { label: 'Verification Officer', color: '#5a189a' },
  moharrar: { label: 'Moharrar', color: '#319795' },
  reader_branch: { label: 'Reader', color: '#805ad5' },
  ad_legal: { label: 'AD Legal', color: '#c05621' },
  dd_legal: { label: 'DD Legal', color: '#c05621' },
  additional_director: { label: 'Addl Director', color: '#2b6cb0' },
  director_general: { label: 'DG', color: '#1a202c' },
};

export default function CaseChatPanel({
  type = 'enquiry',
  id,
  caseNumber = '',
  title = '',
  officers = [],
  compact = false,
}) {
  const { user } = useAuth();
  const [messages, setMessages] = useState([]);
  const [text, setText] = useState('');
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const scrollBottomRef = useRef(null);

  const loadThread = useCallback(async (silent = false) => {
    if (!id) return;
    if (!silent) setLoading(true);
    try {
      const res = await api.get('/messages/case-thread', {
        params: { type, id, case_number: caseNumber },
      });
      const list = Array.isArray(res.data) ? res.data : (res.data?.data || []);
      // Reverse to chronological order (oldest first, newest at bottom)
      const chrono = [...list].sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));
      setMessages(chrono);
    } catch {
      // silent catch
    } finally {
      if (!silent) setLoading(false);
    }
  }, [type, id, caseNumber]);

  useEffect(() => {
    loadThread();
  }, [loadThread]);

  useEffect(() => {
    const timer = setInterval(() => {
      loadThread(true);
    }, 4000);
    return () => clearInterval(timer);
  }, [loadThread]);

  useEffect(() => {
    if (scrollBottomRef.current) {
      scrollBottomRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [messages]);

  const sendMessage = async (e) => {
    if (e) e.preventDefault();
    const body = text.trim();
    if (!body || sending || !id) return;
    setSending(true);
    try {
      const res = await api.post('/messages/case-message', {
        type,
        id,
        case_number: caseNumber,
        message: body,
      });
      setText('');
      setMessages(prev => [...prev, res.data]);
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to send message');
    } finally {
      setSending(false);
    }
  };

  return (
    <div style={{
      display: 'flex',
      flexDirection: 'column',
      height: compact ? 440 : 540,
      background: '#fff',
      borderRadius: 10,
      border: '1px solid #e2e8f0',
      overflow: 'hidden',
      boxShadow: '0 2px 10px rgba(0,0,0,0.04)',
    }}>
      {/* Header */}
      <div style={{
        padding: '12px 18px',
        background: '#f8fafc',
        borderBottom: '1px solid #e2e8f0',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: 10,
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <div style={{
            width: 38,
            height: 38,
            borderRadius: 8,
            background: PRIMARY,
            color: '#fff',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: 18,
          }}>
            💬
          </div>
          <div>
            <div style={{ fontWeight: 700, fontSize: 14, color: '#1e293b', display: 'flex', alignItems: 'center', gap: 8 }}>
              <span>Case Discussion: #{caseNumber || `${type.toUpperCase()}-${id}`}</span>
              <span className="badge" style={{ background: '#dbeafe', color: '#1e40af', fontSize: 11, fontWeight: 700 }}>
                {type === 'enquiry' ? 'Enquiry Room' : 'DAC Case Room'}
              </span>
            </div>
            <div style={{ fontSize: 12, color: '#64748b', marginTop: 2 }}>
              {title ? `${title} · ` : ''}Visible to all assigned officers & supervisors working on this case
            </div>
          </div>
        </div>

        {officers && officers.length > 0 && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap' }}>
            <span style={{ fontSize: 11, color: '#64748b', fontWeight: 600, textTransform: 'uppercase' }}>Team:</span>
            {officers.map((off, idx) => (
              <span
                key={off.id || idx}
                style={{
                  background: '#f1f5f9',
                  border: '1px solid #cbd5e1',
                  borderRadius: 14,
                  padding: '2px 8px',
                  fontSize: 11,
                  color: '#334155',
                  fontWeight: 600,
                }}
              >
                👤 {off.name} {off.role_label ? `(${off.role_label})` : ''}
              </span>
            ))}
          </div>
        )}
      </div>

      {/* Message Feed */}
      <div style={{
        flex: 1,
        overflowY: 'auto',
        padding: '16px 20px',
        background: '#f8fafc',
        display: 'flex',
        flexDirection: 'column',
      }}>
        {loading && messages.length === 0 ? (
          <div style={{ textAlign: 'center', padding: 40, color: '#94a3b8', fontSize: 13 }}>
            Loading case discussions…
          </div>
        ) : messages.length === 0 ? (
          <div style={{ textAlign: 'center', margin: 'auto', color: '#94a3b8', maxWidth: 360 }}>
            <div style={{ fontSize: 32, marginBottom: 8 }}>💬</div>
            <div style={{ fontWeight: 600, fontSize: 14, color: '#475569' }}>No messages in this case yet</div>
            <div style={{ fontSize: 12, marginTop: 4 }}>
              Post updates, questions, investigation findings, or notes for other team members working on Case #{caseNumber || id}.
            </div>
          </div>
        ) : (
          messages.map((m, i) => {
            const mine = Number(m.sender_id) === Number(user?.id);
            const showDay = i === 0 || formatDay(messages[i - 1].created_at) !== formatDay(m.created_at);
            const roleBadge = ROLE_BADGES[m.sender_role] || { label: m.sender_role || 'Officer', color: '#475569' };

            return (
              <div key={m.id || i} style={{ marginBottom: 12 }}>
                {showDay && (
                  <div style={{ textAlign: 'center', margin: '14px 0 10px' }}>
                    <span style={{ background: '#e2e8f0', color: '#64748b', fontSize: 11, padding: '3px 10px', borderRadius: 10, fontWeight: 600 }}>
                      {formatDay(m.created_at)}
                    </span>
                  </div>
                )}
                <div style={{ display: 'flex', justifyContent: mine ? 'flex-end' : 'flex-start', gap: 8, alignItems: 'flex-start' }}>
                  {!mine && (
                    <div style={{
                      width: 32,
                      height: 32,
                      borderRadius: '50%',
                      background: PRIMARY,
                      color: '#fff',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      fontWeight: 700,
                      fontSize: 11,
                      flexShrink: 0,
                    }}>
                      {initialsOf(m.sender_name)}
                    </div>
                  )}

                  <div style={{ maxWidth: '78%' }}>
                    {!mine && (
                      <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4 }}>
                        <span style={{ fontWeight: 700, fontSize: 12, color: '#1e293b' }}>{m.sender_name}</span>
                        <span style={{
                          background: roleBadge.color,
                          color: '#fff',
                          fontSize: 10,
                          padding: '1px 6px',
                          borderRadius: 6,
                          fontWeight: 600,
                        }}>
                          {roleBadge.label}
                        </span>
                        {m.sender_designation && (
                          <span style={{ fontSize: 11, color: '#64748b' }}>({m.sender_designation})</span>
                        )}
                      </div>
                    )}

                    <div style={{
                      background: mine ? PRIMARY : '#ffffff',
                      color: mine ? '#ffffff' : '#1e293b',
                      padding: '10px 14px',
                      borderRadius: 14,
                      borderBottomRightRadius: mine ? 3 : 14,
                      borderBottomLeftRadius: mine ? 14 : 3,
                      boxShadow: '0 1px 3px rgba(0,0,0,0.06)',
                      border: mine ? 'none' : '1px solid #e2e8f0',
                      whiteSpace: 'pre-wrap',
                      wordBreak: 'break-word',
                      fontSize: 13.5,
                      lineHeight: 1.45,
                    }}>
                      {m.message}
                    </div>

                    <div style={{ display: 'flex', justifyContent: mine ? 'flex-end' : 'flex-start', marginTop: 3, padding: '0 2px' }}>
                      <span style={{ fontSize: 10.5, color: '#94a3b8' }}>
                        {formatTime(m.created_at)}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            );
          })
        )}
        <div ref={scrollBottomRef} />
      </div>

      {/* Input box */}
      <form
        onSubmit={sendMessage}
        style={{
          padding: '10px 14px',
          background: '#fff',
          borderTop: '1px solid #e2e8f0',
          display: 'flex',
          gap: 10,
          alignItems: 'center',
        }}
      >
        <textarea
          value={text}
          onChange={e => setText(e.target.value)}
          placeholder={`Type an update or note regarding Case #${caseNumber || id}…`}
          rows={1}
          className="cf-input"
          style={{
            flex: 1,
            resize: 'none',
            minHeight: 38,
            maxHeight: 100,
            padding: '8px 12px',
            fontSize: 13,
          }}
          onKeyDown={e => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              sendMessage(e);
            }
          }}
        />
        <button
          type="submit"
          className="btn btn-primary"
          disabled={sending || !text.trim()}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 6,
            height: 38,
            padding: '0 16px',
            fontSize: 13,
            fontWeight: 600,
          }}
        >
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
          {sending ? 'Posting…' : 'Send'}
        </button>
      </form>
    </div>
  );
}

import { useState, useEffect, useRef, useCallback } from 'react';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import LoadingSkeleton from '../components/LoadingSkeleton';
import CaseChatPanel from '../components/CaseChatPanel';

const PRIMARY = '#015C94';

function timeAgo(iso) {
  if (!iso) return '';
  const diff = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'Just now';
  if (mins < 60) return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  const days = Math.floor(hrs / 24);
  if (days < 7) return `${days}d ago`;
  return new Date(iso).toLocaleDateString();
}

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

function sortMessagesNewestFirst(list) {
  return [...(list || [])].sort((a, b) => {
    const ta = a?.created_at ? new Date(a.created_at).getTime() : 0;
    const tb = b?.created_at ? new Date(b.created_at).getTime() : 0;
    if (tb !== ta) return tb - ta;
    return (b?.id || 0) - (a?.id || 0);
  });
}

function initialsOf(name) {
  return (name || '?').split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

export default function Chat() {
  const { user } = useAuth();
  const [tabMode, setTabMode] = useState('cases'); // 'cases' or 'direct'

  // Direct chat state
  const [conversations, setConversations] = useState([]);
  const [contacts, setContacts] = useState([]);
  const [activeId, setActiveId] = useState(null);
  const [messages, setMessages] = useState([]);
  const [text, setText] = useState('');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [threadLoading, setThreadLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const threadTopRef = useRef(null);
  const meId = user?.id;

  // Case chat state
  const [caseList, setCaseList] = useState([]);
  const [selectedCase, setSelectedCase] = useState(null);
  const [caseSearch, setCaseSearch] = useState('');

  const refreshConversations = useCallback(() => {
    api.get('/messages/conversations').then(r => setConversations(r.data || [])).catch(() => {});
  }, []);

  const refreshCases = useCallback(() => {
    api.get('/messages/cases').then(r => {
      const list = Array.isArray(r.data) ? r.data : [];
      setCaseList(list);
      // Auto-select first case if none selected
      if (!selectedCase && list.length > 0) {
        setSelectedCase(list[0]);
      }
    }).catch(() => {});
  }, [selectedCase]);

  const loadThread = useCallback((otherId) => {
    if (!otherId || otherId === meId) return;
    setThreadLoading(true);
    api.get(`/messages/conversations/${otherId}`).then(r => {
      setMessages(sortMessagesNewestFirst(r.data));
    }).catch(() => {}).finally(() => setThreadLoading(false));
  }, [meId]);

  useEffect(() => {
    api.get('/messages/contacts').then(r => {
      const cList = r.data || [];
      setContacts(cList);
      if (cList.length > 0 && !activeId) {
        setActiveId(cList[0].id);
        loadThread(cList[0].id);
      }
    }).catch(() => {});
    refreshConversations();
    refreshCases();
    setLoading(false);
  }, [refreshConversations, refreshCases, loadThread]);

  useEffect(() => {
    const timer = setInterval(() => {
      if (tabMode === 'direct') {
        refreshConversations();
        if (activeId) loadThread(activeId);
      } else {
        refreshCases();
      }
    }, 5000);
    return () => clearInterval(timer);
  }, [refreshConversations, refreshCases, loadThread, activeId, tabMode]);

  useEffect(() => {
    if (threadTopRef.current) {
      threadTopRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }, [messages, activeId]);

  const selectConversation = (otherId) => {
    setActiveId(otherId);
    setMessages([]);
    loadThread(otherId);
  };

  const sendMessage = async (e) => {
    e.preventDefault();
    const body = text.trim();
    if (!body || !activeId || sending) return;
    setSending(true);
    try {
      const r = await api.post('/messages', { receiver_id: activeId, message: body });
      setMessages(prev => sortMessagesNewestFirst([{
        id: r.data.id,
        sender_id: r.data.sender_id,
        message: r.data.message,
        is_read: false,
        created_at: r.data.created_at,
      }, ...prev]));
      setText('');
      refreshConversations();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to send message');
    } finally {
      setSending(false);
    }
  };

  const activeContact = contacts.find(c => c.id === activeId) || conversations.find(c => c.user?.id === activeId)?.user || null;
  const totalUnread = conversations.reduce((sum, c) => sum + (c.unread || 0), 0);

  const markAllRead = async () => {
    try {
      await api.post('/messages/read-all');
      refreshConversations();
      if (activeId) loadThread(activeId);
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to mark messages as read');
    }
  };

  const filtered = contacts.filter(c => {
    if (!search) return true;
    const q = search.toLowerCase();
    return (c.name || '').toLowerCase().includes(q) || (c.designation || '').toLowerCase().includes(q) || (c.role || '').toLowerCase().includes(q);
  });

  const convFor = (id) => conversations.find(c => c.user?.id === id);

  const sortedContacts = [...filtered].sort((a, b) => {
    const convA = convFor(a.id);
    const convB = convFor(b.id);
    const unreadA = convA?.unread || 0;
    const unreadB = convB?.unread || 0;
    if (unreadB !== unreadA) return unreadB - unreadA;
    const timeA = convA?.last_time ? new Date(convA.last_time).getTime() : 0;
    const timeB = convB?.last_time ? new Date(convB.last_time).getTime() : 0;
    if (timeB !== timeA) return timeB - timeA;
    return (a.name || '').localeCompare(b.name || '');
  });

  const filteredCases = caseList.filter(c => {
    if (!caseSearch) return true;
    const q = caseSearch.toLowerCase();
    return (
      (c.case_number || '').toLowerCase().includes(q) ||
      (c.reference_no || '').toLowerCase().includes(q) ||
      (c.complainant_name || '').toLowerCase().includes(q) ||
      (c.category || '').toLowerCase().includes(q) ||
      (c.officers || []).some(o => (o.name || '').toLowerCase().includes(q))
    );
  });

  if (loading) {
    return (
      <div className="page-header">
        <h1>Messages & Collaboration</h1>
        <LoadingSkeleton rows={6} />
      </div>
    );
  }

  return (
    <div style={{ width: '100%' }}>
      <div className="page-header" style={{ marginBottom: 16 }}>
        <div>
          <h1 className="page-title" style={{ fontSize: 22, fontWeight: 700, color: '#0f172a' }}>Collaboration & Case Discussions</h1>
          <p className="page-subtitle" style={{ color: '#64748b', fontSize: 13, marginTop: 2 }}>
            Chat with team members working on specific enquiries/cases or send direct messages
          </p>
        </div>
      </div>

      <div className="card" style={{ padding: 0, overflow: 'hidden', border: '1px solid #e2e8f0', borderRadius: 12, boxShadow: '0 4px 16px rgba(0,0,0,0.04)', background: '#fff' }}>
        {/* Navigation Tabs Header */}
        <div style={{
          display: 'flex',
          borderBottom: '1px solid #e2e8f0',
          background: '#f8fafc',
          padding: '10px 16px',
          gap: 12,
          alignItems: 'center',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
        }}>
          <div style={{ display: 'flex', gap: 10 }}>
            <button
              type="button"
              onClick={() => setTabMode('cases')}
              style={{
                background: tabMode === 'cases' ? PRIMARY : '#ffffff',
                color: tabMode === 'cases' ? '#ffffff' : '#334155',
                border: tabMode === 'cases' ? 'none' : '1px solid #cbd5e1',
                borderRadius: 8,
                padding: '8px 18px',
                fontSize: 13,
                fontWeight: 700,
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                boxShadow: tabMode === 'cases' ? '0 2px 6px rgba(1,92,148,0.25)' : 'none',
                transition: 'all 0.15s',
              }}
            >
              <span>📁 Case & Enquiry Discussions</span>
              <span style={{
                background: tabMode === 'cases' ? 'rgba(255,255,255,0.25)' : '#e2e8f0',
                padding: '2px 8px',
                borderRadius: 10,
                fontSize: 11,
                fontWeight: 700,
              }}>
                {caseList.length}
              </span>
            </button>

            <button
              type="button"
              onClick={() => setTabMode('direct')}
              style={{
                background: tabMode === 'direct' ? PRIMARY : '#ffffff',
                color: tabMode === 'direct' ? '#ffffff' : '#334155',
                border: tabMode === 'direct' ? 'none' : '1px solid #cbd5e1',
                borderRadius: 8,
                padding: '8px 18px',
                fontSize: 13,
                fontWeight: 700,
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                boxShadow: tabMode === 'direct' ? '0 2px 6px rgba(1,92,148,0.25)' : 'none',
                transition: 'all 0.15s',
              }}
            >
              <span>👤 Direct Messages (1-on-1)</span>
              {totalUnread > 0 && (
                <span style={{
                  background: '#ef4444',
                  color: '#fff',
                  padding: '2px 8px',
                  borderRadius: 10,
                  fontSize: 11,
                  fontWeight: 700,
                }}>
                  {totalUnread}
                </span>
              )}
            </button>
          </div>
        </div>

        {/* CASE DISCUSSIONS MODE */}
        {tabMode === 'cases' && (
          <div style={{ display: 'flex', height: 'calc(100vh - 210px)', minHeight: 560 }}>
            {/* Left Case List Sidebar */}
            <div style={{
              width: 360,
              minWidth: 300,
              borderRight: '1px solid #e5e7eb',
              display: 'flex',
              flexDirection: 'column',
              background: '#f9fafb',
            }}>
              <div style={{ padding: '12px 14px', borderBottom: '1px solid #e5e7eb' }}>
                <input
                  type="search"
                  placeholder="Search Case / Enquiry #, name…"
                  value={caseSearch}
                  onChange={e => setCaseSearch(e.target.value)}
                  className="cf-input"
                  style={{ width: '100%', fontSize: 13, padding: '8px 12px' }}
                />
              </div>

              <div style={{ flex: 1, overflowY: 'auto' }}>
                {filteredCases.length === 0 && (
                  <div style={{ padding: 30, textAlign: 'center', color: '#9ca3af', fontSize: 13 }}>
                    No matching cases or enquiries found.
                  </div>
                )}
                {filteredCases.map(c => {
                  const isSel = selectedCase?.type === c.type && selectedCase?.id === c.id;
                  return (
                    <div
                      key={`${c.type}-${c.id}`}
                      onClick={() => setSelectedCase(c)}
                      style={{
                        padding: '12px 14px',
                        cursor: 'pointer',
                        borderBottom: '1px solid #f0f1f3',
                        background: isSel ? '#eaf2f8' : '#ffffff',
                        borderLeft: isSel ? `4px solid ${PRIMARY}` : '4px solid transparent',
                        transition: 'background 0.15s',
                      }}
                      onMouseEnter={e => { if (!isSel) e.currentTarget.style.background = '#f3f4f6'; }}
                      onMouseLeave={e => { if (!isSel) e.currentTarget.style.background = '#ffffff'; }}
                    >
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 6, marginBottom: 3 }}>
                        <span style={{ fontWeight: 700, fontSize: 13.5, color: '#1e293b' }}>
                          #{c.case_number}
                        </span>
                        {c.last_time && (
                          <span style={{ fontSize: 11, color: '#94a3b8', flexShrink: 0 }}>
                            {timeAgo(c.last_time)}
                          </span>
                        )}
                      </div>

                      <div style={{ fontSize: 12.5, color: '#334155', fontWeight: 600, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', marginBottom: 4 }}>
                        {c.complainant_name} · <span style={{ fontWeight: 400, color: '#64748b' }}>{c.category}</span>
                      </div>

                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 6 }}>
                        <span style={{ fontSize: 12, color: c.has_messages ? '#1e293b' : '#94a3b8', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', flex: 1 }}>
                          {c.last_message ? `💬 ${c.last_message}` : 'No notes yet'}
                        </span>
                        <span style={{
                          background: c.type === 'enquiry' ? 'rgba(1,92,148,0.12)' : 'rgba(45,106,79,0.12)',
                          color: c.type === 'enquiry' ? '#015C94' : '#2d6a4f',
                          fontSize: 10,
                          fontWeight: 700,
                          padding: '1px 6px',
                          borderRadius: 4,
                          textTransform: 'uppercase',
                        }}>
                          {c.type}
                        </span>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Right Chat Panel */}
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', background: '#fff' }}>
              {selectedCase ? (
                <div style={{ height: '100%', padding: 0 }}>
                  <CaseChatPanel
                    type={selectedCase.type}
                    id={selectedCase.id}
                    caseNumber={selectedCase.case_number}
                    title={selectedCase.complainant_name}
                    officers={selectedCase.officers}
                    compact={false}
                  />
                </div>
              ) : (
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', color: '#9ca3af' }}>
                  <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" style={{ opacity: 0.4 }}>
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                  </svg>
                  <p style={{ marginTop: 12, fontSize: 14, fontWeight: 500 }}>Select a case or enquiry to view discussion</p>
                </div>
              )}
            </div>
          </div>
        )}

        {/* DIRECT MESSAGES (1-ON-1) MODE */}
        {tabMode === 'direct' && (
          <div style={{ display: 'flex', height: 'calc(100vh - 210px)', minHeight: 560 }}>
            {/* Contact sidebar */}
            <div style={{ width: 340, minWidth: 280, borderRight: '1px solid #e5e7eb', display: 'flex', flexDirection: 'column', background: '#f9fafb' }}>
              <div style={{ padding: '12px 14px', borderBottom: '1px solid #e5e7eb', display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 8 }}>
                <span style={{ fontWeight: 700, fontSize: 14, color: '#374151' }}>
                  Officers
                  {totalUnread > 0 && <span style={{ marginLeft: 8, background: '#ef4444', color: '#fff', borderRadius: 10, fontSize: 11, minWidth: 18, height: 18, display: 'inline-flex', alignItems: 'center', justifyContent: 'center', padding: '0 5px' }}>{totalUnread}</span>}
                </span>
                <button
                  type="button"
                  onClick={markAllRead}
                  disabled={totalUnread === 0}
                  title="Mark all conversations as read"
                  style={{
                    background: totalUnread > 0 ? '#015C94' : '#e5e7eb',
                    color: totalUnread > 0 ? '#fff' : '#9ca3af',
                    border: 'none', borderRadius: 6, cursor: totalUnread > 0 ? 'pointer' : 'not-allowed',
                    fontSize: 12, fontWeight: 600, padding: '5px 10px', display: 'flex', alignItems: 'center', gap: 5,
                  }}
                >
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                  Mark all read
                </button>
              </div>
              <div style={{ padding: '10px 14px', borderBottom: '1px solid #e5e7eb' }}>
                <input
                  type="search"
                  placeholder="Search officers…"
                  value={search}
                  onChange={e => setSearch(e.target.value)}
                  className="cf-input"
                  style={{ width: '100%', fontSize: 13, padding: '8px 12px' }}
                />
              </div>
              <div style={{ flex: 1, overflowY: 'auto' }}>
                {filtered.length === 0 && (
                  <div style={{ padding: 30, textAlign: 'center', color: '#9ca3af', fontSize: 13 }}>No contacts found</div>
                )}
                {sortedContacts.map(c => {
                  const conv = convFor(c.id);
                  const unread = conv?.unread || 0;
                  const isActive = activeId === c.id;
                  return (
                    <div
                      key={c.id}
                      onClick={() => selectConversation(c.id)}
                      style={{
                        padding: '12px 14px',
                        cursor: 'pointer',
                        borderBottom: '1px solid #f0f1f3',
                        background: isActive ? '#eaf2f8' : 'transparent',
                        borderLeft: isActive ? `4px solid ${PRIMARY}` : '4px solid transparent',
                        display: 'flex',
                        alignItems: 'center',
                        gap: 12,
                        transition: 'background 0.15s',
                      }}
                      onMouseEnter={e => { if (!isActive) e.currentTarget.style.background = '#f3f4f6'; }}
                      onMouseLeave={e => { if (!isActive) e.currentTarget.style.background = 'transparent'; }}
                    >
                      <div style={{
                        width: 40, height: 40, borderRadius: '50%', flexShrink: 0,
                        background: PRIMARY, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
                        fontWeight: 700, fontSize: 14, boxShadow: '0 2px 5px rgba(1,92,148,0.25)',
                      }}>
                        {initialsOf(c.name)}
                      </div>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 6 }}>
                          <span style={{ fontWeight: 600, fontSize: 13.5, color: '#1e293b', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.name}</span>
                          {conv?.last_time && <span style={{ fontSize: 11, color: '#94a3b8', flexShrink: 0 }}>{timeAgo(conv.last_time)}</span>}
                        </div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 6, marginTop: 2 }}>
                          <span style={{ fontSize: 12, color: unread ? PRIMARY : '#64748b', fontWeight: unread ? 600 : 400, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            {conv?.last_message || (c.designation || c.role || '').replace(/_/g, ' ')}
                          </span>
                          {unread > 0 && (
                            <span style={{
                              background: '#ef4444', color: '#fff', borderRadius: 10, fontSize: 11,
                              minWidth: 18, height: 18, display: 'flex', alignItems: 'center', justifyContent: 'center',
                              padding: '0 5px', flexShrink: 0, fontWeight: 700,
                            }}>{unread}</span>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Thread area */}
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', background: '#fff' }}>
              {!activeContact ? (
                <div style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', color: '#9ca3af' }}>
                  <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" style={{ opacity: 0.4 }}>
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                  </svg>
                  <p style={{ marginTop: 12, fontSize: 14, fontWeight: 500 }}>Select a contact to start messaging</p>
                </div>
              ) : (
                <>
                  <div style={{ padding: '12px 18px', borderBottom: '1px solid #e5e7eb', display: 'flex', alignItems: 'center', gap: 12, background: '#f8fafc' }}>
                    <div style={{
                      width: 40, height: 40, borderRadius: '50%', flexShrink: 0,
                      background: PRIMARY, color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
                      fontWeight: 700, fontSize: 14, boxShadow: '0 2px 5px rgba(1,92,148,0.25)',
                    }}>
                      {initialsOf(activeContact.name)}
                    </div>
                    <div>
                      <div style={{ fontWeight: 700, fontSize: 14.5, color: '#0f172a' }}>{activeContact.name}</div>
                      <div style={{ fontSize: 12, color: '#64748b' }}>{(activeContact.designation || activeContact.role || 'Officer').replace(/_/g, ' ')}</div>
                    </div>
                  </div>

                  <div style={{ flex: 1, overflowY: 'auto', padding: '18px 22px', background: '#f1f5f9' }}>
                    <div ref={threadTopRef} />
                    {threadLoading && <div style={{ textAlign: 'center', color: '#94a3b8', fontSize: 13, padding: 20 }}>Loading conversation…</div>}
                    {!threadLoading && messages.length === 0 && (
                      <div style={{ textAlign: 'center', color: '#94a3b8', fontSize: 13, padding: 40 }}>No messages yet. Send a message to start conversation!</div>
                    )}
                    {!threadLoading && messages.map((m, i) => {
                      const mine = Number(m.sender_id) === Number(meId);
                      const showDay = i === 0 || formatDay(messages[i - 1].created_at) !== formatDay(m.created_at);
                      return (
                        <div key={m.id}>
                          {showDay && (
                            <div style={{ textAlign: 'center', margin: '14px 0' }}>
                              <span style={{ background: '#e2e8f0', color: '#475569', fontSize: 11, fontWeight: 600, padding: '4px 12px', borderRadius: 12 }}>{formatDay(m.created_at)}</span>
                            </div>
                          )}
                          <div style={{ display: 'flex', justifyContent: mine ? 'flex-end' : 'flex-start', marginBottom: 4 }}>
                            <div style={{
                              maxWidth: '70%',
                              background: mine ? PRIMARY : '#ffffff',
                              color: mine ? '#ffffff' : '#0f172a',
                              padding: '10px 15px',
                              borderRadius: 16,
                              borderBottomRightRadius: mine ? 4 : 16,
                              borderBottomLeftRadius: mine ? 16 : 4,
                              boxShadow: mine ? '0 2px 6px rgba(1,92,148,0.25)' : '0 2px 6px rgba(0,0,0,0.06)',
                              whiteSpace: 'pre-wrap',
                              wordBreak: 'break-word',
                              fontSize: 13.5,
                              lineHeight: 1.5,
                            }}>
                              {m.message}
                            </div>
                          </div>
                          <div style={{ display: 'flex', justifyContent: mine ? 'flex-end' : 'flex-start', marginBottom: 12 }}>
                            <span style={{ fontSize: 11, color: '#94a3b8', padding: '0 4px' }}>
                              {formatTime(m.created_at)}
                              {mine && (m.is_read ? ' · Read' : ' · Sent')}
                            </span>
                          </div>
                        </div>
                      );
                    })}
                  </div>

                  <form onSubmit={sendMessage} style={{ padding: '12px 18px', borderTop: '1px solid #e5e7eb', display: 'flex', gap: 10, background: '#fff', alignItems: 'center' }}>
                    <textarea
                      value={text}
                      onChange={e => setText(e.target.value)}
                      placeholder="Type your message here (Press Enter to send)…"
                      rows={1}
                      className="cf-input"
                      style={{ flex: 1, resize: 'none', minHeight: 42, maxHeight: 120, padding: '10px 14px', fontSize: 13.5, borderRadius: 8 }}
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
                      style={{
                        background: PRIMARY, color: '#fff', padding: '10px 20px', fontWeight: 700, fontSize: 13,
                        borderRadius: 8, border: 'none', display: 'flex', alignItems: 'center', gap: 6,
                        boxShadow: '0 2px 8px rgba(1,92,148,0.3)', height: 42, cursor: text.trim() ? 'pointer' : 'default',
                      }}
                      disabled={sending || !text.trim()}
                    >
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                      {sending ? 'Sending…' : 'Send'}
                    </button>
                  </form>
                </>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

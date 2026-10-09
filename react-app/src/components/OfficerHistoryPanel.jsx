import { useEffect, useState } from 'react';
import api from '../api';
import { formatDisplayDateTime } from '../utils/datetime';

function SnapshotDetails({ snap }) {
  if (!snap || typeof snap !== 'object') return null;
  const rows = [
    ['Status', snap.status],
    ['Recommendation', snap.recommendation],
    ['Report / CFR', snap.report_text || snap.cfr_summary],
    ['Closure reason', snap.closure_reason],
    ['Priority', snap.priority_type],
    ['FIR No', snap.fir_no],
    ['Enquiry No', snap.enquiry_number],
    ['Submitted at', snap.submitted_at ? formatDisplayDateTime(snap.submitted_at) : null],
    ['Activities logged', snap.activities_count],
    ['Snapshot taken', snap.captured_at ? formatDisplayDateTime(snap.captured_at) : null],
  ].filter(([, v]) => v !== null && v !== undefined && v !== '');

  if (!rows.length) return <div style={{ fontSize: 12, color: '#888' }}>No work details captured for this tenure.</div>;

  return (
    <div style={{ marginTop: 8, padding: 10, background: '#f7f9fc', borderRadius: 8, border: '1px solid #e5eaf0' }}>
      <div style={{ fontSize: 11, fontWeight: 700, color: '#015C94', marginBottom: 6, textTransform: 'uppercase' }}>Work saved at hand-over</div>
      {rows.map(([label, val]) => (
        <div key={label} style={{ display: 'grid', gridTemplateColumns: '140px 1fr', gap: 8, fontSize: 12, marginBottom: 4 }}>
          <span style={{ color: '#64748b', fontWeight: 600 }}>{label}</span>
          <span style={{ color: '#1f2937', whiteSpace: 'pre-wrap', wordBreak: 'break-word' }}>{String(val)}</span>
        </div>
      ))}
    </div>
  );
}

/**
 * @param {{ endpoint: string }} props  e.g. /verifications/12/officer-history
 */
export default function OfficerHistoryPanel({ endpoint }) {
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [openId, setOpenId] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!endpoint) return;
    setLoading(true);
    setError('');
    api.get(endpoint)
      .then(r => setRows(r.data.data || r.data || []))
      .catch(() => setError('Could not load officer history'))
      .finally(() => setLoading(false));
  }, [endpoint]);

  if (!endpoint) return null;

  return (
    <div className="cf-section" style={{ marginTop: 16 }}>
      <div className="cf-section-header">
        <div className="cf-section-icon" style={{ background: '#7c3aed' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
          </svg>
        </div>
        <div>
          <div className="cf-section-title">Officer Assignment History</div>
          <div className="cf-section-sub">Who worked on this file, with date/time — used if officer is changed</div>
        </div>
      </div>
      <div className="cf-body">
        {loading && <div style={{ fontSize: 13, color: '#888' }}>Loading history…</div>}
        {error && <div className="cf-alert cf-alert-error">{error}</div>}
        {!loading && !error && rows.length === 0 && (
          <div style={{ fontSize: 13, color: '#888' }}>No officer changes recorded yet.</div>
        )}
        {!loading && rows.map((h) => {
          const active = !h.unassigned_at;
          const expanded = openId === h.id;
          return (
            <div key={h.id} style={{
              border: '1px solid #e5eaf0', borderRadius: 10, padding: 12, marginBottom: 10,
              background: active ? '#f0fdf4' : '#fff',
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                <div>
                  <div style={{ fontWeight: 700, fontSize: 14, color: '#111827' }}>
                    {h.officer?.name || `Officer #${h.officer_id}`}
                    {active && <span style={{ marginLeft: 8, fontSize: 11, background: '#16a34a', color: '#fff', padding: '2px 8px', borderRadius: 999 }}>Current</span>}
                  </div>
                  <div style={{ fontSize: 12, color: '#64748b', marginTop: 4 }}>
                    Role: {(h.officer_role || '').replace(/_/g, ' ')}
                    {h.officer?.designation ? ` · ${h.officer.designation}` : ''}
                  </div>
                </div>
                <button type="button" className="btn btn-outline btn-sm" onClick={() => setOpenId(expanded ? null : h.id)}>
                  {expanded ? 'Hide work' : 'View work'}
                </button>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 8, marginTop: 10, fontSize: 12 }}>
                <div><strong style={{ color: '#64748b' }}>Assigned</strong><div>{formatDisplayDateTime(h.assigned_at)}</div>
                  {(h.assigned_by_user?.name || h.assignedByUser?.name) && <div style={{ color: '#94a3b8' }}>by {h.assigned_by_user?.name || h.assignedByUser?.name}</div>}
                </div>
                <div><strong style={{ color: '#64748b' }}>Unassigned</strong><div>{h.unassigned_at ? formatDisplayDateTime(h.unassigned_at) : '—'}</div>
                  {(h.unassigned_by_user?.name || h.unassignedByUser?.name) && <div style={{ color: '#94a3b8' }}>by {h.unassigned_by_user?.name || h.unassignedByUser?.name}</div>}
                </div>
                <div><strong style={{ color: '#64748b' }}>Reason</strong><div>{h.change_reason || '—'}</div></div>
              </div>
              {expanded && <SnapshotDetails snap={h.work_snapshot} />}
            </div>
          );
        })}
      </div>
    </div>
  );
}

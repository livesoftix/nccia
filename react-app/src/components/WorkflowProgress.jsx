import ProgressBar from './ProgressBar';

const STATE_STYLES = {
  done: { bg: '#015C94', color: '#fff', border: '#015C94' },
  current: { bg: '#FDDF00', color: '#1a1a1a', border: '#e5a100' },
  todo: { bg: '#eef2f6', color: '#94a3b8', border: '#dbe3ec' },
};

/**
 * Lifecycle stepper: Complaint → Verification → Enquiry → Case → Court
 * Accepts either `workflow` from API or derives from `percent`.
 */
export default function WorkflowProgress({ workflow, percent, stage, compact = false }) {
  const steps = workflow?.steps || [
    { key: 'complaint', label: 'Complaint', state: 'todo' },
    { key: 'verification', label: 'Verification', state: 'todo' },
    { key: 'enquiry', label: 'Enquiry', state: 'todo' },
    { key: 'case', label: 'Case', state: 'todo' },
    { key: 'court', label: 'Court', state: 'todo' },
  ];
  const value = workflow?.percent ?? percent ?? 0;
  const label = workflow?.stage ?? stage;

  return (
    <div style={{ minWidth: compact ? 140 : 220 }}>
      <ProgressBar value={value} showLabel height={compact ? 6 : 8} />
      {label && (
        <div style={{ fontSize: 11, color: '#6c757d', marginTop: 4, fontWeight: 600 }}>{label}</div>
      )}
      <div
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 0,
          marginTop: compact ? 6 : 10,
          overflowX: 'auto',
        }}
      >
        {steps.map((s, i) => {
          const st = STATE_STYLES[s.state] || STATE_STYLES.todo;
          return (
            <div key={s.key} style={{ display: 'flex', alignItems: 'center', flex: 1, minWidth: 0 }}>
              <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', flex: 1, minWidth: 0 }}>
                <div
                  title={`${s.label}: ${s.state}`}
                  style={{
                    width: compact ? 18 : 22,
                    height: compact ? 18 : 22,
                    borderRadius: '50%',
                    background: st.bg,
                    border: `2px solid ${st.border}`,
                    color: st.color,
                    fontSize: compact ? 9 : 10,
                    fontWeight: 800,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  {s.state === 'done' ? '✓' : i + 1}
                </div>
                {!compact && (
                  <div
                    style={{
                      fontSize: 9,
                      fontWeight: 700,
                      color: s.state === 'todo' ? '#94a3b8' : '#334155',
                      marginTop: 4,
                      textAlign: 'center',
                      lineHeight: 1.1,
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {s.label}
                  </div>
                )}
              </div>
              {i < steps.length - 1 && (
                <div
                  style={{
                    height: 2,
                    flex: '0 0 8px',
                    background: s.state === 'done' ? '#015C94' : '#dbe3ec',
                    marginBottom: compact ? 0 : 14,
                  }}
                />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

/** Map enquiry status → simple percent/stage when complaint workflow not loaded */
export function enquiryProgress(status) {
  const map = {
    registered: { percent: 70, stage: 'Enquiry Registered' },
    pending: { percent: 72, stage: 'Pending' },
    registered: { percent: 70, stage: 'Enquiry Registered' },
    assigned: { percent: 75, stage: 'Pending' },
    working: { percent: 80, stage: 'Working' },
    in_progress: { percent: 80, stage: 'Working' },
    complete: { percent: 100, stage: 'Complete' },
    cfr_submitted: { percent: 82, stage: 'CFR Submitted' },
    legal_review: { percent: 84, stage: 'Legal Review' },
    approved: { percent: 85, stage: 'Enquiry Approved' },
    converted_to_case: { percent: 95, stage: 'Converted to Case' },
    referred_court: { percent: 98, stage: 'Referred to Court' },
    closed: { percent: 100, stage: 'Closed' },
    transferred: { percent: 100, stage: 'Transferred' },
  };
  return map[status] || { percent: 70, stage: status || 'Enquiry' };
}

export function caseProgress(status) {
  const map = {
    registered: { percent: 90, stage: 'Case Registered' },
    assigned: { percent: 91, stage: 'IO Assigned' },
    in_progress: { percent: 93, stage: 'Investigation' },
    cfr_submitted: { percent: 95, stage: 'Case CFR Submitted' },
    approved: { percent: 96, stage: 'Case Approved' },
    challan_submitted: { percent: 98, stage: 'Challan Submitted' },
    closed: { percent: 100, stage: 'Closed' },
    transferred: { percent: 100, stage: 'Transferred' },
    merged: { percent: 100, stage: 'Merged' },
  };
  return map[status] || { percent: 90, stage: status || 'Case' };
}

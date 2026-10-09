const STEPS = [
  { label: 'Complaint', short: 'Register', icon: '📝' },
  { label: 'Verification', short: 'VO Report', icon: '✅' },
  { label: 'Enquiry', short: 'EO Diary', icon: '🔍' },
  { label: 'Case / FIR', short: 'DAC File', icon: '📁' },
  { label: 'Forensic', short: 'Evidence', icon: '💾' },
  { label: 'Court', short: 'Legal', icon: '⚖️' },
  { label: 'Closed', short: 'Complete', icon: '🏁' },
];

export default function SoftwareGuideBackdrop({ activeStep = -1 }) {
  const isOverview = activeStep < 0;

  return (
    <div className={`sg-backdrop${isOverview ? ' overview' : ''}`} aria-hidden="true">
      <div className="sg-backdrop-grid" />
      <div className="sg-backdrop-glow" />
      <div className="sg-backdrop-header">
        <span className="sg-backdrop-logo">NCCIA</span>
        <span className="sg-backdrop-tag">CMS Workflow</span>
      </div>
      <div className="sg-backdrop-flow">
        {STEPS.map((step, i) => {
          const isActive = !isOverview && activeStep === i;
          const isDone = !isOverview && activeStep > i;
          const isPending = !isOverview && activeStep >= 0 && i > activeStep;
          return (
            <div
              key={step.label}
              className={`sg-flow-item${isActive ? ' active' : ''}${isDone ? ' done' : ''}${isPending ? ' pending' : ''}${isOverview ? ' overview-item' : ''}`}
            >
              <div className="sg-flow-rail">
                <div className="sg-flow-dot">
                  <span>{isDone ? '✓' : step.icon}</span>
                </div>
                {i < STEPS.length - 1 && <div className="sg-flow-line" />}
              </div>
              <div className="sg-flow-copy">
                <strong>{step.label}</strong>
                <small>{step.short}</small>
              </div>
            </div>
          );
        })}
      </div>
      {activeStep < 0 && (
        <div className="sg-backdrop-hint">Full digital pipeline</div>
      )}
    </div>
  );
}

export { STEPS as WORKFLOW_STEPS };

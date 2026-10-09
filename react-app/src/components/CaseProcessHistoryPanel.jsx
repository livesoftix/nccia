import VerificationReportPanel from './VerificationReportPanel';

const ACTIVITY_LABELS = {
  dac_request: 'DAC Request',
  mobile_record: 'Mobile Record',
  bank_record: 'Bank Record',
  search_seize: 'Search Warrant',
  raid: 'Raid',
  arrest_warrant: 'Arrest Warrant',
  notice: 'Summon',
  diary: 'Diary',
  seizure: 'Seizure',
  seizures: 'Seizure',
  forensic_report: 'Forensic Report',
  recovery: 'Recovery',
};

function Field({ label, value }) {
  return (
    <div>
      <div style={{ fontSize: 10, color: '#64748b', textTransform: 'uppercase', fontWeight: 700 }}>{label}</div>
      <div style={{ marginTop: 2, fontSize: 13 }}>{value || '—'}</div>
    </div>
  );
}

function ReadOnlyBanner({ children }) {
  return (
    <div style={{ padding: '8px 12px', marginBottom: 12, background: '#fef3c7', border: '1px solid #fcd34d', borderRadius: 8, fontSize: 12, fontWeight: 700, color: '#92400e' }}>
      {children}
    </div>
  );
}

export default function CaseProcessHistoryPanel({ inherited }) {
  const complaint = inherited?.complaint;
  const enquiry = inherited?.enquiry;
  const verification = inherited?.verification;
  const verificationReport = inherited?.verificationReport;
  const enquiryActivities = inherited?.enquiryActivities || [];
  const accused = inherited?.accused || [];
  const witnesses = inherited?.witnesses || [];

  if (!complaint && !enquiry && !verification && enquiryActivities.length === 0) {
    return (
      <p style={{ textAlign: 'center', color: '#94a3b8', padding: 24, fontSize: 13 }}>
        No linked complaint / enquiry history. Direct FIR cases show only case-phase records.
      </p>
    );
  }

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      <ReadOnlyBanner>
        Complaint → Verification (VO) → Enquiry (EO) records are live from CMS and cannot be edited or deleted on this case screen. IO may add new case activities and arrests below.
      </ReadOnlyBanner>

      {complaint && (
        <div className="cf-section" style={{ margin: 0 }}>
          <div className="cf-section-header" style={{ padding: '12px 16px' }}>
            <div className="cf-section-title" style={{ fontSize: 14 }}>1. Complaint (Complainant)</div>
            <div className="cf-section-sub">Original registration — read only</div>
          </div>
          <div className="cf-body" style={{ padding: '12px 16px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 12 }}>
              <Field label="Tracking No" value={complaint.tracking_no || complaint.slip_number} />
              <Field label="Complainant" value={complaint.complainant_name} />
              <Field label="CNIC" value={complaint.cnic} />
              <Field label="Contact" value={complaint.contact_no} />
              <Field label="Offence" value={complaint.offence_type} />
              <Field label="Report Date" value={complaint.report_date ? String(complaint.report_date).slice(0, 10) : ''} />
              <Field label="Circle" value={complaint.circle?.name} />
              <Field label="Status" value={(complaint.final_status || complaint.status || '').replace(/_/g, ' ')} />
            </div>
            {complaint.description && (
              <div style={{ marginTop: 12, fontSize: 13, whiteSpace: 'pre-wrap' }}>
                <strong>Gist:</strong> {complaint.description}
              </div>
            )}
          </div>
        </div>
      )}

      {(verification || verificationReport) && (
        <div className="cf-section" style={{ margin: 0 }}>
          <div className="cf-section-header" style={{ padding: '12px 16px' }}>
            <div className="cf-section-title" style={{ fontSize: 14 }}>2. Verification (VO)</div>
            <div className="cf-section-sub">Victim verification report — read only</div>
          </div>
          <div className="cf-body" style={{ padding: '12px 16px' }}>
            <VerificationReportPanel report={verificationReport} verification={verification} />
          </div>
        </div>
      )}

      {enquiry && (
        <div className="cf-section" style={{ margin: 0 }}>
          <div className="cf-section-header" style={{ padding: '12px 16px' }}>
            <div className="cf-section-title" style={{ fontSize: 14 }}>3. Enquiry (EO)</div>
            <div className="cf-section-sub">Enquiry registration &amp; CFR — read only</div>
          </div>
          <div className="cf-body" style={{ padding: '12px 16px' }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: 12, marginBottom: 12 }}>
              <Field label="Enquiry No" value={enquiry.enquiry_number || (enquiry.id ? `ENQ-${enquiry.id}` : '')} />
              <Field label="Enquiry Officer" value={enquiry.officer?.name} />
              <Field label="Status" value={(enquiry.status || '').replace(/_/g, ' ')} />
              <Field label="Registered" value={enquiry.reg_date ? String(enquiry.reg_date).slice(0, 10) : ''} />
              <Field label="Charge Against" value={enquiry.charge_against} />
            </div>
            {enquiry.cfr_summary && (
              <div style={{ fontSize: 13, whiteSpace: 'pre-wrap', marginBottom: 12, padding: 10, background: '#f8fafc', borderRadius: 8, border: '1px solid #e2e8f0' }}>
                <strong>CFR Summary:</strong><br />{enquiry.cfr_summary}
              </div>
            )}
          </div>
        </div>
      )}

      {accused.length > 0 && (
        <div className="cf-section" style={{ margin: 0 }}>
          <div className="cf-section-header" style={{ padding: '12px 16px' }}>
            <div className="cf-section-title" style={{ fontSize: 14 }}>Accused Persons ({accused.length})</div>
            <div className="cf-section-sub">From enquiry / verification — read only</div>
          </div>
          <div className="cf-body" style={{ padding: '12px 16px', overflowX: 'auto' }}>
            <table className="data-table" style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
              <thead>
                <tr style={{ background: '#f1f5f9' }}>
                  <th style={{ border: '1px solid #cbd5e1', padding: 6 }}>#</th>
                  <th style={{ border: '1px solid #cbd5e1', padding: 6 }}>Name</th>
                  <th style={{ border: '1px solid #cbd5e1', padding: 6 }}>Father</th>
                  <th style={{ border: '1px solid #cbd5e1', padding: 6 }}>CNIC</th>
                  <th style={{ border: '1px solid #cbd5e1', padding: 6 }}>Contact</th>
                  <th style={{ border: '1px solid #cbd5e1', padding: 6 }}>Remarks</th>
                </tr>
              </thead>
              <tbody>
                {accused.map((a, i) => (
                  <tr key={a.id || i}>
                    <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{i + 1}</td>
                    <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{a.name || '—'}</td>
                    <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{a.father_name || '—'}</td>
                    <td style={{ border: '1px solid #e2e8f0', padding: 6, fontFamily: 'monospace' }}>{a.cnic || '—'}</td>
                    <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{a.contact_no || a.whatsapp_no || '—'}</td>
                    <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{a.description || a.designation || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {witnesses.length > 0 && (
        <div className="cf-section" style={{ margin: 0 }}>
          <div className="cf-section-header" style={{ padding: '12px 16px' }}>
            <div className="cf-section-title" style={{ fontSize: 14 }}>Witnesses ({witnesses.length})</div>
          </div>
          <div className="cf-body" style={{ padding: '12px 16px' }}>
            <ul style={{ margin: 0, paddingLeft: 18, fontSize: 13 }}>
              {witnesses.map((w, i) => (
                <li key={w.id || i}>{w.name || '—'} {w.cnic ? `(${w.cnic})` : ''}</li>
              ))}
            </ul>
          </div>
        </div>
      )}

      {enquiryActivities.length > 0 && (
        <div className="cf-section" style={{ margin: 0 }}>
          <div className="cf-section-header" style={{ padding: '12px 16px' }}>
            <div className="cf-section-title" style={{ fontSize: 14 }}>4. Enquiry Activities / Diary (EO)</div>
            <div className="cf-section-sub">{enquiryActivities.length} record(s) — read only</div>
          </div>
          <div className="cf-body" style={{ padding: '12px 16px' }}>
            {enquiryActivities.map((act, i) => (
              <div key={act.id || i} style={{ padding: 12, marginBottom: 10, background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: 8, fontSize: 13 }}>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 12, marginBottom: 6 }}>
                  <strong>{ACTIVITY_LABELS[act.type] || act.type || 'Activity'}</strong>
                  <span style={{ color: '#64748b' }}>{act.activity_date ? String(act.activity_date).slice(0, 10) : ''}</span>
                  {act.creator?.name && <span style={{ color: '#64748b' }}>by {act.creator.name}</span>}
                  {act.diary_no && <span style={{ color: '#015C94', fontWeight: 700 }}>Diary: {act.diary_no}</span>}
                </div>
                {act.description && <div style={{ whiteSpace: 'pre-wrap' }}>{act.description}</div>}
                {act.meta?.against_whom && <div style={{ marginTop: 4 }}><strong>Against:</strong> {act.meta.against_whom}</div>}
                {act.meta?.kota && <div><strong>Location:</strong> {act.meta.kota}</div>}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

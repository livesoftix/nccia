import { Link } from 'react-router-dom';

const REC_LABELS = {
  enquiry_registration: 'Enquiry Registration',
  closure: 'Closure',
  merge: 'Merge',
  transfer: 'Transfer',
};

export default function VerificationReportPanel({ report, verification }) {
  if (!report && !verification) {
    return (
      <p style={{ textAlign: 'center', color: '#999', padding: 24 }}>
        No verification report linked to this complaint yet.
      </p>
    );
  }

  const accused = Array.isArray(report?.accused) ? report.accused : [];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      {verification && (
        <div style={{ padding: '12px 14px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8, fontSize: 13 }}>
          <strong>Verification status:</strong> {verification.status?.replace(/_/g, ' ')}
          {verification.officer?.name && <> · <strong>VO:</strong> {verification.officer.name}</>}
          {verification.complainant_message && (
            <div style={{ marginTop: 8, whiteSpace: 'pre-wrap' }}>
              <strong>Latest message:</strong> {verification.complainant_message}
              {verification.message_via && <span style={{ color: '#64748b' }}> ({verification.message_via})</span>}
            </div>
          )}
        </div>
      )}

      {report && (
        <>
          <div className="cf-row-3">
            <div><span style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Tracking</span><div style={{ fontWeight: 600 }}>{report.tracking_no || '—'}</div></div>
            <div><span style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Verification Date</span><div>{report.verification_date ? String(report.verification_date).slice(0, 16).replace('T', ' ') : '—'}</div></div>
            <div><span style={{ fontSize: 11, color: '#64748b', textTransform: 'uppercase' }}>Recommendation</span><div>{REC_LABELS[report.recommendation] || report.recommendation || '—'}</div></div>
          </div>

          <div className="cf-section" style={{ margin: 0 }}>
            <div className="cf-section-header" style={{ padding: '12px 16px' }}>
              <div className="cf-section-title" style={{ fontSize: 14 }}>Victim</div>
            </div>
            <div className="cf-body" style={{ padding: '12px 16px' }}>
              <div className="cf-row-3">
                <div><strong>Name:</strong> {report.victim_name || '—'}</div>
                <div><strong>CNIC:</strong> {report.victim_cnic || '—'}</div>
                <div><strong>Phone:</strong> {(report.victim_country_code || '') + ' ' + (report.victim_phone || '—')}</div>
              </div>
              <div className="cf-row-3" style={{ marginTop: 8 }}>
                <div><strong>Email:</strong> {report.victim_email || '—'}</div>
                <div><strong>Gender:</strong> {report.victim_gender || '—'}</div>
                <div><strong>City:</strong> {report.city || '—'}</div>
              </div>
              {report.crime_description && (
                <div style={{ marginTop: 10, fontSize: 13, whiteSpace: 'pre-wrap' }}>
                  <strong>Crime:</strong> {report.crime_description}
                </div>
              )}
            </div>
          </div>

          {accused.length > 0 && (
            <div className="cf-section" style={{ margin: 0 }}>
              <div className="cf-section-header" style={{ padding: '12px 16px' }}>
                <div className="cf-section-title" style={{ fontSize: 14 }}>Accused ({accused.length})</div>
              </div>
              <div className="cf-body" style={{ padding: '12px 16px' }}>
                {accused.map((a, i) => (
                  <div key={i} style={{ padding: '10px 12px', marginBottom: 8, background: '#f8fafc', borderRadius: 8, border: '1px solid #e2e8f0', fontSize: 13 }}>
                    <div><strong>{a.name || 'Unknown'}</strong>{a.father_name ? ` · s/o ${a.father_name}` : ''}</div>
                    <div style={{ color: '#475569', marginTop: 4 }}>
                      CNIC: {a.cnic || '—'} · Phone: {(a.country_code || '+92') + ' ' + (a.phone || '—')}
                    </div>
                    <div style={{ color: '#475569' }}>Email: {a.email || '—'}</div>
                    {a.address && <div style={{ color: '#475569', marginTop: 4 }}>Address: {a.address}</div>}
                  </div>
                ))}
              </div>
            </div>
          )}

          {report.comments && (
            <div style={{ padding: '12px 14px', background: '#f9fafb', borderRadius: 8, fontSize: 13, whiteSpace: 'pre-wrap' }}>
              <strong>VO Comments:</strong> {report.comments}
            </div>
          )}

          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <Link to={`/verifications/reports/${report.id}/edit`} className="btn btn-outline btn-sm">Open Full Report</Link>
            <a href={`/verifications/reports/${report.id}/pdf`} target="_blank" rel="noreferrer" className="btn btn-outline btn-sm">Download PDF</a>
          </div>
        </>
      )}
    </div>
  );
}

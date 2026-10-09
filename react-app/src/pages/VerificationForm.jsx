import { useState, useEffect, useMemo } from 'react';
import { useParams, useNavigate, useSearchParams } from 'react-router-dom';
import api from '../api';
import SearchableSelect from '../components/SearchableSelect';
import OfficerHistoryPanel from '../components/OfficerHistoryPanel';
import DirectRegistrationFields from '../components/DirectRegistrationFields';
import { useAuth } from '../contexts/AuthContext';
import { formatDisplayDateTime, toLocalInput } from '../utils/datetime';
import { hasRole } from '../utils/permissions';
import {
  emptyDirectInfo,
  normalizeDirectInfo,
  buildDirectInfoPayload,
} from '../utils/directCaseOptions';
import PdfAutoFillBar from '../components/PdfAutoFillBar';
import { mapExtractToDirectInfo, mapExtractToVipAccused } from '../utils/fillFromPdf';

const EMPTY_VIP_ACCUSED = {
  name: '', father_name: '', cnic: '', contact_no: '', email: '', address: '',
  nationality: 'Pakistani', passport_no: '', description: '',
  cnic_front: '', cnic_back: '', passport_attachment: '', picture: '', other_attachment: '',
  cnic_front_url: '', cnic_back_url: '', passport_attachment_url: '', picture_url: '', other_attachment_url: '',
};

const ACCUSED_DOC_FIELDS = [
  { key: 'cnic_front', label: 'CNIC Front', accept: '.jpg,.jpeg,.png,.pdf' },
  { key: 'cnic_back', label: 'CNIC Back', accept: '.jpg,.jpeg,.png,.pdf' },
  { key: 'passport_attachment', label: 'Passport', accept: '.jpg,.jpeg,.png,.pdf' },
  { key: 'picture', label: 'Picture / Photo', accept: 'image/*' },
  { key: 'other_attachment', label: 'Other Document', accept: '.jpg,.jpeg,.png,.pdf,.doc,.docx' },
];

const RECOMMENDATION_OPTIONS = [
  { value: 'enquiry_registration', name: 'Enquiry Registration' },
  { value: 'closure', name: 'Closure' },
  { value: 'merge', name: 'Merge with Another Complaint' },
  { value: 'transfer', name: 'Transfer to Other Circle/Dept' },
];

const CLOSURE_REASONS = [
  { value: 'non_pursuance', name: 'Non-Pursuance by Complainant' },
  { value: 'irrelevant', name: 'Irrelevant' },
  { value: 'invalid', name: 'Invalid' },
  { value: 'lack_of_evidence', name: 'Lack of Evidence' },
];

function buildAppearanceMessage({ trackingNo, officerName, appearAt }) {
  const when = appearAt ? formatDisplayDateTime(appearAt) : '[date/time]';
  const officer = officerName || '[Verification Officer]';
  const tracking = trackingNo || '[tracking no]';
  return `Assalam-o-Alaikum. Aap ki complaint number ${tracking} ke mutaliq verification ke liye aap ko ${officer} ke samnay ${when} par pesh hona hai. Baraye meherbani waqt par hazir hon. — NCCIA / CCRC`;
}

export default function VerificationForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const [form, setForm] = useState({ complaint_id: '', verification_officer_id: '', priority_type: 'normal', status: 'assigned', report_text: '', recommendation: '', closure_reason: '', merge_complaint_id: '', transfer_department: '', transfer_circle_id: '', complainant_message: '', appeared_at: '', message_via: '' });
  const [directMode, setDirectMode] = useState(!id && searchParams.get('direct') === '1');
  const [direct, setDirect] = useState(emptyDirectInfo());
  const [accused, setAccused] = useState([]);
  const [officers, setOfficers] = useState([]);
  const [complaints, setComplaints] = useState([]);
  const [allComplaints, setAllComplaints] = useState([]);
  const [circles, setCircles] = useState([]);
  const [saving, setSaving] = useState(false);
  const [submittingCi, setSubmittingCi] = useState(false);
  const [errors, setErrors] = useState({});
  const [complaintDetail, setComplaintDetail] = useState(null);
  const isEdit = !!id;
  const isAssignedVo = isEdit && String(user?.id) === String(form.verification_officer_id);
  const canSubmitToCi = isAssignedVo && ['assigned', 'in_progress', 'sent_back'].includes(form.status);

  useEffect(() => {
    api.get('/lookup/verification-officers').then(r => {
      const all = r.data.data || r.data;
      setOfficers(Array.isArray(all) ? all : []);
    }).catch(() => {});
    api.get('/lookup/circles').then(r => setCircles(r.data || [])).catch(() => {});
    api.get('/complaints/search').then(r => {
      const all = r.data.data || r.data || [];
      setComplaints(all.filter(c => c.tracking_no));
      setAllComplaints(all);
    }).catch(() => {});
    if (isEdit) {
      api.get(`/verifications/${id}`).then(r => {
        const d = r.data.data || r.data;
         setForm({ complaint_id: d.complaint_id || '', verification_officer_id: d.verification_officer_id || '', priority_type: d.priority_type || 'normal', status: d.status || 'assigned', report_text: d.report_text || '', recommendation: d.recommendation || '', closure_reason: d.closure_reason || '', merge_complaint_id: d.merge_complaint_id || '', transfer_department: d.transfer_department || '', transfer_circle_id: d.transfer_circle_id || '', complainant_message: d.complainant_message || '', appeared_at: toLocalInput(d.appeared_at), message_via: d.message_via || '', assigned_at: d.assigned_at || '', submitted_at: d.submitted_at || '', approved_at: d.approved_at || '', sent_by: d.sent_by || '', sent_by_name: d.sent_by_user?.name || '', whatsapp_sent_at: d.whatsapp_sent_at || '' });
         if (d.complaint) setComplaintDetail(d.complaint);
         else if (d.complaint_id) {
           api.get(`/complaints/${d.complaint_id}`).then(cr => setComplaintDetail(cr.data.data || cr.data)).catch(() => {});
         }
         if (!d.complaint_id && d.direct_info) {
           setDirectMode(true);
           setDirect(normalizeDirectInfo(d.direct_info));
         }
         if (Array.isArray(d.accused)) {
           setAccused(d.accused.map(a => ({ ...EMPTY_VIP_ACCUSED, ...a })));
         }
      }).catch(() => navigate('/verifications'));
    } else if (directMode && hasRole(user, 'verification_officer') && user?.id) {
      // VO opening VIP verification: assign to self by default
      setForm(f => ({ ...f, verification_officer_id: f.verification_officer_id || String(user.id) }));
    }
  }, [id]);

  const selectedComplaint = useMemo(() => {
    if (complaintDetail) return complaintDetail;
    return allComplaints.find(c => String(c.id) === String(form.complaint_id)) || null;
  }, [complaintDetail, allComplaints, form.complaint_id]);

  const officerName = useMemo(() => {
    const fromList = officers.find(o => String(o.id) === String(form.verification_officer_id))?.name;
    return fromList || user?.name || '';
  }, [officers, form.verification_officer_id, user]);

  const fillAppearanceMessage = () => {
    const msg = buildAppearanceMessage({
      trackingNo: selectedComplaint?.tracking_no || direct.reference_no,
      officerName,
      appearAt: form.appeared_at,
    });
    setForm(f => ({ ...f, complainant_message: msg }));
  };

  const openWhatsApp = async () => {
    if (directMode && !selectedComplaint) {
      alert('Direct case mein complainant ka phone number available nahi hai. Message ko record/send manually karein.');
      return;
    }
    const phoneRaw = `${selectedComplaint?.contact_country_code || '+92'}${selectedComplaint?.contact_no || ''}`.replace(/\D/g, '');
    if (!phoneRaw) {
      alert('Complainant phone number not found on complaint.');
      return;
    }
    let message = form.complainant_message;
    if (!message?.trim()) {
      message = buildAppearanceMessage({
        trackingNo: selectedComplaint?.tracking_no || direct.reference_no,
        officerName,
        appearAt: form.appeared_at,
      });
      setForm(f => ({ ...f, complainant_message: message, message_via: 'whatsapp' }));
    }
    if (isEdit) {
      try {
        await api.put(`/verifications/${id}`, {
          ...form,
          complainant_message: message,
          message_via: 'whatsapp',
          whatsapp_sent_at: new Date().toISOString(),
        });
        setForm(f => ({ ...f, complainant_message: message, message_via: 'whatsapp', whatsapp_sent_at: new Date().toISOString() }));
      } catch (_) { /* still open WhatsApp */ }
    } else {
      setForm(f => ({ ...f, message_via: 'whatsapp' }));
    }
    window.open(`https://wa.me/${phoneRaw}?text=${encodeURIComponent(message)}`, '_blank');
  };

  const addAccused = () => setAccused(list => [{ ...EMPTY_VIP_ACCUSED }, ...list]);
  const removeAccused = (i) => setAccused(list => list.filter((_, idx) => idx !== i));
  const updateAccused = (i, field, value) => setAccused(list => list.map((a, idx) => idx === i ? { ...a, [field]: value } : a));

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    try {
      const fd = new FormData();
      Object.entries(form).forEach(([k, v]) => {
        if (v !== null && v !== undefined && v !== '') fd.append(k, v);
      });
      if (directMode && !form.complaint_id) {
        fd.append('direct_info', JSON.stringify(buildDirectInfoPayload(direct, circles)));
      }
      const accusedClean = accused.map((a) => {
        const o = { ...a };
        ACCUSED_DOC_FIELDS.forEach(({ key }) => {
          if (o[key] instanceof File) delete o[key];
        });
        return o;
      });
      fd.append('accused', JSON.stringify(accusedClean));
      accused.forEach((a, i) => {
        ACCUSED_DOC_FIELDS.forEach(({ key }) => {
          if (a[key] instanceof File) {
            const input = {
              cnic_front: 'accused_cnic_front',
              cnic_back: 'accused_cnic_back',
              passport_attachment: 'accused_passport',
              picture: 'accused_picture',
              other_attachment: 'accused_other',
            }[key];
            if (input) fd.append(`${input}[${i}]`, a[key]);
          }
        });
      });
      if (isEdit) {
        fd.append('_method', 'PUT');
        await api.post(`/verifications/${id}`, fd);
      } else {
        await api.post('/verifications', fd);
      }
      navigate('/verifications');
    } catch (err) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {});
      else setErrors({ _general: err.response?.data?.message || 'Failed to save' });
    } finally {
      setSaving(false);
    }
  };

  const handleSubmitToCircleIncharge = async () => {
    if (!isEdit) return;
    if (!form.report_text?.trim() || !form.recommendation) {
      setErrors({ _general: 'Circle Incharge ko submit karne se pehle Report Text aur Recommendation fill karein.' });
      return;
    }
    setSubmittingCi(true);
    setErrors({});
    try {
      await api.put(`/verifications/${id}`, form);
      await api.post(`/verifications/${id}/submit-report`, {
        report_text: form.report_text,
        recommendation: form.recommendation,
        closure_reason: form.closure_reason || null,
        merge_complaint_id: form.merge_complaint_id || null,
        transfer_department: form.transfer_department || null,
        transfer_circle_id: form.transfer_circle_id || null,
      });
      alert('Report Circle Incharge ko submit ho gayi.');
      navigate('/verifications');
    } catch (err) {
      if (err.response?.status === 422) setErrors(err.response.data.errors || {});
      else setErrors({ _general: err.response?.data?.message || 'Submit to Circle Incharge failed' });
    } finally {
      setSubmittingCi(false);
    }
  };

  const setF = (field) => (e) => setForm(f => ({ ...f, [field]: e.target.value }));

  const rec = form.recommendation;

  return (
    <div className="page-content" style={{margin:'0 auto'}}>
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label">Verifications</div>
          <h1 className="page-title">{isEdit ? 'Edit Verification' : 'Assign New Verification'}</h1>
          <p className="page-subtitle">{isEdit ? 'Update verification details & recommendation' : 'Assign a complaint for verification'}</p>
          <div className="title-underline"></div>
        </div>
      </div>

      {!isEdit && (
        <PdfAutoFillBar
          hint="NCCIA verification PDF upload karein — Direct/VIP case par complainant, CNIC, phone, address aur gist auto fill ho jayega."
          onFilled={(extracted) => {
            setDirectMode(true);
            setDirect(d => mapExtractToDirectInfo(extracted, d));
            const row = mapExtractToVipAccused(extracted);
            if (row) {
              setAccused(list => (list.some(a => a.cnic && row.cnic && a.cnic === row.cnic) ? list : [...list, row]));
            }
          }}
        />
      )}

      {errors._general && <div className="cf-alert cf-alert-error">{errors._general}</div>}

      <form onSubmit={handleSubmit}>
        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#015C94'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div><div className="cf-section-title">Assignment Details</div><div className="cf-section-sub">Complaint + Officer + Priority</div></div>
            <div className="cf-section-badge">Step 1</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">{directMode ? '' : 'Complaint'}</label>
                {directMode ? (
                  <div className="cf-input-wrap" style={{background:'#F7F8FA',padding:'8px 10px',borderRadius:6,fontSize:13,color:'#015C94',display:'flex',alignItems:'center',gap:8,flexWrap:'wrap'}}>
                    Direct Case (No Complaint)
                    <span
                      style={{color:'#015C94',cursor:'pointer',fontWeight:600,textDecoration:'underline'}}
                      onClick={() => setDirectMode(false)}
                    >Switch to Complaint</span>
                  </div>
                ) : (
                  <div className="cf-input-wrap">
                    <SearchableSelect
                      value={form.complaint_id}
                      onChange={v => setForm(f => ({ ...f, complaint_id: v }))}
                      options={complaints}
                      placeholder="Select Complaint"
                      valueKey="id"
                      formatLabel={o => o.tracking_no + ' — ' + o.complainant_name}
                    />
                    <div style={{marginTop:6}}>
                      <span style={{color:'#015C94',cursor:'pointer',fontWeight:600,fontSize:12,textDecoration:'underline'}} onClick={() => setDirectMode(true)}>
                        Complaint nahi hai? Direct case create karein
                      </span>
                    </div>
                  </div>
                )}
                {errors.complaint_id && <span className="cf-error">{errors.complaint_id[0]}</span>}
              </div>
              <div className="cf-field">
                <label className="cf-label required">Verification Officer</label>
                <div className="cf-input-wrap">
                  <SearchableSelect
                    value={form.verification_officer_id}
                    onChange={v => setForm(f => ({ ...f, verification_officer_id: v }))}
                    options={officers}
                    placeholder="Select Officer"
                    valueKey="id"
                    formatLabel={o => o.name + (o.designation ? ' (' + o.designation + ')' : '')}
                  />
                </div>
                {errors.verification_officer_id && <span className="cf-error">{errors.verification_officer_id[0]}</span>}
              </div>
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">Priority</label>
                <div className="cf-input-wrap">
                  <select className="cf-input" value={form.priority_type} onChange={setF('priority_type')} required>
                    <option value="">— Select Priority —</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>
              </div>
              <div className="cf-field">
                <label className="cf-label">Status</label>
                <div className="cf-input-wrap">
                  <select className="cf-input" value={form.status} onChange={setF('status')}>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="submitted">Submitted</option>
                    <option value="sent_back">Sent Back</option>
                  </select>
                </div>
              </div>
            </div>

            {directMode && (
              <DirectRegistrationFields
                direct={direct}
                setDirect={setDirect}
                errors={errors}
                title="VIP / Direct Verification Details"
                subtitle="Same fields as CMS Case / Enquiry Registration Form"
                showCaseExtras
              />
            )}

            {directMode && (
              <div style={{ marginTop: 20, borderTop: '1px dashed #dbe2ea', paddingTop: 16 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                  <div>
                    <div className="cf-section-title" style={{ fontSize: 15 }}>Accused Information</div>
                    <div className="cf-section-sub">Add accused with CNIC, passport, and picture attachments</div>
                  </div>
                  <button type="button" className="btn btn-outline btn-sm" onClick={addAccused}>
                    Add Accused
                  </button>
                </div>
                {accused.map((a, i) => (
                  <div key={i} style={{ padding: 14, marginBottom: 12, background: '#f8f8f8', borderRadius: 8, border: '1px solid #e0e0e0' }}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: 12, marginBottom: 12 }}>
                      <div className="cf-field"><label className="cf-label">Name</label>
                        <input type="text" className="cf-input" value={a.name} onChange={e => updateAccused(i, 'name', e.target.value)} placeholder="Accused name" />
                      </div>
                      <div className="cf-field"><label className="cf-label">Father Name</label>
                        <input type="text" className="cf-input" value={a.father_name} onChange={e => updateAccused(i, 'father_name', e.target.value)} />
                      </div>
                      <div className="cf-field"><label className="cf-label">CNIC</label>
                        <input type="text" className="cf-input" value={a.cnic} onChange={e => {
                          let v = e.target.value.replace(/\D/g, '').slice(0, 13);
                          if (v.length > 5) v = v.slice(0, 5) + '-' + v.slice(5);
                          if (v.length > 13) v = v.slice(0, 13) + '-' + v.slice(13);
                          updateAccused(i, 'cnic', v);
                        }} maxLength={15} placeholder="00000-0000000-0" />
                      </div>
                      <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: 8, width: 36, height: 36, alignSelf: 'end' }} onClick={() => removeAccused(i)}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12, marginBottom: 12 }}>
                      <div className="cf-field"><label className="cf-label">Contact No</label>
                        <input type="text" className="cf-input" value={a.contact_no} onChange={e => updateAccused(i, 'contact_no', e.target.value)} />
                      </div>
                      <div className="cf-field"><label className="cf-label">Email</label>
                        <input type="email" className="cf-input" value={a.email} onChange={e => updateAccused(i, 'email', e.target.value)} />
                      </div>
                      <div className="cf-field"><label className="cf-label">Nationality</label>
                        <select className="cf-input" value={a.nationality || 'Pakistani'} onChange={e => updateAccused(i, 'nationality', e.target.value)}>
                          <option value="Pakistani">Pakistani</option>
                          <option value="Dual Nationality Holder">Dual Nationality Holder</option>
                          <option value="Foreigner">Foreigner</option>
                        </select>
                      </div>
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 12 }}>
                      <div className="cf-field"><label className="cf-label">Passport No</label>
                        <input type="text" className="cf-input" value={a.passport_no} onChange={e => updateAccused(i, 'passport_no', e.target.value)} />
                      </div>
                      <div className="cf-field"><label className="cf-label">Address</label>
                        <input type="text" className="cf-input" value={a.address} onChange={e => updateAccused(i, 'address', e.target.value)} />
                      </div>
                    </div>
                    <div className="cf-field" style={{ marginBottom: 12 }}>
                      <label className="cf-label">Description</label>
                      <textarea className="cf-input" rows={2} value={a.description} onChange={e => updateAccused(i, 'description', e.target.value)} placeholder="Short remarks about this accused" style={{ width: '100%' }} />
                    </div>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12 }}>
                      {ACCUSED_DOC_FIELDS.map(doc => (
                        <div key={doc.key} className="cf-field">
                          <label className="cf-label">{doc.label}</label>
                          <input type="file" className="cf-input" accept={doc.accept} onChange={e => updateAccused(i, doc.key, e.target.files?.[0] || '')} />
                          {a[doc.key] instanceof File ? (
                            <span style={{ fontSize: 12, color: '#38a169', marginTop: 4, display: 'block' }}>Selected: {a[doc.key].name}</span>
                          ) : (a[`${doc.key}_url`] || (typeof a[doc.key] === 'string' && a[doc.key])) ? (
                            <a href={a[`${doc.key}_url`] || a[doc.key]} target="_blank" rel="noreferrer" style={{ fontSize: 12, color: '#015C94', fontWeight: 600, marginTop: 4, display: 'inline-block' }}>Current file ↗</a>
                          ) : null}
                        </div>
                      ))}
                    </div>
                  </div>
                ))}
                {accused.length === 0 && (
                  <p style={{ textAlign: 'center', color: '#999', padding: '8px 0 4px' }}>No accused added yet. Click Add Accused.</p>
                )}
              </div>
            )}
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#2B2B2B'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div><div className="cf-section-title">Recommendation</div><div className="cf-section-sub">Verification officer's recommendation</div></div>
            <div className="cf-section-badge">Step 2</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">Outcome</label>
                <select className="cf-input" value={form.recommendation} onChange={setF('recommendation')} required>
                  <option value="">— Select Outcome —</option>
                  {RECOMMENDATION_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                </select>
                {errors.recommendation && <span className="cf-error">{errors.recommendation[0]}</span>}
              </div>

              {rec === 'closure' && (
                <div className="cf-field">
                  <label className="cf-label required">Closure Reason</label>
                  <select className="cf-input" value={form.closure_reason} onChange={setF('closure_reason')} required>
                    <option value="">— Select Reason —</option>
                    {CLOSURE_REASONS.map(r => <option key={r.value} value={r.value}>{r.name}</option>)}
                  </select>
                  {errors.closure_reason && <span className="cf-error">{errors.closure_reason[0]}</span>}
                </div>
              )}
            </div>

            {rec === 'merge' && (
              <div className="cf-field">
                <label className="cf-label required">Merge With Complaint</label>
                <SearchableSelect
                  value={form.merge_complaint_id}
                  onChange={v => setForm(f => ({ ...f, merge_complaint_id: v }))}
                  options={allComplaints.filter(c => c.tracking_no && c.id !== parseInt(form.complaint_id))}
                  placeholder="Select Complaint"
                  valueKey="id"
                  formatLabel={o => o.tracking_no + ' — ' + o.complainant_name}
                />
              </div>
            )}

            {rec === 'transfer' && (
              <div className="cf-row-2">
                <div className="cf-field">
                  <label className="cf-label">Transfer Department</label>
                  <input type="text" className="cf-input" value={form.transfer_department} onChange={setF('transfer_department')} placeholder="e.g. NCCIA, Police" />
                </div>
                <div className="cf-field">
                  <label className="cf-label">Transfer Circle</label>
                  <SearchableSelect
                    value={form.transfer_circle_id}
                    onChange={v => setForm(f => ({ ...f, transfer_circle_id: v }))}
                    options={circles}
                    placeholder="Select Circle"
                    valueKey="id"
                    formatLabel={o => o.name + (o.code ? ' (' + o.code + ')' : '')}
                  />
                </div>
              </div>
            )}
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#264078'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <div><div className="cf-section-title">Report / Remarks</div><div className="cf-section-sub">Detailed findings</div></div>
            <div className="cf-section-badge">Step 3</div>
          </div>
          <div className="cf-body">
            <div className="cf-field">
              <label className="cf-label">Report / Remarks</label>
              <textarea className="cf-input cf-textarea" rows={4} value={form.report_text} onChange={setF('report_text')} placeholder="Enter detailed report or remarks…"></textarea>
              {errors.report_text && <span className="cf-error">{errors.report_text[0]}</span>}
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#6C46A4'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><circle cx="12" cy="7" r="4"/><path d="M1.5 20.5a8.5 8.5 0 0 1 16.5-.5"/></svg>
            </div>
            <div><div className="cf-section-title">Complainant Message</div><div className="cf-section-sub">Message sent to the complainant + appearance date</div></div>
            <div className="cf-section-badge">Step 4</div>
          </div>
          <div className="cf-body">
            <div className="cf-field">
              <label className="cf-label">Appear By (Date &amp; Time)</label>
              <input
                type="datetime-local"
                className="cf-input"
                value={form.appeared_at || ''}
                onChange={setF('appeared_at')}
              />
              {errors.appeared_at && <span className="cf-error">{errors.appeared_at[0]}</span>}
            </div>
            <div className="cf-field">
              <label className="cf-label">Message to Complainant</label>
              <textarea
                className="cf-input cf-textarea"
                rows={4}
                value={form.complainant_message || ''}
                onChange={setF('complainant_message')}
                placeholder="Aap ki complaint number … ke mutaliq … officer ke samnay … par pesh hona hai"
              ></textarea>
              {errors.complainant_message && <span className="cf-error">{errors.complainant_message[0]}</span>}
            </div>
            <div style={{display:'flex', gap:8, flexWrap:'wrap', marginBottom:12}}>
              <button type="button" className="btn btn-outline btn-sm" onClick={fillAppearanceMessage}>
                Auto-fill Appearance Message
              </button>
              <button
                type="button"
                className="btn btn-sm"
                style={{background:'#25D366', color:'#fff', border:'none', borderRadius:8, height:36, padding:'0 12px', fontWeight:600, cursor:'pointer'}}
                onClick={openWhatsApp}
              >
                Send via WhatsApp
              </button>
            </div>
            <div className="cf-field">
              <label className="cf-label">Message Channel</label>
              <select className="cf-input" value={form.message_via || ''} onChange={setF('message_via')}>
                <option value="">— Select —</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="sms">SMS</option>
                <option value="phone">Phone Call</option>
                <option value="in_app">In-App / Record Only</option>
                <option value="email">Email</option>
              </select>
            </div>
            {isEdit && form.sent_by && (
              <div className="cf-field">
                <label className="cf-label">Recorded By</label>
                <div className="cf-input-wrap" style={{color:'#555'}}>{form.sent_by_name || ('by user #' + form.sent_by)}</div>
              </div>
            )}
            {form.whatsapp_sent_at && (
              <div className="cf-field">
                <label className="cf-label">WhatsApp Sent</label>
                <div className="cf-input-wrap" style={{color:'#555'}}>{formatDisplayDateTime(form.whatsapp_sent_at)}</div>
              </div>
            )}
          </div>
        </div>

        {isEdit && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{background:'#0E7C7B'}}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><line x1="12" y1="6" x2="12" y2="20"/><polyline points="6 12 12 18 18 6"/></svg>
              </div>
              <div><div className="cf-section-title">Timeline</div><div className="cf-section-sub">Key verification milestones</div></div>
            </div>
            <div className="cf-body">
              <div style={{display:'grid','gridTemplateColumns':'repeat(4,1fr)',gap:10,maxWidth:'880px'}}>
                {[
                  {label:'Assigned', value: form.assigned_at},
                  {label:'Submitted', value: form.submitted_at},
                  {label:'Approved', value: form.approved_at},
                  {label:'Complainant Notified', value: form.appeared_at},
                ].map(t => (
                  <div key={t.label} className="cf-field" style={{marginBottom:0}}>
                    <label className="cf-label">{t.label}</label>
                    <div className="cf-input-wrap" style={{background:'#F7F8FA',padding:'8px 10px',borderRadius:6,fontSize:13,color:'#333'}}>{t.value ? new Date(t.value).toLocaleString() : '—'}</div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}

        {isEdit && <OfficerHistoryPanel endpoint={`/verifications/${id}/officer-history`} />}

        {canSubmitToCi && (
          <div style={{marginTop:16,padding:'12px 14px',background:'#eff6ff',border:'1px solid #bfdbfe',borderRadius:8,fontSize:13,color:'#1e3a5f'}}>
            Report + Recommendation complete hone ke baad <strong>Submit to Circle Incharge</strong> dabayein — yeh same circle ke CI ko chali jayegi.
          </div>
        )}

        <div style={{display:'flex',gap:12,justifyContent:'flex-end',marginTop:20,flexWrap:'wrap'}}>
          <button type="button" className="btn btn-outline" onClick={() => navigate('/verifications')}>Cancel</button>
          <button type="submit" className="btn btn-primary" disabled={saving || submittingCi} style={{background:'#64748b',color:'#fff',padding:'12px 24px',fontWeight:600,borderRadius:'8px',border:'none'}}>
            {saving ? 'Saving...' : (isEdit ? 'Save Draft' : 'Assign Verification')}
          </button>
          {canSubmitToCi && (
            <button
              type="button"
              className="btn btn-primary"
              disabled={saving || submittingCi}
              onClick={handleSubmitToCircleIncharge}
              style={{background:'#015C94',color:'#fff',padding:'12px 24px',fontWeight:700,borderRadius:'8px',border:'none',boxShadow:'0 2px 8px rgba(1,92,148,0.35)'}}
            >
              {submittingCi ? 'Submitting...' : 'Submit to Circle Incharge'}
            </button>
          )}
        </div>
      </form>
    </div>
  );
}

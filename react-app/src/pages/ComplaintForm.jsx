import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import SearchableSelect from '../components/SearchableSelect';
import PdfAutoFillBar from '../components/PdfAutoFillBar';
import LiveCameraCaptureModal from '../components/LiveCameraCaptureModal';
import { mapExtractToComplaintForm, matchLookupValue } from '../utils/fillFromPdf';
import { countryCodes } from '../data/countries';
import { canAssignVerification, hasRole } from '../utils/permissions';
import { preparePrintWindow, writePrintWindow, closePrintWindow } from '../utils/print';

const SCRUTINY_OPTIONS = [
  { value: 'complete', name: 'Complete (Generate Tracking No)' },
  { value: 'incomplete', name: 'Incomplete' },
  { value: 'invalid', name: 'Invalid' },
  { value: 'irrelevant', name: 'Irrelevant' },
];

const RECEIVED_VIA_OPTIONS = [
  { value: 'Email', name: 'Email' },
  { value: 'Telephone', name: 'Telephone' },
  { value: 'Postal Service', name: 'Postal Service' },
  { value: 'Mobile Apps', name: 'Mobile Apps' },
  { value: 'Online Form', name: 'Online Form' },
  { value: 'In person', name: 'In person' },
  { value: 'Online Platform', name: 'Online Platform' },
  { value: 'Government Department', name: 'Government Department' },
  { value: 'Source Report', name: 'Source Report' },
  { value: 'Within the Organization', name: 'Within the Organization' },
];

const RECEIVED_FROM_OPTIONS = [
  { value: 'Government Sector', name: 'Government Sector' },
  { value: 'President Office', name: 'President Office' },
  { value: 'PM Office', name: 'PM Office' },
  { value: 'Apex Courts', name: 'Apex Courts' },
  { value: 'Ministries', name: 'Ministries' },
  { value: 'Source Reports', name: 'Source Reports' },
  { value: 'National & International (Tipline)', name: 'National & International (Tipline)' },
  { value: 'Other Departments', name: 'Other Departments' },
  { value: 'Private Sector', name: 'Private Sector' },
  { value: 'Banks', name: 'Banks' },
  { value: 'Organizations', name: 'Organizations' },
  { value: 'Universities', name: 'Universities' },
  { value: 'Companies', name: 'Companies' },
  { value: 'AD', name: 'AD' },
  { value: 'DD', name: 'DD' },
  { value: 'ADG', name: 'ADG' },
  { value: 'DG', name: 'DG' },
  { value: 'NGOs', name: 'NGOs' },
  { value: 'Other Offices', name: 'Other Offices' },
  { value: 'General Public', name: 'General Public' },
];

const GENDER_OPTIONS = [
  { value: 'Male', name: 'Male' },
  { value: 'Female', name: 'Female' },
  { value: 'Other', name: 'Other' },
];

const PLATFORM_OPTIONS = [
  'Facebook', 'Instagram', 'WhatsApp', 'TikTok', 'Twitter', 'Youtube', 'Telegram', 'Website Address',
];

const CRIME_MEDIUM_OPTIONS = [
  'Social Media Accounts', 'Gmail', 'ATM', 'Credit Card', 'IBFT', 'Online Banking',
  'Jazz Cash', 'Easy Paisa', 'Upaisa', 'UBL Omni', 'Crypto Currency',
  'Email', 'Website', 'OLX', 'Others',
];

const EVIDENCE_OPTIONS = [
  'Application', 'CNIC', 'Screenshots', 'Chat / Conversation', 'Email',
  'Transaction Receipt', 'Bank Statement', 'Others',
];

const EMPTY_ACCUSED = {
  name: '', father_name: '', mobile_no: '', cnic: '', email: '', social_media_url: '', other_info: '', description: '',
  cnic_front: '', cnic_back: '', picture: '', passport_attachment: '',
  cnic_front_url: '', cnic_back_url: '', picture_url: '', passport_attachment_url: '',
};

const ACCUSED_DOC_FIELDS = [
  { key: 'cnic_front', label: 'CNIC Front', accept: '.jpg,.jpeg,.png,.pdf' },
  { key: 'cnic_back', label: 'CNIC Back', accept: '.jpg,.jpeg,.png,.pdf' },
  { key: 'picture', label: 'Photo', accept: 'image/*' },
  { key: 'passport_attachment', label: 'Passport', accept: '.jpg,.jpeg,.png,.pdf' },
];

const initialForm = {
  complainant_name: '',
  father_name: '',
  cnic: '',
  contact_no: '',
  whatsapp_no: '',
  gender: '',
  email: '',
  contact_country_code: '+92',
  nationality: 'Pakistani',
  passport_no: '',
  address: '',
  post_address: '',
  district: '',
  profession: '',
  report_date: '',
  reporting_time: '',
  diary_no: '',
  received_via: '',
  received_from: '',
  cmu: '',
  priority_type: 'regular',
  offence_type: '',
  crime_mediums: [],
  amount_involved: '',
  bank_name_sender: '',
  bank_name_receiver: '',
  account_no_sender: '',
  account_no_receiver: '',
  transaction_date: '',
  occurrence_date: '',
  laws: [],
  description: '',
  platforms: [],
  platform_profile_page: '',
  platform_username: '',
  platform_email_involved: '',
  platform_mobile_involved: '',
  evidence: [],
  initial_accused: [],
  entry_time: '',
  source: '',
  scrutiny_result: 'complete',
  verification_officer_id: '',
  assign_priority_type: 'normal',
};

const PRIORITY_OPTIONS = [
  { value: 'normal', name: 'Normal' },
  { value: 'high', name: 'High' },
  { value: 'critical', name: 'Critical' },
];

const ARRAY_FORM_FIELDS = ['laws', 'evidence', 'platforms', 'crime_mediums'];

export default function ComplaintForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [form, setForm] = useState(initialForm);
  const [offenceTypes, setOffenceTypes] = useState([]);
  const [circleOptions, setCircleOptions] = useState([]);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [attachmentFiles, setAttachmentFiles] = useState([]);
  const [cnicFrontFile, setCnicFrontFile] = useState(null);
  const [cnicBackFile, setCnicBackFile] = useState(null);
  const [passportFile, setPassportFile] = useState(null);
  const [pictureFile, setPictureFile] = useState(null);
  const [cameraModal, setCameraModal] = useState({ open: false, title: '', onCapture: null });
  const [existingDocs, setExistingDocs] = useState({
    cnic_front_url: '',
    cnic_back_url: '',
    passport_attachment_url: '',
    picture_url: '',
  });
  const [existingAttachment, setExistingAttachment] = useState(null);

  const { user } = useAuth();
  const isOperator = (hasRole(user, 'operator') || hasRole(user, 'front_desk_officer')) && !hasRole(user, 'admin') && !hasRole(user, 'circle_incharge');
  const showAssignVo = canAssignVerification(user);
  const [voOfficers, setVoOfficers] = useState([]);
  const [hasVerification, setHasVerification] = useState(false);

  const set = (field) => (e) => setForm(f => ({ ...f, [field]: e.target.value }));

  const setCNIC = (e) => {
    let val = e.target.value.replace(/\D/g, '');
    if (val.length > 5) val = val.slice(0, 5) + '-' + val.slice(5);
    if (val.length > 13) val = val.slice(0, 13) + '-' + val.slice(13);
    setForm(f => ({ ...f, cnic: val }));
  };

  const setPhone = (e) => {
    let val = e.target.value.replace(/\D/g, '').replace(/^0+/, '');
    if (val.length > 10) val = val.slice(0, 10);
    setForm(f => ({ ...f, contact_no: val }));
  };

  const setWhatsApp = (e) => {
    let val = e.target.value.replace(/\D/g, '').replace(/^0+/, '');
    if (val.length > 10) val = val.slice(0, 10);
    setForm(f => ({ ...f, whatsapp_no: val }));
  };

  const toggleArray = (field, value) => {
    setForm(f => {
      const arr = f[field] || [];
      return {
        ...f,
        [field]: arr.includes(value) ? arr.filter(x => x !== value) : [...arr, value],
      };
    });
  };

  const addInitialAccused = () => {
    setForm(f => ({ ...f, initial_accused: [{ ...EMPTY_ACCUSED }, ...(f.initial_accused || [])] }));
  };

  const removeInitialAccused = (index) => {
    setForm(f => ({ ...f, initial_accused: f.initial_accused.filter((_, i) => i !== index) }));
  };

  const updateInitialAccused = (index, field, value) => {
    setForm(f => ({
      ...f,
      initial_accused: f.initial_accused.map((a, i) => (i === index ? { ...a, [field]: value } : a)),
    }));
  };

  const formatAccusedCnic = (index, raw) => {
    let val = raw.replace(/\D/g, '');
    if (val.length > 5) val = val.slice(0, 5) + '-' + val.slice(5);
    if (val.length > 13) val = val.slice(0, 13) + '-' + val.slice(13);
    updateInitialAccused(index, 'cnic', val);
  };

  const formatAccusedMobile = (index, raw) => {
    let val = raw.replace(/\D/g, '').replace(/^0+/, '');
    if (val.length > 10) val = val.slice(0, 10);
    updateInitialAccused(index, 'mobile_no', val);
  };

  const parseInitialAccused = (value) => {
    if (!value) return [];
    if (typeof value === 'string') {
      try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
      } catch {
        return [];
      }
    }
    return Array.isArray(value) ? value : [];
  };

  const addInitialAccusedDoc = (index, key, file) => {
    updateInitialAccused(index, key, file || '');
  };

  useEffect(() => {
    if (!id && user) {
      const now = new Date();
      const pad = n => String(n).padStart(2, '0');
      const local = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
      setForm(f => ({
        ...f,
        operator_name: user.name || '',
        operator_designation: user.designation || '',
        entry_time: local,
        reporting_time: local,
      }));
    }
  }, [id, user]);

  useEffect(() => {
    api.get('/offence-types').then(r => setOffenceTypes(r.data.data || r.data)).catch(() => {});
    api.get('/lookup/circles').then(r => {
      const all = r.data.data || r.data || [];
      const list = Array.isArray(all) ? all : [];
      setCircleOptions(list.map(c => ({ value: c.name, name: c.name + (c.code ? ` (${c.code})` : '') })));
    }).catch(() => {});
    if (showAssignVo) {
      api.get('/lookup/verification-officers').then(r => {
        const all = r.data.data || r.data;
        setVoOfficers(Array.isArray(all) ? all : []);
      }).catch(() => {});
    }
    if (id) {
      api.get(`/complaints/${id}`).then(r => {
        const d = r.data.data || r.data;
        if (d.contact_no) d.contact_no = d.contact_no.replace(/\D/g, '').replace(/^0+/, '');
        if (d.whatsapp_no) d.whatsapp_no = d.whatsapp_no.replace(/\D/g, '').replace(/^0+/, '');
        if (!d.contact_country_code) d.contact_country_code = '+92';
        setHasVerification(!!d.verification);
        
        if (isOperator && (d.status === 'complete' || !!d.verification)) {
          alert('You cannot edit a complete or assigned complaint.');
          navigate('/');
          return;
        }

        const parsePlatformObj = (val) => {
          if (!val) return {};
          if (typeof val === 'string' && val.startsWith('{')) {
            try { return JSON.parse(val); } catch(e) {}
          }
          return val;
        };

        setForm({
          ...initialForm,
          ...d,
          platform_profile_page: parsePlatformObj(d.platform_profile_page),
          platform_username: parsePlatformObj(d.platform_username),
          laws: d.laws || [],
          evidence: d.evidence || [],
          platforms: d.platforms || [],
          crime_mediums: d.crime_mediums || [],
          initial_accused: parseInitialAccused(d.initial_accused),
          verification_officer_id: d.verification?.verification_officer_id || '',
          assign_priority_type: d.verification?.priority_type || d.priority_type || 'normal',
        });
        if (d.attachment_url) setExistingAttachment(d.attachment_url);
        setExistingDocs({
          cnic_front_url: d.cnic_front_url || '',
          cnic_back_url: d.cnic_back_url || '',
          passport_attachment_url: d.passport_attachment_url || '',
          picture_url: d.picture_url || '',
        });
      }).catch(() => navigate(isOperator ? '/' : '/complaints'));
    }
  }, [id, navigate, showAssignVo, isOperator]);

  const errText = (err) => {
    if (!err) return '';
    if (Array.isArray(err)) return err.filter(Boolean).join(' ');
    if (typeof err === 'object') return Object.values(err).flat().filter(Boolean).join(' ');
    return String(err);
  };

  const normalizeEmail = (raw) => {
    if (!raw) return '';
    const v = String(raw).trim().replace(/,/g, '.');
    if (/^(na|n\/a|nil|none|-)$/i.test(v)) return '';
    return v;
  };

  const handleSubmit = async (e, action = null) => {
    e.preventDefault();
    setSaving(true);
    setErrors({});
    setServerError('');

    const currentScrutiny = action || form.scrutiny_result;

    const fixedEmail = normalizeEmail(form.email);
    if (fixedEmail !== form.email) {
      setForm(f => ({ ...f, email: fixedEmail }));
    }

    const localErrors = {};
    if (fixedEmail && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fixedEmail)) {
      localErrors.email = 'Email invalid — example: name@gmail.com (dot use karein, comma nahi).';
    }
    if (form.cnic && !/^\d{5}-\d{7}-\d$/.test(form.cnic)) {
      localErrors.cnic = 'CNIC format: XXXXX-XXXXXXX-X';
    }

    const needsVo = currentScrutiny === 'complete' && showAssignVo && !hasVerification;
    if (needsVo && !form.verification_officer_id) {
      localErrors.verification_officer_id = 'Verification Officer select karein (Complete Registration).';
    }

    if (Object.keys(localErrors).length) {
      setErrors(localErrors);
      setServerError(Object.values(localErrors).join(' '));
      setSaving(false);
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    try {
      const fd = new FormData();
      const payload = { ...form, email: fixedEmail, scrutiny_result: currentScrutiny };
      if (!payload.entry_time) {
        payload.entry_time = new Date().toISOString().slice(0, 16);
      }
      if (!needsVo) {
        delete payload.verification_officer_id;
        delete payload.assign_priority_type;
      }
      const skipKeys = new Set([
        'attachment', 'attachment_url',
        'cnic_front', 'cnic_front_url', 'cnic_back', 'cnic_back_url',
        'passport_attachment', 'passport_attachment_url', 'picture', 'picture_url',
        'verification', 'enquiry', 'workflow', 'created_at', 'updated_at',
        'progress_percent', 'progress_stage', 'id',
      ]);
      Object.entries(payload).forEach(([k, v]) => {
        if (skipKeys.has(k)) return;
        if (k === 'platform_profile_page' || k === 'platform_username') {
          fd.append(k, typeof v === 'object' && v !== null ? JSON.stringify(v) : (v || ''));
          return;
        }
        if (ARRAY_FORM_FIELDS.includes(k) && Array.isArray(v)) {
          v.forEach(item => fd.append(`${k}[]`, item));
          return;
        }
        if (k === 'initial_accused') {
          if (Array.isArray(v) && v.length > 0) {
            const accusedClean = v.map((a) => {
              const o = { ...a };
              ACCUSED_DOC_FIELDS.forEach(({ key }) => {
                if (o[key] instanceof File) delete o[key];
              });
              return o;
            });
            fd.append('initial_accused', JSON.stringify(accusedClean));
            v.forEach((a, i) => {
              ACCUSED_DOC_FIELDS.forEach(({ key }) => {
                if (a[key] instanceof File) {
                  const input = {
                    cnic_front: 'accused_cnic_front',
                    cnic_back: 'accused_cnic_back',
                    picture: 'accused_picture',
                    passport_attachment: 'accused_passport',
                  }[key];
                  if (input) fd.append(`${input}[${i}]`, a[key]);
                }
              });
            });
          }
          return;
        }
        if (v !== null && v !== undefined && v !== '' && typeof v !== 'object') {
          fd.append(k, v);
        }
      });
      if (attachmentFiles && attachmentFiles.length > 0) {
        attachmentFiles.forEach(file => fd.append('attachment[]', file));
      }
      if (cnicFrontFile) fd.append('cnic_front', cnicFrontFile);
      if (cnicBackFile) fd.append('cnic_back', cnicBackFile);
      if (passportFile) fd.append('passport_attachment', passportFile);
      if (pictureFile) fd.append('picture', pictureFile);
      if (id) {
        // PHP does not populate multipart files on real PUT — spoof method
        fd.append('_method', 'PUT');
        await api.post(`/complaints/${id}`, fd);
      } else {
        const res = await api.post('/complaints', fd);
        const wa = res.data?.complainant_notify?.whatsapp_url;
        if (wa) {
          window.open(wa, '_blank');
        } else if (res.data?.data?.tracking_no && !res.data?.complainant_notify?.phone) {
          alert('Complaint registered (' + res.data.data.tracking_no + '), but complainant phone missing — WhatsApp message nahi bhej saka.');
        }
      }
      navigate(isOperator ? '/' : '/complaints');
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        const flat = {};
        Object.entries(res.errors).forEach(([k, v]) => {
          flat[k] = Array.isArray(v) ? v.join(' ') : String(v);
        });
        setErrors(flat);
        setServerError(Object.values(flat).join(' ') || 'Please fix the highlighted fields below.');
      } else {
        setServerError(res?.message || 'Error saving complaint. Please try again.');
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    } finally {
      setSaving(false);
    }
  };

  const printReport = async () => {
    if (!id) return;
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/complaints/${id}/report`);
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || 'Could not generate report.');
    }
  };

  const renderField = (label, field, opts = {}) => {
    const { type = 'text', placeholder = '', required = false, options = null, rows = null, readOnly = false } = opts;
    const fieldErr = errors[field];
    return (
      <div className="cf-field">
        <label className={`cf-label${required ? ' required' : ''}`}>{label}</label>
        {readOnly ? (
          <div className="cf-input" style={{ padding: '9px 14px', background: '#f5f5f5', color: '#555', borderRadius: 'var(--border-radius-sm)', fontSize: 13, border: fieldErr ? '1.5px solid #e53e3e' : '1.5px solid var(--border)' }}>
            {form[field] || '-'}
          </div>
        ) : options ? (
          <SearchableSelect
            value={form[field]}
            onChange={v => setForm(f => ({ ...f, [field]: v }))}
            options={options}
            placeholder={'Select ' + label}
            valueKey="value"
            labelKey="name"
          />
        ) : rows ? (
          <textarea className="cf-input" value={form[field]} onChange={set(field)} placeholder={placeholder} rows={rows} required={required} style={fieldErr ? { borderColor: '#e53e3e' } : {}} />
        ) : (
          <input type={type} className="cf-input" value={form[field]} onChange={set(field)} placeholder={placeholder} required={required} style={fieldErr ? { borderColor: '#e53e3e' } : {}} />
        )}
        {fieldErr && <div className="cf-error">{errText(fieldErr)}</div>}
      </div>
    );
  };

  const renderCheckboxGroup = (label, field, options) => {
    const fieldErr = errors[field];
    return (
      <div className="cf-field">
        <label className="cf-label">{label}</label>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))', gap: '8px 16px' }}>
          {options.map(opt => (
            <label key={opt} style={{ display: 'flex', alignItems: 'center', gap: '7px', cursor: 'pointer', fontSize: 13 }}>
              <input
                type="checkbox"
                checked={(form[field] || []).includes(opt)}
                onChange={() => toggleArray(field, opt)}
                style={{ accentColor: '#264078' }}
              />
              {opt}
            </label>
          ))}
        </div>
        {fieldErr && <div className="cf-error">{errText(fieldErr)}</div>}
      </div>
    );
  };

  const handlePdfFilled = (extracted, file) => {
    const mapped = mapExtractToComplaintForm(extracted);
    setForm(prev => {
      const next = { ...prev };
      Object.entries(mapped).forEach(([k, v]) => {
        if (v !== '' && v != null) {
          if (k === 'initial_accused' && Array.isArray(v) && v.length > 0) {
            next[k] = v;
          } else {
            next[k] = v;
          }
        }
      });
      if (extracted.crime_category && offenceTypes?.length) {
        const matchedOffence = matchLookupValue(offenceTypes, extracted.crime_category);
        if (matchedOffence) next.offence_type = matchedOffence;
      }
      return next;
    });
    if (file) {
      setAttachmentFile(file);
    }
  };

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">{id ? 'Edit Registration' : 'Complete Registration'}</h1>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginTop: 4 }}>
            <p className="page-subtitle" style={{ margin: 0 }}>{id ? 'Update complaint / registration details' : 'Complaint registration + assign verification officer'}</p>
            <span style={{
              background: '#015C94',
              color: '#fff',
              fontSize: '12px',
              fontWeight: 600,
              padding: '3px 12px',
              borderRadius: '999px',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px'
            }}>
              🏢 Station: NCCIA {user?.circle?.name || user?.circle_name || user?.circle_code || 'Station Central'}
            </span>
          </div>
          <div className="title-underline"></div>
        </div>
      </div>

      {!id && !isOperator && (
        <PdfAutoFillBar
          onFilled={handlePdfFilled}
          hint="Upload verification report PDF (e.g. CCW-C-80519/25) — Name, CNIC, Phone, Address, Offence, Amount, and Accused details will auto-fill automatically."
        />
      )}

      <form onSubmit={handleSubmit} noValidate>
        {serverError && (
          <div className="cf-alert cf-alert-error" style={{ marginBottom: 16 }}>{serverError}</div>
        )}
        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <div><div className="cf-section-title">Complainant Information</div><div className="cf-section-sub">Personal details of the complainant</div></div>
            <div className="cf-section-badge">Step 1</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              {renderField('Complainant Name', 'complainant_name', { required: true })}
              {renderField('Father Name', 'father_name')}
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">CNIC</label>
                <input type="text" className="cf-input" value={form.cnic} onChange={setCNIC} placeholder="XXXXX-XXXXXXX-X" required maxLength={15} />
                {errors.cnic && <div className="cf-error">{errText(errors.cnic)}</div>}
              </div>
              {renderField('Gender', 'gender', { options: GENDER_OPTIONS })}
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">Contact No</label>
                <div style={{ display: 'flex', gap: '8px' }}>
                  <select className="cf-input" value={form.contact_country_code} onChange={set('contact_country_code')} style={{ width: '190px' }}>
                    {countryCodes.map(c => <option key={c.code + c.name} value={c.code}>{c.code} — {c.name}</option>)}
                  </select>
                  <input type="text" className="cf-input" value={form.contact_no} onChange={setPhone} placeholder="3XXXXXXXXX" required maxLength={12} style={{ flex: 1 }} />
                </div>
                {errors.contact_no && <div className="cf-error">{errText(errors.contact_no)}</div>}
              </div>
              <div className="cf-field">
                <label className="cf-label">WhatsApp No</label>
                <div style={{ display: 'flex', gap: '8px' }}>
                  <select className="cf-input" value={form.contact_country_code} onChange={set('contact_country_code')} style={{ width: '190px' }}>
                    {countryCodes.map(c => <option key={c.code + c.name} value={c.code}>{c.code} — {c.name}</option>)}
                  </select>
                  <input type="text" className="cf-input" value={form.whatsapp_no} onChange={setWhatsApp} placeholder="3XXXXXXXXX" maxLength={12} style={{ flex: 1 }} />
                </div>
                {errors.whatsapp_no && <div className="cf-error">{errText(errors.whatsapp_no)}</div>}
              </div>
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">Email</label>
                <input
                  type="text"
                  inputMode="email"
                  autoComplete="email"
                  className="cf-input"
                  value={form.email}
                  onChange={set('email')}
                  onBlur={() => setForm(f => ({ ...f, email: normalizeEmail(f.email) }))}
                  placeholder="name@gmail.com"
                  style={errors.email ? { borderColor: '#e53e3e' } : {}}
                />
                {errors.email && <div className="cf-error">{errText(errors.email)}</div>}
              </div>
              {renderField('Profession', 'profession')}
            </div>
            <div className="cf-row-2">
              {renderField('District', 'district')}
              <div className="cf-field">
                <label className="cf-label">Nationality</label>
                <select className="cf-input" value={form.nationality || 'Pakistani'} onChange={set('nationality')}>
                  <option value="Pakistani">Pakistani</option>
                  <option value="Dual Nationality Holder">Dual Nationality Holder</option>
                  <option value="Foreigner">Foreigner</option>
                </select>
                {errors.nationality && <div className="cf-error">{errors.nationality}</div>}
              </div>
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">Passport No {(['Dual Nationality Holder', 'Foreigner'].includes(form.nationality)) && <span style={{ color: '#e53e3e' }}>*</span>}</label>
                <input type="text" className="cf-input" value={form.passport_no} onChange={set('passport_no')} placeholder={(['Dual Nationality Holder', 'Foreigner'].includes(form.nationality)) ? 'Passport number required' : 'Optional for Pakistani nationals'} required={['Dual Nationality Holder', 'Foreigner'].includes(form.nationality)} />
                {errors.passport_no && <div className="cf-error">{errors.passport_no}</div>}
              </div>
            </div>
            <div className="cf-row-2">
              {renderField('Address', 'address', { rows: 2, required: true })}
              {renderField('Postal Address', 'post_address', { rows: 2 })}
            </div>
            <div style={{ marginTop: 16 }}>
              <div className="cf-label" style={{ fontWeight: 700, marginBottom: 8 }}>Identity Documents</div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
                {[
                  { key: 'cnic_front', label: 'CNIC Front', file: cnicFrontFile, setFile: setCnicFrontFile, url: existingDocs.cnic_front_url, accept: '.jpg,.jpeg,.png,.pdf' },
                  { key: 'cnic_back', label: 'CNIC Back', file: cnicBackFile, setFile: setCnicBackFile, url: existingDocs.cnic_back_url, accept: '.jpg,.jpeg,.png,.pdf' },
                  { key: 'passport_attachment', label: 'Passport', file: passportFile, setFile: setPassportFile, url: existingDocs.passport_attachment_url, accept: '.jpg,.jpeg,.png,.pdf' },
                  { key: 'picture', label: 'Photo', file: pictureFile, setFile: setPictureFile, url: existingDocs.picture_url, accept: 'image/*' },
                ].map(doc => (
                  <div key={doc.key} className="cf-field">
                    <label className="cf-label">{doc.label}</label>
                    <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                      <input type="file" className="cf-input" accept={doc.accept} onChange={e => doc.setFile(e.target.files?.[0] || null)} />
                      {doc.key === 'picture' && (
                        <button
                          type="button"
                          onClick={() => setCameraModal({
                            open: true,
                            title: 'Capture Complainant Photo',
                            onCapture: (file) => setPictureFile(file)
                          })}
                          className="btn btn-outline btn-sm"
                          style={{ height: 38, padding: '0 10px', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 4, whiteSpace: 'nowrap', borderColor: '#015C94', color: '#015C94' }}
                          title="Open Live Webcam / Camera"
                        >
                          📷 Camera
                        </button>
                      )}
                    </div>
                    {doc.file ? (
                      <span style={{ fontSize: 12, color: '#38a169', marginTop: 4, display: 'block' }}>Selected: {doc.file.name}</span>
                    ) : doc.url ? (
                      <a href={doc.url} target="_blank" rel="noreferrer" style={{ fontSize: 12, color: '#015C94', fontWeight: 600, marginTop: 4, display: 'inline-block' }}>Current file ↗</a>
                    ) : null}
                    {errors[doc.key] && <div className="cf-error">{errors[doc.key]}</div>}
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{ background: '#264078' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            </div>
            <div><div className="cf-section-title">Digital / Online Identity</div><div className="cf-section-sub">Platforms and online accounts involved</div></div>
            <div className="cf-section-badge">Step 2</div>
          </div>
          <div className="cf-body">
            {renderCheckboxGroup('Platforms Used', 'platforms', PLATFORM_OPTIONS)}
            
            {form.platforms?.map(plat => (
              <div key={plat} className="cf-row-2" style={{marginTop: 12, padding: 12, border: '1px solid #e2e8f0', borderRadius: 8}}>
                <div className="cf-field">
                  <label className="cf-label">{plat} Profile Page URL</label>
                  <input className="cf-input" value={(typeof form.platform_profile_page === 'object' ? form.platform_profile_page : {})[plat] || ''} onChange={e => setForm(f => ({...f, platform_profile_page: {...(typeof f.platform_profile_page === 'object' ? f.platform_profile_page : {}), [plat]: e.target.value}}))} placeholder="URL or profile link" />
                </div>
                <div className="cf-field">
                  <label className="cf-label">{plat} User Name</label>
                  <input className="cf-input" value={(typeof form.platform_username === 'object' ? form.platform_username : {})[plat] || ''} onChange={e => setForm(f => ({...f, platform_username: {...(typeof f.platform_username === 'object' ? f.platform_username : {}), [plat]: e.target.value}}))} placeholder="User name" />
                </div>
              </div>
            ))}

            {(!form.platforms || form.platforms.length === 0) && typeof form.platform_profile_page === 'string' && form.platform_profile_page && !form.platform_profile_page.startsWith('{') && (
              <div className="cf-row-2" style={{marginTop: 12}}>
                {renderField('Profile Page', 'platform_profile_page', { placeholder: 'URL or profile link' })}
                {renderField('User Name', 'platform_username')}
              </div>
            )}

          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
            </div>
            <div><div className="cf-section-title">Complaint Details</div><div className="cf-section-sub">Receipt and nature of the complaint</div></div>
            <div className="cf-section-badge">Step 3</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              {renderField('Report Date', 'report_date', { type: 'date', required: true })}
              {renderField('Reporting Time', 'reporting_time', { type: 'datetime-local', readOnly: true })}
            </div>
            <div className="cf-row-2">
              {renderField('Received Via', 'received_via', { required: true, options: RECEIVED_VIA_OPTIONS })}
              {renderField('Received From', 'received_from', { required: true, options: RECEIVED_FROM_OPTIONS })}
            </div>
            <div className="cf-row-3">
              {renderField('CCRC', 'cmu', { options: circleOptions })}
              {renderField('Priority', 'priority_type', { options: PRIORITY_OPTIONS })}
              {renderField('Incident Occurrence Date', 'occurrence_date', { type: 'date', required: true })}
            </div>
            <div className="cf-row-2">
              {renderField('Crime Category', 'offence_type', { required: true, options: offenceTypes })}
            </div>
            {renderCheckboxGroup('Crime Medium', 'crime_mediums', CRIME_MEDIUM_OPTIONS)}
            {renderField('Description', 'description', { rows: 4, required: true })}
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{ background: '#0E7C7B' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div><div className="cf-section-title">Financial Details</div><div className="cf-section-sub">Amount and transaction information</div></div>
            <div className="cf-section-badge">Step 4</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-3">
              {renderField('Amount Involved', 'amount_involved', { type: 'number' })}
              {renderField('Transaction Date', 'transaction_date', { type: 'date' })}
            </div>
            <div className="cf-row-2">
              {renderField('Bank Name (Sender)', 'bank_name_sender')}
              {renderField('Account No (Sender)', 'account_no_sender')}
            </div>
            <div className="cf-row-2">
              {renderField('Bank Name (Receiver)', 'bank_name_receiver')}
              {renderField('Account No (Receiver)', 'account_no_receiver')}
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{ background: '#015C94' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div><div className="cf-section-title">Accused Detail (if any)</div><div className="cf-section-sub">Known accused details at registration</div></div>
            <div className="cf-section-badge">Step 5</div>
          </div>
          <div className="cf-body">
            <p style={{ fontSize: 13, color: '#6c757d', marginTop: 0, marginBottom: 16 }}>Add accused details if known at this stage. You can add more during verification.</p>
            <div className="cf-repeater">
              <button type="button" className="btn btn-outline btn-sm" onClick={addInitialAccused} style={{ marginBottom: 16 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Accused
              </button>
              {(form.initial_accused || []).map((a, i) => (
                <div key={i} style={{ padding: '12px', marginBottom: '12px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field">
                      <label className="cf-label">Name</label>
                      <input type="text" className="cf-input" value={a.name} onChange={e => updateInitialAccused(i, 'name', e.target.value)} placeholder="Accused name" />
                    </div>
                    <div className="cf-field">
                      <label className="cf-label">Father Name</label>
                      <input type="text" className="cf-input" value={a.father_name} onChange={e => updateInitialAccused(i, 'father_name', e.target.value)} placeholder="Father's name" />
                    </div>
                    <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end' }} onClick={() => removeInitialAccused(i)}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field">
                      <label className="cf-label">CNIC</label>
                      <input type="text" className="cf-input font-mono" value={a.cnic} onChange={e => formatAccusedCnic(i, e.target.value)} maxLength={15} placeholder="00000-0000000-0" />
                    </div>
                    <div className="cf-field">
                      <label className="cf-label">Mobile No</label>
                      <input type="text" className="cf-input" value={a.mobile_no} onChange={e => formatAccusedMobile(i, e.target.value)} placeholder="3XXXXXXXXX" maxLength={10} />
                    </div>
                    <div className="cf-field">
                      <label className="cf-label">Email</label>
                      <input type="text" inputMode="email" className="cf-input" value={a.email} onChange={e => updateInitialAccused(i, 'email', e.target.value)} placeholder="email@example.com" />
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                    <div className="cf-field">
                      <label className="cf-label">Social Media URL</label>
                      <input type="text" className="cf-input" value={a.social_media_url} onChange={e => updateInitialAccused(i, 'social_media_url', e.target.value)} placeholder="Profile or page URL" />
                    </div>
                    <div className="cf-field">
                      <label className="cf-label">Other Info</label>
                      <input type="text" className="cf-input" value={a.other_info} onChange={e => updateInitialAccused(i, 'other_info', e.target.value)} placeholder="Any other details" />
                    </div>
                  </div>
                  <div className="cf-field">
                    <label className="cf-label">Description</label>
                    <textarea
                      className="cf-input cf-textarea"
                      rows={2}
                      value={a.description || ''}
                      onChange={e => updateInitialAccused(i, 'description', e.target.value)}
                      placeholder="Role, involvement, modus operandi — anything about this accused..."
                    />
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px', marginTop: '12px' }}>
                    {ACCUSED_DOC_FIELDS.map(doc => (
                      <div key={doc.key} className="cf-field">
                        <label className="cf-label">{doc.label}</label>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                          {!isOperator && <input type="file" className="cf-input" accept={doc.accept} onChange={e => addInitialAccusedDoc(i, doc.key, e.target.files?.[0] || '')} />}
                          {doc.key === 'picture' && !isOperator && (
                            <button
                              type="button"
                              onClick={() => setCameraModal({
                                open: true,
                                title: `Capture Photo for Accused #${i + 1} (${a.name || 'Accused'})`,
                                onCapture: (file) => addInitialAccusedDoc(i, 'picture', file)
                              })}
                              className="btn btn-outline btn-sm"
                              style={{ height: 38, padding: '0 10px', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 4, whiteSpace: 'nowrap', borderColor: '#015C94', color: '#015C94' }}
                              title="Open Live Webcam / Camera"
                            >
                              📷 Camera
                            </button>
                          )}
                        </div>
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
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{ background: '#805ad5' }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </div>
            <div><div className="cf-section-title">Supporting Documents</div><div className="cf-section-sub">Evidence types available with the complaint</div></div>
            <div className="cf-section-badge">Step 6</div>
          </div>
          <div className="cf-body">
            {renderCheckboxGroup('Evidence Available', 'evidence', EVIDENCE_OPTIONS)}
          </div>
        </div>

            {showAssignVo && (
              <div className="cf-section" style={{ marginTop: 16, border: '1px solid #bfdbfe', borderRadius: 10 }}>
                <div className="cf-section-header" style={{ background: 'rgba(1,92,148,0.06)' }}>
                  <div className="cf-section-icon" style={{ background: '#015C94' }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                  </div>
                  <div>
                    <div className="cf-section-title">Assign Verification Officer</div>
                    <div className="cf-section-sub">Complete Registration ke sath hi VO assign hota hai</div>
                  </div>
                  <div className="cf-section-badge">Required</div>
                </div>
                <div className="cf-body">
                  {hasVerification ? (
                    <div style={{ fontSize: 13, color: '#166534', background: '#f0fdf4', padding: '10px 12px', borderRadius: 8 }}>
                      Verification officer pehle se assign ho chuka hai.
                    </div>
                  ) : (
                    <div className="cf-row-2">
                      <div className="cf-field">
                        <label className="cf-label required">Verification Officer</label>
                        <SearchableSelect
                          value={form.verification_officer_id ? String(form.verification_officer_id) : ''}
                          onChange={v => setForm(f => ({ ...f, verification_officer_id: v != null ? String(v) : '' }))}
                          options={voOfficers.map(o => ({ ...o, id: String(o.id) }))}
                          placeholder="Select Verification Officer"
                          valueKey="id"
                          formatLabel={o => o.name + (o.designation ? ` (${o.designation})` : '')}
                        />
                        {errors.verification_officer_id && <div className="cf-error">{errors.verification_officer_id}</div>}
                      </div>
                      {renderField('Verification Priority', 'assign_priority_type', { required: true, options: PRIORITY_OPTIONS })}
                    </div>
                  )}
                </div>
              </div>
            )}

            <div className="cf-section" style={{ marginTop: 16 }}>
              <div className="cf-section-header">
                <div className="cf-section-icon" style={{ background: '#805ad5' }}>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                </div>
                <div><div className="cf-section-title">Attachments</div><div className="cf-section-sub">Additional supporting documents</div></div>
                <div className="cf-section-badge">Optional</div>
              </div>
              <div className="cf-body">
                <div className="cf-field">
                  <label className="cf-label">Other Supporting Documents</label>
                  {existingAttachment && (
                    <div style={{ marginBottom: 8, fontSize: 13 }}>
                      Current files:{' '}
                      {Array.isArray(existingAttachment) ? existingAttachment.map((url, i) => (
                         <div key={i}><a href={url} target="_blank" rel="noreferrer" style={{ color: '#015C94', fontWeight: 600 }}>Open attachment {i+1} ↗</a></div>
                      )) : (
                         <a href={existingAttachment} target="_blank" rel="noreferrer" style={{ color: '#015C94', fontWeight: 600 }}>Open attachment ↗</a>
                      )}
                    </div>
                  )}
                  <input
                    type="file"
                    multiple
                    className="cf-input"
                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx"
                    onChange={e => setAttachmentFiles(Array.from(e.target.files))}
                  />
                  <span className="cf-hint">Optional extra files (PDF, image, Word, Excel). Hold Ctrl to select multiple.</span>
                </div>
              </div>
            </div>

        {serverError && (
          <div className="cf-alert cf-alert-error">{serverError}</div>
        )}

        <div style={{ display: 'flex', gap: 12, justifyContent: 'flex-end', marginTop: 20 }}>
          {id && (
            <button type="button" className="btn btn-outline" onClick={printReport} disabled={saving}>
              Print Report
            </button>
          )}
          <button type="button" className="btn btn-outline" onClick={() => navigate(isOperator ? '/' : '/complaints')}>Cancel</button>
          <button type="button" className="btn btn-outline" onClick={(e) => handleSubmit(e, 'incomplete')} disabled={saving}>
            Save as Draft
          </button>
          <button type="submit" className="btn btn-primary" onClick={(e) => handleSubmit(e, 'complete')} disabled={saving}>
            {saving ? 'Saving...' : (id ? 'Update Registration' : 'Complete Registration')}
          </button>
        </div>
      </form>

      <LiveCameraCaptureModal
        open={cameraModal.open}
        title={cameraModal.title}
        onClose={() => setCameraModal({ open: false, title: '', onCapture: null })}
        onCapture={(file) => {
          if (cameraModal.onCapture) cameraModal.onCapture(file);
        }}
      />
    </div>
  );
}

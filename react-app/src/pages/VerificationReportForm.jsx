import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import api from '../api';
import { countryCodes } from '../data/countries';
import { toLocalInput } from '../utils/datetime';
import PdfAutoFillBar from '../components/PdfAutoFillBar';
import LiveCameraCaptureModal from '../components/LiveCameraCaptureModal';
import { mapExtractToVerificationReport, matchLookupValue } from '../utils/fillFromPdf';
import { useAuth } from '../contexts/AuthContext';
import { hasRole } from '../utils/permissions';

const SearchableSelect = ({ options, value, onChange, placeholder, name, required }) => {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const ref = useRef();
  useEffect(() => {
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);
  const filtered = options.filter(o => o.toLowerCase().includes(search.toLowerCase()));
  return (
    <div className="cf-input-wrap" ref={ref} style={{ position: 'relative' }}>
      <input
        className="cf-input"
        placeholder={placeholder}
        value={open ? search : (value || '')}
        onFocus={() => { setOpen(true); setSearch(''); }}
        onChange={e => setSearch(e.target.value)}
        required={required && !value}
        autoComplete="off"
      />
      <input type="hidden" name={name} value={value || ''} />
      {open && (
        <div style={{ position: 'absolute', top: '100%', left: 0, right: 0, zIndex: 99, background: '#fff', border: '1px solid #ccc', maxHeight: 220, overflowY: 'auto', borderRadius: '0 0 6px 6px' }}>
          <div
            style={{ padding: '8px 12px', cursor: 'pointer', background: !value ? '#e8f0fe' : 'transparent', fontWeight: value ? 400 : 500 }}
            onClick={() => { onChange({ target: { value: '' } }); setOpen(false); setSearch(''); }}
          >— {placeholder} —</div>
          {filtered.map(c => (
            <div
              key={c}
              style={{ padding: '8px 12px', cursor: 'pointer', background: value === c ? '#e8f0fe' : 'transparent', fontWeight: value === c ? 600 : 400 }}
              onClick={() => { onChange({ target: { value: c } }); setOpen(false); setSearch(''); }}
            >{c}</div>
          ))}
          {filtered.length === 0 && <div style={{ padding: '8px 12px', color: '#999' }}>No city found</div>}
        </div>
      )}
    </div>
  );
};

const RECOMMENDATION_OPTIONS = [
  { value: 'enquiry_registration', name: 'Enquiry Registration' },
  { value: 'closure', name: 'Closure' },
  { value: 'merge', name: 'Merge' },
  { value: 'transfer', name: 'Transfer' },
];

const CLOSURE_REASONS = [
  { value: 'non_pursuance', name: 'Non-pursuance by Complainant' },
  { value: 'irrelevant', name: 'Irrelevant' },
  { value: 'invalid', name: 'Invalid' },
  { value: 'lack_of_evidence', name: 'Lack of Evidence' },
];

const majorCities = [
  'Islamabad', 'Rawalpindi', 'Murree',
  // Punjab
  'Lahore', 'Faisalabad', 'Multan', 'Gujranwala', 'Sialkot', 'Bahawalpur', 'Sargodha',
  'Sheikhupura', 'Rahim Yar Khan', 'Jhang', 'Gujrat', 'Kasur', 'Sahiwal', 'Okara',
  'Wah', 'Dera Ghazi Khan', 'Chakwal', 'Jhelum', 'Attock', 'Mianwali', 'Bhakkar',
  'Layyah', 'Muzaffargarh', 'Rajanpur', 'Khanewal', 'Vehari', 'Pakpattan',
  'Toba Tek Singh', 'Nankana Sahib', 'Narowal', 'Hafizabad', 'Mandi Bahauddin',
  'Sadiqabad', 'Bahawalnagar', 'Lodhran', 'Hasilpur', 'Yazman', 'Khanpur',
  'Gogera', 'Kamalia', 'Chiniot', 'Pattoki', 'Renala Khurd', 'Daska', 'Pasrur',
  'Kot Addu', 'Taunsa', 'Alipur', 'Arifwala', 'Burewala', 'Mailsi', 'Dunyapur',
  'Jalalpur Pirwala', 'Shujaabad', 'Qadirpur Ran', 'Makhdoompur', 'Talagang',
  'Pind Dadan Khan', 'Kallar Kahar', 'Choa Saidan Shah', 'Khushab', 'Noorpur Thal',
  'Jauharabad', 'Qaidabad', 'Risalpur', 'Wazirabad', 'Kharian', 'Sarai Alamgir',
  // Sindh
  'Karachi', 'Hyderabad', 'Sukkur', 'Larkana', 'Nawabshah', 'Khairpur',
  'Mirpur Khas', 'Jacobabad', 'Shikarpur', 'Dadu', 'Thatta', 'Badin', 'Sanghar',
  'Tando Allahyar', 'Tando Muhammad Khan', 'Umerkot', 'Matiari', 'Jamshoro',
  'Kashmore', 'Ghotki', 'Kandhkot', 'Shahdadkot', 'Qambar', 'Mehar', 'Moro',
  'Hala', 'Sakrand', 'Kotri', 'Sehwan', 'Rohri', 'Pano Aqil', 'Mirpur Mathelo',
  'Dharki', 'Sobhodero', 'Khipro', 'Diplo', 'Chhor', 'Samaro', 'Nabisar Road',
  // KPK
  'Peshawar', 'Mardan', 'Mingora', 'Abbottabad', 'Kohat', 'Bannu', 'Dera Ismail Khan',
  'Swat', 'Mansehra', 'Haripur', 'Charsadda', 'Nowshera', 'Swabi', 'Karak',
  'Lakki Marwat', 'Tank', 'Battagram', 'Shangla', 'Upper Dir', 'Lower Dir',
  'Bajaur', 'Mohmand', 'Khyber', 'Orakzai', 'Kurram', 'North Waziristan',
  'South Waziristan', 'Tor Ghar', 'Kohistan', 'Malakand', 'Buner', 'Hangu',
  'Chitral', 'Timergara', 'Kalam', 'Matta', 'Barikot', 'Saidu Sharif',
  // Balochistan
  'Quetta', 'Turbat', 'Khuzdar', 'Chaman', 'Sibi', 'Zhob', 'Gwadar', 'Panjgur',
  'Lasbela', 'Hub', 'Nushki', 'Dalbandin', 'Kharan', 'Mastung', 'Kalat',
  'Killa Saifullah', 'Killa Abdullah', 'Pishin', 'Ziarat', 'Harnai', 'Kohlu',
  'Dera Bugti', 'Barkhan', 'Musakhel', 'Sherani', 'Loralai', 'Bela', 'Pasni',
  'Ormara', 'Jiwani', 'Tump', 'Basima', 'Wadh', 'Dhadar', 'Sohbatpur',
  'Jhal Magsi', 'Gandava', 'Usta Muhammad', 'Sujawal', 'Shahdadkot',
  // Gilgit-Baltistan
  'Gilgit', 'Skardu', 'Hunza', 'Nagar', 'Khaplu', 'Shigar', 'Astore', 'Diamer',
  'Ghanche', 'Ghizer', 'Roundu', 'Kharmang', 'Aliabad', 'Sost', 'Chilas',
  'Dasu', 'Tangir', 'Darel', 'Ishkoman', 'Yasin', 'Gupis', 'Puniyal',
  // Azad Kashmir
  'Muzaffarabad', 'Mirpur', 'Rawalakot', 'Kotli', 'Bhimber', 'Pallandri',
  'Bagh', 'Haveli', 'Neelum', 'Athmuqam', 'Sharda', 'Kahuta', 'Mangla',
  'Dadyal', 'Jhelum Valley', 'Leepa', 'Samahni', 'Nakyal', 'Tatta Pani',
];

export default function VerificationReportForm() {
  const navigate = useNavigate();
  const { id } = useParams();
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const [complaints, setComplaints] = useState([]);
  const [crimeCategories, setCrimeCategories] = useState([]);
  const [saving, setSaving] = useState(false);
  const [submittingCi, setSubmittingCi] = useState(false);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [linkedVerificationId, setLinkedVerificationId] = useState(null);
  const [verificationStatus, setVerificationStatus] = useState('');
  const [cameraModal, setCameraModal] = useState({ open: false, title: '', onCapture: null });

  const isSupervisor = ['admin', 'circle_incharge', 'ad_legal', 'dd_legal', 'additional_director', 'director_general'].some(r => hasRole(user, r));
  const isVoLocked = !isSupervisor && !!id && ['submitted', 'approved', 'closed', 'merged', 'transferred'].includes(verificationStatus);

  const [form, setForm] = useState({
    tracking_no: '',
    complaint_id: '',
    registration_at: '',
    assignment_date: '',
    verification_date: toLocalInput(new Date()),
    victim_name: '',
    victim_father_name: '',
    victim_occupation: '',
    victim_gender: '',
    victim_cnic: '',
    victim_country_code: '+92',
    victim_phone: '',
    victim_email: '',
    crime_category: '',
    initial_crime_category: '',
    city: '',
    crime_description: '',
    accused_known: '0',
    accused: [],
    recommendation: '',
    closure_reason: '', merge_type: 'enquiry', merge_reference: '',
    recommendation_short: '',
    recommendation_full: '',
    comments: '',
    evidence: [], transactions: [{ bank: '', account: '', amount: '', date: '', file: null }],
    inquiry_no: '',
    case_no: '',
  });


  const setF = (field) => (e) => setForm(f => ({ ...f, [field]: e.target.value }));
  const setFNum = (field) => (e) => {
    let val = e.target.value.replace(/\D/g, '');
    if (field === 'victim_cnic') {
      val = val.slice(0,13);
      if (val.length > 5) val = val.slice(0,5) + '-' + val.slice(5);
      if (val.length > 13) val = val.slice(0,13) + '-' + val.slice(13);
    }
    if (field === 'victim_phone') {
      val = val.replace(/^0+/, '');
    }
    setForm(f => ({ ...f, [field]: val }));
  };

  const setPhone = (e) => {
    let val = e.target.value.replace(/\D/g, '').replace(/^0+/, '').slice(0,10);
    setForm(f => ({ ...f, victim_phone: val }));
  };

  useEffect(() => {
    api.get('/lookup/offence-types').then(r => setCrimeCategories(r.data.data || r.data)).catch(() => {});
    api.get('/complaints/search').then(r => setComplaints(r.data.data || r.data || [])).catch(() => {});
    if (id) {
      api.get(`/verifications/reports/${id}`).then(r => {
        const d = r.data.data || r.data;
        const evidence = (d.evidence || []).map(ev => ({
          file: ev.file || null,
          file_path: ev.file || '',
          file_url: ev.file_url || '',
          original_name: ev.original_name || '',
          desc: ev.description || '',
        }));
        const accused = (d.accused || []).map(a => ({
          name: a.name || '', father_name: a.father_name || '', phone: a.phone || '', email: a.email || '',
          country_code: a.country_code || '+92', cnic: a.cnic || '', address: a.address || '',
          post_address: a.post_address || '', nationality: a.nationality || 'Pakistani',
          passport_no: a.passport_no || '', photo: a.photo || null,
          cnic_front: a.cnic_front || '', cnic_back: a.cnic_back || '',
          passport_attachment: a.passport_attachment || '', picture: a.picture || '',
          other_attachment: a.other_attachment || '',
          photo_url: a.photo_url || '',
          cnic_front_url: a.cnic_front_url || '',
          cnic_back_url: a.cnic_back_url || '',
          passport_attachment_url: a.passport_attachment_url || '',
          picture_url: a.picture_url || '',
          other_attachment_url: a.other_attachment_url || '',
        }));
        const transactions = (d.transactions || []).map(t => ({
          bank: t.bank || '',
          account: t.account || '',
          amount: t.amount || '',
          date: t.date || '',
          file: null,
          file_path: t.file || '',
          file_url: t.file_url || '',
        }));
        setForm(f => ({
          ...f,
          complaint_id: d.complaint_id || '',
          tracking_no: d.tracking_no || '',
          registration_at: toLocalInput(d.registration_at || d.complaint?.created_at || d.complaint?.entry_time),
          assignment_date: toLocalInput(d.assignment_date),
          verification_date: toLocalInput(d.verification_date),
          victim_name: d.victim_name || '',
          victim_father_name: d.victim_father_name || '',
          victim_occupation: d.victim_occupation || '',
          victim_gender: d.victim_gender || '',
          victim_cnic: d.victim_cnic || '',
          victim_country_code: d.victim_country_code || '+92',
          victim_phone: d.victim_phone || '',
          victim_email: d.victim_email || '',
          crime_category: d.crime_category || '', initial_crime_category: d.complaint?.offence_type || d.complaint?.crime_category || d.crime_category || '',
          city: d.city || '',
          crime_description: d.crime_description || '',
          accused_known: d.accused_known ? '1' : '0',
          accused,
          recommendation: d.recommendation || '',
          closure_reason: d.closure_reason || '', merge_type: d.merge_type || 'enquiry', merge_reference: d.merge_reference || '',
          recommendation_short: d.recommendation_short || '',
          recommendation_full: d.recommendation_full || '',
          comments: d.comments || '',
          evidence,
          transactions: transactions.length ? transactions : [{ bank: '', account: '', amount: '', date: '', file: null }],
          inquiry_no: d.inquiry_no || '',
          case_no: d.case_no || '',
        }));
        setVerificationStatus(d.complaint?.verification?.status || d.verification?.status || '');
      }).catch(() => navigate('/verifications/reports'));
    }
  }, [id, navigate]);

  // Helper to extract proper complaint registration and assignment dates
  const resolveComplaintDates = (comp, v = {}) => {
    let regAt = '';
    if (comp) {
      if (comp.created_at) {
        regAt = toLocalInput(comp.created_at);
      } else if (comp.registration_at) {
        regAt = toLocalInput(comp.registration_at);
      } else if (comp.report_date) {
        const rawDate = String(comp.report_date).trim();
        const rawTime = comp.entry_time || comp.reporting_time || '';
        if (rawTime) {
          const timeStr = String(rawTime).trim();
          let hours = 0, mins = 0;
          const isPm = /pm/i.test(timeStr);
          const isAm = /am/i.test(timeStr);
          const cleanTime = timeStr.replace(/[^\d:]/g, '');
          const parts = cleanTime.split(':');
          if (parts.length >= 2) {
            hours = parseInt(parts[0], 10) || 0;
            mins = parseInt(parts[1], 10) || 0;
            if (isPm && hours < 12) hours += 12;
            if (isAm && hours === 12) hours = 0;
            const pad = n => String(n).padStart(2, '0');
            const dStr = toLocalInput(rawDate).split('T')[0];
            if (dStr) regAt = `${dStr}T${pad(hours)}:${pad(mins)}`;
          }
        }
        if (!regAt) regAt = toLocalInput(rawDate);
      }
      if (!regAt && comp.entry_time) {
        regAt = toLocalInput(comp.entry_time);
      }
    }

    let assignAt = '';
    if (v && v.assigned_at) {
      assignAt = toLocalInput(v.assigned_at);
    } else if (comp?.verification?.assigned_at) {
      assignAt = toLocalInput(comp.verification.assigned_at);
    }

    if (!regAt && assignAt) regAt = assignAt;
    if (!assignAt && regAt) assignAt = regAt;

    return { regAt, assignAt };
  };

  // Prefill from top-search deep link: /verifications/reports/create?tracking=0001/26
  useEffect(() => {
    if (id) return;
    const tracking = searchParams.get('tracking');
    if (!tracking || !complaints.length) return;
    const comp = complaints.find(c => c.tracking_no === tracking);
    if (!comp) {
      setForm(f => ({ ...f, tracking_no: tracking }));
      return;
    }
    if (comp.verification?.id) setLinkedVerificationId(comp.verification.id);
    const phone = (comp.contact_no || '').replace(/\D/g, '').replace(/^0+/, '');
    const v = comp.verification || {};
    const { regAt, assignAt } = resolveComplaintDates(comp, v);
    const mapAccused = (Array.isArray(comp.initial_accused) ? comp.initial_accused : []).map(a => ({
      name: a.name || '',
      father_name: a.father_name || '',
      phone: a.mobile_no || a.phone || '',
      email: a.email || '',
      country_code: '+92',
      cnic: a.cnic || '',
      address: a.address || '',
      post_address: a.post_address || '',
      nationality: a.nationality || 'Pakistani',
      passport_no: a.passport_no || '',
      photo: a.photo || null,
      cnic_front: a.cnic_front || '',
      cnic_back: a.cnic_back || '',
      passport_attachment: a.passport_attachment || '',
      picture: a.picture || '',
      other_attachment: a.other_attachment || '',
      photo_url: a.photo_url || '',
      cnic_front_url: a.cnic_front_url || '',
      cnic_back_url: a.cnic_back_url || '',
      passport_attachment_url: a.passport_attachment_url || '',
      picture_url: a.picture_url || '',
      other_attachment_url: a.other_attachment_url || '',
    }));
    setForm(f => ({
      ...f,
      tracking_no: tracking,
      complaint_id: comp.id,
      registration_at: regAt || toLocalInput(comp.created_at),
      assignment_date: assignAt || f.assignment_date,
      verification_date: f.verification_date,
      victim_name: comp.complainant_name || '',
      victim_father_name: comp.father_name || '',
      victim_cnic: comp.cnic || '',
      victim_country_code: comp.contact_country_code || '+92',
      victim_phone: phone,
      victim_occupation: comp.profession || '',
      victim_gender: (comp.gender || '').toLowerCase(),
      victim_email: comp.email || '',
      crime_category: comp.offence_type || '', initial_crime_category: comp.offence_type || '',
      city: comp.cmu || f.city,
      crime_description: comp.description || '',
      accused_known: mapAccused.length ? '1' : f.accused_known,
      accused: mapAccused.length ? mapAccused : f.accused,
    }));
  }, [id, searchParams, complaints]);

  const handleTrackingChange = (e) => {
    const tracking = e.target.value;
    setForm(f => ({ ...f, tracking_no: tracking }));
    const comp = complaints.find(c => c.tracking_no === tracking);
    if (comp?.verification?.id) setLinkedVerificationId(comp.verification.id);
    if (comp) {
      const phone = (comp.contact_no || '').replace(/\D/g, '').replace(/^0+/, '');
      const v = comp.verification || {};
      const { regAt, assignAt } = resolveComplaintDates(comp, v);
      const mapAccused = (Array.isArray(comp.initial_accused) ? comp.initial_accused : []).map(a => ({
        name: a.name || '',
        father_name: a.father_name || '',
        phone: a.mobile_no || a.phone || '',
        email: a.email || '',
        country_code: '+92',
        cnic: a.cnic || '',
        address: a.address || '',
        post_address: a.post_address || '',
        nationality: a.nationality || 'Pakistani',
        passport_no: a.passport_no || '',
        photo: a.photo || null,
        cnic_front: a.cnic_front || '',
        cnic_back: a.cnic_back || '',
        passport_attachment: a.passport_attachment || '',
        picture: a.picture || '',
        other_attachment: a.other_attachment || '',
        photo_url: a.photo_url || '',
        cnic_front_url: a.cnic_front_url || '',
        cnic_back_url: a.cnic_back_url || '',
        passport_attachment_url: a.passport_attachment_url || '',
        picture_url: a.picture_url || '',
        other_attachment_url: a.other_attachment_url || '',
      }));
      // Pre-populate transactions from complaint financial data
      const compTransactions = [];
      if (comp.bank_name_sender || comp.account_no_sender) {
        compTransactions.push({
          bank: comp.bank_name_sender || '',
          account: comp.account_no_sender || '',
          amount: comp.amount_involved || '',
          date: comp.transaction_date || '',
          file: null,
        });
      }
      if (comp.bank_name_receiver || comp.account_no_receiver) {
        compTransactions.push({
          bank: comp.bank_name_receiver || '',
          account: comp.account_no_receiver || '',
          amount: comp.amount_involved || '',
          date: comp.transaction_date || '',
          file: null,
        });
      }
      setForm(f => ({
        ...f,
        tracking_no: tracking,
        complaint_id: comp.id,
        registration_at: regAt || toLocalInput(comp.created_at),
        assignment_date: assignAt || f.assignment_date,
        verification_date: f.verification_date,
        victim_name: comp.complainant_name || '',
        victim_father_name: comp.father_name || '',
        victim_cnic: comp.cnic || '',
        victim_country_code: comp.contact_country_code || '+92',
        victim_phone: phone,
        victim_occupation: comp.profession || '',
        victim_gender: (comp.gender || '').toLowerCase(),
        victim_email: comp.email || '',
        crime_category: comp.offence_type || '', initial_crime_category: comp.offence_type || '',
        city: comp.cmu || '',
        crime_description: comp.description || '',
        accused_known: (mapAccused.length ? '1' : f.accused_known),
        accused: mapAccused.length ? mapAccused : f.accused,
        transactions: compTransactions.length ? compTransactions : [{ bank: '', account: '', amount: '', date: '', file: null }],
      }));
    }
  };

  const buildFormData = (overrides = {}) => {
    const fd = new FormData();
    const dataToSubmit = { ...form, ...overrides };
    Object.entries(dataToSubmit).forEach(([k, v]) => {
      if (v === null || v === undefined || v === '') return;
        if (k === 'initial_crime_category') return;
      if (k === 'evidence') {
        let existingCount = 0, newIdx = 0;
        v.forEach((ev) => {
          if (ev.file instanceof File) {
            fd.append(`evidence_file[${newIdx}]`, ev.file);
            fd.append(`evidence_desc[${newIdx}]`, ev.desc || '');
            newIdx++;
          } else if (ev.file_path) {
            fd.append(`existing_evidence[${existingCount}][file_path]`, ev.file_path);
            fd.append(`existing_evidence[${existingCount}][original_name]`, ev.original_name || '');
            fd.append(`existing_evidence[${existingCount}][description]`, ev.desc || '');
            existingCount++;
          }
        });
        return;
      }
      if (k === 'accused') {
        v.forEach((a, i) => {
          Object.entries(a).forEach(([ak, av]) => {
            if (ak.endsWith('_url')) return;
            if (av instanceof File) {
              const input = {
                photo: 'accused_photo',
                picture: 'accused_picture',
                cnic_front: 'accused_cnic_front',
                cnic_back: 'accused_cnic_back',
                passport_attachment: 'accused_passport',
                other_attachment: 'accused_other',
              }[ak];
              if (input) fd.append(`${input}[${i}]`, av);
              return;
            }
            if (av !== null && av !== undefined && av !== '') fd.append(`accused[${i}][${ak}]`, av);
          });
        });
        return;
      }
      if (k === 'transactions') {
        let newIdx = 0, existingCount = 0;
        (v || []).forEach((t) => {
          if (t.file instanceof File) {
            fd.append(`transaction_bank[${newIdx}]`, t.bank || '');
            fd.append(`transaction_account[${newIdx}]`, t.account || '');
            fd.append(`transaction_amount[${newIdx}]`, t.amount || '');
            fd.append(`transaction_date[${newIdx}]`, t.date || '');
            fd.append(`transaction_file[${newIdx}]`, t.file);
            newIdx++;
          } else if (t.bank || t.account || t.amount) {
            fd.append(`existing_transactions[${existingCount}][bank]`, t.bank || '');
            fd.append(`existing_transactions[${existingCount}][account]`, t.account || '');
            fd.append(`existing_transactions[${existingCount}][amount]`, t.amount || '');
            fd.append(`existing_transactions[${existingCount}][date]`, t.date || '');
            if (t.file_path) fd.append(`existing_transactions[${existingCount}][file]`, t.file_path);
            existingCount++;
          }
        });
        return;
      }
      fd.append(k, v);
    });
    return fd;
  };

  const saveReport = async (overrides = {}) => {
    const fd = buildFormData(overrides);
    const url = id ? `/verifications/reports/${id}` : '/verifications/reports';
    if (id) {
      fd.append('_method', 'PUT');
      await api.post(url, fd);
      return id;
    }
    const res = await api.post(url, fd);
    return res.data?.data?.id || null;
  };

  const resolveVerificationId = () => {
    if (linkedVerificationId) return linkedVerificationId;
    const comp = complaints.find(c => String(c.id) === String(form.complaint_id) || c.tracking_no === form.tracking_no);
    return comp?.verification?.id || null;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (isVoLocked) {
      alert('Yeh Verification Report Circle Incharge ko submit ho chuki hai / Approved hai. Verification Officer ke liye editing locked hai.');
      return;
    }
    setSaving(true);
    setServerError('');
    setErrors({});
    try {
      await saveReport();
      navigate('/verifications/reports');
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        setErrors(res.errors);
        const errList = Object.entries(res.errors).map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`).join(' | ');
        setServerError(`Please fix the highlighted fields: ${errList}`);
      } else if (res?.message) {
        setServerError(res.message + (res.exception ? ' (' + res.exception + ')' : ''));
      } else {
        setServerError('Error saving report. Please try again. Status: ' + (err.response?.status || 'N/A'));
      }
    } finally {
      setSaving(false);
    }
  };

  const handleSubmitToCircleIncharge = async () => {
    if (isVoLocked) {
      alert('Yeh Verification Report pehle hi submit ho chuki hai.');
      return;
    }
    if (!form.recommendation) {
      setServerError('Circle Incharge ko submit karne se pehle Recommendation select karein.');
      return;
    }
    const reportText = (form.recommendation_full || form.recommendation_short || form.comments || '').trim();
    if (!reportText) {
      setServerError('Submit se pehle Recommendation / Comments mein report text likhein.');
      return;
    }
    const verificationId = resolveVerificationId();
    if (!verificationId) {
      setServerError('Is complaint ki Verification assignment nahi mili. Pehle VO assign hona zaroori hai.');
      return;
    }

    if (form.recommendation === 'merge' && !form.merge_reference) {
      setServerError('Merge recommendation ke liye Merge Reference No. zaroori hai.');
      return;
    }
    setSubmittingCi(true);
    setServerError('');
    setErrors({});
    const autoDate = toLocalInput(new Date());
    setForm(f => ({ ...f, verification_date: autoDate }));
    try {
      await saveReport({ verification_date: autoDate });
      await api.post(`/verifications/${verificationId}/submit-report`, {
        report_text: reportText,
        recommendation: form.recommendation,
        closure_reason: form.closure_reason || null, merge_type: form.merge_type, merge_reference: form.merge_reference,
      });
      alert('Verification report Circle Incharge ko submit ho gayi.');
      navigate('/verifications');
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        setErrors(res.errors);
        setServerError('Please fix the highlighted fields below.');
      } else {
        setServerError(res?.message || 'Submit to Circle Incharge failed');
      }
    } finally {
      setSubmittingCi(false);
    }
  };

  const addAccused = () => setForm(f => ({ ...f, accused: [{ name: '', father_name: '', phone: '', email: '', country_code: '+92', cnic: '', address: '', post_address: '', nationality: 'Pakistani', passport_no: '', photo: null, cnic_front: '', cnic_back: '', passport_attachment: '', picture: '', other_attachment: '' }, ...f.accused] }));
  const removeAccused = (i) => setForm(f => ({ ...f, accused: f.accused.filter((_, idx) => idx !== i) }));
  const updateAccused = (i, field, value) => setForm(f => ({ ...f, accused: f.accused.map((a, idx) => idx === i ? { ...a, [field]: value } : a) }));
  const updateAccusedFile = (i, field, file) => setForm(f => ({ ...f, accused: f.accused.map((a, idx) => idx === i ? { ...a, [field]: file } : a) }));

  const addEvidence = () => setForm(f => ({ ...f, evidence: [{ file: null, desc: '' }, ...f.evidence] }));
  const addTransaction = () => setForm(f => ({ ...f, transactions: [{ bank: '', account: '', amount: '', date: '', file: null }, ...(f.transactions || [])] }));
  const removeTransaction = (i) => setForm(f => ({ ...f, transactions: (f.transactions || []).filter((_, idx) => idx !== i) }));
  const updateTransaction = (i, field, value) => setForm(f => ({ ...f, transactions: (f.transactions || []).map((t, idx) => idx === i ? { ...t, [field]: value } : t) }));
  const updateTransactionFile = (i, file) => setForm(f => ({ ...f, transactions: (f.transactions || []).map((t, idx) => idx === i ? { ...t, file } : t) }));
  const removeEvidence = (i) => setForm(f => ({ ...f, evidence: f.evidence.filter((_, idx) => idx !== i) }));
  const updateEvidence = (i, field, value) => setForm(f => ({ ...f, evidence: f.evidence.map((e, idx) => idx === i ? { ...e, [field]: value } : e) }));

  return (
    <div className="page-content" style={{margin:'0 auto'}}>
      <form onSubmit={handleSubmit} encType="multipart/form-data" noValidate>
        <div className="page-header">
          <div className="page-title-group">
            <div className="page-label">Verifications</div>
            <h1 className="page-title">{id ? 'Edit Verification Report' : 'Victim Verification Report'}</h1>
            <p className="page-subtitle">{id ? 'Update verification report details' : 'Record victim appearance & verification findings'}</p>
            <div className="title-underline"></div>
          </div>
          <div className="page-actions">
            <Link to="/verifications/reports" className="btn btn-outline btn-sm">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="15 18 9 12 15 6"/></svg>
              Back
            </Link>
          </div>
        </div>

        {!id && (
          <PdfAutoFillBar
            hint="Scanned NCCIA verification PDF se dates, complainant, gist, recommendation aur accused auto fill."
            onFilled={(extracted, file) => {
              const mapped = mapExtractToVerificationReport(extracted);
              const cat = matchLookupValue(crimeCategories, extracted.crime_category);
              setForm(f => ({
                ...f,
                ...mapped,
                ...(cat ? { crime_category: cat } : { crime_category: f.crime_category }),
                accused: mapped.accused?.length ? mapped.accused : f.accused,
                evidence: file
                  ? [...(f.evidence || []), { file, desc: `Source PDF: ${file.name}` }]
                  : f.evidence,
              }));
            }}
          />
        )}

        {serverError && <div className="cf-error-message" style={{background:'#fff5f5',border:'1px solid #e53e3e',borderRadius:'8px',padding:'12px 16px',marginBottom:'16px',color:'#e53e3e'}}>{serverError}
          {Object.keys(errors).length > 0 && <ul style={{margin:'8px 0 0 0',paddingLeft:'20px'}}>{Object.entries(errors).map(([k,v]) => <li key={k}><strong>{k}:</strong> {Array.isArray(v) ? v.join(', ') : v}</li>)}</ul>}
        </div>}

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#015C94'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Tracking & Dates</div>
              <div className="cf-section-sub">Pull complaint from system, then record key dates</div>
            </div>
            <div className="cf-section-badge">STEP 01</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">Tracking No.</label>
                <div className="cf-input-wrap">
                  <span className="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
                  <select name="tracking_no" className="cf-input cf-select" required value={form.tracking_no} onChange={handleTrackingChange} disabled={!!id}>
                    <option value="">— Select Tracking No. —</option>
                    {complaints.map(c => <option key={c.id} value={c.tracking_no} data-complaint={c.id} data-cnic={c.cnic} data-phone={c.contact_no}>{c.tracking_no} — {c.complainant_name}{c.verification ? ` · V-${c.verification.id}` : ''}</option>)}
                  </select>
                </div>
                <input type="hidden" name="complaint_id" value={form.complaint_id} />
                <span className="cf-hint">Auto-fills registration / assignment / verification date-time</span>
              </div>
              <div className="cf-field">
                <label className="cf-label">
                  Registration Date &amp; Time
                  {!isSupervisor && <span style={{ fontSize: 11, color: '#64748b', marginLeft: 6, fontWeight: 500 }}>🔒 (Auto / Non-editable)</span>}
                </label>
                <div className="cf-input-wrap">
                  <input
                    type="datetime-local"
                    className="cf-input"
                    name="registration_at"
                    value={form.registration_at}
                    onChange={setF('registration_at')}
                    disabled={!isSupervisor || isVoLocked}
                    readOnly={!isSupervisor}
                    style={!isSupervisor ? { background: '#f8fafc', color: '#334155', cursor: 'not-allowed' } : {}}
                  />
                </div>
                <span className="cf-hint">When complaint was registered</span>
              </div>
            </div>
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">
                  Assignment Date &amp; Time
                  {!isSupervisor && <span style={{ fontSize: 11, color: '#64748b', marginLeft: 6, fontWeight: 500 }}>🔒 (Auto / Non-editable)</span>}
                </label>
                <div className="cf-input-wrap">
                  <input
                    type="datetime-local"
                    className="cf-input"
                    name="assignment_date"
                    value={form.assignment_date}
                    onChange={setF('assignment_date')}
                    disabled={!isSupervisor || isVoLocked}
                    readOnly={!isSupervisor}
                    style={!isSupervisor ? { background: '#f8fafc', color: '#334155', cursor: 'not-allowed' } : {}}
                  />
                </div>
                <span className="cf-hint">When assigned to verification officer</span>
              </div>
              <div className="cf-field">
                <label className="cf-label required">Verification Date &amp; Time</label>
                <div className="cf-input-wrap">
                  <input type="datetime-local" className="cf-input" name="verification_date" value={form.verification_date} onChange={setF('verification_date')} disabled={isVoLocked} required />
                </div>
                <span className="cf-hint">When verification was completed / complainant appeared</span>
              </div>
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#0E7C7B'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Verification Officer Comment</div>
              <div className="cf-section-sub">VO remarks / notes for this verification report (required for VO working)</div>
            </div>
            <div className="cf-section-badge">VO</div>
          </div>
          <div className="cf-body">
            <div className="cf-field">
              <label className="cf-label">VO Comment / Remarks</label>
              <textarea
                className="cf-input cf-textarea"
                name="comments"
                rows={4}
                placeholder="Verification officer apni comments / findings yahan likhein…"
                value={form.comments}
                onChange={setF('comments')}
              ></textarea>
              <span className="cf-hint">Yeh comment verification report ke sath save hota hai</span>
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#2B2B2B'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><circle cx="12" cy="8" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Victim Data Form</div>
              <div className="cf-section-sub">Personal details of the complainant / victim</div>
            </div>
            <div className="cf-section-badge">STEP 02</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-3">
              <div className="cf-field">
                <label className="cf-label required">Name</label>
                <div className="cf-input-wrap">
                  <span className="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                  <input type="text" className="cf-input" name="victim_name" placeholder="Full name" value={form.victim_name} onChange={setF('victim_name')} required />
                </div>
              </div>
              <div className="cf-field">
                <label className="cf-label">Father Name</label>
                <div className="cf-input-wrap">
                  <span className="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                  <input type="text" className="cf-input" name="victim_father_name" placeholder="Father's name" value={form.victim_father_name} onChange={setF('victim_father_name')} />
                </div>
              </div>
              <div className="cf-field">
                <label className="cf-label">Occupation</label>
                <div className="cf-input-wrap">
                  <span className="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
                  <input type="text" className="cf-input" name="victim_occupation" placeholder="e.g. Business" value={form.victim_occupation} onChange={setF('victim_occupation')} />
                </div>
              </div>
            </div>

            <div className="cf-row-3">
              <div className="cf-field">
                <label className="cf-label">Gender</label>
                <div className="cf-input-wrap">
                  <select className="cf-input" name="victim_gender" value={form.victim_gender} onChange={e => setForm(f => ({...f, victim_gender: e.target.value}))}>
                    <option value="">— Select Gender —</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                </div>
              </div>

              <div className="cf-field">
                <label className="cf-label required">CNIC</label>
                <div className="cf-input-wrap">
                  <span className="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h4M15 12h4M15 16h4M6 16h6"/></svg></span>
                  <input type="text" className="cf-input font-mono" name="victim_cnic" id="victimCnic" inputMode="numeric" maxLength={15} placeholder="00000-0000000-0" value={form.victim_cnic} onChange={setFNum('victim_cnic')} required />
                </div>
                <span className="cf-hint">13 digits with dashes (auto-formatted)</span>
              </div>

              <div className="cf-field">
                <label className="cf-label required">Phone No.</label>
                  <div className="cf-phone-group" style={{display:'flex',gap:'8px'}}>
                    <div className="cf-input-wrap" style={{width:'190px'}}>
                      <select className="cf-input" name="victim_country_code" value={form.victim_country_code} onChange={setF('victim_country_code')}>
                        {countryCodes.map(c => <option key={c.code + c.name} value={c.code}>{c.code} {c.name}</option>)}
                      </select>
                    </div>
                  <div className="cf-input-wrap cf-phone-num" style={{flex:1}}>
                    <input type="text" className="cf-input font-mono" name="victim_phone" id="victimPhone" inputMode="numeric" placeholder="3XXXXXXXXX" value={form.victim_phone} onChange={setPhone} required />
                  </div>
                </div>
                <span className="cf-hint" id="phoneHint">Pakistan: 10 digits after code</span>
              </div>

              <div className="cf-field">
                <label className="cf-label">Email</label>
                <div className="cf-input-wrap">
                  <input type="email" className="cf-input" name="victim_email" value={form.victim_email} onChange={setF('victim_email')} placeholder="victim@email.com" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#264078'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Crime Details</div>
              <div className="cf-section-sub">Category, description & location</div>
            </div>
            <div className="cf-section-badge">STEP 03</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label">Crime Category <span style={{fontSize:11,fontWeight:400,color:'#888'}}>(Front Desk — Read Only)</span></label>
                <div className="cf-input-wrap">
                  <input
                    type="text"
                    className="cf-input"
                    value={form.initial_crime_category || '—'}
                    readOnly
                    style={{background:'#f1f5f9', color:'#555', cursor:'not-allowed', border:'1px solid #cbd5e1'}}
                  />
                </div>
                <span className="cf-hint">Auto-filled from complaint — cannot be edited</span>
              </div>

              <div className="cf-field">
                <label className="cf-label required">Crime Category <span style={{fontSize:11,fontWeight:400,color:'#015C94'}}>(Verification Officer)</span></label>
                <div className="cf-input-wrap">
                  <select className="cf-input" name="crime_category" value={form.crime_category} onChange={setF('crime_category')} required>
                    <option value="">— Select Category —</option>
                    {crimeCategories.map(c => <option key={c.value||c.name} value={c.value||c.name}>{c.name}</option>)}
                  </select>
                </div>
                <span className="cf-hint">VO selects their verified crime category</span>
              </div>
            </div>

            <div className="cf-row-1" style={{marginTop:'8px'}}>
              <div className="cf-field">
                <label className="cf-label required">City</label>
                <SearchableSelect
                  options={majorCities}
                  value={form.city}
                  onChange={setF('city')}
                  placeholder="Select City"
                  name="city"
                  required
                />
              </div>
            </div>

            <div className="cf-field">
              <label className="cf-label">Crime Description</label>
              <div className="cf-input-wrap">
                <textarea className="cf-input cf-textarea cf-textarea-lg" name="crime_description" rows={4} placeholder="Provide complete description of the crime…" value={form.crime_description} onChange={setF('crime_description')}></textarea>
              </div>
              <div className="cf-char-count"><span id="crimeCount">{form.crime_description.length}</span> / 5000 characters</div>
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#015C94'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Accused Details</div>
              <div className="cf-section-sub">Known or unknown — add multiple accused if known</div>
            </div>
            <div className="cf-section-badge">STEP 04</div>
          </div>
          <div className="cf-body">
            <p style={{fontSize:13,color:'#6c757d',marginTop:0,marginBottom:16}}>If no accused details are entered, it will be treated as unknown. You can add accused info later.</p>
            <div className="cf-repeater">
                <button type="button" className="btn btn-outline btn-sm" onClick={addAccused} style={{marginBottom:'16px'}}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Accused</button>
                <div id="accusedList">
                  {form.accused.map((a, i) => (
                    <div key={i} style={{padding:'12px',marginBottom:'12px',background:'#f8f8f8',borderRadius:'8px',border:'1px solid #e0e0e0'}}>
                      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr auto',gap:'12px',marginBottom:'12px'}}>
                        <div className="cf-field"><label className="cf-label">Name</label><input type="text" className="cf-input" value={a.name} onChange={e => updateAccused(i, 'name', e.target.value)} placeholder="Accused name" /></div>
                        <div className="cf-field"><label className="cf-label">Father Name</label><input type="text" className="cf-input" value={a.father_name} onChange={e => updateAccused(i, 'father_name', e.target.value)} placeholder="Father's name" /></div>
                        <div className="cf-field"><label className="cf-label">Phone</label><div style={{display:'flex',gap:'6px'}}><select className="cf-input" value={a.country_code || '+92'} onChange={e => updateAccused(i,'country_code',e.target.value)} style={{width:'150px',flexShrink:0}}>{countryCodes.map(c => <option key={c.code + c.name} value={c.code}>{c.code} {c.name}</option>)}</select><input type="text" className="cf-input" value={a.phone} onChange={e => { let v=e.target.value.replace(/\D/g,'').replace(/^0+/,''); if(v.length>10) v=v.slice(0,10); updateAccused(i,'phone',v); }} placeholder="3XXXXXXXXX" maxLength={10} style={{flex:1}} /></div></div>
                        <button type="button" className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:'8px',width:'36px',height:'36px',alignSelf:'end',justifySelf:'end'}} onClick={() => removeAccused(i)}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                      </div>
                      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:'12px',marginBottom:'12px'}}>
                        <div className="cf-field"><label className="cf-label">CNIC</label><input type="text" className="cf-input font-mono" value={a.cnic} onChange={e => {let v=e.target.value.replace(/\D/g,'').slice(0,13);if(v.length>5)v=v.slice(0,5)+'-'+v.slice(5);if(v.length>13)v=v.slice(0,13)+'-'+v.slice(13);updateAccused(i,'cnic',v);}} maxLength={15} placeholder="00000-0000000-0" /></div>
                        <div className="cf-field"><label className="cf-label">Email</label><input type="email" className="cf-input" value={a.email || ''} onChange={e => updateAccused(i, 'email', e.target.value)} placeholder="accused@email.com" /></div>
                        <div className="cf-field"><label className="cf-label">Address</label><input type="text" className="cf-input" value={a.address || ''} onChange={e => updateAccused(i, 'address', e.target.value)} placeholder="Accused address" /></div>
                      </div>
                      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'12px',marginBottom:'12px'}}>
                        <div className="cf-field"><label className="cf-label">Postal Address</label><input type="text" className="cf-input" value={a.post_address || ''} onChange={e => updateAccused(i, 'post_address', e.target.value)} placeholder="Accused postal address" /></div>
                        <div className="cf-field"><label className="cf-label">Nationality</label>
                          <select className="cf-input" value={a.nationality || 'Pakistani'} onChange={e => updateAccused(i, 'nationality', e.target.value)}>
                            <option value="Pakistani">Pakistani</option>
                            <option value="Dual Nationality Holder">Dual Nationality Holder</option>
                            <option value="Foreigner">Foreigner</option>
                          </select>
                        </div>
                        <div className="cf-field"><label className="cf-label">Passport No {(['Dual Nationality Holder','Foreigner'].includes(a.nationality)) ? <span style={{color:'#e53e3e'}}>*</span> : null}</label>
                          <input type="text" className="cf-input" value={a.passport_no || ''} onChange={e => updateAccused(i, 'passport_no', e.target.value)} placeholder={(['Dual Nationality Holder','Foreigner'].includes(a.nationality)) ? 'Passport number required' : 'Optional for Pakistani nationals'} required={['Dual Nationality Holder','Foreigner'].includes(a.nationality)} />
                        </div>
                      </div>
                      <div style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr',gap:'12px'}}>
                        {[
                          { key: 'cnic_front', label: 'CNIC Front', accept: '.jpg,.jpeg,.png,.pdf' },
                          { key: 'cnic_back', label: 'CNIC Back', accept: '.jpg,.jpeg,.png,.pdf' },
                          { key: 'passport_attachment', label: 'Passport', accept: '.jpg,.jpeg,.png,.pdf' },
                          { key: 'picture', label: 'Picture / Photo', accept: 'image/*' },
                          { key: 'photo', label: 'Photo (legacy)', accept: 'image/*' },
                          { key: 'other_attachment', label: 'Other Document', accept: '.jpg,.jpeg,.png,.pdf,.doc,.docx' },
                        ].filter(doc => doc.key !== 'photo').map(doc => (
                          <div key={doc.key} className="cf-field">
                            <label className="cf-label">{doc.label}</label>
                            <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                              <input type="file" className="cf-input" accept={doc.accept} onChange={e => updateAccusedFile(i, doc.key, e.target.files[0])} />
                              {doc.key === 'picture' && !isVoLocked && (
                                <button
                                  type="button"
                                  onClick={() => setCameraModal({
                                    open: true,
                                    title: `Capture Photo for Accused #${i + 1} (${a.name || 'Accused'})`,
                                    onCapture: (file) => updateAccusedFile(i, 'picture', file)
                                  })}
                                  className="btn btn-outline btn-sm"
                                  style={{ height: 38, padding: '0 10px', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 4, whiteSpace: 'nowrap', borderColor: '#015C94', color: '#015C94' }}
                                  title="Open Live Webcam / Camera"
                                >
                                  📷 Camera
                                </button>
                              )}
                            </div>
                            {a[doc.key] instanceof File
                              ? <span style={{fontSize:12,color:'#38a169',marginTop:4,display:'block'}}>Selected: {a[doc.key].name}</span>
                              : (a[`${doc.key}_url`] || (typeof a[doc.key] === 'string' && a[doc.key])
                                ? <span style={{fontSize:12,marginTop:4,display:'block'}}>Current: <a href={a[`${doc.key}_url`]} target="_blank" rel="noreferrer" style={{color:'#015C94'}}>View ↗</a></span>
                                : null)}
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#2B2B2B'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Recommendations</div>
              <div className="cf-section-sub">Officer's recommendation on the case</div>
            </div>
            <div className="cf-section-badge">STEP 05</div>
          </div>
          <div className="cf-body">
            <div className="cf-row-2">
              <div className="cf-field">
                <label className="cf-label required">Recommendation Type</label>
                <div className="cf-input-wrap">
                  <select className="cf-input" name="recommendation" value={form.recommendation} onChange={setF('recommendation')} required>
                    <option value="">— Select Recommendation —</option>
                    {RECOMMENDATION_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                  </select>
                </div>
                <span className="cf-hint">Select the verification outcome</span>
              </div>

              {form.recommendation === 'merge' && (
                <div className="cf-row-2" style={{ marginTop: '12px' }}>
                  <div className="cf-field">
                    <label className="cf-label required">Merge Type</label>
                    <div className="cf-input-wrap">
                      <select className="cf-input" name="merge_type" value={form.merge_type} onChange={setF('merge_type')} required>
                        <option value="enquiry">Enquiry Merge</option>
                        <option value="fir">FIR Merge</option>
                      </select>
                    </div>
                    <span className="cf-hint">Type of record to merge with</span>
                  </div>
                  <div className="cf-field">
                    <label className="cf-label required">Merge Reference No.</label>
                    <div className="cf-input-wrap">
                      <input type="text" className="cf-input" name="merge_reference" value={form.merge_reference} onChange={setF('merge_reference')} placeholder="e.g. ENQ-001/24" required />
                    </div>
                    <span className="cf-hint">kis kay sath merge karna hay</span>
                  </div>
                </div>
              )}

              {form.recommendation === 'closure' && (
                <div className="cf-field">
                  <label className="cf-label required">Closure Reason</label>
                  <div className="cf-input-wrap">
                    <select className="cf-input" name="closure_reason" value={form.closure_reason} onChange={setF('closure_reason')} required>
                      <option value="">— Select Closure Reason —</option>
                      {CLOSURE_REASONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                    </select>
                  </div>
                  <span className="cf-hint">Required for Closure recommendation</span>
                </div>
              )}
            </div>

            <div className="cf-field">
              <label className="cf-label">Short Description</label>
              <div className="cf-input-wrap">
                <textarea className="cf-input cf-textarea" name="recommendation_short" rows={2} placeholder="Brief recommendation…" value={form.recommendation_short} onChange={setF('recommendation_short')}></textarea>
              </div>
              <div className="cf-char-count"><span id="recShortCount">{form.recommendation_short.length}</span> / 2000 characters</div>
            </div>

            <div className="cf-field">
              <label className="cf-label">Full Description</label>
              <div className="cf-input-wrap">
                <textarea className="cf-input cf-textarea cf-textarea-lg" name="recommendation_full" rows={4} placeholder="Detailed recommendation & rationale…" value={form.recommendation_full} onChange={setF('recommendation_full')}></textarea>
              </div>
              <div className="cf-char-count"><span id="recFullCount">{form.recommendation_full.length}</span> / 10000 characters</div>
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon" style={{background:'#264078'}}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Evidence</div>
              <div className="cf-section-sub">Upload media files — add a short description for each</div>
            </div>
            <div className="cf-section-badge">STEP 06</div>
          </div>
          <div className="cf-body">
            <div className="cf-repeater">
              <button type="button" className="btn btn-outline btn-sm" onClick={addEvidence} style={{ marginBottom: 16 }}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Evidence</button>
              <div id="evidenceList">
                {form.evidence.map((e, i) => (
                  <div key={i} style={{display:'grid',gridTemplateColumns:'1fr 1fr auto',gap:'12px',marginBottom:'12px',padding:'12px',background:'#f8f8f8',borderRadius:'8px',border:'1px solid #e0e0e0'}}>
                    <div className="cf-field"><label className="cf-label">Evidence File</label><input type="file" className="cf-input" accept="image/*,application/pdf" onChange={e => updateEvidence(i, 'file', e.target.files[0])} />
                    {!((e.file) instanceof File) && e.file_path ? <span style={{fontSize:12,marginTop:4,display:'block'}}>Current file: <a href={e.file_url} target="_blank" rel="noreferrer" style={{color:'#015C94'}}>Open ↗</a></span> : null}
                  </div>
                    <div className="cf-field"><label className="cf-label">Description</label><input type="text" className="cf-input" value={e.desc} onChange={e => updateEvidence(i, 'desc', e.target.value)} placeholder="Brief description" /></div>
                    <button type="button" className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:'8px',width:'36px',height:'36px',alignSelf:'end'}} onClick={() => removeEvidence(i)}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>

        <div className="cf-section">
          <div className="cf-section-header">
            <div className="cf-section-icon">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </div>
            <div>
              <div className="cf-section-title">Transaction Details</div>
              <div className="cf-section-sub">Record any financial transactions (Compulsory to attach file)</div>
            </div>
            <div className="cf-section-badge">STEP 07</div>
          </div>
          <div className="cf-body">
            <div className="cf-repeater">
              <button type="button" className="btn btn-outline btn-sm" onClick={addTransaction} style={{ marginBottom: 16 }}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Transaction</button>
              <div id="transactionList">
                {(form.transactions || []).map((t, i) => (
                  <div key={i} style={{display:'grid',gridTemplateColumns:'1fr 1fr 1fr 1fr 1fr auto',gap:'12px',marginBottom:'12px',padding:'12px',background:'#f8f8f8',borderRadius:'8px',border:'1px solid #e0e0e0'}}>
                    <div className="cf-field"><label className="cf-label required">Bank / Method</label><input type="text" className="cf-input" value={t.bank} onChange={e => updateTransaction(i, 'bank', e.target.value)} placeholder="Meezan, Easypaisa" required/></div>
                    <div className="cf-field"><label className="cf-label required">Account / IBAN</label><input type="text" className="cf-input" value={t.account} onChange={e => updateTransaction(i, 'account', e.target.value)} required/></div>
                    <div className="cf-field"><label className="cf-label required">Amount</label><input type="number" className="cf-input" value={t.amount} onChange={e => updateTransaction(i, 'amount', e.target.value)} required/></div>
                    <div className="cf-field"><label className="cf-label required">Date</label><input type="date" className="cf-input" value={t.date} onChange={e => updateTransaction(i, 'date', e.target.value)} required/></div>
                    <div className="cf-field"><label className="cf-label required">Attachment</label><input type="file" className="cf-input" accept="image/*,application/pdf" onChange={e => updateTransactionFile(i, e.target.files[0])} required />
                      {!((t.file) instanceof File) && t.file_path ? <span style={{fontSize:12,marginTop:4,display:'block'}}><a href={t.file_url} target="_blank" rel="noreferrer" style={{color:'#015C94'}}>Open file</a></span> : null}
                    </div>
                    <button type="button" className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:'8px',width:'36px',height:'36px',alignSelf:'end'}} onClick={() => removeTransaction(i)}><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>



        {serverError && (
          <div className="cf-alert cf-alert-error">{serverError}</div>
        )}

        {isVoLocked && (
          <div style={{ marginTop: 16, padding: '12px 16px', background: '#fef2f2', border: '1.5px solid #fecaca', borderRadius: 8, fontSize: 13, color: '#991b1b', fontWeight: 600 }}>
            🔒 Yeh Verification Report Circle Incharge ko submit ho chuki hai / Approved hai. Verification Officer ke liye edit permissions locked hain (Read-Only Mode).
          </div>
        )}

        <div className="cf-form-actions" style={{display:'flex',justifyContent:'flex-end',gap:'10px',paddingTop:'20px',marginTop:'10px',flexWrap:'wrap'}}>
          <Link to="/verifications/reports" className="btn btn-outline">Back to Reports</Link>
          {!isVoLocked && (
            <>
              <button type="submit" className="btn cf-submit-btn" disabled={saving || submittingCi} style={{background:'#64748b',color:'#fff',padding:'12px 24px',fontWeight:600,fontSize:'14px',borderRadius:'8px',display:'flex',alignItems:'center',gap:'8px',border:'none',cursor:saving?'not-allowed':'pointer',opacity:saving?0.7:1}}>
                {saving ? 'Saving...' : (id ? 'Save Draft' : 'Save Report')}
              </button>
              <button
                type="button"
                className="btn cf-submit-btn"
                disabled={saving || submittingCi}
                onClick={handleSubmitToCircleIncharge}
                style={{background:'#015C94',color:'#fff',padding:'12px 24px',fontWeight:700,fontSize:'14px',borderRadius:'8px',display:'flex',alignItems:'center',gap:'8px',border:'none',cursor:submittingCi?'not-allowed':'pointer',opacity:submittingCi?0.7:1,boxShadow:'0 2px 8px rgba(1,92,148,0.35)'}}
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><path d="M22 2L11 13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                {submittingCi ? 'Submitting...' : 'Submit to Circle Incharge'}
              </button>
            </>
          )}
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


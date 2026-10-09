import { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import OfficerHistoryPanel from '../components/OfficerHistoryPanel';
import CaseProcessHistoryPanel from '../components/CaseProcessHistoryPanel';
import DirectRegistrationFields from '../components/DirectRegistrationFields';
import CaseChatPanel from '../components/CaseChatPanel';
import {
  emptyDirectInfo,
  normalizeDirectInfo,
  buildDirectInfoPayload,
  CASE_CATEGORIES,
} from '../utils/directCaseOptions';
import { SIMPLE_STATUSES, PRIORITY_OPTIONS, toSimpleStatus, fromSimpleStatus } from '../utils/simpleStatus';
import { preparePrintWindow, writePrintWindow, closePrintWindow } from '../utils/print';
import { isSeizeItemLocked, activityHasLockedSeizeItems, applyForensicLocksToActivities, seizeItemKey, lockSeizeItemsAgainstForensic } from '../utils/seizeItemLock';
import { canFillLegalAndApprove, CASE_CFR_REVIEW_STATUSES, isSavedDiaryLocked } from '../utils/permissions';

const CASE_STATUS = [
  { value: 'registered', name: 'Registered (Moharrar)' },
  { value: 'assigned', name: 'Assigned to IO' },
  { value: 'in_progress', name: 'In Progress (IO Investigating)' },
  { value: 'cfr_submitted', name: 'CFR Submitted' },
  { value: 'legal_review_dd', name: 'Legal Review - DD Legal' },
  { value: 'legal_review_ad', name: 'Legal Review - AD Legal' },
  { value: 'legal_review_dg', name: 'Legal Review - DG Legal' },
  { value: 'approved', name: 'Approved' },
  { value: 'closed', name: 'Closed' },
  { value: 'transferred', name: 'Transferred' },
  { value: 'merged', name: 'Merged' },
  { value: 'challan_submitted', name: 'Challan U/S 173 CrPC Submitted' },
];

const ACTIVITY_TYPES = [
  { value: 'dac_request', name: 'DAC Request' },
  { value: 'mobile_record', name: 'Mobile Record Obtained' },
  { value: 'bank_record', name: 'Bank Record Obtained' },
  { value: 'search_seize', name: 'Search Warrant' },
  { value: 'raid', name: 'Raid Permission / Operation' },
  { value: 'arrest_warrant', name: 'Arrest Warrant' },
  { value: 'notice', name: 'Summon Issued' },
  { value: 'diary', name: 'Diary Maintained' },
  { value: 'seizure', name: 'Seizure Memo' },
  { value: 'forensic_report', name: 'Forensic Report' },
  { value: 'recovery', name: 'Recovery Effected' },
];

const WARRANT_TYPES = ['search_seize', 'raid', 'arrest_warrant'];

const EMPTY_SEIZE_ITEM = {
  item_type: '', make_model: '', imei: '', imei2: '', serial_no: '', storage_capacity: '', condition: 'sealed', quantity: 1, description: '', owner_type: '', owner_ref: '',
};

const SEIZE_ITEM_TYPES = [
  { value: 'phone', name: 'Mobile Phone' },
  { value: 'laptop', name: 'Laptop' },
  { value: 'computer', name: 'Computer / Desktop' },
  { value: 'hdd', name: 'Hard Disk - HDD / SSD' },
  { value: 'dvr', name: 'DVR' },
  { value: 'ipad_tablet', name: 'iPad / Tablet' },
  { value: 'memory_card', name: 'Memory Card' },
  { value: 'sim', name: 'SIM Card' },
  { value: 'usb', name: 'USB / Flash Drive' },
  { value: 'cd_dvd', name: 'CD / DVD' },
  { value: 'other', name: 'Other' },
];

const RECOMMENDATIONS = [
  { value: 'transfer', name: 'Transfer' },
  { value: 'merge', name: 'Merge' },
  { value: 'challan_submission', name: 'Challan U/S 173 CrPC' },
];

const LEGAL_ROLES = ['circle_incharge', 'dd_legal', 'ad_legal', 'dg_legal', 'additional_director'];
const LEGAL_DECISIONS = ['agree', 'disagree', 'review'];

function buildInheritedFromEnquiry(enq) {
  if (!enq) {
    return { complaint: null, accused: [], witnesses: [], devices: [], enquiry: null, enquiryActivities: [], verification: null, verificationReport: null };
  }
  const devices = (enq.activities || [])
    .filter(a => a.type === 'seizures' || a.type === 'seizure')
    .flatMap(a => a.meta?.seize_items || a.seize_items || []);
  const complaint = enq.complaint || null;

  return {
    complaint,
    accused: enq.accused_persons || enq.accusedPersons || [],
    witnesses: enq.witnesses || [],
    devices,
    enquiry: {
      id: enq.id,
      enquiry_number: enq.enquiry_number,
      status: enq.status,
      reg_date: enq.reg_date,
      cfr_summary: enq.cfr_summary,
      charge_against: enq.charge_against,
      officer: enq.officer || null,
    },
    enquiryActivities: enq.activities || [],
    verification: complaint?.verification || null,
    verificationReport: complaint?.latest_verification_report || complaint?.latestVerificationReport || null,
  };
}

const initialForm = {
  enquiry_id: '',
  fir_no: '',
  investigation_officer_id: '',
  status: 'registered',
  priority: 'normal',
  recommendation: '',
  transfer_department: '',
  transfer_circle: '',
  merge_complaint_id: '',
  activities: [],
  arrests: [],
  legal_opinions: [],
  approvals: [],
};

export default function CaseForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const roleNames = (user?.roles || []).map(r => r.name || r);
  const isSupervisor = roleNames.some(r => ['admin', 'circle_incharge', 'director_general'].includes(r));
  const canFillLegal = canFillLegalAndApprove(user);
  const [form, setForm] = useState(initialForm);
  const [enquiries, setEnquiries] = useState([]);
  const [officers, setOfficers] = useState([]);
  const [legalOfficers, setLegalOfficers] = useState([]);
  const [circles, setCircles] = useState([]);
  const [circleOptions, setCircleOptions] = useState([]);
  const [circleIncharges, setCircleIncharges] = useState([]);
  const [saving, setSaving] = useState(false);
  const [approvingCfr, setApprovingCfr] = useState(false);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [activeTab, setActiveTab] = useState('details');
  const [directMode, setDirectMode] = useState(!id && searchParams.get('direct') === '1');
  const [direct, setDirect] = useState(emptyDirectInfo());
  const [inherited, setInherited] = useState(() => buildInheritedFromEnquiry(null));
  const [sendingForensic, setSendingForensic] = useState(false);
  const [linkedForensicRequests, setLinkedForensicRequests] = useState([]);
  const linkedForensicRef = useRef([]);

  const loadLinkedForensicRequests = async (caseId, enquiryId) => {
    const targetId = caseId || id;
    if (!targetId) return;
    try {
      const qs = enquiryId ? `case_id=${targetId}&enquiry_id=${enquiryId}` : `case_id=${targetId}`;
      const res = await api.get(`/forensic-requests?${qs}`);
      const list = res.data?.data || [];
      linkedForensicRef.current = list;
      setLinkedForensicRequests(list);
    } catch (err) {
      console.warn('Could not load linked forensic requests', err);
    }
  };

  useEffect(() => {
    api.get('/enquiries?status=registered,assigned,in_progress,cfr_submitted,approved,closed,converted_to_case').then(r => {
      const d = r.data.data || r.data;
      setEnquiries((Array.isArray(d) ? d : []).map(e => ({
        value: e.id,
        name: '#' + (e.enquiry_number || e.id) + ' — ' + (e.complaint?.tracking_no || e.direct_info?.reference_no || e.complaint_id || 'N/A'),
      })));
    }).catch(() => {});
    api.get('/lookup/investigation-officers').then(r => { const d = r.data.data || r.data; setOfficers((Array.isArray(d) ? d : []).map(o => ({ value: o.id, name: o.name + (o.designation ? ' (' + o.designation + ')' : '') }))); }).catch(() => {});
    api.get('/lookup/legal-officers').then(r => setLegalOfficers(r.data.data || r.data)).catch(() => {});
    api.get('/lookup/circles').then(r => {
      const d = r.data.data || r.data;
      const list = Array.isArray(d) ? d : [];
      setCircles(list);
      setCircleOptions(list.map(c => ({ value: c.id ?? c.code ?? c.name, name: c.name + (c.code ? ' (' + c.code + ')' : '') })));
    }).catch(() => {});
    api.get('/lookup/circle-incharges').then(r => { const d = r.data.data || r.data; setCircleIncharges(Array.isArray(d) ? d : []); }).catch(() => {});
  }, []);

  useEffect(() => {
    if (id) {
      linkedForensicRef.current = [];
      setLinkedForensicRequests([]);
      api.get(`/cases/${id}`).then(r => {
        const d = r.data.data || r.data;
        setForm({
          enquiry_id: d.enquiry_id || d.enquiry?.id || '',
          fir_no: d.fir_no || '',
          investigation_officer_id: d.investigation_officer_id || '',
          status: d.status || 'registered',
          priority: d.priority || d.enquiry?.priority || d.enquiry?.complaint?.priority_type || 'normal',
          recommendation: d.recommendation || '',
          transfer_department: d.transfer_department || '',
          transfer_circle: d.transfer_circle || '',
          merge_complaint_id: d.merge_complaint_id || '',
          activities: (d.activities || []).map(a => ({
            id: a.id,
            type: a.type || '',
            case_category: a.meta?.case_category || a.case_category || 'Financial Fraud',
            description: a.description || '',
            activity_date: a.activity_date ? String(a.activity_date).slice(0, 10) : '',
            attachment: null,
            subject: a.meta?.subject || a.subject || '',
            kota: a.meta?.kota || a.kota || '',
            against_whom: a.meta?.against_whom || a.against_whom || '',
            scheduled_at: a.meta?.scheduled_at || a.scheduled_at || '',
            checklist_tech_report: Boolean(a.meta?.checklist_tech_report || a.checklist_tech_report),
            checklist_seizure_memo: Boolean(a.meta?.checklist_seizure_memo || a.checklist_seizure_memo),
            checklist_fir_copy: Boolean(a.meta?.checklist_fir_copy || a.checklist_fir_copy),
            audio_script: a.meta?.audio_script || a.audio_script || '',
            analysis_scope: a.meta?.analysis_scope || a.analysis_scope || '',
            seize_items: lockSeizeItemsAgainstForensic(
              Array.isArray(a.meta?.seize_items) ? a.meta.seize_items : (Array.isArray(a.seize_items) ? a.seize_items : []),
              linkedForensicRef.current
            ),
          })),
          arrests: (d.arrests || []).map(a => ({
            id: a.id,
            accused_name: a.accused_name || '',
            cnic: a.cnic || '',
            arrest_date: a.arrest_date ? String(a.arrest_date).slice(0, 10) : '',
            remand_details: a.remand_details || '',
          })),
          legal_opinions: (d.legal_opinions || []).map(lo => ({
            id: lo.id,
            role: lo.role || '',
            opinion_text: lo.opinion_text || '',
            decision: lo.decision || '',
            created_by: lo.created_by || '',
          })),
          approvals: (d.approvals || []).map(ap => ({
            id: ap.id,
            circle_incharge_id: ap.circle_incharge_id || '',
            decision: ap.decision || '',
            remarks: ap.remarks || '',
          })),
        });
        const enq = d.enquiry || {};
        setInherited(buildInheritedFromEnquiry(enq));
        if (!d.enquiry_id && d.direct_info) {
          setDirectMode(true);
          setDirect(normalizeDirectInfo(d.direct_info));
        }
        loadLinkedForensicRequests(id, d.enquiry_id || d.enquiry?.id);
      }).catch(() => navigate('/cases'));
    }
  }, [id, navigate]);

  useEffect(() => {
    if (!form.enquiry_id || directMode) {
      if (!form.enquiry_id) setInherited(buildInheritedFromEnquiry(null));
      return;
    }
    api.get(`/enquiries/${form.enquiry_id}`).then(r => {
      const enq = r.data?.data || r.data;
      setInherited(buildInheritedFromEnquiry(enq));
    }).catch(() => {});
  }, [form.enquiry_id, directMode]);

  useEffect(() => {
    linkedForensicRef.current = linkedForensicRequests;
    if (!linkedForensicRequests.length) return;
    setForm(f => {
      const next = applyForensicLocksToActivities(f.activities, linkedForensicRequests, ['seizure', 'seizures']);
      return next === f.activities ? f : { ...f, activities: next };
    });
  }, [linkedForensicRequests]);

  // No auto-refresh on edit form — it was wiping unsaved work every 30s.

  const setF = (field) => (e) => setForm(f => ({ ...f, [field]: e.target.value }));

  // Activities
  const addActivity = () => setForm(f => ({ ...f, activities: [{ _uid: `act_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, type: '', case_category: 'Financial Fraud', description: '', activity_date: new Date().toISOString().split('T')[0], attachment: null, subject: '', kota: '', against_whom: '', scheduled_at: '', seize_items: [], analysis_scope: '' }, ...f.activities] }));
  const removeActivity = (i) => {
    const act = form.activities[i];
    if (isSavedDiaryLocked(act, user)) {
      alert('Saved diary / bank / warrant entry Circle Incharge ke ilawa edit ya delete nahi ho sakti.');
      return;
    }
    if (activityHasLockedSeizeItems(act)) {
      alert('Is seizure memo mein forensic ko bheje hue items hain — poori activity delete nahi ho sakti.');
      return;
    }
    setForm(f => ({ ...f, activities: f.activities.filter((_, idx) => idx !== i) }));
  };
  const updateActivity = (i, field, value) => setForm(f => ({
    ...f,
    activities: f.activities.map((a, idx) => {
      if (idx !== i) return a;
      if (isSavedDiaryLocked(a, user)) return a;
      if (field === 'type' && activityHasLockedSeizeItems(a) && value !== a.type) {
        alert('Forensic ko bheje hue items ki wajah se is activity ki type change nahi ho sakti.');
        return a;
      }
      const next = { ...a, [field]: value };
      if (field === 'type' && WARRANT_TYPES.includes(value) && !next.against_whom) {
        const firstAcc = (inherited.accused || []).find(x => (x.name || '').trim());
        if (firstAcc) next.against_whom = firstAcc.name;
      }
      if (field === 'type' && value === 'seizure' && !(next.seize_items || []).length) {
        next.seize_items = [{ ...EMPTY_SEIZE_ITEM, _uid: `sz_${Date.now()}_${Math.random().toString(36).slice(2, 7)}` }];
      }
      return next;
    }),
  }));
  const updateActivityFile = (i, file) => setForm(f => ({ ...f, activities: f.activities.map((a, idx) => (idx === i && !isSavedDiaryLocked(a, user) ? { ...a, attachment: file } : a)) }));
  const addSeizeItem = (activityIndex) => setForm(f => {
    const target = f.activities[activityIndex];
    if (isSavedDiaryLocked(target, user)) return f;
    return {
    ...f,
    activities: f.activities.map((a, idx) => idx === activityIndex
      ? { ...a, seize_items: [{ ...EMPTY_SEIZE_ITEM, _uid: `sz_${Date.now()}_${Math.random().toString(36).slice(2, 7)}` }, ...(a.seize_items || [])] }
      : a),
    };
  });
  const removeSeizeItem = (activityIndex, itemIndex) => setForm(f => {
    const parent = f.activities[activityIndex];
    if (isSavedDiaryLocked(parent, user)) return f;
    const target = (f.activities[activityIndex]?.seize_items || [])[itemIndex];
    if (isSeizeItemLocked(target)) {
      alert('Yeh item forensic / technical ko bhej diya gaya hai — delete nahi ho sakta.');
      return f;
    }
    return {
      ...f,
      activities: f.activities.map((a, idx) => idx === activityIndex
        ? { ...a, seize_items: (a.seize_items || []).filter((_, si) => si !== itemIndex) }
        : a),
    };
  });
  const updateSeizeItem = (activityIndex, itemIndex, field, value) => setForm(f => ({
    ...f,
    activities: f.activities.map((a, idx) => idx === activityIndex
      ? { ...a, seize_items: (a.seize_items || []).map((it, si) => {
          if (si !== itemIndex) return it;
          if (isSavedDiaryLocked(a, user) || isSeizeItemLocked(it)) return it;
          return { ...it, [field]: value };
        }) }
      : a),
  }));

  // Arrests
  const addArrest = (prefill = null) => setForm(f => ({
    ...f,
    arrests: [{
      _uid: `arr_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
      accused_name: prefill?.name || '',
      cnic: prefill?.cnic || '',
      arrest_date: new Date().toISOString().split('T')[0],
      remand_details: '',
    }, ...f.arrests],
  }));
  const removeArrest = (i) => {
    const row = form.arrests[i];
    if (isSavedDiaryLocked(row, user)) {
      alert('Saved arrest Circle Incharge ke ilawa edit ya delete nahi ho sakti.');
      return;
    }
    setForm(f => ({ ...f, arrests: f.arrests.filter((_, idx) => idx !== i) }));
  };
  const updateArrest = (i, field, value) => setForm(f => ({
    ...f,
    arrests: f.arrests.map((a, idx) => {
      if (idx !== i) return a;
      if (isSavedDiaryLocked(a, user)) return a;
      return { ...a, [field]: value };
    }),
  }));

  // Legal Opinions
  const addLegalOpinion = () => {
    const defaultRole = ['dd_legal', 'ad_legal', 'additional_director', 'circle_incharge', 'dg_legal'].find(r => roleNames.includes(r))
      || (roleNames.includes('director_general') ? 'dg_legal' : '');
    setForm(f => ({
      ...f,
      legal_opinions: [{
        _uid: `lo_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`,
        role: defaultRole,
        opinion_text: '',
        decision: '',
        created_by: user?.id || '',
      }, ...f.legal_opinions],
    }));
  };
  const removeLegalOpinion = (i) => setForm(f => ({ ...f, legal_opinions: f.legal_opinions.filter((_, idx) => idx !== i) }));
  const updateLegalOpinion = (i, field, value) => setForm(f => ({ ...f, legal_opinions: f.legal_opinions.map((a, idx) => idx === i ? { ...a, [field]: value } : a) }));

  // Approvals
  const addApproval = () => setForm(f => ({
    ...f,
    approvals: [{ _uid: `app_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, circle_incharge_id: user?.id || '', decision: '', remarks: '' }, ...f.approvals],
  }));
  const removeApproval = (i) => setForm(f => ({ ...f, approvals: f.approvals.filter((_, idx) => idx !== i) }));
  const updateApproval = (i, field, value) => setForm(f => ({ ...f, approvals: f.approvals.map((a, idx) => idx === i ? { ...a, [field]: value } : a) }));

  const legalReviewOfficers = (() => {
    const map = new Map();
    [...(Array.isArray(legalOfficers) ? legalOfficers : []), ...(Array.isArray(circleIncharges) ? circleIncharges : [])].forEach(o => {
      if (o?.id != null) map.set(Number(o.id), o);
    });
    if (user?.id && !map.has(Number(user.id))) {
      map.set(Number(user.id), { id: user.id, name: user.name, designation: user.designation });
    }
    return [...map.values()];
  })();
  const canApproveCase = canFillLegal && !!id && CASE_CFR_REVIEW_STATUSES.includes(form.status);

  const handleApproveCfr = async (decision = 'agree') => {
    if (!id) return;
    let remarks = '';
    if (decision === 'disagree') {
      const input = window.prompt('Case CFR Send Back karne ki wajah / deficiency remarks darj karein:');
      if (input === null) return;
      remarks = input.trim();
    }
    setApprovingCfr(true);
    setServerError('');
    try {
      await api.post(`/cases/${id}/approve`, {
        decision,
        recommendation: form.recommendation || undefined,
        remarks: remarks || undefined,
      });
      window.alert(decision === 'agree' ? 'Case CFR approved successfully.' : 'Case CFR sent back to Investigation Officer.');
      navigate('/cases');
    } catch (err) {
      window.alert(err.response?.data?.message || err.message || 'Approval action failed.');
    } finally {
      setApprovingCfr(false);
    }
  };

  const buildPayload = () => {
    const payload = {
      enquiry_id: directMode ? null : (form.enquiry_id || undefined),
      investigation_officer_id: form.investigation_officer_id || undefined,
      status: form.status,
      priority: form.priority || 'normal',
      recommendation: form.recommendation || undefined,
      transfer_department: form.transfer_department || undefined,
      transfer_circle: form.transfer_circle || undefined,
      merge_complaint_id: form.merge_complaint_id || undefined,
      activities: form.activities
        .filter(a => a.type)
        .map(a => ({
          id: a.id,
          type: a.type,
          case_category: a.case_category || 'Financial Fraud',
          description: a.description,
          activity_date: a.activity_date,
          subject: a.subject || '',
          kota: a.kota || '',
          against_whom: a.against_whom || '',
          scheduled_at: a.scheduled_at || '',
          checklist_tech_report: Boolean(a.checklist_tech_report),
          checklist_seizure_memo: Boolean(a.checklist_seizure_memo),
          checklist_fir_copy: Boolean(a.checklist_fir_copy),
          audio_script: a.audio_script || '',
          analysis_scope: a.analysis_scope || '',
          seize_items: a.seize_items || [],
        })),
      arrests: form.arrests.filter(a => a.accused_name),
      legal_opinions: form.legal_opinions.filter(lo => lo.role),
      approvals: form.approvals.filter(ap => ap.decision),
    };

    if (directMode && !form.enquiry_id) {
      payload.direct_info = buildDirectInfoPayload(direct, circles);
    }

    return payload;
  };

  const saveCase = async ({ navigateAway = true } = {}) => {
    setSaving(true);
    setErrors({});
    setServerError('');
    try {
      const payload = buildPayload();
      if (id) {
        await api.put(`/cases/${id}`, payload);
      } else {
        const r = await api.post('/cases', payload);
        const newId = r.data?.data?.id || r.data?.id;
        if (navigateAway) {
          navigate('/cases');
        } else if (newId) {
          navigate(`/cases/${newId}/edit`);
        }
        return;
      }
      if (navigateAway) navigate('/cases');
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        setErrors(res.errors);
        setServerError('Please fix the highlighted fields below.');
      } else {
        setServerError(res?.message || 'Error saving case. Please try again.');
      }
      throw err;
    } finally {
      setSaving(false);
    }
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      await saveCase({ navigateAway: true });
    } catch (e2) {
      // errors already shown
    }
  };

  const printWarrantFromActivity = async (endpoint, act) => {
    if (!id) {
      alert('Pehle case save karein, phir print karein.');
      return;
    }
    if (!act?.id) {
      alert('Pehle case save karein taake warrant QR verify page par sahi details dikhein.');
    }
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/cases/${id}/${endpoint}`, {
        params: {
          activity_id: act.id || '',
          subject: act.subject || act.meta?.subject || '',
          kota: act.kota || act.meta?.kota || '',
          against_whom: act.against_whom || act.meta?.against_whom || '',
          scheduled_at: act.scheduled_at || act.meta?.scheduled_at || act.activity_date || '',
          description: act.description || '',
        },
      });
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || 'Could not print document.');
    }
  };

  const getSeizeItemOwnerText = (it) => {
    if (it.owner_type === 'accused') {
      const acc = inherited.accused[it.owner_ref] || inherited.accused.find(x => x.name === it.owner_ref);
      return acc?.name || it.owner_ref || 'Accused';
    }
    if (it.owner_type === 'witness') {
      const wit = inherited.witnesses[it.owner_ref] || inherited.witnesses.find(x => x.name === it.owner_ref);
      return wit?.name || it.owner_ref || 'Witness';
    }
    if (it.owner_type === 'complainant') return inherited.complaint?.complainant_name || 'Complainant';
    return it.owner_ref || '';
  };

  const printActivityForensicRequest = async (act) => {
    if (!id) {
      alert('Pehle case save karein, phir print karein.');
      return;
    }
    const devices = (act.seize_items || [])
      .filter(it => it.item_type || it.make_model || it.imei || it.serial_no || it.description)
      .map(it => ({
        type: it.item_type || 'Digital Device',
        model: it.make_model || '—',
        imei: it.imei || it.serial_no || 'N/A',
        owner: getSeizeItemOwnerText(it),
        storage_capacity: it.storage_capacity || '—',
      }));
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/cases/${id}/forensic-request-print`, {
        params: { devices: JSON.stringify(devices), analysis_scope: (act.analysis_scope || '').trim() },
      });
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || 'Could not print scope letter.');
    }
  };

  const submitActivityToForensic = async (act, destination = 'forensic') => {
    if (!id) {
      alert('Pehle case save karein, phir submit karein.');
      return;
    }
    setSendingForensic(true);
    try {
      await saveCase({ navigateAway: false });
      if (destination === 'forensic') {
        if (!act.checklist_tech_report || !act.checklist_seizure_memo) {
          alert('Forensic submission ke liye Technical Report aur Seizure Memo dono verified/attached hona compulsory hai.');
          return;
        }
        const catLower = (act.case_category || '').toLowerCase();
        const isAudio = catLower.includes('audio') || catLower.includes('voice');
        if (isAudio) {
          if (!act.audio_script || !String(act.audio_script).trim()) {
            alert('Audio Forensic ke liye written transcript / script likhna compulsory hai.');
            return;
          }
          if (!act.audio_source_file || !act.audio_sample_file) {
            alert('Audio Forensic ke liye Source File aur Sample File dono attach karna compulsory hain.');
            return;
          }
        }
      }
      const allItems = (act.seize_items || [])
        .filter(it => it.item_type || it.make_model || it.imei || it.serial_no || it.description);
      const unlockedItems = allItems.filter(it => !isSeizeItemLocked(it));
      if (!unlockedItems.length && allItems.length) {
        alert('Yeh items pehle hi forensic / technical ko bhej diye gaye hain. Naye items add karke dubara submit karein.');
        return;
      }
      const items = unlockedItems.map(it => ({
          item_type: it.item_type || 'other',
          make_model: it.make_model || null,
          imei: it.imei || null,
          imei2: it.imei2 || null,
          serial_no: it.serial_no || null,
          storage_capacity: it.storage_capacity || null,
          condition: it.condition || null,
          seized_from: getSeizeItemOwnerText(it) || null,
          quantity: Number(it.quantity) > 0 ? Number(it.quantity) : 1,
          description: it.description || null,
        }));
      const fd = new FormData();
      fd.append('case_id', String(id));
      if (form.enquiry_id) fd.append('enquiry_id', String(form.enquiry_id));
      fd.append('destination', destination);
      fd.append('external_category', act.case_category || 'Financial Fraud');
      fd.append('analysis_scope', act.analysis_scope || '');
      fd.append('external_scope', act.analysis_scope || '');
      fd.append('note', act.description || `Seizure memo evidence submitted for ${destination} examination.`);
      fd.append('checklist_tech_report', act.checklist_tech_report ? '1' : '0');
      fd.append('checklist_seizure_memo', act.checklist_seizure_memo ? '1' : '0');
      fd.append('checklist_fir_copy', act.checklist_fir_copy ? '1' : '0');
      if (act.audio_script) fd.append('audio_script', act.audio_script);
      if (act.audio_source_file) fd.append('audio_source', act.audio_source_file);
      if (act.audio_sample_file) fd.append('audio_sample', act.audio_sample_file);
      fd.append('items', JSON.stringify(items.length ? items : [{
        item_type: 'other',
        description: act.description || 'Seizure evidence items',
        quantity: 1,
      }]));
      const r = await api.post('/forensic-requests', fd);
      alert(r.data?.message || 'Seized evidence submitted.');
      const submittedKeys = new Set(unlockedItems.map(seizeItemKey));
      setForm(f => ({
        ...f,
        activities: f.activities.map(a => ({
          ...a,
          seize_items: (a.seize_items || []).map(it =>
            submittedKeys.has(seizeItemKey(it)) ? { ...it, locked: true, submitted: true } : it
          ),
        })),
      }));
      await loadLinkedForensicRequests(id, form.enquiry_id);
    } catch (err) {
      alert(err.response?.data?.message || err.message || 'Submission failed.');
    } finally {
      setSendingForensic(false);
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
          <select className="cf-input" value={form[field]} onChange={setF(field)} required={required} style={fieldErr ? { borderColor: '#e53e3e' } : {}}>
            <option value="">Select {label}</option>
            {options.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
          </select>
        ) : rows ? (
          <textarea className="cf-input" value={form[field]} onChange={setF(field)} placeholder={placeholder} rows={rows} required={required} style={fieldErr ? { borderColor: '#e53e3e' } : {}} />
        ) : (
          <input type={type} className="cf-input" value={form[field]} onChange={setF(field)} placeholder={placeholder} required={required} style={fieldErr ? { borderColor: '#e53e3e' } : {}} />
        )}
        {fieldErr && <div className="cf-error">{fieldErr}</div>}
      </div>
    );
  };

  return (
    <div className="page-content" style={{ margin: '0 auto' }}>
      <form onSubmit={handleSubmit}>
        <div className="page-header">
          <div className="page-title-group">
            <h1 className="page-title">{id ? 'Edit Case/FIR' : 'New Case/FIR'}</h1>
            <p className="page-subtitle">{id ? 'Update case details' : 'Register a new case/FIR'}</p>
            <div className="title-underline"></div>
          </div>
        </div>

        <div className="cf-tabs" style={{ display: 'flex', gap: '4px', marginBottom: '20px', borderBottom: '2px solid var(--border)', paddingBottom: '4px', flexWrap: 'wrap' }}>
          {['details', 'history', 'activities', 'arrests', 'legal', 'approvals', 'outcome', 'chat'].map(tab => (
            <button key={tab} type="button" className={`cf-tab ${activeTab === tab ? 'active' : ''}`} onClick={() => setActiveTab(tab)} style={{ padding: '10px 20px', border: 'none', background: activeTab === tab ? 'var(--primary)' : 'transparent', color: activeTab === tab ? '#fff' : '#666', borderRadius: '8px 8px 0 0', fontWeight: 600, cursor: 'pointer', fontSize: 13 }}>
              {tab === 'details' && 'Details'}
              {tab === 'history' && 'Process History'}
              {tab === 'activities' && 'Activities'}
              {tab === 'arrests' && 'Arrests'}
              {tab === 'legal' && 'Legal Opinions'}
              {tab === 'approvals' && 'Approvals'}
              {tab === 'outcome' && 'Outcome'}
              {tab === 'chat' && 'Case Discussion 💬'}
            </button>
          ))}
        </div>

        {serverError && (
          <div className="cf-alert cf-alert-error" style={{ marginBottom: 20 }}>{serverError}
            {Object.keys(errors).length > 0 && <ul style={{ margin: '8px 0 0 0', paddingLeft: '20px' }}>{Object.entries(errors).map(([k, v]) => <li key={k}><strong>{k}:</strong> {Array.isArray(v) ? v.join(', ') : v}</li>)}</ul>}
          </div>
        )}

        {/* DETAILS TAB */}
        {activeTab === 'details' && (
          <>
            <div className="cf-section">
              <div className="cf-section-header">
                <div className="cf-section-icon" style={{ background: '#015C94' }}>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                </div>
                <div><div className="cf-section-title">Case Reference</div><div className="cf-section-sub">Link to enquiry, or register VIP / direct FIR</div></div>
                <div className="cf-section-badge">STEP 1</div>
              </div>
              <div className="cf-body">
                <div className="cf-row-3">
                  <div className="cf-field">
                    <label className={`cf-label${directMode ? '' : ' required'}`}>{directMode ? '' : 'Enquiry'}</label>
                    {directMode ? (
                      <div className="cf-input-wrap" style={{background:'#F7F8FA',padding:'8px 10px',borderRadius:6,fontSize:13,color:'#015C94',display:'flex',alignItems:'center',gap:8,flexWrap:'wrap'}}>
                        Direct FIR (No Enquiry / Complaint)
                        <span
                          style={{color:'#015C94',cursor:'pointer',fontWeight:600,textDecoration:'underline'}}
                          onClick={() => { setDirectMode(false); setDirect(emptyDirectInfo()); }}
                        >Switch to Enquiry</span>
                      </div>
                    ) : (
                      <div className="cf-input-wrap">
                        <select className="cf-input" value={form.enquiry_id} onChange={setF('enquiry_id')} required={!directMode} style={errors.enquiry_id ? { borderColor: '#e53e3e' } : {}}>
                          <option value="">Select Enquiry</option>
                          {enquiries.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                        </select>
                        <div style={{marginTop:6}}>
                          <span style={{color:'#015C94',cursor:'pointer',fontWeight:600,fontSize:12,textDecoration:'underline'}} onClick={() => { setDirectMode(true); setForm(f => ({ ...f, enquiry_id: '' })); }}>
                            Enquiry nahi hai? Direct FIR create karein
                          </span>
                        </div>
                      </div>
                    )}
                    {errors.enquiry_id && <div className="cf-error">{errors.enquiry_id}</div>}
                  </div>
                  <div className="cf-field">
                    <label className="cf-label">FIR Number</label>
                    <div style={{ padding: '9px 14px', background: '#f5f5f5', borderRadius: 8, fontSize: 13, border: '1.5px solid var(--border)', fontWeight: 700, fontFamily: 'monospace' }}>{form.fir_no || 'Auto-generated on save'}</div>
                  </div>
                  <div className="cf-field">
                    <label className="cf-label required">Status</label>
                    <select
                      className="cf-input"
                      value={toSimpleStatus(form.status)}
                      onChange={e => setForm(f => ({ ...f, status: fromSimpleStatus(e.target.value, f.status) }))}
                    >
                      {SIMPLE_STATUSES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                    </select>
                  </div>
                  <div className="cf-field">
                    <label className="cf-label">Priority</label>
                    <select className="cf-input" value={form.priority || 'normal'} onChange={setF('priority')}>
                      {PRIORITY_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                    </select>
                  </div>
                </div>

                {directMode && (
                  <DirectRegistrationFields
                    direct={direct}
                    setDirect={setDirect}
                    errors={errors}
                    title="VIP / Direct FIR Details"
                    subtitle="Same fields as CMS Case Registration Form"
                    showCaseExtras
                  />
                )}
              </div>
            </div>

            {inherited.complaint && (
              <div className="cf-section" style={{ marginBottom: 16 }}>
                <div className="cf-section-header">
                  <div className="cf-section-icon" style={{ background: '#2d6a4f' }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  </div>
                  <div><div className="cf-section-title">Previous Record</div><div className="cf-section-sub">Auto-fetched from complaint / enquiry</div></div>
                </div>
                <div className="cf-body">
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 12, fontSize: 13, marginBottom: 14 }}>
                    {[
                      ['Complainant', inherited.complaint.complainant_name],
                      ['CNIC', inherited.complaint.cnic],
                      ['Contact', inherited.complaint.contact_no],
                      ['Offence', inherited.complaint.offence_type],
                      ['Tracking No', inherited.complaint.tracking_no],
                    ].map(([label, val]) => (
                      <div key={label}><strong style={{ color: '#666', fontSize: 11, textTransform: 'uppercase' }}>{label}</strong><div style={{ marginTop: 2 }}>{val || '—'}</div></div>
                    ))}
                  </div>
                  {inherited.accused.length > 0 && (
                    <div style={{ marginBottom: 12 }}>
                      <strong style={{ fontSize: 12, color: '#015C94' }}>Accused</strong>
                      <ul style={{ margin: '6px 0 0 18px', fontSize: 13 }}>
                        {inherited.accused.map((a, i) => (
                          <li key={i}>{a.name || '—'} {a.cnic ? `(${a.cnic})` : ''} {a.father_name ? `s/o ${a.father_name}` : ''}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                  {inherited.witnesses.length > 0 && (
                    <div style={{ marginBottom: 12 }}>
                      <strong style={{ fontSize: 12, color: '#015C94' }}>Witnesses</strong>
                      <ul style={{ margin: '6px 0 0 18px', fontSize: 13 }}>
                        {inherited.witnesses.map((w, i) => (
                          <li key={i}>{w.name || '—'} {w.cnic ? `(${w.cnic})` : ''}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                  {inherited.devices.length > 0 && (
                    <div>
                      <strong style={{ fontSize: 12, color: '#015C94' }}>Seized devices</strong>
                      <ul style={{ margin: '6px 0 0 18px', fontSize: 13 }}>
                        {inherited.devices.map((d, i) => (
                          <li key={i}>{(d.item_type || d.type || 'Device')} {d.make_model || ''} {d.imei ? `IMEI ${d.imei}` : (d.serial_no ? `SN ${d.serial_no}` : '')}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                </div>
              </div>
            )}
            {id && <OfficerHistoryPanel endpoint={`/cases/${id}/officer-history`} />}
          </>
        )}

        {/* PROCESS HISTORY TAB */}
        {activeTab === 'history' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#2d6a4f' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="10"/></svg>
              </div>
              <div><div className="cf-section-title">Full Process History</div><div className="cf-section-sub">Complaint → VO verification → EO enquiry (live, read-only)</div></div>
            </div>
            <div className="cf-body">
              <CaseProcessHistoryPanel inherited={inherited} />
            </div>
          </div>
        )}

        {/* ACTIVITIES TAB */}
        {activeTab === 'activities' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#264078' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
              </div>
              <div><div className="cf-section-title">Investigation Activities</div><div className="cf-section-sub">DAC, Search Warrant, Raid, Arrest, Summons, Diaries, Seizures, Forensic, Recoveries</div></div>
              <div className="cf-section-badge">STEP 3</div>
            </div>
            <div className="cf-body">
              {(inherited.enquiryActivities || []).length > 0 && (
                <div style={{ marginBottom: 20, padding: 14, background: '#fffbeb', border: '1px solid #fde68a', borderRadius: 8 }}>
                  <div style={{ fontWeight: 800, fontSize: 13, color: '#92400e', marginBottom: 10 }}>
                    Enquiry phase (EO) — read only · {inherited.enquiryActivities.length} record(s)
                  </div>
                  {inherited.enquiryActivities.map((act, i) => (
                    <div key={act.id || `eo-${i}`} style={{ padding: 10, marginBottom: 8, background: '#fff', border: '1px solid #fcd34d', borderRadius: 6, fontSize: 12 }}>
                      <strong>{ACTIVITY_TYPES.find(t => t.value === act.type)?.name || act.type}</strong>
                      {' · '}{act.activity_date ? String(act.activity_date).slice(0, 10) : ''}
                      {act.creator?.name ? ` · ${act.creator.name}` : ''}
                      {act.description && <div style={{ marginTop: 4, whiteSpace: 'pre-wrap' }}>{act.description}</div>}
                    </div>
                  ))}
                  <p style={{ margin: '8px 0 0', fontSize: 11, color: '#92400e' }}>Case investigation activities add karein — neeche &quot;Add Activity&quot; use karein.</p>
                </div>
              )}
              <button type="button" className="btn btn-outline btn-sm" onClick={addActivity} style={{ marginBottom: 16 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Activity
              </button>
              {form.activities.map((a, i) => (
                <div key={a.id || a._uid || `new-act-${i}`} className={isSavedDiaryLocked(a, user) ? 'diary-saved-locked' : undefined} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  {isSavedDiaryLocked(a, user) && (
                    <div style={{ padding: '8px 12px', marginBottom: 12, background: '#e2e8f0', color: '#334155', borderRadius: 6, fontSize: 12, fontWeight: 700 }}>
                      Saved diary entry — only Circle Incharge can edit or delete. New activities abhi bhi add ki ja sakti hain.
                    </div>
                  )}
                  <div style={{ display: 'grid', gridTemplateColumns: '1.2fr 1.2fr 1fr 1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Activity Type</label>
                      <select className="cf-input" value={a.type} disabled={activityHasLockedSeizeItems(a)} onChange={e => updateActivity(i, 'type', e.target.value)}>
                        <option value="">— Select Type —</option>
                        {ACTIVITY_TYPES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    {a.type !== 'seizure' && (
                    <div className="cf-field"><label className="cf-label">Case Category</label>
                      <select className="cf-input" value={a.case_category || 'Financial Fraud'} onChange={e => updateActivity(i, 'case_category', e.target.value)}>
                        {CASE_CATEGORIES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    )}
                    <div className="cf-field"><label className="cf-label">Date</label>
                      <input type="date" className="cf-input" value={a.activity_date} onChange={e => updateActivity(i, 'activity_date', e.target.value)} />
                    </div>
                    <div className="cf-field"><label className="cf-label">Attachment</label>
                      <input type="file" className="cf-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onChange={e => updateActivityFile(i, e.target.files[0])} />
                    </div>
                    {!activityHasLockedSeizeItems(a) && !isSavedDiaryLocked(a, user) && (
                    <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end', justifySelf: 'end' }} onClick={() => removeActivity(i)}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    )}
                  </div>
                  {!WARRANT_TYPES.includes(a.type) && (
                    <div className="cf-field"><label className="cf-label">Description</label>
                      <textarea className="cf-input" rows={2} value={a.description} onChange={e => updateActivity(i, 'description', e.target.value)} placeholder="Describe the activity…" style={{ width: '100%' }}></textarea>
                    </div>
                  )}

                  {WARRANT_TYPES.includes(a.type) && (
                    <div style={{ marginTop: 4, padding: 14, background: '#fff', border: '1.5px solid #cbd5e1', borderRadius: 8 }}>
                      <div style={{ fontWeight: 800, fontSize: 13, color: '#015C94', marginBottom: 12 }}>
                        {a.type === 'raid' ? 'Raid Permission Details' : a.type === 'arrest_warrant' ? 'Arrest Warrant Details' : 'Search Warrant Details'}
                      </div>
                      <div className="cf-field" style={{ marginBottom: 10 }}>
                        <label className="cf-label required">Subject</label>
                        <input
                          type="text"
                          className="cf-input"
                          value={a.subject || ''}
                          onChange={e => updateActivity(i, 'subject', e.target.value)}
                          placeholder={a.type === 'raid'
                            ? 'PERMISSION TO CONDUCT A RAID IN CASE FIR NO. …'
                            : a.type === 'arrest_warrant'
                              ? 'ARREST WARRANT IN FIR NO. …'
                              : 'SEARCH WARRANT U/S 33 PECA-2016 IN FIR NO. …'}
                          style={{ width: '100%' }}
                        />
                      </div>
                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10, marginBottom: 10 }}>
                        <div className="cf-field">
                          <label className="cf-label required">Kota / Location (Premises)</label>
                          <input
                            type="text"
                            className="cf-input"
                            value={a.kota || ''}
                            onChange={e => updateActivity(i, 'kota', e.target.value)}
                            placeholder="Address / kota jahan raid ya search hogi"
                          />
                        </div>
                        <div className="cf-field">
                          <label className="cf-label required">Kis ke khilaf (Against whom)</label>
                          <select
                            className="cf-input"
                            value={(inherited.accused || []).some(ac => ac.name === a.against_whom) ? (a.against_whom || '') : ''}
                            onChange={e => updateActivity(i, 'against_whom', e.target.value)}
                          >
                            <option value="">— Select accused —</option>
                            {(inherited.accused || []).filter(ac => (ac.name || '').trim()).map((ac, idx) => (
                              <option key={idx} value={ac.name}>{ac.name}{ac.father_name ? ` s/o ${ac.father_name}` : ''}</option>
                            ))}
                            {(form.arrests || []).filter(ar => (ar.accused_name || '').trim() && !(inherited.accused || []).some(ac => ac.name === ar.accused_name)).map((ar, idx) => (
                              <option key={`arr-${idx}`} value={ar.accused_name}>{ar.accused_name}</option>
                            ))}
                          </select>
                          <input
                            type="text"
                            className="cf-input"
                            style={{ marginTop: 6 }}
                            value={a.against_whom || ''}
                            onChange={e => updateActivity(i, 'against_whom', e.target.value)}
                            placeholder="Ya manually naam likhein"
                          />
                        </div>
                      </div>
                      <div className="cf-field" style={{ marginBottom: 10 }}>
                        <label className="cf-label required">Kab karna hay (Date &amp; Time)</label>
                        <input
                          type="datetime-local"
                          className="cf-input"
                          value={a.scheduled_at || ''}
                          onChange={e => updateActivity(i, 'scheduled_at', e.target.value)}
                        />
                      </div>
                      <div className="cf-field" style={{ marginBottom: 12 }}>
                        <label className="cf-label">Brief facts</label>
                        <textarea
                          className="cf-input"
                          rows={3}
                          value={a.description || ''}
                          onChange={e => updateActivity(i, 'description', e.target.value)}
                          placeholder="Mukhtasir facts — complainant, offence, kyun raid/search/arrest darkar hai"
                          style={{ width: '100%' }}
                        />
                      </div>
                      <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
                        {a.type === 'raid' && (
                          <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => printWarrantFromActivity('raid-permission-print', a)}>
                            Print Raid Permission
                          </button>
                        )}
                        {a.type === 'search_seize' && (
                          <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => printWarrantFromActivity('search-warrant-print', a)}>
                            Print Search Warrant
                          </button>
                        )}
                        {a.type === 'arrest_warrant' && (
                          <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => printWarrantFromActivity('arrest-warrant-print', a)}>
                            Print Arrest Warrant
                          </button>
                        )}
                      </div>
                    </div>
                  )}

                  {a.type === 'seizure' && (
                    <div style={{ marginTop: 14, padding: 14, background: '#fff', border: '1.5px solid #bfdbfe', borderRadius: 8, boxShadow: '0 2px 8px rgba(1,92,148,0.06)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, flexWrap: 'wrap', gap: 8 }}>
                        <div>
                          <strong style={{ fontSize: 13.5, color: '#015C94', display: 'block' }}>📦 Seized Evidence &amp; Digital Devices</strong>
                          <span style={{ fontSize: 11, color: '#64748b' }}>Seized items darj karein, analysis scope likhein aur Circle Incharge ko mark karein ya print karein.</span>
                        </div>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                          {!isSupervisor && (
                            <button type="button" className="btn btn-outline btn-sm" onClick={() => addSeizeItem(i)}>+ Add Item</button>
                          )}
                          <button type="button" className="btn btn-outline btn-sm" style={{ color: '#015C94', borderColor: '#015C94', fontWeight: 600 }} data-locked-ok="1" onClick={() => printActivityForensicRequest(a)}>
                            Print Scope Letter
                          </button>
                          {!isSupervisor && (
                            <>
                              <button type="button" className="btn btn-outline btn-sm" style={{ color: '#0f766e', borderColor: '#0f766e', fontSize: 12, fontWeight: 700 }} data-locked-ok="1" disabled={sendingForensic} onClick={() => submitActivityToForensic(a, 'technical')}>
                                {sendingForensic ? 'Submitting…' : '⚙️ Submit to Technical'}
                              </button>
                              <button type="button" className="btn btn-outline btn-sm" style={{ color: '#64748b', borderColor: '#94a3b8', fontSize: 12, fontWeight: 600 }} disabled={saving} onClick={() => saveCase({ navigateAway: false })}>
                                💾 Save
                              </button>
                              <button type="button" className="btn btn-primary btn-sm" style={{ background: '#015C94', color: '#fff', fontSize: 12, fontWeight: 700 }} data-locked-ok="1" disabled={sendingForensic} onClick={() => submitActivityToForensic(a, 'forensic')}>
                                {sendingForensic ? 'Submitting…' : '📋 Mark to Circle Incharge'}
                              </button>
                            </>
                          )}
                        </div>
                      </div>

                      {(a.seize_items || []).map((it, si) => {
                        const itemLocked = isSupervisor || isSeizeItemLocked(it);
                        return (
                        <div key={si} style={{ padding: 12, marginBottom: 12, background: '#f8fafc', borderRadius: 8, border: isSeizeItemLocked(it) ? '1px solid #94a3b8' : '1px solid #e2e8f0' }}>
                          {isSeizeItemLocked(it) && (
                            <div style={{ marginBottom: 8, padding: '6px 10px', background: '#e2e8f0', color: '#334155', borderRadius: 6, fontSize: 12, fontWeight: 700 }}>
                              🔒 Yeh item forensic / technical ko bhej diya gaya hai — edit ya delete nahi ho sakta.
                            </div>
                          )}
                          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1.2fr 1.2fr 1.5fr 1fr auto', gap: 10, marginBottom: 8 }}>
                            <div className="cf-field"><label className="cf-label required">Item Type</label>
                              <select className="cf-input" value={it.item_type || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'item_type', e.target.value)}>
                                <option value="">— Select —</option>
                                {SEIZE_ITEM_TYPES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                              </select>
                            </div>
                            <div className="cf-field"><label className="cf-label required">Owner Type</label>
                              <select className="cf-input" value={it.owner_type || ''} disabled={itemLocked} onChange={e => { updateSeizeItem(i, si, 'owner_type', e.target.value); updateSeizeItem(i, si, 'owner_ref', ''); }}>
                                <option value="">— Select —</option>
                                <option value="complainant">Complainant</option>
                                <option value="accused">Accused</option>
                                <option value="witness">Witness</option>
                                <option value="other">Other</option>
                              </select>
                            </div>
                            <div className="cf-field"><label className="cf-label required">Owner / Person</label>
                              {it.owner_type === 'accused' ? (
                                <select className="cf-input" value={it.owner_ref || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'owner_ref', e.target.value)}>
                                  <option value="">— Select Accused —</option>
                                  {(inherited.accused || []).map((ac, idx) => <option key={idx} value={idx}>{ac.name || `Accused ${idx + 1}`}</option>)}
                                  {(form.arrests || []).filter(ar => (ar.accused_name || '').trim()).map((ar, idx) => <option key={`arr-${idx}`} value={ar.accused_name}>{ar.accused_name}</option>)}
                                </select>
                              ) : it.owner_type === 'witness' ? (
                                <select className="cf-input" value={it.owner_ref || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'owner_ref', e.target.value)}>
                                  <option value="">— Select Witness —</option>
                                  {(inherited.witnesses || []).map((w, idx) => <option key={idx} value={idx}>{w.name || `Witness ${idx + 1}`}</option>)}
                                </select>
                              ) : (
                                <input type="text" className="cf-input" placeholder={it.owner_type === 'complainant' ? 'Complainant (Auto)' : 'Name'} value={it.owner_type === 'complainant' ? (inherited.complaint?.complainant_name || it.owner_ref || '') : (it.owner_ref || '')} onChange={e => updateSeizeItem(i, si, 'owner_ref', e.target.value)} disabled={itemLocked || it.owner_type === 'complainant'} />
                              )}
                            </div>
                            <div className="cf-field"><label className="cf-label">Make / Model</label>
                              <input type="text" className="cf-input" placeholder="e.g. iPhone 15 Pro, Dell..." value={it.make_model || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'make_model', e.target.value)} />
                            </div>
                            <div className="cf-field"><label className="cf-label">IMEI / IMEI 2</label>
                              <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
                                <input type="text" className="cf-input" placeholder="IMEI 1" value={it.imei || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'imei', e.target.value)} />
                                <input type="text" className="cf-input" placeholder="IMEI 2" value={it.imei2 || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'imei2', e.target.value)} />
                              </div>
                            </div>
                            <div className="cf-field"><label className="cf-label">Serial Number</label>
                              <input type="text" className="cf-input" placeholder="Device Serial No" value={it.serial_no || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'serial_no', e.target.value)} />
                            </div>
                            {!isSupervisor && !isSeizeItemLocked(it) && (
                              <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: 8, width: 36, height: 36, alignSelf: 'end' }} onClick={() => removeSeizeItem(i, si)}>
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2 2v2"/></svg>
                              </button>
                            )}
                          </div>
                          <div style={{ display: 'grid', gridTemplateColumns: '90px 140px 1fr', gap: 10 }}>
                            <div className="cf-field"><label className="cf-label">Qty</label>
                              <input type="number" min={1} className="cf-input" value={it.quantity ?? 1} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'quantity', e.target.value)} />
                            </div>
                            <div className="cf-field"><label className="cf-label">Capacity / Storage</label>
                              <input type="text" className="cf-input" placeholder="e.g. 256GB, 1TB" value={it.storage_capacity || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'storage_capacity', e.target.value)} />
                            </div>
                            <div className="cf-field"><label className="cf-label">Item Description / Seized From</label>
                              <input type="text" className="cf-input" value={it.description || ''} disabled={itemLocked} onChange={e => updateSeizeItem(i, si, 'description', e.target.value)} placeholder="e.g. Seized from accused bedroom table..." />
                            </div>
                          </div>
                        </div>
                        );
                      })}

                      {(a.seize_items || []).length === 0 && (
                        <p style={{ margin: '0 0 10px 0', fontSize: 12, color: '#888' }}>No seized items entered yet. Click "+ Add Item".</p>
                      )}

                      <div style={{ marginTop: 12, borderTop: '1px solid #e2e8f0', paddingTop: 10, display: 'grid', gridTemplateColumns: '1fr 2fr', gap: 12 }}>
                        <div className="cf-field">
                          <label className="cf-label required"><strong>Case Category / Offence Type</strong></label>
                          <select className="cf-input" value={a.case_category || 'Financial Fraud'} onChange={e => updateActivity(i, 'case_category', e.target.value)}>
                            {CASE_CATEGORIES.map(cat => <option key={cat.value} value={cat.value}>{cat.name}</option>)}
                          </select>
                          <span className="cf-hint" style={{ fontSize: 11, color: '#64748b' }}>Forensic Lab analysis category</span>
                        </div>
                        <div className="cf-field">
                          <label className="cf-label"><strong>Analysis Scope (For Forensic Lab)</strong></label>
                          <textarea className="cf-input" rows={2} placeholder="e.g. Conduct forensic examination and data extraction to identify Facebook IDs, communication chats, emails, media..." value={a.analysis_scope || ''} onChange={e => updateActivity(i, 'analysis_scope', e.target.value)} style={{ width: '100%' }} />
                          <span className="cf-hint" style={{ fontSize: 11, color: '#64748b' }}>Ye scope text official Forensic Request PDF letter mein print hoga.</span>
                        </div>
                      </div>

                      <div style={{ marginTop: 14, padding: '12px 14px', background: '#eff6ff', borderRadius: 8, border: '1.5px solid #bfdbfe' }}>
                        <div style={{ fontWeight: 800, fontSize: 13, color: '#1e40af', marginBottom: 8 }}>📋 Forensic Submission Mandatory Checklist (Compulsory):</div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 10 }}>
                          <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: 'pointer' }}>
                            <input type="checkbox" checked={Boolean(a.checklist_tech_report)} onChange={e => updateActivity(i, 'checklist_tech_report', e.target.checked)} />
                            <span><strong>Technical Report</strong> verified &amp; attached <span style={{ color: '#dc2626' }}>*</span></span>
                          </label>
                          <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: 'pointer' }}>
                            <input type="checkbox" checked={Boolean(a.checklist_seizure_memo)} onChange={e => updateActivity(i, 'checklist_seizure_memo', e.target.checked)} />
                            <span><strong>Seizure Memo</strong> verified &amp; generated <span style={{ color: '#dc2626' }}>*</span></span>
                          </label>
                          <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: 'pointer' }}>
                            <input type="checkbox" checked={Boolean(a.checklist_fir_copy)} onChange={e => updateActivity(i, 'checklist_fir_copy', e.target.checked)} />
                            <span><strong>FIR Copy</strong> (Certified) <span style={{ color: '#dc2626' }}>*</span></span>
                          </label>
                        </div>
                      </div>

                      {(String(a.case_category || '').toLowerCase().includes('audio') || String(a.case_category || '').toLowerCase().includes('voice')) && (
                        <div style={{ marginTop: 14, padding: 14, background: '#fffbeb', borderRadius: 8, border: '1.5px solid #fde68a' }}>
                          <div style={{ color: '#b45309', fontWeight: 800, fontSize: 13, marginBottom: 6 }}>All Audio &amp; Voice Forensic Examinations are routed to NCCIA Forensic HQ, Islamabad.</div>
                          <div className="cf-field" style={{ marginBottom: 10 }}>
                            <label className="cf-label required">Audio Script / Written Transcript</label>
                            <textarea className="cf-input" rows={2} value={a.audio_script || ''} onChange={e => updateActivity(i, 'audio_script', e.target.value)} placeholder="Enter complete written transcript..." />
                          </div>
                          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                            <div className="cf-field">
                              <label className="cf-label required">Source Audio File</label>
                              <input type="file" className="cf-input" accept="audio/*,.wav,.mp3,.m4a,.aac,.ogg" onChange={e => updateActivity(i, 'audio_source_file', e.target.files?.[0] || null)} />
                            </div>
                            <div className="cf-field">
                              <label className="cf-label required">Sample Audio File</label>
                              <input type="file" className="cf-input" accept="audio/*,.wav,.mp3,.m4a,.aac,.ogg" onChange={e => updateActivity(i, 'audio_sample_file', e.target.files?.[0] || null)} />
                            </div>
                          </div>
                        </div>
                      )}
                    </div>
                  )}

                  {a.type === 'forensic_report' && (
                    <div style={{ marginTop: 12, padding: '12px 14px', background: '#eff6ff', borderRadius: 8, border: '1.5px solid #bfdbfe' }}>
                      <div style={{ fontWeight: 800, fontSize: 13, color: '#1e40af', marginBottom: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
                        📋 IO Forensic Submission Mandatory Checklist (Compulsory for FIR):
                      </div>
                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 10 }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: 'pointer' }}>
                          <input
                            type="checkbox"
                            checked={Boolean(a.checklist_tech_report)}
                            onChange={e => updateActivity(i, 'checklist_tech_report', e.target.checked)}
                          />
                          <span><strong>Technical Report</strong> <span style={{ color: '#dc2626' }}>*</span></span>
                        </label>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: 'pointer' }}>
                          <input
                            type="checkbox"
                            checked={Boolean(a.checklist_seizure_memo)}
                            onChange={e => updateActivity(i, 'checklist_seizure_memo', e.target.checked)}
                          />
                          <span><strong>Seizure Memo</strong> <span style={{ color: '#dc2626' }}>*</span></span>
                        </label>
                        <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: 'pointer' }}>
                          <input
                            type="checkbox"
                            checked={Boolean(a.checklist_fir_copy)}
                            onChange={e => updateActivity(i, 'checklist_fir_copy', e.target.checked)}
                          />
                          <span><strong>FIR Copy</strong> (Certified) <span style={{ color: '#dc2626' }}>*</span></span>
                        </label>
                      </div>
                    </div>
                  )}

                  {/* Audio Forensics Specific Requirements & Islamabad Routing */}
                  {(a.type !== 'seizure' && (String(a.case_category || '').toLowerCase().includes('audio') || String(a.case_category || '').toLowerCase().includes('voice'))) && (
                    <div style={{ marginTop: 12, padding: '14px', background: '#fffbeb', borderRadius: 8, border: '1.5px solid #fde68a' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: '#b45309', fontWeight: 800, fontSize: 13, marginBottom: 6 }}>
                        🏛️ NOTICE: All Audio &amp; Voice Forensic Examinations are exclusively routed to and examined at NCCIA Forensic HQ, Islamabad.
                      </div>
                      <p style={{ fontSize: 11.5, color: '#92400e', marginBottom: 10 }}>
                        For Audio/Voice examination, a written script and both audio files (Source &amp; Sample) are <strong>compulsory</strong>.
                      </p>

                      <div className="cf-field" style={{ marginBottom: 10 }}>
                        <label className="cf-label required"><strong>1. Audio Script / Written Transcript (Compulsory):</strong></label>
                        <textarea
                          className="cf-input"
                          rows={2}
                          placeholder="Enter complete written transcript/dialogue of the disputed audio..."
                          value={a.audio_script || ''}
                          onChange={e => updateActivity(i, 'audio_script', e.target.value)}
                        />
                      </div>

                      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                        <div className="cf-field">
                          <label className="cf-label required"><strong>2. Source Audio File:</strong></label>
                          <input
                            type="file"
                            className="cf-input"
                            accept="audio/*,.wav,.mp3,.m4a,.aac,.ogg"
                            onChange={e => updateActivity(i, 'audio_source_file', e.target.files?.[0] || null)}
                          />
                        </div>
                        <div className="cf-field">
                          <label className="cf-label required"><strong>3. Sample Audio File:</strong></label>
                          <input
                            type="file"
                            className="cf-input"
                            accept="audio/*,.wav,.mp3,.m4a,.aac,.ogg"
                            onChange={e => updateActivity(i, 'audio_sample_file', e.target.files?.[0] || null)}
                          />
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              ))}
              {form.activities.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No activities added yet. Click "Add Activity" to start.</p>}
            </div>
          </div>
        )}

        {/* ARRESTS TAB */}
        {activeTab === 'arrests' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#015C94' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
              <div><div className="cf-section-title">Arrests</div><div className="cf-section-sub">Record arrests with accused details and remand information</div></div>
              <div className="cf-section-badge">STEP 4</div>
            </div>
            <div className="cf-body">
              {inherited.accused.length > 0 && (
                <div style={{ marginBottom: 20, padding: 14, background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8 }}>
                  <div style={{ fontWeight: 800, fontSize: 13, color: '#1e40af', marginBottom: 10 }}>
                    Accused from enquiry (live, read-only)
                  </div>
                  <div style={{ overflowX: 'auto' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: 12 }}>
                      <thead>
                        <tr style={{ background: '#dbeafe' }}>
                          <th style={{ border: '1px solid #93c5fd', padding: 6, textAlign: 'left' }}>Name</th>
                          <th style={{ border: '1px solid #93c5fd', padding: 6, textAlign: 'left' }}>Father</th>
                          <th style={{ border: '1px solid #93c5fd', padding: 6, textAlign: 'left' }}>CNIC</th>
                          <th style={{ border: '1px solid #93c5fd', padding: 6, textAlign: 'left' }}>Contact</th>
                          <th style={{ border: '1px solid #93c5fd', padding: 6 }}>Action</th>
                        </tr>
                      </thead>
                      <tbody>
                        {inherited.accused.map((acc, i) => (
                          <tr key={acc.id || i}>
                            <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{acc.name || '—'}</td>
                            <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{acc.father_name || '—'}</td>
                            <td style={{ border: '1px solid #e2e8f0', padding: 6, fontFamily: 'monospace' }}>{acc.cnic || '—'}</td>
                            <td style={{ border: '1px solid #e2e8f0', padding: 6 }}>{acc.contact_no || acc.whatsapp_no || '—'}</td>
                            <td style={{ border: '1px solid #e2e8f0', padding: 6, textAlign: 'center' }}>
                              <button type="button" className="btn btn-outline btn-sm" onClick={() => addArrest(acc)} style={{ fontSize: 11 }}>
                                Record arrest
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 16 }}>
              <button type="button" className="btn btn-outline btn-sm" onClick={() => addArrest()}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Arrest
              </button>
              </div>
              {form.arrests.map((a, i) => (
                <div key={a.id || a._uid || `new-arr-${i}`} className={isSavedDiaryLocked(a, user) ? 'diary-saved-locked' : undefined} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  {isSavedDiaryLocked(a, user) && (
                    <div style={{ padding: '8px 12px', marginBottom: 12, background: '#e2e8f0', color: '#334155', borderRadius: 6, fontSize: 12, fontWeight: 700 }}>
                      Saved arrest — only Circle Incharge can edit or delete.
                    </div>
                  )}
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label required">Accused Name</label>
                      <input type="text" className="cf-input" value={a.accused_name} onChange={e => updateArrest(i, 'accused_name', e.target.value)} placeholder="Accused name" required />
                    </div>
                    <div className="cf-field"><label className="cf-label required">CNIC</label>
                      <input type="text" className="cf-input font-mono" value={a.cnic} onChange={e => {let v=e.target.value.replace(/\D/g,'').slice(0,13);if(v.length>5)v=v.slice(0,5)+'-'+v.slice(5);if(v.length>13)v=v.slice(0,13)+'-'+v.slice(13);updateArrest(i,'cnic',v);}} maxLength={15} placeholder="00000-0000000-0" required />
                    </div>
                    <div className="cf-field"><label className="cf-label required">Arrest Date</label>
                      <input type="date" className="cf-input" value={a.arrest_date} onChange={e => updateArrest(i, 'arrest_date', e.target.value)} required />
                    </div>
                    {!isSavedDiaryLocked(a, user) && (
                    <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end', justifySelf: 'end' }} onClick={() => removeArrest(i)}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                    )}
                  </div>
                  <div className="cf-field"><label className="cf-label">Remand Details</label>
                    <textarea className="cf-input" rows={2} value={a.remand_details} onChange={e => updateArrest(i, 'remand_details', e.target.value)} placeholder="Remand details, court orders, etc." style={{ width: '100%' }}></textarea>
                  </div>
                </div>
              ))}
              {form.arrests.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No arrests recorded yet.</p>}
            </div>
          </div>
        )}

        {/* LEGAL OPINIONS TAB */}
        {activeTab === 'legal' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#015C94' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
              <div><div className="cf-section-title">Legal Opinion Chain</div><div className="cf-section-sub">DD Legal → AD Legal → Additional Director → DG Legal</div></div>
              <div className="cf-section-badge">STEP 5</div>
            </div>
            <div className="cf-body">
              {!canFillLegal && (
                <div style={{ padding: '10px 14px', marginBottom: 16, background: '#eef4f8', border: '1px solid #c5d9e8', borderRadius: 8, fontSize: 13, color: '#2b5d7f' }}>
                  This section is read-only. Only Circle Incharge, DD Legal, AD Legal and Admin can add or modify legal opinions.
                </div>
              )}
              {canFillLegal && (
                <button type="button" className="btn btn-outline btn-sm" onClick={addLegalOpinion} style={{ marginBottom: 16 }}>
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Legal Opinion
                </button>
              )}
              {form.legal_opinions.map((lo, i) => (
                <div key={lo.id || lo._uid || `lo-${i}`} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Role</label>
                      <select className="cf-input" value={lo.role} onChange={e => updateLegalOpinion(i, 'role', e.target.value)} disabled={!canFillLegal}>
                        <option value="">— Select Role —</option>
                        {LEGAL_ROLES.map(r => <option key={r} value={r}>{r.toUpperCase().replace('_', ' ')}</option>)}
                      </select>
                    </div>
                    <div className="cf-field"><label className="cf-label">Decision</label>
                      <select className="cf-input" value={lo.decision} onChange={e => updateLegalOpinion(i, 'decision', e.target.value)} disabled={!canFillLegal}>
                        <option value="">— Select —</option>
                        {LEGAL_DECISIONS.map(d => <option key={d} value={d}>{d.charAt(0).toUpperCase() + d.slice(1)}</option>)}
                      </select>
                    </div>
                    <div className="cf-field"><label className="cf-label">Officer</label>
                      <select className="cf-input" value={lo.created_by} onChange={e => updateLegalOpinion(i, 'created_by', e.target.value)} disabled={!canFillLegal}>
                        <option value="">— Select Officer —</option>
                        {legalReviewOfficers.map(o => <option key={o.id} value={o.id}>{o.name}{o.designation ? ` (${o.designation})` : ''}</option>)}
                      </select>
                    </div>
                    {canFillLegal && (
                      <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end', justifySelf: 'end' }} onClick={() => removeLegalOpinion(i)}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    )}
                  </div>
                  <div className="cf-field"><label className="cf-label">Opinion Text</label>
                    <textarea className="cf-input" rows={3} value={lo.opinion_text} onChange={e => updateLegalOpinion(i, 'opinion_text', e.target.value)} disabled={!canFillLegal} placeholder="Enter legal opinion…" style={{ width: '100%' }}></textarea>
                  </div>
                </div>
              ))}
              {form.legal_opinions.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No legal opinions added yet.</p>}
            </div>
          </div>
        )}

        {/* APPROVALS TAB */}
        {activeTab === 'approvals' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#2B2B2B' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
              </div>
              <div><div className="cf-section-title">Circle Incharge / Legal Approvals</div><div className="cf-section-sub">Approval chain for case finalization</div></div>
              <div className="cf-section-badge">STEP 6</div>
            </div>
            <div className="cf-body">
              {!canFillLegal && (
                <div style={{ padding: '10px 14px', marginBottom: 16, background: '#eef4f8', border: '1px solid #c5d9e8', borderRadius: 8, fontSize: 13, color: '#2b5d7f' }}>
                  This section is read-only. Only Circle Incharge, DD Legal, AD Legal and Admin can add or modify approvals.
                </div>
              )}
              {canFillLegal && (
                <button type="button" className="btn btn-outline btn-sm" onClick={addApproval} style={{ marginBottom: 16 }}>
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Approval
                </button>
              )}
              {form.approvals.map((ap, i) => (
                <div key={ap.id || ap._uid || `ap-${i}`} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Reviewing Officer</label>
                      <select className="cf-input" value={ap.circle_incharge_id} onChange={e => updateApproval(i, 'circle_incharge_id', e.target.value)} disabled={!canFillLegal}>
                        <option value="">— Select —</option>
                        {legalReviewOfficers.map(o => <option key={o.id} value={o.id}>{o.name}{o.designation ? ' (' + o.designation + ')' : ''}</option>)}
                      </select>
                    </div>
                    <div className="cf-field"><label className="cf-label">Decision</label>
                      <select className="cf-input" value={ap.decision} onChange={e => updateApproval(i, 'decision', e.target.value)} disabled={!canFillLegal}>
                        <option value="">— Select —</option>
                        <option value="agree">Agree</option>
                        <option value="review">Review</option>
                      </select>
                    </div>
                    <div className="cf-field"><label className="cf-label">Remarks</label>
                      <input type="text" className="cf-input" value={ap.remarks} onChange={e => updateApproval(i, 'remarks', e.target.value)} disabled={!canFillLegal} placeholder="Remarks" />
                    </div>
                    {canFillLegal && (
                      <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end', justifySelf: 'end' }} onClick={() => removeApproval(i)}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    )}
                  </div>
                </div>
              ))}
              {form.approvals.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No approvals added yet.</p>}
            </div>
          </div>
        )}

        {/* OUTCOME TAB */}
        {activeTab === 'outcome' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#015C94' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              </div>
              <div><div className="cf-section-title">Final Outcome</div><div className="cf-section-sub">Challan U/S 173 CrPC / Transfer / Merge / Closure</div></div>
              <div className="cf-section-badge">STEP 7</div>
            </div>
            <div className="cf-body">
              <div className="cf-row-3">
                {renderField('Recommendation', 'recommendation', { options: RECOMMENDATIONS, required: true })}
              </div>
              <div className="cf-row-2">
                {renderField('Transfer Department', 'transfer_department')}
                {renderField('Transfer Circle', 'transfer_circle', { options: circleOptions })}
              </div>
              {renderField('Merge Complaint ID', 'merge_complaint_id', { placeholder: 'Complaint ID to merge with' })}
            </div>
          </div>
        )}

        {/* CASE DISCUSSION / CHAT TAB */}
        {activeTab === 'chat' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#015C94' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
              </div>
              <div>
                <div className="cf-section-title">Case Discussion Room</div>
                <div className="cf-section-sub">Real-time team chat & notes for Case/FIR #{form.fir_no || id}</div>
              </div>
            </div>
            <div className="cf-body" style={{ padding: 16 }}>
              <CaseChatPanel
                type="case"
                id={id || 1}
                caseNumber={form.fir_no || (id ? `CASE-${id}` : '')}
                title={complaintDetail?.complainant_name || direct?.complainant_name || ''}
                officers={form.investigation_officer_id ? [{ name: 'Investigation Officer', role_label: 'Assigned IO' }] : []}
                compact={false}
              />
            </div>
          </div>
        )}

        <div style={{ display: 'flex', gap: 12, justifyContent: 'flex-end', marginTop: 20, flexWrap: 'wrap' }}>
          <Link to="/cases" className="btn btn-outline">Cancel</Link>
          <button type="submit" className="btn btn-primary" disabled={saving || approvingCfr} style={{ background: '#015C94', color: '#fff', padding: '12px 24px', fontWeight: 600, fontSize: '14px', borderRadius: '8px', display: 'flex', alignItems: 'center', gap: '8px', border: 'none' }}>
            {saving ? 'Saving...' : (id ? 'Update Case' : 'Register Case/FIR')}
          </button>
          {canApproveCase && (
            <>
              <button
                type="button"
                className="btn btn-primary"
                disabled={saving || approvingCfr}
                onClick={() => handleApproveCfr('agree')}
                style={{ background: '#059669', color: '#fff', padding: '12px 24px', fontWeight: 700, fontSize: '14px', borderRadius: '8px', border: 'none', boxShadow: '0 2px 8px rgba(5,150,105,0.35)' }}
                title="Approve case CFR"
              >
                {approvingCfr ? 'Processing…' : 'Approve CFR'}
              </button>
              <button
                type="button"
                className="btn btn-outline"
                disabled={saving || approvingCfr}
                onClick={() => handleApproveCfr('disagree')}
                style={{ color: '#d97706', borderColor: '#d97706', padding: '12px 24px', fontWeight: 700, fontSize: '14px', borderRadius: '8px' }}
                title="Send back CFR to Investigation Officer"
              >
                Send Back to IO
              </button>
            </>
          )}
        </div>
      </form>
    </div>
  );
}

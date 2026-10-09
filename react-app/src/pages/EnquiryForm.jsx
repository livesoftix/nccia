import { useState, useEffect, useMemo, useRef } from 'react';
import { useParams, useNavigate, Link, useSearchParams } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../api';
import VerificationReportPanel from '../components/VerificationReportPanel';
import OfficerHistoryPanel from '../components/OfficerHistoryPanel';
import DirectRegistrationFields from '../components/DirectRegistrationFields';
import CaseChatPanel from '../components/CaseChatPanel';
import AudioSampleRecorder from '../components/AudioSampleRecorder';
import LiveCameraCaptureModal from '../components/LiveCameraCaptureModal';
import { canRegisterCaseFromEnquiry, enquiryReadyForCaseRegistration, canViewVerificationReportInEnquiry, canFillLegalAndApprove, isSavedDiaryLocked } from '../utils/permissions';
import { toLocalInput } from '../utils/datetime';
import { preparePrintWindow, writePrintWindow, closePrintWindow } from '../utils/print';
import {
  emptyDirectInfo,
  normalizeDirectInfo,
  buildDirectInfoPayload,
  CASE_CATEGORIES,
} from '../utils/directCaseOptions';
import { SIMPLE_STATUSES, PRIORITY_OPTIONS, toSimpleStatus, fromSimpleStatus, isSubmittedToCircle } from '../utils/simpleStatus';
import { isSeizeItemLocked, activityHasLockedSeizeItems, applyForensicLocksToActivities, seizeItemKey, lockSeizeItemsAgainstForensic } from '../utils/seizeItemLock';

const responseArray = (data, endpoint) => {
  if (Array.isArray(data)) return data;
  if (Array.isArray(data?.data)) return data.data;
  console.error(`Unexpected ${endpoint} response: expected an array.`);
  return [];
};

const ENQUIRY_STATUS = [
  { value: 'registered', name: 'Registered (Reader Branch)' },
  { value: 'assigned', name: 'Assigned to IO' },
  { value: 'in_progress', name: 'In Progress (IO Conducting Enquiry)' },
  { value: 'cfr_submitted', name: 'CFR Submitted' },
  { value: 'legal_review_dd', name: 'Legal Review - DD Legal' },
  { value: 'legal_review_ad', name: 'Legal Review - AD Legal' },
  { value: 'legal_review_dg', name: 'Legal Review - DG Legal' },
  { value: 'approved', name: 'Approved' },
  { value: 'closed', name: 'Closed' },
  { value: 'transferred', name: 'Transferred' },
  { value: 'converted_to_case', name: 'Converted to Case' },
  { value: 'referred_court', name: 'Referred to Court' },
];

const LEGAL_ROLES = ['circle_incharge', 'dd_legal', 'ad_legal', 'additional_director'];
const LEGAL_DECISIONS = ['agree', 'disagree', 'review'];

const ACTIVITY_TYPES = [
  { value: 'dac_request', name: 'DAC (Departmental Accounts Committee)' },
  { value: 'bank_record', name: 'Bank Enquiry' },
  { value: 'search_seize', name: 'Search Warrant' },
  { value: 'raid', name: 'Raid Permission / Operation' },
  { value: 'arrest_warrant', name: 'Arrest Warrant' },
  { value: 'notices', name: 'Summon Issued' },
  { value: 'diaries', name: 'Diary Entry' },
  { value: 'seizures', name: 'Seizure Memo' },
  { value: 'recoveries', name: 'Recovery' },
  { value: 'cfr', name: 'CFR (Challan/Final Report)' },
  { value: 'other', name: 'Other' },
];

const RECOMMENDATIONS = [
  { value: 'convert_to_case', name: 'FIR / Convert to Case' },
  { value: 'irrelevant', name: 'Irrelevant' },
  { value: 'lack_of_documents', name: 'Lack of Documents' },
  { value: 'transfer', name: 'Transfer' },
  { value: 'closure', name: 'Closure' },
];

const CFR_TYPES = [
  { value: 'CFR', name: 'CFR' },
  { value: 'SCFR', name: 'SCFR' },
];

const GENDER_OPTIONS = [
  { value: 'male', name: 'Male' },
  { value: 'female', name: 'Female' },
  { value: 'other', name: 'Other' },
];

const APPEARANCE_REMARKS_OPTIONS = [
  { value: 'appeared', name: 'Appeared' },
  { value: 'did_not_appear', name: 'Did Not Appear' },
];

const REQUISITION_TYPES = [
  { value: 'bank', name: 'Bank' },
  { value: 'telecom', name: 'Telecom' },
  { value: 'nadra', name: 'NADRA' },
  { value: 'travel', name: 'Travel History' },
];

const EMPTY_ACCUSED = {
  name: '', cnic: '', father_name: '', gender: '', contact_no: '', whatsapp_no: '',
  email: '', postal_address: '', permanent_address: '', religion: '', district_domicile: '',
  identification_mark: '', occupation: '', is_government: false, department_name: '', designation: '',
  description: '',
  cnic_attachment: null, passport_attachment: null, nadra_verisys_attachment: null,
};

const EMPTY_SEIZE_ITEM = {
  item_type: '', make_model: '', imei: '', imei2: '', serial_no: '', storage_capacity: '', condition: 'sealed', quantity: 1, description: '', owner_type: '', owner_ref: ''
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

const EMPTY_WITNESS = {
  name: '', father_name: '', relation: '', gender: '', cnic: '', domicile_district: '',
  nationality: '', passport: '', occupation: '', is_government: false, department_name: '',
  designation: '', scale: '', contact_no: '', whatsapp_no: '', mailing_address: '',
  permanent_address: '', address: '', attachment: null, picture: null, statement_attachment: null,
};

const EMPTY_ATTACHMENT = {
  title: '', attachment_date: new Date().toISOString().split('T')[0], file: null, file_path: '',
};

const EMPTY_REQUISITION = {
  type: '', email_to: '', subject: '', body: '', status: '', authorized_by: null, authorized_by_name: '',
};

const BASE_TAB_ORDER = [
  'details', 'accused', 'witnesses', 'notices', 'attachments',
  'activities', 'requisitions', 'legal', 'approvals', 'outcome', 'chat',
];

const CLOSURE_REASONS = [
  { value: 'non_pursuance', name: 'Non-Pursuance by Complainant' },
  { value: 'irrelevant', name: 'Irrelevant' },
  { value: 'invalid', name: 'Invalid' },
  { value: 'lack_of_evidence', name: 'Lack of Evidence' },
  { value: 'compromise', name: 'Compromise (Parties Settled)' },
];

const NOTICE_VIA_OPTIONS = [
  { value: 'whatsapp', name: 'WhatsApp' },
  { value: 'sms', name: 'SMS' },
  { value: 'email', name: 'Email' },
  { value: 'phone', name: 'Phone' },
  { value: 'fax', name: 'Fax' },
  { value: 'postal', name: 'Postal' },
  { value: 'call', name: 'Call' },
];

const PERSON_TYPE_OPTIONS = [
  { value: 'complainant', name: 'Complainant' },
  { value: 'accused', name: 'Accused' },
  { value: 'witness', name: 'Witness' },
];

const NOTICE_STATUS_OPTIONS = [
  { value: 'issued', name: 'Issued' },
  { value: 'served', name: 'Served / Appeared' },
  { value: 'unserved', name: 'Unserved' },
  { value: 'non_appearance', name: 'Non-Appearance' },
];

const NOTICE_TYPE_OPTIONS = [
  { value: 'Summon', name: 'Summon' },
  { value: 'Warning', name: 'Warning' },
  { value: 'Final Summon', name: 'Final Summon' },
  { value: 'Show Cause', name: 'Show Cause' },
  { value: 'Other', name: 'Other' },
];

const initialForm = {
  complaint_id: '',
  tracking_no: '',
  enquiry_number: '',
  reg_date: new Date().toISOString().split('T')[0],
  status: 'assigned',
  priority: 'normal',
  enquiry_officer_id: '',
  recommendation: '',
  closure_reason: '',
  transfer_department: '',
  transfer_circle: '',
  merge_complaint_id: '',
  cfr_type: '',
  cfr_date: '',
  charge_against: '',
  oral_evidence: '',
  documentary_evidence: '',
  plea: '',
  conclusion: '',
  cfr_remarks: '',
  activities: [],
  legal_opinions: [],
  approvals: [],
  accused: [],
  witnesses: [],
  notices: [],
  attachments: [],
  requisitions: [],
  technical_report: '',
  forensic_report: '',
};

export default function EnquiryForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { user } = useAuth();
  const userRoles = (user?.roles || []).map(r => (typeof r === 'string' ? r : r.name));
  const isSupervisor = userRoles.some(r => ['admin', 'circle_incharge', 'ad_legal', 'dd_legal', 'additional_director', 'director_general'].includes(r));
  const [form, setForm] = useState(initialForm);
  const [complaints, setComplaints] = useState([]);
  const [complaintDetail, setComplaintDetail] = useState(null);
  const [officers, setOfficers] = useState([]);
  const [legalOfficers, setLegalOfficers] = useState([]);
  const [circles, setCircles] = useState([]);
  const [circleIncharges, setCircleIncharges] = useState([]);
  const [saving, setSaving] = useState(false);
  const [submittingCfr, setSubmittingCfr] = useState(false);
  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState('');
  const [successMsg, setSuccessMsg] = useState('');
  const [activeTab, setActiveTab] = useState('details');
  const [technicalFile, setTechnicalFile] = useState(null);
  const [forensicFile, setForensicFile] = useState(null);
  const [technicalReportUrl, setTechnicalReportUrl] = useState('');
  const [forensicReportUrl, setForensicReportUrl] = useState('');
  const [sendingForensic, setSendingForensic] = useState(false);
  const [forensicSendMsg, setForensicSendMsg] = useState('');
  const [caseFileId, setCaseFileId] = useState(null);
  const [ioOfficers, setIoOfficers] = useState([]);
  const [registerIoId, setRegisterIoId] = useState('');
  const [registerRemarks, setRegisterRemarks] = useState('');
  const [registerSaving, setRegisterSaving] = useState(false);
  const [verificationReport, setVerificationReport] = useState(null);
  const [linkedVerification, setLinkedVerification] = useState(null);
  const [directMode, setDirectMode] = useState(!id && searchParams.get('direct') === '1');
  const [direct, setDirect] = useState(emptyDirectInfo());
  const [editingAccusedIndex, setEditingAccusedIndex] = useState(null);
  const [editingWitnessIndex, setEditingWitnessIndex] = useState(null);
  const [editingNoticeIndex, setEditingNoticeIndex] = useState(null);
  const [linkedForensicRequests, setLinkedForensicRequests] = useState([]);
  const linkedForensicRef = useRef([]);
  const [loadingLinkedReports, setLoadingLinkedReports] = useState(false);
  const [cameraModal, setCameraModal] = useState({ open: false, title: '', onCapture: null });
  const [showAccountOpeningModal, setShowAccountOpeningModal] = useState(false);
  const [accountOpeningData, setAccountOpeningData] = useState({
    account_no: '',
    cnic: '',
    account_title: '',
    bank_name: '',
    fraud_amount: '',
    fraud_type: '',
    reason: '',
    recovered_amount: '',
    mode_of_recovery: '',
  });

  const openAccountOpeningModal = () => {
    setAccountOpeningData({
      account_no: '',
      cnic: form.accused?.[0]?.cnic || '',
      account_title: form.accused?.[0]?.name || '',
      bank_name: '',
      fraud_amount: form.direct_info?.amount_pkr ? `PKR ${form.direct_info.amount_pkr}` : (form.complaint?.amount_lost ? `PKR ${form.complaint.amount_lost}` : ''),
      fraud_type: form.complaint?.offence_type || form.direct_info?.case_category || 'Financial Fraud',
      reason: 'Settlement reached / Account cleared from investigation',
      recovered_amount: '',
      mode_of_recovery: 'Pay Order / Cash / IBFT',
    });
    setShowAccountOpeningModal(true);
  };

  const printAccountOpeningProforma = (data) => {
    printDocument('account-opening-print', data || accountOpeningData);
    setShowAccountOpeningModal(false);
  };

  const loadLinkedForensicRequests = async (enquiryId) => {
    const targetId = enquiryId || id;
    if (!targetId || targetId === 'new') return;
    setLoadingLinkedReports(true);
    try {
      const res = await api.get(`/forensic-requests?enquiry_id=${targetId}`);
      const list = res.data?.data || [];
      linkedForensicRef.current = list;
      setLinkedForensicRequests(list);
    } catch (e) {
      console.warn('Could not load linked forensic requests', e);
    } finally {
      setLoadingLinkedReports(false);
    }
  };

  const roleNames = user?.roles?.map?.(r => r.name || r) || [user?.role].filter(Boolean);
  const isPrivileged = roleNames.some(r => ['admin', 'circle_incharge'].includes(r));
  const canFillLegal = canFillLegalAndApprove(user);
  const isEO = roleNames.some(r => ['enquiry_officer', 'investigation_officer'].includes(r)) && !roleNames.some(r => ['admin', 'circle_incharge', 'director_general'].includes(r));
  const canEditCfrRemarks = roleNames.some(r => ['admin', 'additional_director'].includes(r));
  const canAuthorizeRequisitions = roleNames.some(r => ['admin', 'director_general', 'additional_director'].includes(r));
  const canRegisterCase = canRegisterCaseFromEnquiry(user);
  const isEoLocked = !isSupervisor && !!id && isSubmittedToCircle(form.status);
  const canSubmitCfr = !isSupervisor && !isEoLocked && !!id
    && ['registered', 'assigned', 'in_progress', 'pending', 'working'].includes(form.status)
    && roleNames.some(r => ['enquiry_officer', 'investigation_officer'].includes(r));
  const showRegisterCase = !!id && canRegisterCase && enquiryReadyForCaseRegistration({ status: form.status, case_file_id: caseFileId });
  const showVerificationReport = !!id && canViewVerificationReportInEnquiry(user);
  const [approvingCfr, setApprovingCfr] = useState(false);

  const handleApproveCfr = async (decision = 'agree') => {
    if (!id) return;
    let remarks = '';
    if (decision === 'disagree') {
      const input = prompt('CFR Send Back karne ki wajah / deficiency remarks darj karein:');
      if (input === null) return;
      remarks = input.trim();
    }
    setApprovingCfr(true);
    setServerError('');
    try {
      await api.post(`/enquiries/${id}/approve`, {
        decision,
        recommendation: form.recommendation,
        closure_reason: form.closure_reason || null,
        transfer_department: form.transfer_department || null,
        transfer_circle: form.transfer_circle || null,
        merge_complaint_id: form.merge_complaint_id || null,
        remarks: remarks || form.cfr_remarks || null,
      });
      alert(decision === 'agree' ? 'CFR Approved successfully!' : 'CFR Sent back to Enquiry Officer.');
      navigate('/enquiries');
    } catch (err) {
      alert(err.response?.data?.message || err.message || 'Approval action failed.');
    } finally {
      setApprovingCfr(false);
    }
  };

  const tabOrder = useMemo(() => {
    if (!showVerificationReport) return BASE_TAB_ORDER;
    const order = [...BASE_TAB_ORDER];
    const detailsIdx = order.indexOf('details');
    order.splice(detailsIdx + 1, 0, 'verification');
    return order;
  }, [showVerificationReport]);

  const mergeAccusedLists = (existingList = [], sourceList = []) => {
    const merged = [...(Array.isArray(existingList) ? existingList : [])];
    const isMatch = (a, b) => {
      const cnicA = (a.cnic || '').replace(/\D/g, '');
      const cnicB = (b.cnic || '').replace(/\D/g, '');
      if (cnicA && cnicB && cnicA === cnicB) return true;
      const nameA = (a.name || '').trim().toLowerCase();
      const nameB = (b.name || '').trim().toLowerCase();
      if (nameA && nameB && nameA === nameB) return true;
      return false;
    };

    (Array.isArray(sourceList) ? sourceList : []).forEach(src => {
      if (!src || (!src.name && !src.cnic)) return;
      const exists = merged.some(m => isMatch(m, src));
      if (!exists) {
        merged.push({
          ...EMPTY_ACCUSED,
          id: src.id || undefined,
          name: src.name || '',
          cnic: src.cnic || '',
          father_name: src.father_name || '',
          gender: src.gender || '',
          contact_no: src.contact_no || src.phone || src.mobile_no || '',
          whatsapp_no: src.whatsapp_no || src.phone || src.contact_no || '',
          email: src.email || '',
          postal_address: src.postal_address || src.post_address || src.address || '',
          permanent_address: src.permanent_address || src.address || '',
          religion: src.religion || '',
          district_domicile: src.district_domicile || '',
          identification_mark: src.identification_mark || '',
          occupation: src.occupation || '',
          is_government: !!src.is_government,
          department_name: src.department_name || '',
          designation: src.designation || '',
          description: src.description || (src.address ? `Address: ${src.address}` : ''),
          cnic_attachment: src.cnic_attachment || null,
          passport_attachment: src.passport_attachment || null,
          nadra_verisys_attachment: src.nadra_verisys_attachment || null,
        });
      }
    });

    return merged;
  };

  const pullAccusedFromVerification = () => {
    const sourceAccused = [
      ...(Array.isArray(verificationReport?.accused) ? verificationReport.accused : []),
      ...(Array.isArray(selectedComplaint?.latest_verification_report?.accused) ? selectedComplaint.latest_verification_report.accused : []),
      ...(Array.isArray(complaintDetail?.latest_verification_report?.accused) ? complaintDetail.latest_verification_report.accused : []),
      ...(Array.isArray(selectedComplaint?.initial_accused) ? selectedComplaint.initial_accused : []),
      ...(Array.isArray(complaintDetail?.initial_accused) ? complaintDetail.initial_accused : []),
    ];

    if (!sourceAccused.length) {
      alert('Verification report ya complaint mein koi naya accused record nahi mila.');
      return;
    }

    setForm(f => {
      const merged = mergeAccusedLists(f.accused, sourceAccused);
      const addedCount = merged.length - f.accused.length;
      if (addedCount > 0) {
        setSuccessMsg(`${addedCount} accused person(s) verification / complaint se sync ho gaye.`);
        setTimeout(() => setSuccessMsg(''), 4000);
      } else {
        alert('Tamam accused persons pehle se list mein mojood hain.');
      }
      return { ...f, accused: merged };
    });
  };

  const applyEnquiryPayload = (d) => {
    setTechnicalReportUrl(d.technical_report_attachment_url || '');
    setForensicReportUrl(d.forensic_report_attachment_url || '');
    setCaseFileId(d.case_file_id || d.case_file?.id || null);
    const vRep = d.complaint?.latest_verification_report || d.complaint?.latestVerificationReport || d.verification_report || null;
    setVerificationReport(vRep);
    setLinkedVerification(d.complaint?.verification || null);
    const toDate = (v) => (v ? String(v).slice(0, 10) : '');

    // Deduplicate loaded accused from enquiry
    let initialAccused = (d.accused_persons || d.accused || []).map(a => ({
      ...EMPTY_ACCUSED,
      id: a.id,
      name: a.name || '',
      cnic: a.cnic || '',
      father_name: a.father_name || '',
      gender: a.gender || '',
      contact_no: a.contact_no || a.phone || a.mobile_no || '',
      whatsapp_no: a.whatsapp_no || a.phone || a.contact_no || '',
      email: a.email || '',
      postal_address: a.postal_address || a.post_address || a.address || '',
      permanent_address: a.permanent_address || a.address || '',
      religion: a.religion || '',
      district_domicile: a.district_domicile || '',
      identification_mark: a.identification_mark || '',
      occupation: a.occupation || '',
      is_government: !!a.is_government,
      department_name: a.department_name || '',
      designation: a.designation || '',
      description: a.description || '',
      cnic_attachment: a.cnic_attachment || null,
      cnic_attachment_url: a.cnic_attachment_url || '',
      passport_attachment: a.passport_attachment || null,
      passport_attachment_url: a.passport_attachment_url || '',
      nadra_verisys_attachment: a.nadra_verisys_attachment || null,
      nadra_verisys_attachment_url: a.nadra_verisys_attachment_url || '',
    }));

    // Deduplicate in memory
    initialAccused = mergeAccusedLists([], initialAccused);

    if (initialAccused.length === 0 && d.complaint) {
      const vAccused = vRep?.accused || d.complaint.latest_verification_report?.accused || d.complaint.verification_report?.accused || [];
      const cAccused = d.complaint.initial_accused || [];
      initialAccused = mergeAccusedLists([], [...(Array.isArray(vAccused) ? vAccused : []), ...(Array.isArray(cAccused) ? cAccused : [])]);
    }

    let initialWitnesses = (d.witnesses || []).map(w => ({
      id: w.id,
      name: w.name || '',
      father_name: w.father_name || '',
      relation: w.relation || '',
      gender: w.gender || '',
      cnic: w.cnic || '',
      domicile_district: w.domicile_district || '',
      nationality: w.nationality || '',
      passport: w.passport || '',
      occupation: w.occupation || '',
      is_government: !!w.is_government,
      department_name: w.department_name || '',
      designation: w.designation || '',
      scale: w.scale || '',
      contact_no: w.contact_no || '',
      whatsapp_no: w.whatsapp_no || '',
      mailing_address: w.mailing_address || '',
      permanent_address: w.permanent_address || '',
      address: w.address || w.mailing_address || '',
      attachment: w.attachment || null,
      picture: w.picture || null,
      statement_attachment: w.statement_attachment || null,
      attachment_url: w.attachment_url || '',
      picture_url: w.picture_url || '',
      statement_attachment_url: w.statement_attachment_url || '',
      attachment_path: w.attachment || '',
      picture_path: w.picture || '',
      statement_path: w.statement_attachment || '',
    }));

    const seenWit = {};
    initialWitnesses = initialWitnesses.filter(w => {
      const key = ((w.cnic || '').replace(/\D/g, '') || (w.name || '').trim().toLowerCase());
      if (!key) return true;
      if (seenWit[key]) return false;
      seenWit[key] = true;
      return true;
    });

    if (initialWitnesses.length === 0 && d.complaint) {
      const vWit = vRep?.witnesses || vRep?.initial_witnesses || d.complaint.witnesses || d.complaint.initial_witnesses || [];
      (Array.isArray(vWit) ? vWit : []).forEach(w => {
        const key = ((w.cnic || '').replace(/\D/g, '') || (w.name || '').trim().toLowerCase());
        if (key && seenWit[key]) return;
        if (key) seenWit[key] = true;
        initialWitnesses.push({
          name: w.name || '',
          father_name: w.father_name || '',
          relation: w.relation || '',
          gender: w.gender || '',
          cnic: w.cnic || '',
          domicile_district: w.domicile_district || '',
          nationality: w.nationality || '',
          passport: w.passport || '',
          occupation: w.occupation || '',
          is_government: !!w.is_government,
          department_name: w.department_name || '',
          designation: w.designation || '',
          scale: w.scale || '',
          contact_no: w.contact_no || w.phone || '',
          whatsapp_no: w.whatsapp_no || '',
          mailing_address: w.mailing_address || w.address || '',
          permanent_address: w.permanent_address || w.address || '',
          address: w.address || w.mailing_address || '',
          attachment: null,
          picture: null,
          statement_attachment: null,
          attachment_path: '',
          picture_path: '',
          statement_path: '',
        });
      });
    }

    setForm(f => ({
      ...f,
      complaint_id: d.complaint_id || d.complaint?.id || '',
      tracking_no: d.complaint?.tracking_no || d.tracking_no || '',
      enquiry_number: d.enquiry_number || '',
      reg_date: toDate(d.reg_date) || toDate(d.created_at),
      status: d.status || 'assigned',
      priority: d.priority || d.complaint?.priority_type || 'normal',
      enquiry_officer_id: d.enquiry_officer_id || '',
      recommendation: d.recommendation || '',
      closure_reason: d.closure_reason || '',
      transfer_department: d.transfer_department || '',
      transfer_circle: d.transfer_circle || '',
      merge_complaint_id: d.merge_complaint?.tracking_no || d.merge_complaint_id || '',
      cfr_summary: d.cfr_summary || '',
      cfr_type: d.cfr_type || '',
      cfr_date: toDate(d.cfr_date),
      charge_against: d.charge_against || '',
      oral_evidence: d.oral_evidence || '',
      documentary_evidence: d.documentary_evidence || '',
      plea: d.plea || '',
      conclusion: d.conclusion || '',
      cfr_remarks: d.cfr_remarks || '',
      technical_report: d.technical_report || '',
      forensic_report: d.forensic_report || '',
      accused: initialAccused,
      witnesses: initialWitnesses,
      attachments: (d.enquiry_attachments || d.attachments || []).map(at => ({
        id: at.id,
        title: at.title || '',
        attachment_date: toDate(at.attachment_date),
        file: null,
        file_path: at.file_path || '',
        file_path_url: at.file_path_url || '',
      })),
      requisitions: (d.requisitions || []).map(rq => ({
        id: rq.id,
        type: rq.type || '',
        email_to: rq.email_to || '',
        subject: rq.subject || '',
        body: rq.body || '',
        status: rq.status || '',
        authorized_by: rq.authorized_by || null,
        authorized_by_name: rq.authorizer?.name || rq.authorized_by_name || '',
      })),
      activities: (() => {
        const seen = new Set();
        return (d.activities || []).filter(a => {
          const key = a.id ? `id_${a.id}` : `${a.type}_${a.diary_no || ''}_${a.activity_date || ''}_${(a.description || '').slice(0, 40)}`;
          if (seen.has(key)) return false;
          seen.add(key);
          return true;
        }).map(a => ({
          id: a.id,
          type: a.type || '',
          diary_no: a.diary_no || '',
          description: a.description || '',
          activity_date: toDate(a.activity_date),
          attachment_path: a.attachment_path || '',
          attachment_path_url: a.attachment_path_url || '',
          seize_items: lockSeizeItemsAgainstForensic(
            Array.isArray(a.meta?.seize_items) ? a.meta.seize_items : (Array.isArray(a.seize_items) ? a.seize_items : []),
            linkedForensicRef.current
          ),
          analysis_scope: a.meta?.analysis_scope || a.analysis_scope || '',
          case_category: a.meta?.case_category || a.case_category || 'Financial Fraud',
          subject: a.meta?.subject || a.subject || '',
          kota: a.meta?.kota || a.kota || '',
          against_whom: a.meta?.against_whom || a.against_whom || '',
          scheduled_at: a.meta?.scheduled_at || a.scheduled_at || '',
        }));
      })(),
      notices: (() => {
        const seenN = new Set();
        return (d.notices || []).filter(n => {
          const key = n.id ? `id_${n.id}` : `${n.notice_number || ''}_${n.receiver_name || ''}_${n.sequence_no || ''}`;
          if (seenN.has(key)) return false;
          seenN.add(key);
          return true;
        }).map(n => ({
          id: n.id,
          notice_number: n.notice_number || '',
          notice_type: n.notice_type || '',
          receiver_name: n.receiver_name || '',
          father_name: n.father_name || '',
          cnic: n.cnic || '',
          person_type: n.person_type || '',
          person_ref: n.person_ref ?? '',
          notice_via: n.notice_via || '',
          notice_date: toDate(n.notice_date),
          appearance_date: toLocalInput(n.appearance_date),
          appearance_remarks: n.appearance_remarks || '',
          address: n.address || '',
          phone: n.phone || '',
          description: n.description || '',
          status: n.status || 'issued',
        }));
      })(),
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
        decision: ap.decision || 'agree',
        remarks: ap.remarks || '',
      })),
    }));
    if (d.complaint) setComplaintDetail(d.complaint);
    else if (d.complaint_id) {
      api.get(`/complaints/${d.complaint_id}`).then(cr => setComplaintDetail(cr.data.data || cr.data)).catch(() => {});
    }
    if (!d.complaint_id && d.direct_info) {
      setDirectMode(true);
      setDirect(normalizeDirectInfo(d.direct_info));
    }
  };

  const selectedComplaint = useMemo(() => {
    if (complaintDetail) return complaintDetail;
    return complaints.find(c => String(c.id) === String(form.complaint_id)) || null;
  }, [complaintDetail, complaints, form.complaint_id]);

  const officerName = officers.find(o => String(o.value) === String(form.enquiry_officer_id))?.name || '';
  const recName = RECOMMENDATIONS.find(o => o.value === form.recommendation)?.name || '';
  const closureName = CLOSURE_REASONS.find(o => o.value === form.closure_reason)?.name || '';
  const transferCircleName = circles.find(c => String(c.id) === String(form.transfer_circle) || c.name === form.transfer_circle)?.name || '';

  useEffect(() => {
    api.get('/complaints?status=complete').then(r => setComplaints(responseArray(r.data, '/complaints?status=complete'))).catch(error => console.error('Failed to load complaints for enquiry:', error));
    api.get('/lookup/enquiry-officers').then(r => { const d = r.data.data || r.data; setOfficers((Array.isArray(d) ? d : []).map(o => ({ value: o.id, name: o.name + (o.designation ? ' (' + o.designation + ')' : '') }))); }).catch(() => {});
    api.get('/lookup/legal-officers').then(r => setLegalOfficers(responseArray(r.data, '/lookup/legal-officers'))).catch(error => console.error('Failed to load legal officers for enquiry:', error));
    api.get('/lookup/circles').then(r => setCircles(responseArray(r.data, '/lookup/circles'))).catch(error => console.error('Failed to load circles for enquiry:', error));
    api.get('/lookup/circle-incharges').then(r => { const d = r.data.data || r.data; setCircleIncharges(Array.isArray(d) ? d : []); }).catch(() => {});
    if (canRegisterCaseFromEnquiry(user)) {
      api.get('/lookup/investigation-officers').then(r => {
        const d = r.data.data || r.data;
        setIoOfficers((Array.isArray(d) ? d : []).map(o => ({ value: o.id, name: o.name + (o.designation ? ' (' + o.designation + ')' : '') })));
      }).catch(() => {});
    }
  }, [user]);

  useEffect(() => {
    if (id) {
      linkedForensicRef.current = [];
      setLinkedForensicRequests([]);
      api.get(`/enquiries/${id}`).then(r => {
        applyEnquiryPayload(r.data.data || r.data);
      }).catch(() => navigate('/enquiries'));
      loadLinkedForensicRequests(id);
    }
  }, [id, navigate]);

  useEffect(() => {
    linkedForensicRef.current = linkedForensicRequests;
    if (!linkedForensicRequests.length) return;
    setForm(f => {
      const next = applyForensicLocksToActivities(f.activities, linkedForensicRequests, ['seizures']);
      return next === f.activities ? f : { ...f, activities: next };
    });
  }, [linkedForensicRequests]);

  // No auto-refresh on edit form — it was wiping unsaved work every 30s.

  useEffect(() => {
    if (!form.complaint_id) {
      setComplaintDetail(null);
      return;
    }
    api.get(`/complaints/${form.complaint_id}`).then(r => {
      const cd = r.data.data || r.data;
      setComplaintDetail(cd);

      // If form accused is empty, auto-populate from complaint initial_accused or verification report
      setForm(f => {
        const hasRealAccused = (f.accused || []).some(a => (a.name && a.name.trim()) || (a.cnic && a.cnic.trim()));
        if (hasRealAccused) return f;

        const sourceAccused = cd.latest_verification_report?.accused ||
                              cd.verification_report?.accused ||
                              cd.initial_accused || [];

        if (!Array.isArray(sourceAccused) || sourceAccused.length === 0) return f;

        const mapped = sourceAccused.map(a => ({
          ...EMPTY_ACCUSED,
          name: a.name || '',
          cnic: a.cnic || '',
          father_name: a.father_name || '',
          gender: a.gender || '',
          contact_no: a.contact_no || a.phone || '',
          whatsapp_no: a.whatsapp_no || a.phone || a.contact_no || '',
          email: a.email || '',
          postal_address: a.postal_address || a.post_address || a.address || '',
          permanent_address: a.permanent_address || a.address || '',
          description: a.description || '',
        }));

        return { ...f, accused: mapped };
      });
    }).catch(() => {});
  }, [form.complaint_id]);

  const handleTrackingChange = (e) => {
    const tracking = e.target.value;
    const comp = complaints.find(c => c.tracking_no === tracking);
    setForm(f => ({
      ...f,
      tracking_no: tracking,
      complaint_id: comp ? comp.id : '',
    }));
  };

  const setF = (field) => (e) => setForm(f => ({ ...f, [field]: e.target.value }));
  const setFNum = (field) => (e) => {
    let val = e.target.value.replace(/\D/g, '');
    if (field === 'cnic') {
      if (val.length > 5) val = val.slice(0,5) + '-' + val.slice(5);
      if (val.length > 13) val = val.slice(0,13) + '-' + val.slice(13);
    }
    setForm(f => ({ ...f, [field]: val }));
  };

  // Accused
  const addAccused = () => {
    setForm(f => {
      setEditingAccusedIndex(0);
      return { ...f, accused: [{ ...EMPTY_ACCUSED, _uid: `acc_${Date.now()}_${Math.random().toString(36).slice(2, 7)}` }, ...f.accused] };
    });
  };
  const removeAccused = (i) => {
    const rec = form.accused[i];
    if (isSavedDiaryLocked(rec, user)) {
      alert('Saved accused Circle Incharge ke ilawa edit ya delete nahi ho sakta.');
      return;
    }
    setForm(f => ({ ...f, accused: f.accused.filter((_, idx) => idx !== i) }));
    setEditingAccusedIndex(prev => (prev === i ? null : (prev != null && prev > i ? prev - 1 : prev)));
  };
  const updateAccused = (i, field, value) => setForm(f => ({
    ...f,
    accused: f.accused.map((a, idx) => {
      if (idx !== i) return a;
      if (isSavedDiaryLocked(a, user)) return a;
      return { ...a, [field]: value };
    }),
  }));

  const updateAccusedCnic = (i, raw) => {
    let val = raw.replace(/\D/g, '');
    if (val.length > 5) val = val.slice(0, 5) + '-' + val.slice(5);
    if (val.length > 13) val = val.slice(0, 13) + '-' + val.slice(13);
    updateAccused(i, 'cnic', val);
  };
  const updateAccusedContact = (i, field, raw) => {
    let val = raw.replace(/\D/g, '').replace(/^0+/, '');
    if (val.length > 10) val = val.slice(0, 10);
    updateAccused(i, field, val);
  };

  const updateWitnessCnic = (i, raw) => {
    let val = raw.replace(/\D/g, '');
    if (val.length > 5) val = val.slice(0, 5) + '-' + val.slice(5);
    if (val.length > 13) val = val.slice(0, 13) + '-' + val.slice(13);
    updateWitness(i, 'cnic', val);
  };
  const updateWitnessContact = (i, field, raw) => {
    let val = raw.replace(/\D/g, '').replace(/^0+/, '');
    if (val.length > 10) val = val.slice(0, 10);
    updateWitness(i, field, val);
  };

  const updateAccusedFile = (i, field, file) => setForm(f => ({ ...f, accused: f.accused.map((a, idx) => idx === i ? { ...a, [field]: file } : a) }));
  const isAccusedEditing = (a, i) => editingAccusedIndex === i;

  // Attachments
  const addAttachment = () => setForm(f => ({ ...f, attachments: [{ ...EMPTY_ATTACHMENT, _uid: `att_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, attachment_date: new Date().toISOString().split('T')[0] }, ...f.attachments] }));
  const removeAttachment = (i) => {
    const rec = form.attachments[i];
    if (isSavedDiaryLocked(rec, user)) {
      alert('Saved attachment Circle Incharge ke ilawa edit ya delete nahi ho sakta.');
      return;
    }
    setForm(f => ({ ...f, attachments: f.attachments.filter((_, idx) => idx !== i) }));
  };
  const updateAttachment = (i, field, value) => setForm(f => ({
    ...f,
    attachments: f.attachments.map((a, idx) => {
      if (idx !== i) return a;
      if (isSavedDiaryLocked(a, user)) return a;
      return { ...a, [field]: value };
    }),
  }));
  const updateAttachmentFile = (i, file) => setForm(f => ({ ...f, attachments: f.attachments.map((a, idx) => idx === i ? { ...a, file } : a) }));

  // Requisitions
  const addRequisition = () => setForm(f => ({ ...f, requisitions: [{ ...EMPTY_REQUISITION, _uid: `req_${Date.now()}_${Math.random().toString(36).slice(2, 7)}` }, ...f.requisitions] }));
  const removeRequisition = (i) => setForm(f => ({ ...f, requisitions: f.requisitions.filter((_, idx) => idx !== i) }));
  const updateRequisition = (i, field, value) => setForm(f => ({ ...f, requisitions: f.requisitions.map((a, idx) => idx === i ? { ...a, [field]: value } : a) }));

  // Activities
  const addActivity = () => setForm(f => ({ ...f, activities: [{ _uid: `act_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, type: '', diary_no: '', description: '', activity_date: new Date().toISOString().split('T')[0], attachment: null, seize_items: [], analysis_scope: '', case_category: 'Financial Fraud', subject: '', kota: '', against_whom: '', scheduled_at: '' }, ...f.activities] }));
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
      if (field === 'type' && value === 'seizures' && !(next.seize_items || []).length) {
        next.seize_items = [{ ...EMPTY_SEIZE_ITEM, _uid: `sz_${Date.now()}_${Math.random().toString(36).slice(2, 7)}` }];
      }
      if (field === 'type' && ['search_seize', 'raid', 'arrest_warrant'].includes(value) && !next.against_whom) {
        const firstAcc = (form.accused || []).find(x => (x.name || '').trim());
        if (firstAcc) next.against_whom = firstAcc.name;
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
      ? {
          ...a,
          seize_items: (a.seize_items || []).map((it, si) => {
            if (si !== itemIndex) return it;
            if (isSavedDiaryLocked(a, user) || isSeizeItemLocked(it)) return it;
            return { ...it, [field]: value };
          }),
        }
      : a),
  }));

  // Legal Opinions
  const addLegalOpinion = () => setForm(f => ({ ...f, legal_opinions: [{ _uid: `lo_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, role: '', opinion_text: '', decision: '', created_by: user?.id }, ...f.legal_opinions] }));
  const removeLegalOpinion = (i) => setForm(f => ({ ...f, legal_opinions: f.legal_opinions.filter((_, idx) => idx !== i) }));
  const updateLegalOpinion = (i, field, value) => setForm(f => ({ ...f, legal_opinions: f.legal_opinions.map((a, idx) => idx === i ? { ...a, [field]: value } : a) }));

  // Approvals
  const addApproval = () => setForm(f => ({ ...f, approvals: [{ _uid: `app_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, circle_incharge_id: '', decision: '', remarks: '' }, ...f.approvals] }));
  const removeApproval = (i) => setForm(f => ({ ...f, approvals: f.approvals.filter((_, idx) => idx !== i) }));
  const updateApproval = (i, field, value) => setForm(f => ({ ...f, approvals: f.approvals.map((a, idx) => idx === i ? { ...a, [field]: value } : a) }));

  // Witnesses
  const addWitness = () => {
    setForm(f => {
      setEditingWitnessIndex(0);
      return { ...f, witnesses: [{ ...EMPTY_WITNESS, _uid: `wit_${Date.now()}_${Math.random().toString(36).slice(2, 7)}` }, ...f.witnesses] };
    });
  };
  const removeWitness = (i) => {
    const rec = form.witnesses[i];
    if (isSavedDiaryLocked(rec, user)) {
      alert('Saved witness Circle Incharge ke ilawa edit ya delete nahi ho sakta.');
      return;
    }
    setForm(f => ({ ...f, witnesses: f.witnesses.filter((_, idx) => idx !== i) }));
    setEditingWitnessIndex(prev => (prev === i ? null : (prev != null && prev > i ? prev - 1 : prev)));
  };
  const isWitnessEditing = (w, i) => editingWitnessIndex === i;
  const updateWitness = (i, field, value) => setForm(f => ({
    ...f,
    witnesses: f.witnesses.map((a, idx) => {
      if (idx !== i) return a;
      if (isSavedDiaryLocked(a, user)) return a;
      return { ...a, [field]: value };
    }),
  }));
  const updateWitnessFile = (i, field, file) => setForm(f => ({ ...f, witnesses: f.witnesses.map((a, idx) => idx === i ? { ...a, [field]: file } : a) }));

  // Notices
  const addNotice = () => {
    setForm(f => {
      setEditingNoticeIndex(0);
      return { ...f, notices: [{ _uid: `not_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, notice_number: '', notice_type: '', receiver_name: '', father_name: '', cnic: '', person_type: '', person_ref: '', notice_via: '', notice_date: new Date().toISOString().split('T')[0], appearance_date: '', appearance_remarks: '', address: '', phone: '', description: '', status: 'issued' }, ...f.notices] };
    });
  };
  const duplicateNotice = (i) => {
    setForm(f => {
      const existing = f.notices[i];
      const newNotice = { ...existing, id: undefined, _uid: `not_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`, notice_date: new Date().toISOString().split('T')[0], appearance_date: '', appearance_remarks: '', status: 'issued' };
      setEditingNoticeIndex(0);
      return { ...f, notices: [newNotice, ...f.notices] };
    });
  };
  const removeNotice = (i) => {
    const rec = form.notices[i];
    if (isSavedDiaryLocked(rec, user)) {
      alert('Saved notice Circle Incharge ke ilawa edit ya delete nahi ho sakti.');
      return;
    }
    setForm(f => ({ ...f, notices: f.notices.filter((_, idx) => idx !== i) }));
    setEditingNoticeIndex(prev => (prev === i ? null : (prev != null && prev > i ? prev - 1 : prev)));
  };
  const updateNotice = (i, field, value) => setForm(f => ({
    ...f,
    notices: f.notices.map((a, idx) => {
      if (idx !== i) return a;
      if (isSavedDiaryLocked(a, user)) return a;
      return { ...a, [field]: value };
    }),
  }));
  const isNoticeEditing = (n, i) => !n.id || editingNoticeIndex === i;

  const fillNoticeFromPerson = (noticeIndex, personType, personRef) => {
    setForm(f => {
      const notices = f.notices.map((n, idx) => {
        if (idx !== noticeIndex) return n;
        const next = { ...n, person_type: personType, person_ref: personRef === '' || personRef == null ? '' : String(personRef) };
        if (personType === 'accused' && personRef !== '' && personRef != null) {
          const a = f.accused[Number(personRef)];
          if (a) {
            next.receiver_name = a.name || '';
            next.father_name = a.father_name || '';
            next.cnic = a.cnic || '';
            next.phone = a.contact_no || a.whatsapp_no || '';
            next.address = a.postal_address || a.permanent_address || '';
          }
        } else if (personType === 'witness' && personRef !== '' && personRef != null) {
          const w = f.witnesses[Number(personRef)];
          if (w) {
            next.receiver_name = w.name || '';
            next.father_name = w.father_name || '';
            next.cnic = w.cnic || '';
            next.phone = w.contact_no || w.whatsapp_no || '';
            next.address = w.address || w.mailing_address || w.permanent_address || '';
          }
        } else if (personType === 'complainant') {
          if (selectedComplaint) {
            next.receiver_name = selectedComplaint.complainant_name || '';
            next.father_name = selectedComplaint.father_name || '';
            next.cnic = selectedComplaint.cnic || '';
            next.phone = selectedComplaint.contact_no || selectedComplaint.whatsapp_no || '';
            next.address = selectedComplaint.address || '';
          } else if (directMode && direct) {
            next.receiver_name = direct.complainant_name || '';
            next.father_name = direct.father_name || '';
            next.cnic = direct.cnic || '';
            next.phone = direct.contact_no || direct.whatsapp_no || '';
            next.address = direct.address || '';
          }
        }
        return next;
      });
      return { ...f, notices };
    });
  };

  const onNoticePersonTypeChange = (noticeIndex, personType) => {
    if (personType === 'complainant') {
      fillNoticeFromPerson(noticeIndex, 'complainant', '');
      return;
    }
    setForm(f => ({
      ...f,
      notices: f.notices.map((n, idx) => idx === noticeIndex
        ? { ...n, person_type: personType, person_ref: '', receiver_name: '', father_name: '', cnic: '', phone: '', address: '' }
        : n),
    }));
  };

  const printNotice = async (n) => {
    if (!id) { alert('Save the enquiry first, then you can print summons.'); return; }
    if (!n.id) { alert('Save the enquiry first, then you can print summons.'); return; }
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/enquiries/${id}/notice-print`, { params: { notice_id: n.id } });
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || 'Could not print summon.');
    }
  };

  const printDocument = async (endpoint, payload = {}) => {
    if (!id) { alert('Save the enquiry first before printing documents.'); return; }
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/enquiries/${id}/${endpoint}`, { params: payload });
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || `Could not print document (${endpoint}).`);
    }
  };

  const getSeizeItemOwnerText = (it) => {
    if (it.owner_type === 'accused') return form.accused[it.owner_ref]?.name || `Accused ${Number(it.owner_ref) + 1}`;
    if (it.owner_type === 'witness') return form.witnesses[it.owner_ref]?.name || `Witness ${Number(it.owner_ref) + 1}`;
    if (it.owner_type === 'complainant') return 'Complainant';
    return it.owner_ref || (it.owner_type ? it.owner_type.charAt(0).toUpperCase() + it.owner_type.slice(1) : '');
  };

  const printActivityForensicRequest = (act) => {
    if (!id) { alert('Pehle enquiry save karein, phir print karein.'); return; }
    const devices = (act.seize_items || [])
      .filter(it => it.item_type || it.make_model || it.imei || it.serial_no || it.description)
      .map(it => ({
        type: it.item_type || 'Digital Device',
        model: it.make_model || '—',
        imei: it.imei || it.serial_no || 'N/A',
        owner: getSeizeItemOwnerText(it),
        storage_capacity: it.storage_capacity || '—',
      }));
    const analysisScope = (act.analysis_scope || '').trim();
    printDocument('forensic-request-print', { devices: JSON.stringify(devices), analysis_scope: analysisScope });
  };

  const printForensicRequest = () => {
    const scope = window.prompt('Enter Analysis Scope (For Forensic Lab) or leave blank for default:', '') ?? '';
    const devices = [];
    form.activities.forEach(a => {
      if (a.seize_items && a.seize_items.length > 0) {
        a.seize_items.forEach(si => {
          devices.push({
            type: si.item_type || 'Digital Device',
            model: si.make_model || 'Unknown',
            imei: si.imei || si.serial_no || 'N/A',
            owner: getSeizeItemOwnerText(si),
            storage_capacity: si.storage_capacity || '—',
          });
        });
      }
    });
    printDocument('forensic-request-print', { devices: JSON.stringify(devices), analysis_scope: scope.trim() });
  };

  const printWarrantFromActivity = (endpoint, act) => {
    if (!act?.id) {
      alert('Pehle enquiry save karein taake warrant QR verify page par sahi details dikhein.');
    }
    printDocument(endpoint, {
      activity_id: act.id || '',
      subject: act.subject || act.meta?.subject || '',
      kota: act.kota || act.meta?.kota || '',
      against_whom: act.against_whom || act.meta?.against_whom || '',
      scheduled_at: act.scheduled_at || act.meta?.scheduled_at || act.activity_date || '',
      description: act.description || '',
    });
  };

  const sendSummonWhatsApp = (n) => {
    let phoneRaw = (n.phone || '').replace(/\D/g, '');
    if (!phoneRaw) {
      if (n.person_type === 'complainant') {
        phoneRaw = `${selectedComplaint?.contact_country_code || '+92'}${selectedComplaint?.contact_no || ''}`.replace(/\D/g, '');
      } else if (n.person_type === 'accused' && n.person_ref !== '' && n.person_ref != null) {
        const a = form.accused[Number(n.person_ref)];
        phoneRaw = (a?.whatsapp_no || a?.contact_no || '').replace(/\D/g, '');
      } else if (n.person_type === 'witness' && n.person_ref !== '' && n.person_ref != null) {
        const w = form.witnesses[Number(n.person_ref)];
        phoneRaw = (w?.whatsapp_no || w?.contact_no || '').replace(/\D/g, '');
      }
    }
    if (!phoneRaw) {
      alert('Recipient contact number nahi mila. Barah-e-karam summon mein ya accused/person details mein phone number enter karein.');
      return;
    }
    if (!phoneRaw.startsWith('92') && phoneRaw.length === 10) {
      phoneRaw = '92' + phoneRaw;
    } else if (phoneRaw.startsWith('0')) {
      phoneRaw = '92' + phoneRaw.slice(1);
    }

    const enqNo = form.enquiry_number || (id ? `ENQ-${id}` : 'Enquiry');
    const recipient = n.receiver_name || (n.person_type ? n.person_type.toUpperCase() : 'Concerned Person');
    const summonNo = n.notice_number || 'Official Summon';
    const appDate = n.appearance_date ? new Date(n.appearance_date).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' }) : 'As notified';
    const circleName = transferCircleName || selectedComplaint?.circle?.name || 'NCCIA Office';
    const offName = officerName || user?.name || 'Enquiry Officer';

    const text = `*NATIONAL CYBER CRIME INVESTIGATION AGENCY (NCCIA)*\n` +
      `*OFFICIAL SUMMON / NOTICE*\n\n` +
      `*Summon No:* ${summonNo}\n` +
      `*Enquiry No:* ${enqNo}\n` +
      `*To:* ${recipient} (${n.person_type || 'Respondent'})\n\n` +
      `You are hereby directed to appear in person before the Enquiry Officer for verification / statement regarding the subject enquiry.\n\n` +
      `📅 *Appearance Date & Time:* ${appDate}\n` +
      `📍 *Location:* ${circleName}\n` +
      `👤 *Enquiry Officer:* ${offName}\n\n` +
      `*Instructions:* ${n.description || 'Please bring original CNIC, relevant records/devices and evidence.'}\n\n` +
      `*Note:* Failure to appear may result in ex-parte legal proceedings under the law.`;

    window.open(`https://wa.me/${phoneRaw}?text=${encodeURIComponent(text)}`, '_blank');
  };

  const printDiary = async (a) => {
    if (!id) { alert('Save the enquiry first, then you can print diaries.'); return; }
    if (!a.id) { alert('Save the enquiry first, then you can print diaries.'); return; }
    const win = preparePrintWindow();
    if (!win) return;
    try {
      const r = await api.get(`/enquiries/${id}/diary-print`, { params: { activity_id: a.id } });
      writePrintWindow(win, r.data.html);
    } catch (e) {
      closePrintWindow(win);
      alert(e.response?.data?.message || 'Could not print diary.');
    }
  };

  const handleAuthorizeRequisitions = async () => {
    setSaving(true);
    setServerError('');
    try {
      await saveEnquiry({ navigateAway: false });
      alert('Requisitions saved. Authorization & send will be processed by the backend.');
    } catch (err) {
      const res = err.response?.data;
      setServerError(res?.message || 'Could not save requisitions.');
    } finally {
      setSaving(false);
    }
  };

  const sendReportToDepartment = async (destination) => {
    if (!id) {
      setForensicSendMsg('Pehle enquiry save karein, phir AD Forensic ko bhejein.');
      return;
    }
    const note = destination === 'forensic'
      ? (form.forensic_report || '').trim()
      : (form.technical_report || '').trim();
    if (!note) {
      setForensicSendMsg(destination === 'forensic'
        ? 'Forensic Report text likhein, phir Submit to AD Forensic dabayein.'
        : 'Technical Report text likhein, phir Submit to Technical dabayein.');
      return;
    }
    setSendingForensic(true);
    setForensicSendMsg('');
    try {
      const resolveSeizedPerson = (it) => {
        if (it.seized_from) return it.seized_from;
        if (it.owner_type === 'accused') {
          const acc = form.accused[it.owner_ref] || form.accused[0];
          return acc?.name ? `Accused: ${acc.name}` : (it.owner_ref || null);
        }
        if (it.owner_type === 'witness') {
          const wit = form.witnesses[it.owner_ref];
          return wit?.name ? `Witness: ${wit.name}` : (it.owner_ref || null);
        }
        if (it.owner_type === 'complainant') {
          return form.complainant_name ? `Complainant: ${form.complainant_name}` : 'Complainant';
        }
        return it.owner_ref || null;
      };

      const seizeItems = (form.activities || [])
        .filter(a => a.type === 'seizures')
        .flatMap(a => a.seize_items || [])
        .filter(it => it.item_type || it.make_model || it.imei || it.serial_no || it.description)
        .map(it => ({
          item_type: it.item_type || 'other',
          make_model: it.make_model || null,
          imei: it.imei || null,
          imei2: it.imei2 || null,
          serial_no: it.serial_no || null,
          storage_capacity: it.storage_capacity || null,
          condition: it.condition || null,
          seized_from: resolveSeizedPerson(it),
          quantity: Number(it.quantity) > 0 ? Number(it.quantity) : 1,
          description: it.description || null,
        }));
      const fd = new FormData();
      fd.append('enquiry_id', String(id));
      fd.append('destination', destination);
      fd.append('brief_contents', form.brief_allegation || '');
      fd.append('analysis_scope', act.analysis_scope || '');
      fd.append('note', note);
      fd.append('items', JSON.stringify(seizeItems.length ? seizeItems : [{
        item_type: 'report',
        description: destination === 'forensic' ? 'Forensic report from enquiry' : 'Technical report from enquiry',
        quantity: 1,
      }]));
      const file = destination === 'forensic' ? forensicFile : technicalFile;
      if (file) fd.append('attachment', file);
      const r = await api.post('/forensic-requests', fd);
      setForensicSendMsg(r.data?.message || `Submitted to ${destination}.`);
      if (destination === 'forensic') setForensicFile(null);
      else setTechnicalFile(null);
    } catch (err) {
      setForensicSendMsg(err.response?.data?.message || err.message || 'Submit failed');
    } finally {
      setSendingForensic(false);
    }
  };

  const submitActivityToForensic = async (act, destination = 'forensic') => {
    if (!id || id === 'new') {
      alert('Pehle enquiry save karein, phir submit karein.');
      return;
    }
    setSendingForensic(true);
    setForensicSendMsg('');
    try {
      await saveEnquiry({ navigateAway: false });
      const resolveSeizedPerson = (it) => {
        if (it.seized_from) return it.seized_from;
        if (it.owner_type === 'accused') {
          const acc = form.accused[it.owner_ref] || form.accused[0];
          return acc?.name ? `Accused: ${acc.name}` : (it.owner_ref || null);
        }
        if (it.owner_type === 'witness') {
          const wit = form.witnesses[it.owner_ref];
          return wit?.name ? `Witness: ${wit.name}` : (it.owner_ref || null);
        }
        if (it.owner_type === 'complainant') {
          return form.complainant_name ? `Complainant: ${form.complainant_name}` : 'Complainant';
        }
        return it.owner_ref || null;
      };

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
          seized_from: resolveSeizedPerson(it),
          quantity: Number(it.quantity) > 0 ? Number(it.quantity) : 1,
          description: it.description || null,
        }));

      if (destination === 'forensic') {
        if (!act.checklist_tech_report || !act.checklist_seizure_memo) {
          alert('⚠️ Forensic submission ke liye Technical Report aur Seizure Memo dono ka verified/attached hona compulsory (lazmi) hai!');
          return;
        }
        const catLower = (act.case_category || '').toLowerCase();
        const isAudio = catLower.includes('audio') || catLower.includes('voice');
        if (isAudio) {
          if (!act.audio_script || !act.audio_script.trim()) {
            alert('⚠️ Audio Forensic ke liye Written Transcript / Context likhna compulsory (lazmi) hai!');
            return;
          }
          if (!act.audio_source_file) {
            alert('⚠️ Audio Forensic ke liye Disputed / Questioned Audio File attach karna compulsory (lazmi) hai!');
            return;
          }
          const sample1 = act.audio_sample_file_1 || act.audio_sample_file;
          const sample2 = act.audio_sample_file_2;
          if (!sample1 || !sample2) {
            alert('⚠️ Audio Forensic ke liye Subject ki 2 Reference Sample Audios (30 sec each) live record ya attach karna compulsory (lazmi) hain!');
            return;
          }
          if (!act.checklist_audio_samples || !act.checklist_summons || !act.checklist_usb || !act.checklist_transcript) {
            alert('⚠️ Audio Forensic ke liye tamam compulsory checklist items (2 Audio Samples, Transcript, Summons Copy, USB Media) check karna lazmi hain!');
            return;
          }
        }
      }

      const fd = new FormData();
      fd.append('enquiry_id', String(id));
      fd.append('destination', destination);
      fd.append('external_category', act.case_category || 'Financial Fraud');
      fd.append('brief_contents', form.brief_allegation || '');
      fd.append('analysis_scope', act.analysis_scope || '');
      fd.append('note', act.description || `Seizure memo evidence submitted for ${destination === 'forensic' ? 'forensic' : 'technical'} examination.`);
      fd.append('checklist_tech_report', act.checklist_tech_report ? '1' : '0');
      fd.append('checklist_seizure_memo', act.checklist_seizure_memo ? '1' : '0');
      fd.append('checklist_fir_copy', act.checklist_fir_copy ? '1' : '0');
      fd.append('checklist_audio_samples', act.checklist_audio_samples ? '1' : '0');
      fd.append('checklist_summons', act.checklist_summons ? '1' : '0');
      fd.append('checklist_usb', act.checklist_usb ? '1' : '0');
      fd.append('checklist_transcript', act.checklist_transcript ? '1' : '0');
      if (act.audio_script) fd.append('audio_script', act.audio_script);
      if (act.audio_source_file) fd.append('audio_source', act.audio_source_file);
      if (act.audio_sample_file_1 || act.audio_sample_file) fd.append('audio_sample', act.audio_sample_file_1 || act.audio_sample_file);
      if (act.audio_sample_file_2) fd.append('audio_sample_2', act.audio_sample_file_2);
      fd.append('items', JSON.stringify(items.length ? items : [{
        item_type: 'other',
        description: act.description || 'Seizure evidence items',
        quantity: 1,
      }]));
      if (act.file) {
        fd.append('attachment', act.file);
      }
      const r = await api.post('/forensic-requests', fd);
      alert(r.data?.message || (isSupervisor
        ? 'Scope Letter & Seized evidence submitted directly to DD Forensic Lab!'
        : 'Scope Letter & Seized evidence submitted to Circle Incharge for review.'));
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
      await loadLinkedForensicRequests(id);
    } catch (err) {
      alert(err.response?.data?.message || err.message || 'Submission failed.');
    } finally {
      setSendingForensic(false);
    }
  };

  const forwardActivityToForensic = async (act) => {
    if (!id || id === 'new') {
      alert('Pehle enquiry save karein, phir forward karein.');
      return;
    }
    const remarks = window.prompt('Enter Forwarding Remarks for DD Forensic (Optional):', 'Forwarded for digital forensic examination.') ?? '';
    setSendingForensic(true);
    try {
      await submitActivityToForensic(act, 'forensic');
    } catch (e) {
      // handled
    }
  };

  const sendBackActivityToEo = async (act) => {
    const remarks = window.prompt('Enter deficiency / remarks to send back to IO:', 'Please provide complete device specifications / URLs.');
    if (!remarks) return;
    alert(`Scope letter marked with deficiency: "${remarks}" and sent back to IO.`);
  };

  const nonAppearanceCount = form.notices.filter(n => n.status === 'non_appearance').length;
  const referredToCourt = nonAppearanceCount >= 3;
  const seizedItemsPreview = useMemo(() => (
    (form.activities || [])
      .filter(a => a.type === 'seizures')
      .flatMap(a => (a.seize_items || []).map(it => ({ ...it, activity_type: a.type })))
      .filter(it => it.item_type || it.make_model || it.imei || it.serial_no || it.description)
  ), [form.activities]);

  const saveEnquiry = async ({ navigateAway = true } = {}) => {
    if (isEoLocked) {
      alert('Yeh Enquiry Circle Incharge ko submit ho chuki hai / Approved hai. Enquiry Officer ke liye editing locked hai.');
      return;
    }
    const fd = new FormData();

    const serializeArr = (items, fileFieldName) => {
      const clean = items.map((it, i) => {
        const o = { ...it };
        delete o._uid;
        if (Array.isArray(o.seize_items)) {
          o.seize_items = o.seize_items.map(si => {
            const copy = { ...si };
            delete copy._uid;
            return copy;
          });
        }
        if (o.attachment instanceof File) {
          fd.append(`${fileFieldName}[${i}]`, o.attachment);
          delete o.attachment;
        } else if (o.attachment && typeof o.attachment === 'string') {
          o.attachment_path = o.attachment_path || o.attachment;
          delete o.attachment;
        }
        return o;
      });
      return JSON.stringify(clean);
    };

    const serializeWitnesses = (items) => {
      const clean = items.map((it, i) => {
        const o = { ...it };
        delete o._uid;
        if (o.attachment instanceof File) {
          fd.append(`witness_attachments[${i}]`, o.attachment);
          delete o.attachment;
        }
        if (o.picture instanceof File) {
          fd.append(`witness_pictures[${i}]`, o.picture);
          delete o.picture;
        }
        if (o.statement_attachment instanceof File) {
          fd.append(`witness_statements[${i}]`, o.statement_attachment);
          delete o.statement_attachment;
        }
        return o;
      });
      return JSON.stringify(clean);
    };

    const serializeAccused = (items) => {
      const clean = items.map((it, i) => {
        const o = { ...it };
        delete o._uid;
        if (o.cnic_attachment instanceof File) {
          fd.append(`accused_cnic_attachments[${i}]`, o.cnic_attachment);
          delete o.cnic_attachment;
        }
        if (o.passport_attachment instanceof File) {
          fd.append(`accused_passport_attachments[${i}]`, o.passport_attachment);
          delete o.passport_attachment;
        }
        if (o.nadra_verisys_attachment instanceof File) {
          fd.append(`accused_nadra_attachments[${i}]`, o.nadra_verisys_attachment);
          delete o.nadra_verisys_attachment;
        }
        return o;
      });
      return JSON.stringify(clean);
    };

    const serializeEnquiryAttachments = (items) => {
      const clean = items.map((it, i) => {
        const o = { ...it };
        delete o._uid;
        if (o.file instanceof File) {
          fd.append(`enquiry_attachment_files[${i}]`, o.file);
          delete o.file;
        }
        return o;
      });
      return JSON.stringify(clean);
    };

    const stripUid = (items) => (items || []).map(it => {
      const { _uid, ...rest } = it;
      return rest;
    });

    const scalarKeys = [
      'complaint_id', 'tracking_no', 'enquiry_number', 'reg_date', 'status', 'priority', 'enquiry_officer_id',
      'recommendation', 'closure_reason', 'transfer_department', 'transfer_circle',
      'merge_complaint_id', 'cfr_summary', 'cfr_type', 'cfr_date', 'charge_against',
      'oral_evidence', 'documentary_evidence', 'plea', 'conclusion', 'cfr_remarks',
      'technical_report', 'forensic_report',
    ];
    scalarKeys.forEach((k) => {
      const v = form[k];
      if (v !== null && v !== undefined && v !== '') fd.append(k, v);
    });
    // Always send report fields so backend can persist them (even if empty string cleared)
    fd.set('technical_report', form.technical_report ?? '');
    fd.set('forensic_report', form.forensic_report ?? '');

    if (directMode && !form.complaint_id) {
      fd.append('direct_info', JSON.stringify(buildDirectInfoPayload(direct, circles)));
    }

    fd.append('activities', serializeArr(form.activities || [], 'activity_attachments'));
    fd.append('accused', serializeAccused(form.accused || []));
    fd.append('witnesses', serializeWitnesses(form.witnesses || []));
    fd.append('notices', JSON.stringify(stripUid(form.notices || [])));
    fd.append('attachments', serializeEnquiryAttachments(form.attachments || []));
    fd.append('requisitions', JSON.stringify(stripUid(form.requisitions || [])));
    fd.append('legal_opinions', JSON.stringify(stripUid(form.legal_opinions || [])));
    fd.append('approvals', JSON.stringify(stripUid(form.approvals || [])));

    if (technicalFile) fd.append('technical_report_attachment', technicalFile);
    if (forensicFile) fd.append('forensic_report_attachment', forensicFile);

    // PHP does not populate multipart on real PUT — use method spoofing
    let res;
    if (id) {
      fd.append('_method', 'PUT');
      res = await api.post(`/enquiries/${id}`, fd);
    } else {
      res = await api.post('/enquiries', fd);
    }
    const saved = res?.data?.data || res?.data;
    if (saved?.id) {
      applyEnquiryPayload(saved);
      setEditingAccusedIndex(null);
      setEditingNoticeIndex(null);
      if (!id && !navigateAway) {
        navigate(`/enquiries/${saved.id}/edit`, { replace: true });
      }
    }
    setSuccessMsg('Enquiry record successfully saved / updated.');
    setTimeout(() => setSuccessMsg(''), 5000);
    if (navigateAway) navigate('/enquiries');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (isEoLocked) {
      alert('Yeh Enquiry Circle Incharge ko submit ho chuki hai. Edit nahi ho sakti.');
      return;
    }
    setSaving(true);
    setErrors({});
    setServerError('');
    setSuccessMsg('');
    try {
      await saveEnquiry({ navigateAway: false });
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        setErrors(res.errors);
        setServerError('Please fix the highlighted fields below.');
      } else {
        setServerError(res?.message || 'Error saving enquiry. Please try again.');
      }
    } finally {
      setSaving(false);
    }
  };

  const handleSubmitCfr = async () => {
    if (!id) return;
    const cfrRecommendations = ['closure', 'transfer', 'convert_to_case', 'irrelevant', 'lack_of_documents'];
    if (!form.recommendation || !cfrRecommendations.includes(form.recommendation)) {
      setActiveTab('outcome');
      setServerError('Submit se pehle CFR tab mein Recommendation select karein.');
      return;
    }
    if (!form.cfr_summary?.trim()) {
      setActiveTab('outcome');
      setServerError('Submit se pehle CFR tab mein CFR Summary likhein.');
      return;
    }

    setSubmittingCfr(true);
    setErrors({});
    setServerError('');
    try {
      await saveEnquiry({ navigateAway: false });
      await api.post(`/enquiries/${id}/submit-cfr`, {
        cfr_summary: form.cfr_summary.trim(),
        recommendation: form.recommendation,
      });
      navigate('/enquiries');
    } catch (err) {
      const res = err.response?.data;
      if (res?.errors) {
        setErrors(res.errors);
        setServerError('Please fix the highlighted fields below.');
      } else {
        setServerError(res?.message || 'CFR submit failed. Please try again.');
      }
    } finally {
      setSubmittingCfr(false);
    }
  };

  const handleRegisterCase = async () => {
    if (!id) return;
    setRegisterSaving(true);
    setServerError('');
    try {
      const res = await api.post(`/enquiries/${id}/register-case`, {
        investigation_officer_id: registerIoId || undefined,
        remarks: registerRemarks || undefined,
      });
      const caseFile = res.data?.data?.case_file;
      const enquiry = res.data?.data?.enquiry;
      if (enquiry?.status) setForm(f => ({ ...f, status: enquiry.status, recommendation: 'convert_to_case' }));
      if (caseFile?.id) {
        setCaseFileId(caseFile.id);
        navigate(`/cases/${caseFile.id}/edit`);
      } else {
        navigate('/enquiries');
      }
    } catch (err) {
      setServerError(err.response?.data?.message || 'Case registration failed. Please try again.');
    } finally {
      setRegisterSaving(false);
    }
  };

  const renderField = (label, field, opts = {}) => {
    const { type = 'text', placeholder = '', required = false, options = null, rows = null, readOnly = false, icon = null } = opts;
    const locked = readOnly || isEoLocked;
    const fieldErr = errors[field];
    return (
      <div className="cf-field">
        <label className={`cf-label${required ? ' required' : ''}`}>{label}</label>
        {locked ? (
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
        {icon && <span className="cf-input-icon">{icon}</span>}
        {fieldErr && <div className="cf-error">{fieldErr}</div>}
      </div>
    );
  };

  return (
    <div className="page-content" style={{ margin: '0 auto' }}>
      <form onSubmit={handleSubmit}>
        <div className="page-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: 12 }}>
          <div className="page-title-group">
            <h1 className="page-title">{id ? (isEoLocked ? 'View Enquiry' : 'Edit Enquiry') : 'New Enquiry'}</h1>
            <p className="page-subtitle">{id ? (isEoLocked ? 'Circle Incharge ko submit ho chuki hai — read only' : 'Update enquiry details') : 'Register a new enquiry'}</p>
            <div className="title-underline"></div>
          </div>
          {id && (
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              <button
                type="button"
                className="btn btn-outline btn-sm"
                onClick={openAccountOpeningModal}
                title="Print Account Opening / Unfreeze Request Proforma"
                data-locked-ok="1"
                style={{ color: '#0f766e', borderColor: '#0f766e', fontWeight: 600 }}
              >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Account Opening Proforma
              </button>
              <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => printDocument('cfr-print')} title="Print Confidential Final Report">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print CFR
              </button>
            </div>
          )}
        </div>

        <div className="cf-tabs" style={{ display: 'flex', gap: '4px', marginBottom: '20px', borderBottom: '2px solid var(--border)', paddingBottom: '4px', flexWrap: 'wrap' }}>
          {tabOrder.map(tab => (
            <button key={tab} type="button" className={`cf-tab ${activeTab === tab ? 'active' : ''}`} onClick={() => setActiveTab(tab)} style={{ padding: '10px 16px', border: 'none', background: activeTab === tab ? 'var(--primary)' : 'transparent', color: activeTab === tab ? '#fff' : '#666', borderRadius: '8px 8px 0 0', fontWeight: 600, cursor: 'pointer', fontSize: 13 }}>
              {tab === 'details' && 'Details'}
              {tab === 'verification' && 'Verification Report'}
              {tab === 'accused' && `Accused${form.accused?.length ? ` (${form.accused.length})` : ''}`}
              {tab === 'witnesses' && `Witnesses${form.witnesses?.length ? ` (${form.witnesses.length})` : ''}`}
              {tab === 'notices' && `Summons${nonAppearanceCount ? ' !' : ''}${form.notices?.length ? ` (${form.notices.length})` : ''}`}
              {tab === 'attachments' && `Attachments${form.attachments?.length ? ` (${form.attachments.length})` : ''}`}
              {tab === 'activities' && `Enquiry Diary${form.activities?.length ? ` (${form.activities.length})` : ''}${linkedForensicRequests?.length ? ` · 🔬${linkedForensicRequests.length}` : ''}`}
              {tab === 'requisitions' && `Requisitions${form.requisitions?.length ? ` (${form.requisitions.length})` : ''}`}
              {tab === 'legal' && 'Legal Opinions'}
              {tab === 'approvals' && 'Approvals'}
              {tab === 'outcome' && 'CFR'}
              {tab === 'chat' && 'Case Discussion 💬'}
            </button>
          ))}
        </div>

        {successMsg && (
          <div className="cf-alert cf-alert-success" style={{ marginBottom: 20, background: '#f0fdf4', color: '#166534', border: '1px solid #bbf7d0', padding: '12px 16px', borderRadius: 8, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 8 }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            {successMsg}
          </div>
        )}

        {serverError && (
          <div className="cf-alert cf-alert-error" style={{ marginBottom: 20 }}>{serverError}
            {Object.keys(errors).length > 0 && <ul style={{ margin: '8px 0 0 0', paddingLeft: '20px' }}>{Object.entries(errors).map(([k, v]) => <li key={k}><strong>{k}:</strong> {Array.isArray(v) ? v.join(', ') : v}</li>)}</ul>}
          </div>
        )}

        {isEoLocked && (
          <style>{`
            .enquiry-eo-locked input:not([type="hidden"]),
            .enquiry-eo-locked select,
            .enquiry-eo-locked textarea {
              pointer-events: none !important;
              background: #f1f5f9 !important;
              color: #334155 !important;
              cursor: default !important;
            }
            .enquiry-eo-locked button:not([data-locked-ok]) {
              display: none !important;
            }
          `}</style>
        )}

        <div className={isEoLocked && activeTab !== 'chat' ? 'enquiry-eo-locked' : undefined}>

        {/* DETAILS TAB */}
        {activeTab === 'details' && (
          <>
            {directMode && !selectedComplaint && (
              <div className="cf-section" style={{ marginBottom: 16 }}>
                <div className="cf-section-header">
                  <div className="cf-section-icon" style={{ background: '#0E7C7B' }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  </div>
                  <div><div className="cf-section-title">Direct Case Complainant</div><div className="cf-section-sub">From direct case details (no complaint)</div></div>
                </div>
                <div className="cf-body">
                  <div className="cf-row-3" style={{ marginBottom: 12 }}>
                    <div className="cf-field"><label className="cf-label">Reference No</label><div style={{ padding: '9px 14px', background: '#f5f5f5', borderRadius: 8, fontSize: 13, border: '1.5px solid var(--border)' }}>{direct.reference_no || '—'}</div></div>
                    <div className="cf-field"><label className="cf-label">Enquiry No</label><div style={{ padding: '9px 14px', background: '#f5f5f5', borderRadius: 8, fontSize: 13, border: '1.5px solid var(--border)' }}>{form.enquiry_number || '—'}</div></div>
                    <div className="cf-field">
                      <label className="cf-label">Date of Registration</label>
                      <input type="date" className="cf-input" value={form.reg_date} onChange={setF('reg_date')} />
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 12, fontSize: 13 }}>
                    {[['Name', direct.complainant_name], ['Reference', direct.reference_no], ['Circle Code', direct.circle_code]].map(([label, val]) => (
                      <div key={label}><strong style={{ color: '#666', fontSize: 11, textTransform: 'uppercase' }}>{label}</strong><div style={{ marginTop: 2 }}>{val || '—'}</div></div>
                    ))}
                  </div>
                </div>
              </div>
            )}

            {selectedComplaint && (
              <div className="cf-section" style={{ marginBottom: 16 }}>
                <div className="cf-section-header">
                  <div className="cf-section-icon" style={{ background: '#2d6a4f' }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                  </div>
                  <div><div className="cf-section-title">Complainant Details</div><div className="cf-section-sub">Read-only from linked complaint</div></div>
                </div>
                <div className="cf-body">
                  <div className="cf-row-3" style={{ marginBottom: 12 }}>
                    <div className="cf-field"><label className="cf-label">Complainant No</label><div style={{ padding: '9px 14px', background: '#f5f5f5', borderRadius: 8, fontSize: 13, border: '1.5px solid var(--border)' }}>{selectedComplaint.tracking_no || form.tracking_no || '—'}</div></div>
                    <div className="cf-field"><label className="cf-label">Enquiry No</label><div style={{ padding: '9px 14px', background: '#f5f5f5', borderRadius: 8, fontSize: 13, border: '1.5px solid var(--border)' }}>{form.enquiry_number || '—'}</div></div>
                    <div className="cf-field">
                      <label className="cf-label">Date of Registration</label>
                      {isPrivileged ? (
                        <input type="date" className="cf-input" value={form.reg_date} onChange={setF('reg_date')} />
                      ) : (
                        <div style={{ padding: '9px 14px', background: '#f5f5f5', borderRadius: 8, fontSize: 13, border: '1.5px solid var(--border)' }}>{form.reg_date || '—'}</div>
                      )}
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 12, fontSize: 13 }}>
                    {[
                      ['Name', selectedComplaint.complainant_name],
                      ['Father Name', selectedComplaint.father_name],
                      ['CNIC', selectedComplaint.cnic],
                      ['Contact', selectedComplaint.contact_no],
                      ['WhatsApp', selectedComplaint.whatsapp_no],
                      ['Gender', selectedComplaint.gender],
                      ['Email', selectedComplaint.email],
                      ['District', selectedComplaint.district],
                      ['Offence', selectedComplaint.offence_type],
                    ].map(([label, val]) => (
                      <div key={label}><strong style={{ color: '#666', fontSize: 11, textTransform: 'uppercase' }}>{label}</strong><div style={{ marginTop: 2 }}>{val || '—'}</div></div>
                    ))}
                    <div style={{ gridColumn: '1 / -1' }}><strong style={{ color: '#666', fontSize: 11, textTransform: 'uppercase' }}>Address</strong><div style={{ marginTop: 2 }}>{selectedComplaint.address || '—'}</div></div>
                  </div>
                </div>
              </div>
            )}

            {!isEO && (
              <div className="cf-section">
                <div className="cf-section-header">
                  <div className="cf-section-icon" style={{ background: '#015C94' }}>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                  </div>
                  <div><div className="cf-section-title">Complaint Reference</div><div className="cf-section-sub">Link to complaint with tracking number</div></div>
                  <div className="cf-section-badge">STEP 1</div>
                </div>
                <div className="cf-body">
                  <div className="cf-row-3">
                    <div className="cf-field">
                      <label className="cf-label">{directMode ? '' : 'Tracking No.'}</label>
                      {directMode ? (
                        <div className="cf-input-wrap" style={{background:'#F7F8FA',padding:'8px 10px',borderRadius:6,fontSize:13,color:'#015C94',display:'flex',alignItems:'center',gap:8,flexWrap:'wrap'}}>
                          Direct Enquiry (No Complaint)
                          <span style={{color:'#015C94',cursor:'pointer',fontWeight:600,textDecoration:'underline'}} onClick={() => { setDirectMode(false); setComplaintDetail(null); }}>Switch to Complaint</span>
                        </div>
                      ) : (
                        <div className="cf-input-wrap">
                          <span className="cf-input-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
                          <select className="cf-input cf-select" value={form.tracking_no} onChange={handleTrackingChange} required>
                            <option value="">— Select Tracking No. —</option>
                            {complaints.map(c => <option key={c.id} value={c.tracking_no}>{c.tracking_no} — {c.complainant_name}</option>)}
                          </select>
                          <span className="cf-hint">Auto-fills from complaint record</span>
                        </div>
                      )}
                      <input type="hidden" name="complaint_id" value={form.complaint_id} />
                      {!directMode && (
                        <span style={{color:'#015C94',cursor:'pointer',fontWeight:600,fontSize:12,textDecoration:'underline'}} onClick={() => setDirectMode(true)}>
                          Complaint nahi hai? Direct enquiry create karein
                        </span>
                      )}
                    </div>
                    {renderField('Enquiry Number', 'enquiry_number', { placeholder: 'Manual entry (optional)' })}
                  </div>

                  {directMode && (
                    <DirectRegistrationFields
                      direct={direct}
                      setDirect={setDirect}
                      errors={errors}
                      title="VIP / Direct Enquiry Details"
                      subtitle="Same fields as CMS Enquiry Registration Form"
                      showCaseExtras={false}
                    />
                  )}
                </div>
              </div>
            )}

            <div className="cf-section">
              <div className="cf-section-header">
                <div className="cf-section-icon" style={{ background: '#015C94' }}>
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/></svg>
                </div>
                <div><div className="cf-section-title">Case Details</div><div className="cf-section-sub">Status and priority for this enquiry</div></div>
              </div>
              <div className="cf-body">
                <div className="cf-row-3">
                  <div className="cf-field">
                    <label className="cf-label">Enquiry No</label>
                    <div style={{ padding: '9px 14px', background: '#f5f5f5', borderRadius: 8, fontSize: 13, border: '1.5px solid var(--border)', fontWeight: 700 }}>{form.enquiry_number || '—'}</div>
                  </div>
                  <div className="cf-field">
                    <label className="cf-label required">Status</label>
                    <select
                      className="cf-input cf-select"
                      value={toSimpleStatus(form.status)}
                      disabled={isEoLocked}
                      onChange={e => setForm(f => ({ ...f, status: fromSimpleStatus(e.target.value, f.status) }))}
                    >
                      {SIMPLE_STATUSES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                    </select>
                  </div>
                  <div className="cf-field">
                    <label className="cf-label">Priority</label>
                    <select
                      className="cf-input cf-select"
                      value={form.priority || 'normal'}
                      disabled={isEoLocked}
                      onChange={e => setForm(f => ({ ...f, priority: e.target.value }))}
                    >
                      {PRIORITY_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                    </select>
                  </div>
                </div>
              </div>
            </div>
            {id && <OfficerHistoryPanel endpoint={`/enquiries/${id}/officer-history`} />}
          </>
        )}

        {/* VERIFICATION TAB */}
        {activeTab === 'verification' && showVerificationReport && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#264078' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              </div>
              <div><div className="cf-section-title">Verification Report</div><div className="cf-section-sub">Linked complaint verification — read only</div></div>
            </div>
            <div className="cf-body">
              <VerificationReportPanel report={verificationReport} verification={linkedVerification} />
            </div>
          </div>
        )}

        {/* ACCUSED TAB */}
        {activeTab === 'accused' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#9b2226' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
              </div>
              <div><div className="cf-section-title">Accused Persons</div><div className="cf-section-sub">Saved accused list — Edit to update full details</div></div>
            </div>
            <div className="cf-body">
              <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginBottom: 16 }}>
                <button type="button" className="btn btn-outline btn-sm" onClick={addAccused}>
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Accused
                </button>
                <button
                  type="button"
                  className="btn btn-outline btn-sm"
                  onClick={pullAccusedFromVerification}
                  title="Import / Merge Accused from Verification Report & Complaint"
                  style={{ color: '#0E7C7B', borderColor: '#0E7C7B', fontWeight: 600, display: 'inline-flex', alignItems: 'center', gap: 6 }}
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                  Sync Accused from Verification / Complaint
                </button>
              </div>

              {form.accused.some((a, i) => !isAccusedEditing(a, i)) ? (
                <div className="table-card table-responsive" style={{ marginBottom: 16 }}>
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>NAME</th>
                        <th>CNIC</th>
                        <th>CONTACT</th>
                        <th>DESCRIPTION</th>
                        <th>ACTIONS</th>
                      </tr>
                    </thead>
                    <tbody>
                      {form.accused.map((a, i) => {
                        if (isAccusedEditing(a, i)) return null;
                        return (
                          <tr key={a.id || `acc-${i}`}>
                            <td><span className="badge" style={{ background: 'rgba(1,92,148,0.12)', color: '#015C94', fontWeight: 700 }}>#{i + 1}</span></td>
                            <td style={{ fontWeight: 600 }}>{a.name || '—'}</td>
                            <td>{a.cnic || '—'}</td>
                            <td>{a.contact_no || a.whatsapp_no || '—'}</td>
                            <td style={{ maxWidth: 280, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{a.description || a.permanent_address || a.postal_address || '—'}</td>
                            <td>
                              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <button
                                  type="button"
                                  className="btn btn-outline btn-sm"
                                  onClick={() => setEditingAccusedIndex(i)}
                                  title="Edit"
                                >
                                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                  Edit
                                </button>
                                {!isSavedDiaryLocked(a, user) && (
                                  <button
                                    type="button"
                                    className="btn btn-sm"
                                    style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: 8, width: 36, height: 36 }}
                                    onClick={() => removeAccused(i)}
                                    title="Delete Accused"
                                  >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                  </button>
                                )}
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : null}

              {form.accused.map((a, i) => {
                if (!isAccusedEditing(a, i)) return null;
                return (
                <div key={a.id || `new-${i}`} className={isSavedDiaryLocked(a, user) ? 'diary-saved-locked' : undefined} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <strong style={{ fontSize: 13, color: '#015C94' }}>{a.id ? `Edit Accused #${i + 1}` : `Accused Person #${i + 1}`}</strong>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => setEditingAccusedIndex(null)}>Done</button>
                      {!isSavedDiaryLocked(a, user) && (
                        <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px' }} onClick={() => removeAccused(i)} title="Delete Accused (CI/AD/DD/Admin only)">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      )}
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Name</label><input type="text" className="cf-input" value={a.name} onChange={e => updateAccused(i, 'name', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">CNIC</label><input type="text" className="cf-input" value={a.cnic} onChange={e => updateAccusedCnic(i, e.target.value)} placeholder="00000-0000000-0" maxLength={15} /></div>
                    <div className="cf-field"><label className="cf-label">Father Name</label><input type="text" className="cf-input" value={a.father_name} onChange={e => updateAccused(i, 'father_name', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Gender</label>
                      <select className="cf-input" value={a.gender} onChange={e => updateAccused(i, 'gender', e.target.value)}>
                        <option value="">— Select —</option>
                        {GENDER_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Contact No</label><input type="text" className="cf-input" value={a.contact_no} onChange={e => updateAccusedContact(i, 'contact_no', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">WhatsApp No</label><input type="text" className="cf-input" value={a.whatsapp_no} onChange={e => updateAccusedContact(i, 'whatsapp_no', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Email</label><input type="email" className="cf-input" value={a.email} onChange={e => updateAccused(i, 'email', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Religion</label><input type="text" className="cf-input" value={a.religion} onChange={e => updateAccused(i, 'religion', e.target.value)} /></div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Postal Address</label><textarea className="cf-input" rows={2} value={a.postal_address} onChange={e => updateAccused(i, 'postal_address', e.target.value)} style={{ width: '100%' }} /></div>
                    <div className="cf-field"><label className="cf-label">Permanent Address</label><textarea className="cf-input" rows={2} value={a.permanent_address} onChange={e => updateAccused(i, 'permanent_address', e.target.value)} style={{ width: '100%' }} /></div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">District (Domicile)</label><input type="text" className="cf-input" value={a.district_domicile} onChange={e => updateAccused(i, 'district_domicile', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Identification Mark</label><input type="text" className="cf-input" value={a.identification_mark} onChange={e => updateAccused(i, 'identification_mark', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Occupation</label><input type="text" className="cf-input" value={a.occupation} onChange={e => updateAccused(i, 'occupation', e.target.value)} /></div>
                    <div className="cf-field" style={{ display: 'flex', alignItems: 'center', gap: 8, paddingTop: 24 }}>
                      <input type="checkbox" id={`accused-gov-${i}`} checked={!!a.is_government} onChange={e => updateAccused(i, 'is_government', e.target.checked)} />
                      <label htmlFor={`accused-gov-${i}`} className="cf-label" style={{ margin: 0 }}>Government Employee</label>
                    </div>
                  </div>
                  {a.is_government && (
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                      <div className="cf-field"><label className="cf-label">Department</label><input type="text" className="cf-input" value={a.department_name} onChange={e => updateAccused(i, 'department_name', e.target.value)} /></div>
                      <div className="cf-field"><label className="cf-label">Designation</label><input type="text" className="cf-input" value={a.designation} onChange={e => updateAccused(i, 'designation', e.target.value)} /></div>
                    </div>
                  )}
                  <div className="cf-field" style={{ marginBottom: 12 }}>
                    <label className="cf-label">Description</label>
                    <textarea
                      className="cf-input"
                      rows={2}
                      value={a.description || ''}
                      onChange={e => updateAccused(i, 'description', e.target.value)}
                      placeholder="Short description / remarks about this accused"
                      style={{ width: '100%' }}
                    />
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px' }}>
                    {[
                      ['cnic_attachment', 'CNIC Attachment', '.pdf,.jpg,.jpeg,.png,.doc,.docx'],
                      ['passport_attachment', 'Passport Attachment', '.pdf,.jpg,.jpeg,.png,.doc,.docx'],
                      ['nadra_verisys_attachment', 'NADRA Verisys', '.pdf,.jpg,.jpeg,.png,.doc,.docx'],
                      ['picture', 'Photo / Picture', 'image/*'],
                    ].map(([field, label, accept]) => (
                      <div key={field} className="cf-field">
                        <label className="cf-label">{label}</label>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                          <input type="file" className="cf-input" accept={accept} onChange={e => updateAccusedFile(i, field, e.target.files[0])} />
                          {field === 'picture' && (
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
                        {a[field] instanceof File ? (
                          <span style={{ fontSize: 12, color: '#38a169', marginTop: 4, display: 'block' }}>Selected: {a[field].name}</span>
                        ) : (a[field] && typeof a[field] === 'string') ? (
                          <a href={a[`${field}_url`]} target="_blank" rel="noreferrer" style={{ fontSize: 12, color: '#015C94', fontWeight: 600, marginTop: 4, display: 'inline-block' }}>Existing file ↗</a>
                        ) : null}
                      </div>
                    ))}
                  </div>
                </div>
                );
              })}
              {form.accused.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No accused added yet.</p>}
            </div>
          </div>
        )}

        {/* WITNESSES TAB */}
        {activeTab === 'witnesses' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#264078' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </div>
              <div><div className="cf-section-title">Witnesses</div><div className="cf-section-sub">Full witness details, picture &amp; statement attachments</div></div>
            </div>
            <div className="cf-body">
              <button type="button" className="btn btn-outline btn-sm" onClick={addWitness} style={{ marginBottom: 16 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Witness
              </button>

              {form.witnesses.some((w, i) => !isWitnessEditing(w, i)) ? (
                <div className="table-card table-responsive" style={{ marginBottom: 16 }}>
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>NAME</th>
                        <th>CNIC</th>
                        <th>RELATION</th>
                        <th>CONTACT</th>
                        <th>ADDRESS</th>
                        <th>ACTIONS</th>
                      </tr>
                    </thead>
                    <tbody>
                      {form.witnesses.map((w, i) => {
                        if (isWitnessEditing(w, i)) return null;
                        return (
                          <tr key={w.id || `wit-${i}`}>
                            <td><span className="badge" style={{ background: 'rgba(38,64,120,0.12)', color: '#264078', fontWeight: 700 }}>#{i + 1}</span></td>
                            <td style={{ fontWeight: 600 }}>{w.name || '—'}</td>
                            <td>{w.cnic || '—'}</td>
                            <td>{w.relation || '—'}</td>
                            <td>{w.contact_no || w.whatsapp_no || '—'}</td>
                            <td style={{ maxWidth: 220, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{w.address || w.permanent_address || w.mailing_address || '—'}</td>
                            <td>
                              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                                <button
                                  type="button"
                                  className="btn btn-outline btn-sm"
                                  onClick={() => setEditingWitnessIndex(i)}
                                  title="Edit"
                                >
                                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                  Edit
                                </button>
                                {!isSavedDiaryLocked(w, user) && (
                                  <button
                                    type="button"
                                    className="btn btn-sm"
                                    style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: 8, width: 36, height: 36 }}
                                    onClick={() => removeWitness(i)}
                                    title="Delete Witness"
                                  >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                  </button>
                                )}
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : null}

              {form.witnesses.map((w, i) => {
                if (!isWitnessEditing(w, i)) return null;
                return (
                <div key={w.id || `new-wit-${i}`} className={isSavedDiaryLocked(w, user) ? 'diary-saved-locked' : undefined} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12 }}>
                    <strong style={{ fontSize: 13, color: '#264078' }}>{w.id ? `Edit Witness #${i + 1}` : `Witness Person #${i + 1}`}</strong>
                    <div style={{ display: 'flex', gap: 8 }}>
                      <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => setEditingWitnessIndex(null)}>Done</button>
                      {!isSavedDiaryLocked(w, user) && (
                        <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px' }} onClick={() => removeWitness(i)} title="Delete Witness (CI/AD/DD/Admin only)">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      )}
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Witness Name</label><input type="text" className="cf-input" value={w.name} onChange={e => updateWitness(i, 'name', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Father Name</label><input type="text" className="cf-input" value={w.father_name} onChange={e => updateWitness(i, 'father_name', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Relation</label><input type="text" className="cf-input" value={w.relation} onChange={e => updateWitness(i, 'relation', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Gender</label>
                      <select className="cf-input" value={w.gender} onChange={e => updateWitness(i, 'gender', e.target.value)}>
                        <option value="">— Select —</option>
                        {GENDER_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">CNIC</label><input type="text" className="cf-input" value={w.cnic} onChange={e => updateWitnessCnic(i, e.target.value)} placeholder="00000-0000000-0" maxLength={15} /></div>
                    <div className="cf-field"><label className="cf-label">Domicile District</label><input type="text" className="cf-input" value={w.domicile_district} onChange={e => updateWitness(i, 'domicile_district', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Nationality</label><input type="text" className="cf-input" value={w.nationality} onChange={e => updateWitness(i, 'nationality', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Passport No</label><input type="text" className="cf-input" value={w.passport} onChange={e => updateWitness(i, 'passport', e.target.value)} /></div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Occupation</label><input type="text" className="cf-input" value={w.occupation} onChange={e => updateWitness(i, 'occupation', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Contact No</label><input type="text" className="cf-input" value={w.contact_no} onChange={e => updateWitnessContact(i, 'contact_no', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">WhatsApp No</label><input type="text" className="cf-input" value={w.whatsapp_no} onChange={e => updateWitnessContact(i, 'whatsapp_no', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Scale</label><input type="text" className="cf-input" value={w.scale} onChange={e => updateWitness(i, 'scale', e.target.value)} /></div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field" style={{ display: 'flex', alignItems: 'center', gap: 8, paddingTop: 8 }}>
                      <input type="checkbox" id={`witness-gov-${i}`} checked={!!w.is_government} onChange={e => updateWitness(i, 'is_government', e.target.checked)} />
                      <label htmlFor={`witness-gov-${i}`} className="cf-label" style={{ margin: 0 }}>Government Employee</label>
                    </div>
                  </div>
                  {w.is_government && (
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                      <div className="cf-field"><label className="cf-label">Department</label><input type="text" className="cf-input" value={w.department_name} onChange={e => updateWitness(i, 'department_name', e.target.value)} /></div>
                      <div className="cf-field"><label className="cf-label">Designation</label><input type="text" className="cf-input" value={w.designation} onChange={e => updateWitness(i, 'designation', e.target.value)} /></div>
                    </div>
                  )}
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Mailing Address</label><textarea className="cf-input" rows={2} value={w.mailing_address} onChange={e => updateWitness(i, 'mailing_address', e.target.value)} style={{ width: '100%' }} /></div>
                    <div className="cf-field"><label className="cf-label">Permanent Address</label><textarea className="cf-input" rows={2} value={w.permanent_address} onChange={e => updateWitness(i, 'permanent_address', e.target.value)} style={{ width: '100%' }} /></div>
                  </div>
                  <div className="cf-field" style={{ marginBottom: 12 }}><label className="cf-label">Address</label><textarea className="cf-input" rows={2} value={w.address} onChange={e => updateWitness(i, 'address', e.target.value)} style={{ width: '100%' }} /></div>
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px' }}>
                    {[
                      ['attachment', 'CNIC Attachment', '.pdf,.jpg,.jpeg,.png,.doc,.docx'],
                      ['picture', 'Picture / Photo', 'image/*'],
                      ['statement_attachment', 'Statement Attachment', '.pdf,.jpg,.jpeg,.png,.doc,.docx'],
                    ].map(([field, label, accept]) => (
                      <div key={field} className="cf-field">
                        <label className="cf-label">{label}</label>
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                          <input type="file" className="cf-input" accept={accept} onChange={e => updateWitnessFile(i, field, e.target.files[0])} />
                          {field === 'picture' && (
                            <button
                              type="button"
                              onClick={() => setCameraModal({
                                open: true,
                                title: `Capture Photo for Witness #${i + 1} (${w.name || 'Witness'})`,
                                onCapture: (file) => updateWitnessFile(i, 'picture', file)
                              })}
                              className="btn btn-outline btn-sm"
                              style={{ height: 38, padding: '0 10px', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 4, whiteSpace: 'nowrap', borderColor: '#015C94', color: '#015C94' }}
                              title="Open Live Webcam / Camera"
                            >
                              📷 Camera
                            </button>
                          )}
                        </div>
                        {w[field] instanceof File ? (
                          <span style={{ fontSize: 12, color: '#38a169', marginTop: 4, display: 'block' }}>Selected: {w[field].name}</span>
                        ) : (w[field] && typeof w[field] === 'string') ? (
                          <a href={w[`${field}_url`]} target="_blank" rel="noreferrer" style={{ fontSize: 12, color: '#015C94', fontWeight: 600, marginTop: 4, display: 'inline-block' }}>Existing file ↗</a>
                        ) : null}
                      </div>
                    ))}
                  </div>
                </div>
              );
              })}
              {form.witnesses.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No witnesses added yet. Click "Add Witness" to start.</p>}
            </div>
          </div>
        )}

        {/* NOTICES TAB */}
        {activeTab === 'notices' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#B7791F' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
              </div>
              <div><div className="cf-section-title">Summon Detail</div><div className="cf-section-sub">Issue summons — after 3 non-appearances the matter is referred to court</div></div>
            </div>
            <div className="cf-body">
              {nonAppearanceCount > 0 && (
                <div style={{ padding: '12px 16px', marginBottom: 16, background: 'rgba(255,193,7,0.14)', border: '1px solid #d69e2e', borderRadius: 8, fontSize: 13, color: '#7a5b00', display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span style={{ fontSize: 20 }}>!</span>
                  <div>
                    <strong>Non-Appearance recorded ({nonAppearanceCount} of 3).</strong>
                    {referredToCourt
                      ? ' This enquiry has been referred to court as per procedure.'
                      : ' After 3 non-appearances the file will be referred to court.'}
                  </div>
                </div>
              )}
              {referredToCourt && (
                <div style={{ padding: '12px 16px', marginBottom: 16, background: 'rgba(229,62,62,0.1)', border: '1px solid #e53e3e', borderRadius: 8, fontSize: 13, color: '#b42318', fontWeight: 600 }}>
                  Warning: This enquiry has been referred to court (3 non-appearances).
                </div>
              )}

              <button type="button" className="btn btn-outline btn-sm" onClick={addNotice} style={{ marginBottom: 16 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Summon
              </button>

              {/* SAVED SUMMONS SUMMARY TABLE */}
              {form.notices.some((n, i) => (n.id || n.notice_number || n.receiver_name) && !isNoticeEditing(n, i)) ? (
                <div className="table-card table-responsive" style={{ marginBottom: 16 }}>
                  <table className="data-table">
                    <thead>
                      <tr>
                        <th>#</th>
                        <th>SUMMON NO</th>
                        <th>RECIPIENT / PERSON TYPE</th>
                        <th>SUMMON DATE</th>
                        <th>SUMMON VIA</th>
                        <th>APPEARANCE DATE</th>
                        <th>STATUS & REMARKS</th>
                        <th>ACTIONS</th>
                      </tr>
                    </thead>
                    <tbody>
                      {form.notices.map((n, i) => {
                        if (isNoticeEditing(n, i)) return null;
                        const statusObj = NOTICE_STATUS_OPTIONS.find(o => o.value === n.status);
                        const statusLabel = statusObj?.name || n.status || 'Issued';
                        const isNonApp = n.appearance_remarks === 'non_appearance';
                        const statusColor = isNonApp ? '#e53e3e' : n.status === 'served' ? '#2d6a4f' : '#B7791F';

                        return (
                          <tr key={n.id || i}>
                            <td><span className="badge" style={{ background: 'rgba(1,92,148,0.12)', color: '#015C94', fontWeight: 700 }}>#{i + 1}</span></td>
                            <td style={{ fontWeight: 700, color: '#1e293b' }}>{n.notice_number || '—'}</td>
                            <td>
                              <div style={{ fontWeight: 600 }}>{n.receiver_name || '—'}</div>
                              {n.person_type && (
                                <span className="badge" style={{ fontSize: 10, background: '#e2e8f0', color: '#475569', textTransform: 'capitalize' }}>
                                  {n.person_type}
                                </span>
                              )}
                            </td>
                            <td>{n.notice_date || '—'}</td>
                            <td>
                              <div style={{ fontSize: 12 }}>{NOTICE_VIA_OPTIONS.find(o => o.value === n.notice_via)?.name || n.notice_via || '—'}</div>
                              {n.notice_type && <div style={{ color: '#64748b', fontSize: 11 }}>{NOTICE_TYPE_OPTIONS.find(o => o.value === n.notice_type)?.name || n.notice_type}</div>}
                            </td>
                            <td>
                              {n.appearance_date ? (
                                <div style={{ fontWeight: 600, color: '#1e293b' }}>
                                  {new Date(n.appearance_date).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}
                                </div>
                              ) : '—'}
                            </td>
                            <td>
                              <span className="badge" style={{ background: `${statusColor}18`, color: statusColor, fontWeight: 700 }}>
                                {statusLabel}
                              </span>
                              {n.appearance_remarks && (
                                <div style={{ fontSize: 11, color: isNonApp ? '#e53e3e' : '#64748b', marginTop: 2, fontWeight: isNonApp ? 600 : 400 }}>
                                  {APPEARANCE_REMARKS_OPTIONS.find(o => o.value === n.appearance_remarks)?.name || n.appearance_remarks}
                                </div>
                              )}
                            </td>
                            <td>
                              <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                                <button
                                  type="button"
                                  className="btn btn-sm"
                                  style={{ background: '#25D366', color: '#fff', border: 'none', borderRadius: 8, fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 4 }}
                                  onClick={() => sendSummonWhatsApp(n)}
                                  title="Send Summon via WhatsApp"
                                >
                                  <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.204 8.204 0 0 1 2.41 5.82c0 4.54-3.7 8.24-8.24 8.24-1.45 0-2.87-.38-4.12-1.1l-.3-.17-3.12.82.83-3.04-.19-.31a8.216 8.216 0 0 1-1.26-4.44c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.64c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.05s.88 2.38 1 2.54c.12.17 1.74 2.65 4.21 3.72.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.23-.17-.48-.29z"/></svg>
                                  WhatsApp
                                </button>
                                <button
                                  type="button"
                                  className="btn btn-outline btn-sm"
                                  onClick={() => printNotice(n)}
                                  title="Print Summon"
                                  data-locked-ok="1"
                                >
                                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                  Print
                                </button>
                                <button
                                  type="button"
                                  className="btn btn-outline btn-sm"
                                  onClick={() => setEditingNoticeIndex(i)}
                                  title="Edit"
                                >
                                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                  Edit
                                </button>
                                {n.id && (
                                  <button
                                    type="button"
                                    className="btn btn-outline btn-sm"
                                    onClick={() => duplicateNotice(i)}
                                    title="Issue Next Notice (Duplicate)"
                                  >
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    Re-Issue
                                  </button>
                                )}
                                {!n.id && (
                                  <button
                                    type="button"
                                    className="btn btn-sm"
                                    style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: 8, width: 32, height: 32 }}
                                    onClick={() => removeNotice(i)}
                                    title="Remove"
                                  >
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                  </button>
                                )}
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : null}

              {/* EXPANDED EDIT FORM FOR SELECTED / NEW SUMMON */}
              {form.notices.map((n, i) => {
                if (!isNoticeEditing(n, i)) return null;
                return (
                <div key={n.id || `notice-${i}`} className={isSavedDiaryLocked(n, user) ? 'diary-saved-locked' : undefined} style={{ border: '1px solid #015C94', borderRadius: 10, padding: 16, marginBottom: 14, background: '#f8fafc' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, borderBottom: '1px solid #e2e8f0', paddingBottom: 8 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <span className="badge" style={{ background: '#015C94', color: '#fff', fontWeight: 700 }}>
                        {n.id || n.notice_number ? `Edit Summon #${i + 1} (${n.notice_number || ''})` : `New Summon #${i + 1}`}
                      </span>
                    </div>
                    <div style={{ display: 'flex', gap: 6 }}>
                      {(n.id || n.notice_number || n.receiver_name) && (
                        <button
                          type="button"
                          className="btn btn-outline btn-sm"
                          data-locked-ok="1"
                          onClick={() => setEditingNoticeIndex(null)}
                        >
                          Done (Collapse)
                        </button>
                      )}
                      {!isSavedDiaryLocked(n, user) && (
                      <button
                        type="button"
                        className="btn btn-sm"
                        style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: 8 }}
                        onClick={() => removeNotice(i)}
                      >
                        Remove
                      </button>
                      )}
                    </div>
                  </div>

                  {i >= 1 && form.notices.slice(0, i).length > 0 && (
                    <div style={{ padding: '10px 14px', marginBottom: 12, background: '#eef4f8', border: '1px solid #c5d9e8', borderRadius: 8, fontSize: 12 }}>
                      <strong style={{ display: 'block', marginBottom: 6 }}>Previous Summon History</strong>
                      {form.notices.slice(0, i).map((prev, pi) => (
                        <div key={pi} style={{ marginBottom: 4 }}>
                          #{pi + 1}: {prev.notice_date || '—'} — {NOTICE_STATUS_OPTIONS.find(o => o.value === prev.status)?.name || prev.status || '—'}
                          {prev.appearance_remarks && ` (${APPEARANCE_REMARKS_OPTIONS.find(o => o.value === prev.appearance_remarks)?.name || prev.appearance_remarks})`}
                        </div>
                      ))}
                    </div>
                  )}
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Person Type</label>
                      <select className="cf-input" value={n.person_type} onChange={e => onNoticePersonTypeChange(i, e.target.value)}>
                        <option value="">— Select —</option>
                        {PERSON_TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    {n.person_type === 'accused' && (
                      <div className="cf-field"><label className="cf-label">Select Accused</label>
                        <select
                          className="cf-input"
                          value={n.person_ref ?? ''}
                          onChange={e => fillNoticeFromPerson(i, 'accused', e.target.value)}
                        >
                          <option value="">— Select Accused —</option>
                          {form.accused.map((a, ai) => (
                            <option key={ai} value={String(ai)} disabled={!a.name}>
                              {a.name || `Accused #${ai + 1}`}{a.cnic ? ` (${a.cnic})` : ''}
                            </option>
                          ))}
                        </select>
                      </div>
                    )}
                    {n.person_type === 'witness' && (
                      <div className="cf-field"><label className="cf-label">Select Witness</label>
                        <select
                          className="cf-input"
                          value={n.person_ref ?? ''}
                          onChange={e => fillNoticeFromPerson(i, 'witness', e.target.value)}
                        >
                          <option value="">— Select Witness —</option>
                          {form.witnesses.map((w, wi) => (
                            <option key={wi} value={String(wi)} disabled={!w.name}>
                              {w.name || `Witness #${wi + 1}`}{w.cnic ? ` (${w.cnic})` : ''}
                            </option>
                          ))}
                        </select>
                      </div>
                    )}
                    <div className="cf-field"><label className="cf-label">Receiver Name</label>
                      <input type="text" className="cf-input" value={n.receiver_name} onChange={e => updateNotice(i, 'receiver_name', e.target.value)} placeholder="Recipient name" />
                    </div>
                    <div className="cf-field"><label className="cf-label">Father Name</label>
                      <input type="text" className="cf-input" value={n.father_name || ''} onChange={e => updateNotice(i, 'father_name', e.target.value)} placeholder="Father name" />
                    </div>
                    <div className="cf-field"><label className="cf-label">CNIC</label>
                      <input type="text" className="cf-input" value={n.cnic || ''} onChange={e => updateNotice(i, 'cnic', e.target.value.replace(/\D/g, '').slice(0,13))} placeholder="CNIC Number" />
                    </div>
                    <div className="cf-field"><label className="cf-label">Summon No</label>
                      <input type="text" className="cf-input" value={n.notice_number} onChange={e => updateNotice(i, 'notice_number', e.target.value)} placeholder="e.g. NCCIA/N/25" />
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Summon Type</label>
                      <select className="cf-input" value={n.notice_type} onChange={e => updateNotice(i, 'notice_type', e.target.value)}>
                        <option value="">— Select —</option>
                        {NOTICE_TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    <div className="cf-field"><label className="cf-label">Summon Date</label>
                      <input type="date" className="cf-input" value={n.notice_date} onChange={e => updateNotice(i, 'notice_date', e.target.value)} />
                    </div>
                    <div className="cf-field"><label className="cf-label">Summon Via</label>
                      <select className="cf-input" value={n.notice_via} onChange={e => updateNotice(i, 'notice_via', e.target.value)}>
                        <option value="">— Select —</option>
                        {NOTICE_VIA_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    <div className="cf-field" style={{ gridColumn: 'span 1' }}><label className="cf-label">Phone</label>
                      <input type="text" className="cf-input" value={n.phone} onChange={e => updateNotice(i, 'phone', e.target.value)} placeholder="Phone number" />
                    </div>
                    <div className="cf-field" style={{ gridColumn: 'span 4' }}><label className="cf-label">Gist of Allegation / Description</label>
                      <textarea className="cf-input" rows={2} value={n.description || ''} onChange={e => updateNotice(i, 'description', e.target.value)} placeholder="Enter custom gist of allegation for this summon..." style={{ width: '100%' }} />
                    </div>
                  </div>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Status</label>
                      <select className="cf-input" value={n.status} onChange={e => updateNotice(i, 'status', e.target.value)}>
                        {NOTICE_STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    <div className="cf-field"><label className="cf-label">Appearance Date</label>
                      <input type="datetime-local" className="cf-input" value={n.appearance_date || ''} onChange={e => updateNotice(i, 'appearance_date', e.target.value)} />
                    </div>
                    <div className="cf-field"><label className="cf-label">Appearance Remarks</label>
                      <select className="cf-input" value={n.appearance_remarks || ''} onChange={e => updateNotice(i, 'appearance_remarks', e.target.value)}>
                        <option value="">— Select —</option>
                        {APPEARANCE_REMARKS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                  </div>
                  <div className="cf-field" style={{ marginBottom: 12 }}><label className="cf-label">Address</label>
<textarea className="cf-input" rows={2} value={n.address} onChange={e => updateNotice(i, 'address', e.target.value)} placeholder="Delivery / contact address" style={{ width: '100%' }}></textarea>
                  </div>
                  <div className="cf-field" style={{ gridColumn: '1 / -1' }}>
                    <label className="cf-label">Description / Instructions</label>
                    <textarea className="cf-input" rows={2} value={n.description} onChange={e => updateNotice(i, 'description', e.target.value)} placeholder="Brief description / instructions on the summon" style={{ width: '100%' }}></textarea>
                  </div>
                  <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 8, flexWrap: 'wrap' }}>
                    <button
                      type="button"
                      className="btn btn-sm"
                      style={{ background: '#25D366', color: '#fff', border: 'none', borderRadius: 8, fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 6 }}
                      onClick={() => sendSummonWhatsApp(n)}
                      title="Send via WhatsApp"
                    >
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm.01 1.67c2.2 0 4.26.86 5.82 2.42a8.204 8.204 0 0 1 2.41 5.82c0 4.54-3.7 8.24-8.24 8.24-1.45 0-2.87-.38-4.12-1.1l-.3-.17-3.12.82.83-3.04-.19-.31a8.216 8.216 0 0 1-1.26-4.44c0-4.54 3.7-8.24 8.24-8.24zm4.52 11.64c-.25-.12-1.47-.72-1.7-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.24-1.47-1.38-1.72-.14-.25-.02-.38.11-.5.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.4-.42-.56-.43h-.47c-.17 0-.43.06-.66.31-.22.25-.86.84-.86 2.05s.88 2.38 1 2.54c.12.17 1.74 2.65 4.21 3.72.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.47-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.15-1.18-.06-.11-.23-.17-.48-.29z"/></svg>
                      Send via WhatsApp
                    </button>
                    <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => printNotice(n)}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print Summon
                    </button>
                    {(n.id || n.notice_number || n.receiver_name) && (
                      <button
                        type="button"
                        className="btn btn-primary btn-sm"
                        onClick={() => setEditingNoticeIndex(null)}
                      >
                        Done (Save & Collapse)
                      </button>
                    )}
                  </div>
                </div>
              );
              })}
              {form.notices.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No summons added yet. Click "Add Summon" to start.</p>}
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
                <div className="cf-section-sub">Real-time team chat & notes for Enquiry #{form.enquiry_number || id}</div>
              </div>
            </div>
            <div className="cf-body" style={{ padding: 16 }}>
              <CaseChatPanel
                type="enquiry"
                id={id || form.enquiry_number || 1}
                caseNumber={form.enquiry_number || (id ? `ENQ-${id}` : '')}
                title={selectedComplaint?.complainant_name || direct?.complainant_name || ''}
                officers={form.enquiry_officer_id ? [{ name: 'Enquiry Officer', role_label: 'Assigned' }] : []}
                compact={false}
              />
            </div>
          </div>
        )}

        {/* ATTACHMENTS TAB */}
        {activeTab === 'attachments' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#5a189a' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
              </div>
              <div><div className="cf-section-title">Enquiry Attachments</div><div className="cf-section-sub">Supporting documents with title and date</div></div>
            </div>
            <div className="cf-body">
              <button type="button" className="btn btn-outline btn-sm" onClick={addAttachment} style={{ marginBottom: 16 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Attachment
              </button>
              {form.attachments.map((at, i) => (
                <div key={at.id || at._uid || `att-${i}`} className={isSavedDiaryLocked(at, user) ? 'diary-saved-locked' : undefined} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '12px', alignItems: 'end' }}>
                    <div className="cf-field"><label className="cf-label">Title</label><input type="text" className="cf-input" value={at.title} onChange={e => updateAttachment(i, 'title', e.target.value)} /></div>
                    <div className="cf-field"><label className="cf-label">Attachment Date</label><input type="date" className="cf-input" value={at.attachment_date} onChange={e => updateAttachment(i, 'attachment_date', e.target.value)} /></div>
                    <div className="cf-field">
                      <label className="cf-label">File</label>
                      <input type="file" className="cf-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onChange={e => updateAttachmentFile(i, e.target.files[0])} />
                      {at.file_path && (
                        <a href={at.file_path_url} target="_blank" rel="noreferrer" style={{ fontSize: 12, color: '#015C94', fontWeight: 600, marginTop: 4, display: 'inline-block' }}>Download existing Γåù</a>
                      )}
                    </div>
                    {(!at.id || isPrivileged) && !isSavedDiaryLocked(at, user) && (
                      <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px' }} onClick={() => removeAttachment(i)}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    )}
                  </div>
                </div>
              ))}
              {form.attachments.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No attachments added yet.</p>}
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
              <div><div className="cf-section-title">Enquiry Diary &amp; Forensic Seizures</div><div className="cf-section-sub">DAC, Bank, Search, Seize, Summons, Diaries, Seizures, Recoveries, Lab Reports</div></div>
              <div className="cf-section-badge">STEP 3</div>
            </div>
            <div className="cf-body">
              {/* Linked Forensic & Technical Reports Status Banner */}
              {linkedForensicRequests.length > 0 && (
                <div style={{ marginBottom: 20, padding: 16, background: '#f8fafc', border: '1.5px solid #cbd5e1', borderRadius: 10 }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, flexWrap: 'wrap', gap: 8 }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <span style={{ fontSize: 16 }}>🔬</span>
                      <strong style={{ fontSize: 13.5, color: '#0f172a' }}>
                        Lab Examination &amp; Forensic Reports Status ({linkedForensicRequests.length})
                      </strong>
                    </div>
                    <button
                      type="button"
                      className="btn btn-outline btn-sm"
                      onClick={() => loadLinkedForensicRequests(id)}
                      data-locked-ok="1"
                      disabled={loadingLinkedReports}
                      style={{ fontSize: 11.5 }}
                    >
                      {loadingLinkedReports ? 'Refreshing…' : '🔄 Refresh Status'}
                    </button>
                  </div>

                  <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                    {linkedForensicRequests.map((fr) => {
                      const isReady = fr.status === 'report_ready' || fr.status === 'handed_over';
                      return (
                        <div
                          key={fr.id}
                          style={{
                            padding: '14px 18px',
                            borderRadius: 8,
                            background: isReady ? '#ecfdf5' : '#fffbeb',
                            border: `1.5px solid ${isReady ? '#6ee7b7' : '#fde68a'}`,
                          }}
                        >
                          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 10 }}>
                            <div style={{ flex: 1, minWidth: 0 }}>
                              <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                                <strong style={{ fontSize: 13.5, color: '#0f172a' }}>{fr.request_no}</strong>
                                <span style={{
                                  fontSize: 11, fontWeight: 700, padding: '3px 9px', borderRadius: 12,
                                  background: isReady ? '#d1fae5' : '#fef3c7',
                                  color: isReady ? '#065f46' : '#92400e',
                                }}>
                                  {isReady
                                    ? (fr.status === 'handed_over' ? '📦 Custody Handed Over to EO' : '✅ Forensic Report Approved & Ready')
                                    : (fr.status === 'submitted'
                                      ? '⏳ Pending AD Review'
                                      : fr.status === 'assigned'
                                        ? `👤 Assigned to FO (${fr.assignee?.name || 'FO'})`
                                        : fr.status === 'submitted_to_ad'
                                          ? '📝 Submitted to AD for Approval'
                                          : '🔬 In Lab Examination')}
                                </span>
                                <span style={{ fontSize: 11, color: '#64748b', textTransform: 'capitalize' }}>
                                  ({fr.destination === 'forensic' ? 'Digital Forensic Lab' : 'Technical Dept'})
                                </span>
                              </div>

                              {fr.report_code && (
                                <div style={{ marginTop: 8, fontSize: 13 }}>
                                  <span style={{ color: '#334155' }}>Official Report Code: </span>
                                  <strong style={{ fontFamily: 'monospace', fontSize: 14, background: '#fff', padding: '2px 8px', borderRadius: 4, border: '1px solid #10b981', color: '#065f46' }}>
                                    {fr.report_code}
                                  </strong>
                                  <span style={{ fontSize: 11.5, color: '#059669', marginLeft: 8, fontWeight: 600 }}>
                                    (Collect physical report by-hand from Forensic Lab using this code)
                                  </span>
                                </div>
                              )}

                              {fr.findings && (
                                <div style={{ marginTop: 8, fontSize: 12.5, color: '#1e293b', background: '#fff', padding: '8px 12px', borderRadius: 6, border: '1px solid #e2e8f0', whiteSpace: 'pre-wrap' }}>
                                  <strong>Lab Examination Findings:</strong> {fr.findings}
                                </div>
                              )}

                              <div style={{ marginTop: 6, fontSize: 11.5, color: '#64748b' }}>
                                {fr.items?.length > 0 && <span>Evidence: {fr.items.length} item(s) • </span>}
                                {fr.created_at && <span>Submitted: {new Date(fr.created_at).toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' })} • </span>}
                                {fr.assignee?.name && <span>FO: {fr.assignee.name}</span>}
                              </div>
                            </div>

                            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
                              {fr.report_attachment_path && (
                                <a
                                  href={fr.report_attachment_url}
                                  target="_blank"
                                  rel="noreferrer"
                                  className="btn btn-primary btn-sm"
                                  style={{ background: '#059669', display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 12 }}
                                >
                                  📥 Download Lab Report PDF
                                </a>
                              )}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
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
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Activity Type</label>
                      <select className="cf-input" value={a.type} disabled={activityHasLockedSeizeItems(a)} onChange={e => updateActivity(i, 'type', e.target.value)}>
                        <option value="">— Select Type —</option>
                        {ACTIVITY_TYPES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    {a.type === 'diaries' && (
                      <div className="cf-field"><label className="cf-label">Diary No</label>
                        <input type="text" className="cf-input" value={a.diary_no || ''} onChange={e => updateActivity(i, 'diary_no', e.target.value)} placeholder="Diary number" />
                      </div>
                    )}
                    <div className="cf-field"><label className="cf-label">Date</label>
                      <input type="date" className="cf-input" value={a.activity_date} onChange={e => updateActivity(i, 'activity_date', e.target.value)} />
                    </div>
                    <div className="cf-field"><label className="cf-label">Attachment</label>
                      <input type="file" className="cf-input" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onChange={e => updateActivityFile(i, e.target.files[0])} />
                    </div>
                    {(!a.id || isPrivileged) && !activityHasLockedSeizeItems(a) && !isSavedDiaryLocked(a, user) && (
                      <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end', justifySelf: 'end' }} onClick={() => removeActivity(i)}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    )}
                  </div>
                  {!['search_seize', 'raid', 'arrest_warrant'].includes(a.type) && (
                    <div className="cf-field"><label className="cf-label">Description</label>
                      <textarea className="cf-input" rows={3} value={a.description} onChange={e => updateActivity(i, 'description', e.target.value)} placeholder="Describe the activity..." style={{ width: '100%' }}></textarea>
                    </div>
                  )}

                  {['search_seize', 'raid', 'arrest_warrant'].includes(a.type) && (
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
                            ? 'PERMISSION TO CONDUCT A RAID IN CASE FIR / ENQUIRY NO. …'
                            : a.type === 'arrest_warrant'
                              ? 'ARREST WARRANT IN ENQUIRY / FIR NO. …'
                              : 'SEARCH WARRANT U/S 33 PECA-2016 IN ENQUIRY NO. …'}
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
                            value={(form.accused || []).some(ac => ac.name === a.against_whom) ? (a.against_whom || '') : ''}
                            onChange={e => updateActivity(i, 'against_whom', e.target.value)}
                          >
                            <option value="">— Select accused —</option>
                            {(form.accused || []).filter(ac => (ac.name || '').trim()).map((ac, idx) => (
                              <option key={idx} value={ac.name}>{ac.name}{ac.father_name ? ` s/o ${ac.father_name}` : ''}</option>
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

                  {a.type === 'seizures' && (
                    <div style={{ marginTop: 14, padding: 14, background: '#fff', border: '1.5px solid #bfdbfe', borderRadius: 8, boxShadow: '0 2px 8px rgba(1,92,148,0.06)' }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 12, flexWrap: 'wrap', gap: 8 }}>
                        <div>
                          <strong style={{ fontSize: 13.5, color: '#015C94', display: 'block' }}>📦 Seized Evidence &amp; Digital Devices</strong>
                          <span style={{ fontSize: 11, color: '#64748b' }}>Seized items darj karein, analysis scope likhein aur Circle Incharge ko mark karein ya print karein.</span>
                        </div>
                        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                          {!isSupervisor && (
                            <button type="button" className="btn btn-outline btn-sm" onClick={() => addSeizeItem(i)}>
                              + Add Item
                            </button>
                          )}
                          <button
                            type="button"
                            className="btn btn-outline btn-sm"
                            style={{ color: '#015C94', borderColor: '#015C94', fontWeight: 600 }}
                            data-locked-ok="1"
                            onClick={() => printActivityForensicRequest(a)}
                            title="Print official Forensic Analysis Scope letter for these seized items"
                          >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ marginRight: 4 }}><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print Scope Letter
                          </button>
                          {!isSupervisor && (
                            <>
                              <button
                                type="button"
                                className="btn btn-outline btn-sm"
                                style={{ color: '#0f766e', borderColor: '#0f766e', fontSize: 12, fontWeight: 700 }}
                                disabled={sendingForensic}
                                data-locked-ok="1"
                                onClick={() => submitActivityToForensic(a, 'technical')}
                                title="Submit this activity's seized items to Technical Department"
                              >
                                {sendingForensic ? 'Submitting…' : '⚙️ Submit to Technical'}
                              </button>
                              <button
                                type="button"
                                className="btn btn-outline btn-sm"
                                style={{ color: '#64748b', borderColor: '#94a3b8', fontSize: 12, fontWeight: 600 }}
                                disabled={sendingForensic}
                                onClick={() => saveEnquiry({ navigateAway: false })}
                                title="Sirf save karein — Circle Incharge ko submit mat karein"
                              >
                                💾 Save
                              </button>
                              <button
                                type="button"
                                className="btn btn-primary btn-sm"
                                style={{ background: '#015C94', color: '#fff', fontSize: 12, fontWeight: 700 }}
                                disabled={sendingForensic}
                                data-locked-ok="1"
                                onClick={() => submitActivityToForensic(a, 'forensic')}
                                title="Is activity ka Scope Letter aur seized items Circle Incharge ko mark karein review ke liye"
                              >
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
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #dbeafe' }}>{SEIZE_ITEM_TYPES.find(o => o.value === it.item_type)?.name || it.item_type || '—'}</div>
                                : <select className="cf-input" value={it.item_type || ''} onChange={e => updateSeizeItem(i, si, 'item_type', e.target.value)}>
                                  <option value="">— Select —</option>
                                  {SEIZE_ITEM_TYPES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                                </select>
                              }
                            </div>
                            <div className="cf-field"><label className="cf-label required">Owner Type</label>
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #dbeafe', textTransform: 'capitalize' }}>{it.owner_type || '—'}</div>
                                : <select className="cf-input" value={it.owner_type || ''} onChange={e => { updateSeizeItem(i, si, 'owner_type', e.target.value); updateSeizeItem(i, si, 'owner_ref', ''); }}>
                                    <option value="">— Select —</option>
                                    <option value="complainant">Complainant</option>
                                    <option value="accused">Accused</option>
                                    <option value="witness">Witness</option>
                                    <option value="other">Other</option>
                                  </select>
                              }
                            </div>
                            <div className="cf-field"><label className="cf-label required">Owner / Person</label>
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #dbeafe' }}>
                                    {it.owner_type === 'accused' ? (form.accused[it.owner_ref]?.name || `Accused ${Number(it.owner_ref) + 1}`) :
                                     it.owner_type === 'witness' ? (form.witnesses[it.owner_ref]?.name || `Witness ${Number(it.owner_ref) + 1}`) :
                                     it.owner_type === 'complainant' ? 'Complainant' :
                                     it.owner_ref || '—'}
                                  </div>
                                : (
                                  it.owner_type === 'accused' ? (
                                    <select className="cf-input" value={it.owner_ref || ''} onChange={e => updateSeizeItem(i, si, 'owner_ref', e.target.value)}>
                                      <option value="">— Select Accused —</option>
                                      {form.accused.map((a, idx) => <option key={idx} value={idx}>{a.name || `Accused ${idx + 1}`}</option>)}
                                    </select>
                                  ) : it.owner_type === 'witness' ? (
                                    <select className="cf-input" value={it.owner_ref || ''} onChange={e => updateSeizeItem(i, si, 'owner_ref', e.target.value)}>
                                      <option value="">— Select Witness —</option>
                                      {form.witnesses.map((w, idx) => <option key={idx} value={idx}>{w.name || `Witness ${idx + 1}`}</option>)}
                                    </select>
                                  ) : (
                                    <input type="text" className="cf-input" placeholder={it.owner_type === 'complainant' ? 'Complainant (Auto)' : 'Name'} value={it.owner_ref || ''} onChange={e => updateSeizeItem(i, si, 'owner_ref', e.target.value)} disabled={it.owner_type === 'complainant'} />
                                  )
                                )
                              }
                            </div>
                            <div className="cf-field"><label className="cf-label">Make / Model</label>
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #dbeafe' }}>{it.make_model || '—'}</div>
                                : <input type="text" className="cf-input" placeholder="e.g. iPhone 15 Pro, Dell..." value={it.make_model || ''} onChange={e => updateSeizeItem(i, si, 'make_model', e.target.value)} />
                              }
                            </div>
                            <div className="cf-field"><label className="cf-label">IMEI / IMEI 2</label>
                              {itemLocked
                                ? (
                                  <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                    <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', fontFamily: 'monospace', border: '1px solid #dbeafe' }}>{it.imei || '—'}</div>
                                    <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', fontFamily: 'monospace', border: '1px solid #dbeafe' }}>{it.imei2 || '—'}</div>
                                  </div>
                                ) : (
                                  <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
                                    <input type="text" className="cf-input" placeholder="IMEI 1" value={it.imei || ''} onChange={e => updateSeizeItem(i, si, 'imei', e.target.value)} />
                                    <input type="text" className="cf-input" placeholder="IMEI 2" value={it.imei2 || ''} onChange={e => updateSeizeItem(i, si, 'imei2', e.target.value)} />
                                  </div>
                                )
                              }
                            </div>
                            <div className="cf-field"><label className="cf-label">Serial Number</label>
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', fontFamily: 'monospace', border: '1px solid #dbeafe' }}>{it.serial_no || '—'}</div>
                                : <input type="text" className="cf-input" placeholder="Device Serial No" value={it.serial_no || ''} onChange={e => updateSeizeItem(i, si, 'serial_no', e.target.value)} />
                              }
                            </div>
                            {!isSupervisor && !isSeizeItemLocked(it) && (
                              <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: 8, width: 36, height: 36, alignSelf: 'end' }} onClick={() => removeSeizeItem(i, si)} title="Delete Item">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                              </button>
                            )}
                          </div>

                          <div style={{ display: 'grid', gridTemplateColumns: '90px 140px 160px 1fr', gap: 10 }}>
                            <div className="cf-field"><label className="cf-label">Qty</label>
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #dbeafe' }}>{it.quantity ?? 1}</div>
                                : <input type="number" min={1} className="cf-input" value={it.quantity ?? 1} onChange={e => updateSeizeItem(i, si, 'quantity', e.target.value)} />
                              }
                            </div>
                            <div className="cf-field"><label className="cf-label">Capacity / Storage</label>
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #dbeafe' }}>{it.storage_capacity || '—'}</div>
                                : <input type="text" className="cf-input" placeholder="e.g. 256GB, 1TB" value={it.storage_capacity || ''} onChange={e => updateSeizeItem(i, si, 'storage_capacity', e.target.value)} />
                              }
                            </div>
                            {/* Condition is managed by AD Forensic Lab */}
                            <div className="cf-field"><label className="cf-label">Item Description / Seized From</label>
                              {itemLocked
                                ? <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #dbeafe' }}>{it.description || '—'}</div>
                                : <input type="text" className="cf-input" value={it.description || ''} onChange={e => updateSeizeItem(i, si, 'description', e.target.value)} placeholder="e.g. Seized from accused bedroom table, gold color..." />
                              }
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
                          {isSupervisor ? (
                            <div style={{ padding: '7px 10px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #bfdbfe' }}>
                              {a.case_category || 'Financial Fraud'}
                            </div>
                          ) : (
                            <select
                              className="cf-input"
                              value={a.case_category || 'Financial Fraud'}
                              onChange={e => updateActivity(i, 'case_category', e.target.value)}
                            >
                              {CASE_CATEGORIES.map(cat => <option key={cat.value} value={cat.value}>{cat.name}</option>)}
                            </select>
                          )}
                          <span className="cf-hint" style={{ fontSize: 11, color: '#64748b' }}>
                            Forensic Lab analysis category
                          </span>
                        </div>

                        <div className="cf-field">
                          <label className="cf-label"><strong>Analysis Scope (For Forensic Lab)</strong></label>
                          {isSupervisor ? (
                            <div style={{ padding: '10px 12px', background: '#f0f4f8', borderRadius: 6, fontSize: 13, color: '#0f172a', lineHeight: 1.6, border: '1px solid #bfdbfe', minHeight: 48, whiteSpace: 'pre-wrap' }}>
                              {a.analysis_scope || <span style={{ color: '#94a3b8', fontStyle: 'italic' }}>EO ne abhi Analysis Scope nahi likha.</span>}
                            </div>
                          ) : (
                            <textarea
                              className="cf-input"
                              rows={2}
                              placeholder="e.g. Conduct forensic examination and data extraction to identify Facebook IDs, communication chats, emails, media..."
                              value={a.analysis_scope || ''}
                              onChange={e => updateActivity(i, 'analysis_scope', e.target.value)}
                              style={{ width: '100%' }}
                            />
                          )}
                          <span className="cf-hint" style={{ fontSize: 11, color: '#64748b' }}>
                            {isSupervisor ? 'Yeh scope EO ne likha hai. Circle Incharge siraf parh sakta hai, edit nahi kar sakta.' : 'Ye scope text official Forensic Request PDF letter mein print hoga.'}
                          </span>
                        </div>
                      </div>

                      {/* Forensic Submission Mandatory Checklist */}
                      <div style={{ marginTop: 14, padding: '12px 14px', background: '#eff6ff', borderRadius: 8, border: '1.5px solid #bfdbfe' }}>
                        <div style={{ fontWeight: 800, fontSize: 13, color: '#1e40af', marginBottom: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
                          📋 Forensic Submission Mandatory Checklist (Compulsory):
                        </div>
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                          <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: isSupervisor ? 'default' : 'pointer' }}>
                            <input
                              type="checkbox"
                              checked={Boolean(a.checklist_tech_report)}
                              onChange={e => updateActivity(i, 'checklist_tech_report', e.target.checked)}
                              disabled={isSupervisor}
                            />
                            <span><strong>Technical Report</strong> verified &amp; attached <span style={{ color: '#dc2626' }}>*</span></span>
                          </label>
                          <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: isSupervisor ? 'default' : 'pointer' }}>
                            <input
                              type="checkbox"
                              checked={Boolean(a.checklist_seizure_memo)}
                              onChange={e => updateActivity(i, 'checklist_seizure_memo', e.target.checked)}
                              disabled={isSupervisor}
                            />
                            <span><strong>Seizure Memo</strong> verified &amp; generated <span style={{ color: '#dc2626' }}>*</span></span>
                          </label>
                        </div>
                      </div>

                      {/* Audio Forensics Specific Requirements & Islamabad Routing */}
                      {(String(a.case_category || '').toLowerCase().includes('audio') || String(a.case_category || '').toLowerCase().includes('voice')) && (
                        <div style={{ marginTop: 14, padding: '16px', background: '#fffbeb', borderRadius: 10, border: '2px solid #fcd34d', boxShadow: '0 2px 10px rgba(217,119,6,0.08)' }}>
                          <div style={{ display: 'flex', alignItems: 'center', gap: 10, color: '#b45309', fontWeight: 800, fontSize: 13.5, marginBottom: 8 }}>
                            <span style={{ fontSize: 18 }}>🏛️</span>
                            <span>OFFICIAL ROUTING: Circle Incharge Approval ➔ DD Forensic HQ (All Pakistan, Islamabad)</span>
                          </div>
                          <p style={{ fontSize: 12.5, color: '#92400e', marginBottom: 14, lineHeight: 1.5 }}>
                            All Audio &amp; Voice Biometric Forensic Examinations are exclusively conducted at <strong>NCCIA Forensic HQ, Islamabad</strong>. The questioned audio, 2 subject voice reference samples (30s each), written transcript and checklist are <strong>compulsory (lazmi)</strong>.
                          </p>

                          {/* 1. Questioned Audio File */}
                          <div className="cf-field" style={{ marginBottom: 14, background: '#fff', padding: 12, borderRadius: 8, border: '1px solid #fde68a' }}>
                            <label className="cf-label required"><strong>1. Questioned / Disputed Audio File (Jis ki forensic karwani hai):</strong></label>
                            {!isSupervisor && (
                              <input
                                type="file"
                                className="cf-input"
                                accept="audio/*,.wav,.mp3,.m4a,.aac,.ogg,.webm"
                                onChange={e => updateActivity(i, 'audio_source_file', e.target.files?.[0] || null)}
                              />
                            )}
                            <span style={{ fontSize: 11, color: '#64748b', display: 'block', marginTop: 4 }}>
                              Original disputed audio recording file, call recording or USB extraction file.
                            </span>
                          </div>

                          {/* 2. Two Reference Sample Audios (30 seconds each) */}
                          <div style={{ marginBottom: 14 }}>
                            <label className="cf-label required" style={{ fontSize: 13.5, fontWeight: 800, color: '#92400e', marginBottom: 8, display: 'block' }}>
                              2. Subject Voice Reference Samples (2 Samples · 30 Seconds Each · Live Mic Record / USB Upload):
                            </label>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 12 }}>
                              <AudioSampleRecorder
                                label="Subject Sample Audio 1 (30s)"
                                file={a.audio_sample_file_1 || a.audio_sample_file}
                                onChange={f => updateActivity(i, 'audio_sample_file_1', f)}
                                disabled={isSupervisor}
                                targetDuration={30}
                              />
                              <AudioSampleRecorder
                                label="Subject Sample Audio 2 (30s)"
                                file={a.audio_sample_file_2}
                                onChange={f => updateActivity(i, 'audio_sample_file_2', f)}
                                disabled={isSupervisor}
                                targetDuration={30}
                              />
                            </div>
                          </div>

                          {/* 3. Written Transcript / Context */}
                          <div className="cf-field" style={{ marginBottom: 14, background: '#fff', padding: 12, borderRadius: 8, border: '1px solid #fde68a' }}>
                            <label className="cf-label required"><strong>3. Audio Script / Written Transcript (Written Context of Audio):</strong></label>
                            {isSupervisor ? (
                              <div style={{ padding: '10px 12px', background: '#f8fafc', borderRadius: 6, fontSize: 13, color: '#0f172a', border: '1px solid #e2e8f0', whiteSpace: 'pre-wrap', minHeight: 60 }}>
                                {a.audio_script || '—'}
                              </div>
                            ) : (
                              <textarea
                                className="cf-input"
                                rows={3}
                                placeholder="Enter complete written transcript/dialogue of the audio context in Urdu or English..."
                                value={a.audio_script || ''}
                                onChange={e => updateActivity(i, 'audio_script', e.target.value)}
                                style={{ width: '100%', fontSize: 13 }}
                              />
                            )}
                            <span style={{ fontSize: 11, color: '#64748b', display: 'block', marginTop: 4 }}>
                              Word-by-word transcription/dialogue of what is said in the disputed audio.
                            </span>
                          </div>

                          {/* 4. Audio Forensics Mandatory Checkboxes (All Compulsory) */}
                          <div style={{ padding: '12px 14px', background: '#ffffff', borderRadius: 8, border: '1.5px solid #fcd34d' }}>
                            <div style={{ fontWeight: 800, fontSize: 13, color: '#92400e', marginBottom: 8, display: 'flex', alignItems: 'center', gap: 6 }}>
                              ☑️ Audio Forensic Compulsory Verification Checkboxes:
                            </div>
                            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))', gap: 10 }}>
                              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: isSupervisor ? 'default' : 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={Boolean(a.checklist_questioned_audio)}
                                  onChange={e => updateActivity(i, 'checklist_questioned_audio', e.target.checked)}
                                  disabled={isSupervisor}
                                />
                                <span>Questioned Audio Identified <span style={{ color: '#dc2626' }}>*</span></span>
                              </label>

                              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: isSupervisor ? 'default' : 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={Boolean(a.checklist_audio_samples)}
                                  onChange={e => updateActivity(i, 'checklist_audio_samples', e.target.checked)}
                                  disabled={isSupervisor}
                                />
                                <span>2 Sample Audios (30s) Recorded <span style={{ color: '#dc2626' }}>*</span></span>
                              </label>

                              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: isSupervisor ? 'default' : 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={Boolean(a.checklist_transcript)}
                                  onChange={e => updateActivity(i, 'checklist_transcript', e.target.checked)}
                                  disabled={isSupervisor}
                                />
                                <span>Written Transcript Included <span style={{ color: '#dc2626' }}>*</span></span>
                              </label>

                              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: isSupervisor ? 'default' : 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={Boolean(a.checklist_summons)}
                                  onChange={e => updateActivity(i, 'checklist_summons', e.target.checked)}
                                  disabled={isSupervisor}
                                />
                                <span>Summons Copy Attached <span style={{ color: '#dc2626' }}>*</span></span>
                              </label>

                              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: isSupervisor ? 'default' : 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={Boolean(a.checklist_fir_copy)}
                                  onChange={e => updateActivity(i, 'checklist_fir_copy', e.target.checked)}
                                  disabled={isSupervisor}
                                />
                                <span>FIR Copy Attached (if registered)</span>
                              </label>

                              <label style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, cursor: isSupervisor ? 'default' : 'pointer' }}>
                                <input
                                  type="checkbox"
                                  checked={Boolean(a.checklist_usb)}
                                  onChange={e => updateActivity(i, 'checklist_usb', e.target.checked)}
                                  disabled={isSupervisor}
                                />
                                <span>USB / Storage Media Handed Over <span style={{ color: '#dc2626' }}>*</span></span>
                              </label>
                            </div>
                          </div>
                        </div>
                      )}
                    </div>
                  )}

                  {a.type === 'diaries' && (
                    <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end', marginTop: 12 }}>
                      <button type="button" className="btn btn-outline btn-sm" data-locked-ok="1" onClick={() => printDiary(a)}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Print Diary
                      </button>
                    </div>
                  )}
                </div>
              ))}
              {form.activities.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No activities added yet. Click "Add Activity" to start.</p>}
            </div>
          </div>
        )}

        {/* REQUISITIONS TAB */}
        {activeTab === 'requisitions' && (
          <div className="cf-section">
            <div className="cf-section-header">
              <div className="cf-section-icon" style={{ background: '#0077b6' }}>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </div>
              <div><div className="cf-section-title">Requisitions</div><div className="cf-section-sub">Bank / Telecom / NADRA / Travel History requests</div></div>
            </div>
            <div className="cf-body">
              <button type="button" className="btn btn-outline btn-sm" onClick={addRequisition} style={{ marginBottom: 16 }}>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add Requisition
              </button>
              {form.requisitions.map((rq, i) => (
                <div key={rq.id || rq._uid || `rq-${i}`} style={{ padding: '16px', marginBottom: '16px', background: '#f8f8f8', borderRadius: '8px', border: '1px solid #e0e0e0' }}>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr auto', gap: '12px', marginBottom: '12px' }}>
                    <div className="cf-field"><label className="cf-label">Type</label>
                      <select className="cf-input" value={rq.type} onChange={e => updateRequisition(i, 'type', e.target.value)}>
                        <option value="">— Select —</option>
                        {REQUISITION_TYPES.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                      </select>
                    </div>
                    <div className="cf-field"><label className="cf-label">Email To</label><input type="email" className="cf-input" value={rq.email_to} onChange={e => updateRequisition(i, 'email_to', e.target.value)} /></div>
                    <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end' }} onClick={() => removeRequisition(i)}>
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    </button>
                  </div>
                  <div className="cf-field" style={{ marginBottom: 12 }}><label className="cf-label">Subject</label><input type="text" className="cf-input" value={rq.subject} onChange={e => updateRequisition(i, 'subject', e.target.value)} style={{ width: '100%' }} /></div>
                  <div className="cf-field" style={{ marginBottom: 12 }}><label className="cf-label">Body</label><textarea className="cf-input" rows={4} value={rq.body} onChange={e => updateRequisition(i, 'body', e.target.value)} style={{ width: '100%' }} /></div>
                  {(rq.status || rq.authorized_by_name) && (
                    <div style={{ fontSize: 12, color: '#555', marginBottom: 8 }}>
                      {rq.status && <span style={{ marginRight: 16 }}><strong>Status:</strong> {rq.status}</span>}
                      {rq.authorized_by_name && <span><strong>Authorized by:</strong> {rq.authorized_by_name}</span>}
                    </div>
                  )}
                </div>
              ))}
              {form.requisitions.length === 0 && <p style={{ textAlign: 'center', color: '#999', padding: '20px' }}>No requisitions added yet.</p>}
              {canAuthorizeRequisitions && form.requisitions.length > 0 && (
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 8 }}>
                  <button type="button" className="btn btn-primary btn-sm" disabled={saving} onClick={handleAuthorizeRequisitions} style={{ background: '#0077b6', color: '#fff', border: 'none', padding: '10px 18px', borderRadius: 8, fontWeight: 600 }}>
                    Request Authorization &amp; Send
                  </button>
                </div>
              )}
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
              <div><div className="cf-section-title">Legal Opinion Chain</div><div className="cf-section-sub">DD Legal ΓåÆ AD Legal ΓåÆ DG Legal</div></div>
              <div className="cf-section-badge">STEP 4</div>
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
                        {[...legalOfficers, ...circleIncharges].filter((o, idx, arr) => o?.id && arr.findIndex(x => Number(x.id) === Number(o.id)) === idx).map(o => <option key={o.id} value={o.id}>{o.name}{o.designation ? ` (${o.designation})` : ''}</option>)}
                      </select>
                    </div>
                    {canFillLegal && (
                      <button type="button" className="btn btn-sm" style={{ background: 'rgba(229,62,62,0.15)', color: '#e53e3e', border: 'none', borderRadius: '8px', width: '36px', height: '36px', alignSelf: 'end', justifySelf: 'end' }} onClick={() => removeLegalOpinion(i)}>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    )}
                  </div>
                  <div className="cf-field"><label className="cf-label">Opinion Text</label>
                    <textarea className="cf-input" rows={3} value={lo.opinion_text} onChange={e => updateLegalOpinion(i, 'opinion_text', e.target.value)} disabled={!canFillLegal} placeholder="Enter legal opinion..." style={{ width: '100%' }}></textarea>
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
              <div><div className="cf-section-title">Circle Incharge Approvals</div><div className="cf-section-sub">Approval chain for enquiry finalization</div></div>
              <div className="cf-section-badge">STEP 5</div>
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
                        {[...circleIncharges, ...legalOfficers].filter((o, idx, arr) => o?.id && arr.findIndex(x => Number(x.id) === Number(o.id)) === idx).map(o => <option key={o.id} value={o.id}>{o.name}{o.designation ? ' (' + o.designation + ')' : ''}</option>)}
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
              <div><div className="cf-section-title">CFR / SCFR Outcome</div><div className="cf-section-sub">Final enquiry report and recommendation</div></div>
            </div>
            <div className="cf-body">
              <div className="cf-row-3">
                {renderField('CFR Type', 'cfr_type', { options: CFR_TYPES })}
                {renderField('CFR Date', 'cfr_date', { type: 'date' })}
                {renderField('Recommendation', 'recommendation', { options: RECOMMENDATIONS, required: true })}
              </div>
              {(form.recommendation === 'closure' || isSupervisor) && renderField('Closure Reason', 'closure_reason', { options: CLOSURE_REASONS })}
              {renderField('Brief Allegation', 'brief_allegation', { rows: 3, placeholder: 'Brief details of the allegation...' })}
              {renderField('Charge Against', 'charge_against', { rows: 3, placeholder: 'Charges framed against accused...' })}
              {renderField('Oral Evidence', 'oral_evidence', { rows: 3, placeholder: 'Summary of oral evidence...' })}
              {renderField('Documentary Evidence', 'documentary_evidence', { rows: 3, placeholder: 'List of documentary evidence...' })}
              {renderField('Plea', 'plea', { rows: 2, placeholder: 'Plea of accused...' })}
              {renderField('Conclusion of Enquiry Officer with convincing reasons against alleged', 'conclusion', { rows: 3, placeholder: 'Investigation conclusion...' })}
              {renderField('CFR Summary', 'cfr_summary', {
                rows: 4,
                required: true,
                placeholder: 'Final enquiry findings / CFR summary for Circle Incharge review',
              })}
              <div className="cf-field" style={{ marginBottom: 16 }}>
                <label className="cf-label">CFR Remarks</label>
                {canEditCfrRemarks ? (
                  <textarea className="cf-input" rows={3} value={form.cfr_remarks || ''} onChange={setF('cfr_remarks')} placeholder="Additional Director / Admin remarks..." style={{ width: '100%' }} />
                ) : (
                  <div style={{ padding: '9px 14px', background: '#f5f5f5', color: '#555', borderRadius: 'var(--border-radius-sm)', fontSize: 13, border: '1.5px solid var(--border)', minHeight: 60 }}>
                    {form.cfr_remarks || '—'}
                  </div>
                )}
              </div>
              {isSupervisor && (
                <>
                  <div className="cf-row-2">
                    {renderField('Transfer Department', 'transfer_department')}
                    {renderField('Transfer Circle', 'transfer_circle', { options: circles.map(c => ({ value: c.name, name: c.name })) })}
                  </div>
                  {renderField('Merge Complaint ID', 'merge_complaint_id', { placeholder: 'Complaint ID to merge with' })}
                </>
              )}
            </div>
          </div>
        )}

        {showRegisterCase && (
          <div style={{ marginTop: 16, padding: '14px 16px', background: '#ecfdf5', border: '1px solid #6ee7b7', borderRadius: 8 }}>
            <div style={{ fontSize: 14, fontWeight: 700, color: '#065f46', marginBottom: 8 }}>Case / FIR Registration</div>
            <p style={{ fontSize: 13, color: '#047857', marginBottom: 12 }}>
              Enquiry complete ho chuki hai. Neeche se case register karein — FIR number auto generate hoga.
            </p>
            <div className="cf-row-2" style={{ marginBottom: 12 }}>
              <div className="cf-field">
                <label className="cf-label">Investigation Officer (optional)</label>
                <select className="cf-input" value={registerIoId} onChange={e => setRegisterIoId(e.target.value)}>
                  <option value="">Select IO...</option>
                  {ioOfficers.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
                </select>
              </div>
              <div className="cf-field">
                <label className="cf-label">Remarks</label>
                <input type="text" className="cf-input" value={registerRemarks} onChange={e => setRegisterRemarks(e.target.value)} placeholder="Optional remarks" />
              </div>
            </div>
          </div>
        )}

        {caseFileId && (
          <div style={{ marginTop: 16, padding: '12px 14px', background: '#f0fff4', border: '1px solid #9ae6b4', borderRadius: 8, fontSize: 13, color: '#276749' }}>
            Case already registered. <Link to={`/cases/${caseFileId}/edit`} style={{ fontWeight: 700, color: '#015C94' }}>View Case →</Link>
          </div>
        )}

        </div>

        {canSubmitCfr && (
          <div style={{ marginTop: 16, padding: '12px 14px', background: '#eff6ff', border: '1px solid #bfdbfe', borderRadius: 8, fontSize: 13, color: '#1e3a5f' }}>
            {isSupervisor 
              ? "Outcome tab mein Recommendation + CFR Summary complete karke Submit CFR dabayein — Circle Incharge ko review ke liye chala jayega."
              : "Outcome tab mein CFR Summary complete karke Submit CFR dabayein — Circle Incharge ko review ke liye chala jayega."}
          </div>
        )}

        {isEoLocked && (
          <div style={{ marginTop: 16, padding: '12px 16px', background: '#fef2f2', border: '1.5px solid #fecaca', borderRadius: 8, fontSize: 13, color: '#991b1b', fontWeight: 600 }}>
            🔒 Yeh Enquiry Circle Incharge ko submit ho chuki hai. Enquiry Officer isay edit nahi kar sakta (Read-Only). Circle Incharge Send Back kare to phir edit khul jayegi.
          </div>
        )}

        <div style={{ display: 'flex', gap: 12, justifyContent: 'flex-end', marginTop: 20, flexWrap: 'wrap' }}>
          <Link to="/enquiries" className="btn btn-outline">Back to Enquiries</Link>
          {!isEoLocked && (
            <button
              type="submit"
              className="btn btn-primary"
              disabled={saving || submittingCfr || registerSaving || approvingCfr}
              style={{ background: '#64748b', color: '#fff', padding: '12px 24px', fontWeight: 600, fontSize: '14px', borderRadius: '8px', border: 'none' }}
            >
              {saving ? 'Saving...' : (id ? 'Update Enquiry' : 'Register Enquiry')}
            </button>
          )}
          {canSubmitCfr && (
            <button
              type="button"
              className="btn btn-primary"
              disabled={saving || submittingCfr || registerSaving}
              onClick={handleSubmitCfr}
              style={{ background: '#015C94', color: '#fff', padding: '12px 24px', fontWeight: 700, fontSize: '14px', borderRadius: '8px', border: 'none', boxShadow: '0 2px 8px rgba(1,92,148,0.35)' }}
            >
              {submittingCfr ? 'Submitting...' : 'Submit CFR'}
            </button>
          )}
          {isSupervisor && form.status === 'cfr_submitted' && (
            <>
              <button
                type="button"
                className="btn btn-primary"
                disabled={saving || approvingCfr}
                onClick={() => handleApproveCfr('agree')}
                style={{ background: '#059669', color: '#fff', padding: '12px 24px', fontWeight: 700, fontSize: '14px', borderRadius: '8px', border: 'none', boxShadow: '0 2px 8px rgba(5,150,105,0.35)' }}
                title="Approve CFR and finalize outcome"
              >
                {approvingCfr ? 'Processing…' : '📋 Approve CFR'}
              </button>
              <button
                type="button"
                className="btn btn-outline"
                disabled={saving || approvingCfr}
                onClick={() => handleApproveCfr('disagree')}
                style={{ color: '#d97706', borderColor: '#d97706', padding: '12px 24px', fontWeight: 700, fontSize: '14px', borderRadius: '8px' }}
                title="Send back CFR to Enquiry Officer with remarks"
              >
                ↩️ Send Back to EO
              </button>
            </>
          )}
          {id && activeTab === 'outcome' && (
            <button
              type="button"
              className="btn btn-outline"
              onClick={() => printDocument('cfr-print')}
              style={{ padding: '12px 24px', fontWeight: 700, fontSize: '14px', borderRadius: '8px' }}
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{ marginRight: 8, verticalAlign: 'text-bottom' }}>
                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
              </svg>
              Print CFR
            </button>
          )}
          {showRegisterCase && (
            <button
              type="button"
              className="btn btn-primary"
              disabled={saving || submittingCfr || registerSaving || approvingCfr}
              onClick={handleRegisterCase}
              style={{ background: '#059669', color: '#fff', padding: '12px 24px', fontWeight: 700, fontSize: '14px', borderRadius: '8px', border: 'none', boxShadow: '0 2px 8px rgba(5,150,105,0.35)' }}
            >
              {registerSaving ? 'Registering...' : 'Register Case / FIR'}
            </button>
          )}
        </div>
      </form>

      {/* ACCOUNT OPENING / UNFREEZE PROFORMA MODAL */}
      {showAccountOpeningModal && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(15,23,42,0.6)', backdropFilter: 'blur(3px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 99999, padding: 16,
        }}>
          <div style={{
            background: '#fff', borderRadius: 14, maxWidth: 640, width: '100%', maxHeight: '90vh',
            display: 'flex', flexDirection: 'column', boxShadow: '0 20px 40px rgba(0,0,0,0.25)',
          }}>
            <div style={{ padding: '16px 20px', borderBottom: '1px solid #e2e8f0', display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc', borderTopLeftRadius: 14, borderTopRightRadius: 14 }}>
              <h3 style={{ margin: 0, fontSize: 16, fontWeight: 800, color: '#0f172a' }}>
                📄 Account Opening / Unfreeze Request Proforma
              </h3>
              <button type="button" onClick={() => setShowAccountOpeningModal(false)} style={{ background: 'none', border: 'none', fontSize: 20, cursor: 'pointer', color: '#64748b' }}>×</button>
            </div>

            <div style={{ padding: 20, overflowY: 'auto', flex: 1, display: 'flex', flexDirection: 'column', gap: 12 }}>
              <p style={{ fontSize: 12, color: '#64748b', margin: 0 }}>
                Fill in the details below to generate the official printable proforma, or leave fields blank to print a manual handwriting copy.
              </p>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <div className="cf-field">
                  <label className="cf-label">Total Fraud Involved</label>
                  <input type="text" className="cf-input" placeholder="e.g. PKR 500,000" value={accountOpeningData.fraud_amount} onChange={e => setAccountOpeningData({ ...accountOpeningData, fraud_amount: e.target.value })} />
                </div>
                <div className="cf-field">
                  <label className="cf-label">Fraud Type</label>
                  <input type="text" className="cf-input" placeholder="e.g. Online Investment / Impersonation" value={accountOpeningData.fraud_type} onChange={e => setAccountOpeningData({ ...accountOpeningData, fraud_type: e.target.value })} />
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <div className="cf-field">
                  <label className="cf-label">Account No.</label>
                  <input type="text" className="cf-input" placeholder="e.g. 01234567890123" value={accountOpeningData.account_no} onChange={e => setAccountOpeningData({ ...accountOpeningData, account_no: e.target.value })} />
                </div>
                <div className="cf-field">
                  <label className="cf-label">Or CNIC</label>
                  <input type="text" className="cf-input" placeholder="e.g. 35201-1234567-1" value={accountOpeningData.cnic} onChange={e => setAccountOpeningData({ ...accountOpeningData, cnic: e.target.value })} />
                </div>
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <div className="cf-field">
                  <label className="cf-label">Account Title</label>
                  <input type="text" className="cf-input" placeholder="Account Holder Name" value={accountOpeningData.account_title} onChange={e => setAccountOpeningData({ ...accountOpeningData, account_title: e.target.value })} />
                </div>
                <div className="cf-field">
                  <label className="cf-label">Bank Name</label>
                  <input type="text" className="cf-input" placeholder="e.g. Meezan Bank / HBL" value={accountOpeningData.bank_name} onChange={e => setAccountOpeningData({ ...accountOpeningData, bank_name: e.target.value })} />
                </div>
              </div>

              <div className="cf-field">
                <label className="cf-label">Reason of Removal of Debit Block</label>
                <textarea className="cf-input" rows={2} placeholder="Reason for unfreezing/unblocking the account..." value={accountOpeningData.reason} onChange={e => setAccountOpeningData({ ...accountOpeningData, reason: e.target.value })} />
              </div>

              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <div className="cf-field">
                  <label className="cf-label">Amount Recovered / Returned</label>
                  <input type="text" className="cf-input" placeholder="e.g. PKR 500,000" value={accountOpeningData.recovered_amount} onChange={e => setAccountOpeningData({ ...accountOpeningData, recovered_amount: e.target.value })} />
                </div>
                <div className="cf-field">
                  <label className="cf-label">Mode of Recovery</label>
                  <input type="text" className="cf-input" placeholder="e.g. Pay Order / IBFT / Cash" value={accountOpeningData.mode_of_recovery} onChange={e => setAccountOpeningData({ ...accountOpeningData, mode_of_recovery: e.target.value })} />
                </div>
              </div>
            </div>

            <div style={{ padding: '14px 20px', borderTop: '1px solid #e2e8f0', display: 'flex', justifyContent: 'flex-end', gap: 10, background: '#f8fafc', borderBottomLeftRadius: 14, borderBottomRightRadius: 14 }}>
              <button type="button" className="btn btn-outline" onClick={() => setShowAccountOpeningModal(false)}>
                Cancel
              </button>
              <button type="button" className="btn btn-primary" onClick={() => printAccountOpeningProforma(accountOpeningData)} style={{ background: '#0f766e', borderColor: '#0f766e' }}>
                🖨️ Generate &amp; Print Proforma
              </button>
            </div>
          </div>
        </div>
      )}

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


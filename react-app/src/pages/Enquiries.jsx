import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../api';
import { useAuth } from '../contexts/AuthContext';
import ConfirmModal from '../components/ConfirmModal';
import LoadingSkeleton from '../components/LoadingSkeleton';
import SearchableSelect from '../components/SearchableSelect';
import WorkflowProgress, { enquiryProgress } from '../components/WorkflowProgress';
import CaseChatModal from '../components/CaseChatModal';
import { canRegisterCaseFromEnquiry, enquiryReadyForCaseRegistration, canCreateEnquiry, hasRole as userHasRole, hasAnyRole } from '../utils/permissions';
import { useAutoRefresh } from '../utils/useAutoRefresh';
import { preparePrintWindow, writePrintWindow, closePrintWindow } from '../utils/print';
import { generateOfficialDocumentQr } from '../utils/qrcode';
import { SIMPLE_STATUSES, simpleStatusLabel, toSimpleStatus, isSubmittedToCircle } from '../utils/simpleStatus';

const STATUS_COLORS = {
  pending: 'badge-pending',
  registered: 'badge-pending',
  assigned: 'badge-pending',
  working: 'badge-info',
  in_progress: 'badge-info',
  cfr_submitted: 'badge-info',
  complete: 'badge-finalized',
  approved: 'badge-finalized',
  closed: 'badge-finalized',
  transferred: 'badge-warning',
  converted_to_case: 'badge-finalized',
  referred_court: 'badge-urgent',
};

const STATUS_FILTERS = {
  pending: 'registered,assigned,pending',
  working: 'in_progress,working,cfr_submitted,legal_review_dd,legal_review_ad,legal_review_dg,referred_court',
  complete: 'complete,approved,closed,transferred,converted_to_case',
};

export default function Enquiries() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({ total: 0, pending: 0, progress: 0, approved: 0 });
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [deleteTarget, setDeleteTarget] = useState(null);

  // Assign modal
  const [assignTarget, setAssignTarget] = useState(null);
  const [assignOfficerId, setAssignOfficerId] = useState('');
  const [officers, setOfficers] = useState([]);
  const [assignSaving, setAssignSaving] = useState(false);

  // CFR modal
  const [cfrTarget, setCfrTarget] = useState(null);
  const [cfrForm, setCfrForm] = useState({ cfr_summary: '', recommendation: '' });
  const [cfrSaving, setCfrSaving] = useState(false);

  // Approve modal
  const [approveTarget, setApproveTarget] = useState(null);
  const [approveForm, setApproveForm] = useState({ decision: 'agree', recommendation: '', closure_reason: '', merge_complaint_id: '', transfer_department: '', transfer_circle: '', remarks: '' });
  const [circles, setCircles] = useState([]);
  const [approveSaving, setApproveSaving] = useState(false);

  // Change Officer modal
  const [changeTarget, setChangeTarget] = useState(null);
  const [changeOfficerId, setChangeOfficerId] = useState('');
  const [changeSaving, setChangeSaving] = useState(false);

  // Case Chat Modal
  const [chatTarget, setChatTarget] = useState(null);

  // Register Case modal
  const [registerTarget, setRegisterTarget] = useState(null);
  const [registerForm, setRegisterForm] = useState({ investigation_officer_id: '', remarks: '' });
  const [ioOfficers, setIoOfficers] = useState([]);
  const [registerSaving, setRegisterSaving] = useState(false);

  const hasRole = (roleName) => userHasRole(user, roleName);
  const canRegisterCase = canRegisterCaseFromEnquiry(user);

  const fetchData = useCallback((p = page) => {
    setLoading(true);
    const params = { page: p };
    if (search) params.search = search;
    if (statusFilter) params.status = STATUS_FILTERS[statusFilter] || statusFilter;
    api.get('/enquiries', { params }).then(r => {
      const d = r.data;
      if (Array.isArray(d)) {
        setList(d);
      } else if (Array.isArray(d?.data)) {
        setList(d.data);
        if (d.last_page) setLastPage(d.last_page);
        if (d.current_page) setPage(d.current_page);
      } else if (Array.isArray(d?.data?.data)) {
        setList(d.data.data);
        if (d.data.last_page) setLastPage(d.data.last_page);
        if (d.data.current_page) setPage(d.data.current_page);
      } else {
        setList([]);
      }
    }).catch(err => {
      console.error('Failed to fetch enquiries:', err);
      setList([]);
    }).finally(() => setLoading(false));
    api.get('/enquiries/stats').then(r => {
      if (r.data) setStats(r.data);
    }).catch(() => {});
  }, [page, search, statusFilter]);

  useEffect(() => { fetchData(); }, [fetchData]);
  useAutoRefresh(() => fetchData(page), [page, search, statusFilter], 30000);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    await api.delete(`/enquiries/${deleteTarget.id}`);
    setList(list.filter(e => e.id !== deleteTarget.id));
    setDeleteTarget(null);
  };

  const loadOfficers = () => {
    api.get('/lookup/enquiry-officers').then(r => {
      const all = r.data.data || r.data;
      setOfficers(Array.isArray(all) ? all : []);
    }).catch(() => {});
  };

  // ── Assign ──
  const openAssign = (e) => {
    loadOfficers();
    setAssignTarget(e);
    setAssignOfficerId('');
  };
  const handleAssign = async () => {
    if (!assignTarget || !assignOfficerId) return;
    setAssignSaving(true);
    try {
      await api.post(`/enquiries/${assignTarget.id}/assign`, { enquiry_officer_id: assignOfficerId });
      fetchData();
      setAssignTarget(null);
    } catch (e) { alert(e.response?.data?.message || 'Assignment failed'); }
    finally { setAssignSaving(false); }
  };

  // ── Submit CFR ──
  const openCfr = (e) => {
    setCfrTarget(e);
    setCfrForm({ cfr_summary: '', recommendation: '' });
  };
  const handleCfr = async () => {
    if (!cfrTarget) return;
    setCfrSaving(true);
    try {
      await api.post(`/enquiries/${cfrTarget.id}/submit-cfr`, cfrForm);
      fetchData();
      setCfrTarget(null);
    } catch (e) { alert(e.response?.data?.message || 'CFR submission failed'); }
    finally { setCfrSaving(false); }
  };

  // ── Approve/Review ──
  const openApprove = (e) => {
    api.get('/lookup/circles').then(r => setCircles(r.data || [])).catch(() => {});
    setApproveTarget(e);
    setApproveForm({ decision: 'agree', recommendation: e.recommendation || '', closure_reason: '', merge_complaint_id: '', transfer_department: '', transfer_circle: '', remarks: '' });
  };
  const handleApprove = async () => {
    if (!approveTarget) return;
    setApproveSaving(true);
    try {
      await api.post(`/enquiries/${approveTarget.id}/approve`, approveForm);
      fetchData();
      setApproveTarget(null);
    } catch (e) { alert(e.response?.data?.message || 'Approval failed'); }
    finally { setApproveSaving(false); }
  };

  // ── Change Officer ──
  const openChange = (e) => {
    loadOfficers();
    setChangeTarget(e);
    setChangeOfficerId('');
  };
  const handleChange = async () => {
    if (!changeTarget || !changeOfficerId) return;
    setChangeSaving(true);
    try {
      await api.post(`/enquiries/${changeTarget.id}/change-officer`, { enquiry_officer_id: changeOfficerId });
      fetchData();
      setChangeTarget(null);
    } catch (e) { alert(e.response?.data?.message || 'Change failed'); }
    finally { setChangeSaving(false); }
  };

  // ── Register Case ──
  const openRegisterCase = (e) => {
    api.get('/lookup/investigation-officers').then(r => {
      const all = r.data.data || r.data;
      setIoOfficers(Array.isArray(all) ? all : []);
    }).catch(() => {});
    setRegisterTarget(e);
    setRegisterForm({ investigation_officer_id: '', remarks: '' });
  };

  const handleRegisterCase = async () => {
    if (!registerTarget) return;
    setRegisterSaving(true);
    try {
      const res = await api.post(`/enquiries/${registerTarget.id}/register-case`, {
        investigation_officer_id: registerForm.investigation_officer_id || undefined,
        remarks: registerForm.remarks || undefined,
      });
      const caseFile = res.data?.data?.case_file;
      fetchData();
      setRegisterTarget(null);
      if (caseFile?.id) {
        navigate(`/cases/${caseFile.id}/edit`);
      } else {
        alert(res.data?.message || 'Case registered successfully');
      }
    } catch (e) {
      alert(e.response?.data?.message || 'Case registration failed');
    } finally {
      setRegisterSaving(false);
    }
  };

  // ── Scope Letter Review & Approval Modal ──
  const [scopeLetterTarget, setScopeLetterTarget] = useState(null);
  const [scopeLetterLoading, setScopeLetterLoading] = useState(false);
  const [scopeLetterData, setScopeLetterData] = useState({
    enquiry: null,
    items: [],
    analysisScope: '',
    linkedRequests: [],
  });
  const [scopeLetterActionSaving, setScopeLetterActionSaving] = useState(false);

  const openScopeLetterModal = async (enquiry) => {
    setScopeLetterTarget(enquiry);
    setScopeLetterLoading(true);
    try {
      const [enqRes, reqRes] = await Promise.all([
        api.get(`/enquiries/${enquiry.id}`),
        api.get(`/forensic-requests?enquiry_id=${enquiry.id}`).catch(() => ({ data: { data: [] } }))
      ]);
      const fullEnq = enqRes.data?.data || enqRes.data;
      const requests = reqRes.data?.data || reqRes.data || [];

      const activities = fullEnq.activities || [];
      const seizureActs = activities.filter(a => a.type === 'seizures' || a.type === 'search_seize');
      let items = seizureActs.flatMap(a => (a.seize_items || []));

      if (items.length === 0 && requests.length > 0) {
        items = requests.flatMap(r => r.items || []);
      }

      const scope = seizureActs.map(a => a.meta?.analysis_scope || a.analysis_scope).filter(Boolean).join('\n\n') ||
        requests.map(r => r.analysis_scope).filter(Boolean).join('\n\n') || '';

      const hasEoSubmitted = items.length > 0 && !!scope.trim();

      setScopeLetterData({
        enquiry: fullEnq,
        items,
        analysisScope: scope,
        linkedRequests: requests,
        hasEoSubmitted,
      });
    } catch (err) {
      alert('Failed to load scope letter data: ' + (err.response?.data?.message || err.message));
    } finally {
      setScopeLetterLoading(false);
    }
  };

  const handlePrintScopeLetterFromModal = async () => {
    if (!scopeLetterTarget) return;
    const win = preparePrintWindow();
    if (!win) return;
    try {
    const enq = scopeLetterData.enquiry || scopeLetterTarget;
    const enqNo = enq.enquiry_number || enq.complaint?.tracking_no || `ENQ-${enq.id}`;
    const enqRegDate = enq.reg_date ? new Date(enq.reg_date).toLocaleDateString('en-GB') : (enq.created_at ? new Date(enq.created_at).toLocaleDateString('en-GB') : '');
    const enqNoDisplay = enqRegDate ? `${enqNo} dated ${enqRegDate}` : enqNo;
    const compName = enq.complaint?.complainant_name || enq.direct_info?.complainant_name || 'Complainant';
    const compCnic = enq.complaint?.cnic || enq.direct_info?.cnic || '—';
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-GB') + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    const officerName = enq.officer?.name || user?.name || 'Enquiry Officer';
    const officerDesig = enq.officer?.designation || user?.designation || 'Enquiry Officer';
    const officerPhone = enq.officer?.phone || enq.officer?.contact_no || user?.phone || user?.contact_no || '';

    const accusedNames = (enq.accused_persons || enq.accusedPersons || []).map(a => a.name).filter(Boolean);
    const items = Array.isArray(scopeLetterData.items) ? scopeLetterData.items : [];

    const itemRows = (items.length ? items : [{ item_type: 'Device', make_model: 'Seized Device', quantity: 1 }]).map((it, idx) => {
      let imeiStr = [];
      const hasImei1 = Boolean(it.imei && it.imei.trim());
      const hasImei2 = Boolean(it.imei2 && it.imei2.trim());
      if (hasImei1 && hasImei2) {
        imeiStr.push('IMEI 1: ' + it.imei.trim());
        imeiStr.push('IMEI 2: ' + it.imei2.trim());
      } else if (hasImei1 || hasImei2) {
        imeiStr.push('IMEI: ' + (it.imei || it.imei2).trim());
      }
      if (it.serial_no && it.serial_no.trim()) {
        imeiStr.push('SN: ' + it.serial_no.trim());
      }
      let imeiFinal = imeiStr.length > 0 ? imeiStr.join('<br/>') : '—';
      
      const seizedFromPerson = it.seized_from
        || (accusedNames[idx] ? `Accused: ${accusedNames[idx]}` : (accusedNames.length > 0 ? `Accused: ${accusedNames[0]}` : (compName ? `Case: ${compName}` : '—')));

      return `
      <tr>
        <td style="border:1px solid #000;padding:6px;text-align:center;">${idx + 1}</td>
        <td style="border:1px solid #000;padding:6px;font-weight:bold;color:#0f172a;">${seizedFromPerson}</td>
        <td style="border:1px solid #000;padding:6px;"><strong>${it.item_type || 'Device'}</strong></td>
        <td style="border:1px solid #000;padding:6px;">${it.make_model || '—'}</td>
        <td style="border:1px solid #000;padding:6px;font-family:monospace;">${imeiFinal}</td>
        <td style="border:1px solid #000;padding:6px;">${it.storage_capacity ? it.storage_capacity + ' GB' : '—'}</td>
        <td style="border:1px solid #000;padding:6px;">${it.condition || 'Sealed'}</td>
        <td style="border:1px solid #000;padding:6px;">${it.description || '—'}</td>
      </tr>
      `;
    }).join('');

    const rawCircle = enq.complaint?.circle?.city || enq.complaint?.circle?.name || enq.direct_info?.circle_name || 'Lahore';
    const cleanCity = rawCircle.replace(/circle|zone|nccia-rc|nccia|-/gi, '').trim().toUpperCase() || 'LAHORE';
    const rcName = `NCCIA-RC ${cleanCity}`;
    const zoneName = `NCCIA - ZONE ${cleanCity}`;
    const linkedReq = (scopeLetterData.linkedRequests || [])[0] || null;
    const ciRemarks = (scopeLetterData.ciRemarks || linkedReq?.note || 'Approved & Forwarded for Forensic Examination.').trim();
    const ddRemarks = (linkedReq?.examiner_assignment_notes || linkedReq?.forensic_remarks || 'Marked to AD (Forensics) / Forensic Examiner for examination and detailed forensic report.').trim();
    const ciDateStr = linkedReq?.created_at ? new Date(linkedReq.created_at).toLocaleDateString('en-GB') : dateStr;
    const ddDateStr = linkedReq?.assigned_to_examiner_at
      ? new Date(linkedReq.assigned_to_examiner_at).toLocaleDateString('en-GB')
      : dateStr;
    const qr = await generateOfficialDocumentQr({
      type: 'scope',
      id: enq.id,
      size: 60,
      fallback: {
        type: 'Forensic Scope Letter',
        number: enqNo,
        complainant: compName,
        officer: officerName,
        circle: rawCircle,
        date: dateStr,
      },
    });
    const qrSvg = qr.svg;
    const qrCaption = qr.caption || enqNo;

    const html = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8"/>
        <title>Forensic Scope Letter - ${enqNo}</title>
        <style>
          @page { size: A4 portrait; margin: 12mm 14mm; }
          body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; padding: 0; line-height: 1.4; font-size: 12.5px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          .meta-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 12.5px; }
          .to-sec { margin-bottom: 10px; font-size: 12.5px; }
          .subj { font-weight: 800; text-transform: uppercase; border-bottom: 1.5px solid #000; padding-bottom: 3px; margin: 10px 0; font-size: 12px; }
          table { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 11.5px; }
          th { border: 1px solid #000; background: #f1f5f9; padding: 5px 6px; text-align: left; }
          td { border: 1px solid #000; padding: 5px 6px; }
          .scope-box { background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 10px; border-radius: 4px; margin: 6px 0; font-size: 12px; }
          .sign-block { margin-top: 25px; margin-left: auto; width: 280px; text-align: right; font-size: 12px; line-height: 1.35; }
          .endorsement-block { margin-top: 20px; border-top: 1.5px dashed #000; padding-top: 12px; page-break-inside: avoid; font-size: 12px; }
          .endorsement-head { display: flex; justify-content: space-between; margin-bottom: 6px; }
        </style>
      </head>
      <body>
        <table style="width:100%; border-collapse:collapse; border-bottom:2px solid #000; padding-bottom:6px; margin-bottom:12px;">
          <tr>
            <td style="width:80px; vertical-align:middle; text-align:left; border:none; padding:0 8px 6px 0;">
              <img src="/images/images.jpg" alt="NCCIA" style="width:52px; height:52px; object-fit:contain;" />
              <div style="margin-top:4px;">${qrSvg}</div>
              <div style="font-family:monospace; font-size:8px; font-weight:bold; margin-top:2px; text-align:center;">${qrCaption}</div>
            </td>
            <td style="vertical-align:middle; text-align:center; border:none; padding:0 8px 6px 8px;">
              <div style="font-size:22px; font-weight:800; letter-spacing:4px; line-height:1.1;">NCCIA</div>
              <div style="font-size:11px; font-weight:700; text-transform:uppercase; margin-top:2px;">National Cyber Crime Investigation Agency</div>
              <div style="font-size:10px; font-weight:600; margin-top:1px;">Ministry of Interior &bull; Government of Pakistan</div>
              <div style="font-size:11.5px; font-weight:600; margin-top:3px;">Cyber Crime Reporting Center &middot; Forensic Lab Examination Request</div>
            </td>
            <td style="width:80px; vertical-align:middle; text-align:right; border:none; padding:0 0 6px 8px;">
              <img src="/images/pak-govt-logo.png" alt="Ministry of Interior" style="width:52px; height:52px; object-fit:contain;" />
            </td>
          </tr>
        </table>
        <div class="meta-row">
          <div><strong>Enquiry / Case No:</strong> ${enqNoDisplay}</div>
          <div><strong>Dated:</strong> ${dateStr}</div>
        </div>
        <div class="to-sec">
          To,<br/>
          <strong>The Assistant Director / Deputy Director (Forensics),</strong><br/>
          Forensic Lab, NCCIA HQ / Regional Center.
        </div>
        <div class="subj">SUBJECT: REQUEST FOR FORENSIC EXAMINATION & ANALYSIS OF SEIZED EVIDENCE / DEVICES IN ENQ NO. ${enqNoDisplay}</div>
        <p style="margin: 6px 0;">
          With reference to Enquiry No. <strong>${enqNoDisplay}</strong> regarding complainant <strong>${compName}</strong> (CNIC: ${compCnic}), the following seized evidentiary media/devices have been submitted for technical & forensic analysis:
        </p>
        <table>
          <thead>
            <tr>
              <th style="width:30px;text-align:center;">#</th>
              <th>Recovered / Seized From</th>
              <th>Item Type</th>
              <th>Make / Model</th>
              <th>IMEI / Serial No.</th>
              <th>Storage</th>
              <th>Condition</th>
              <th>Description / Remarks</th>
            </tr>
          </thead>
          <tbody>
            ${itemRows}
          </tbody>
        </table>
        <div class="subj" style="border:none;margin-top:10px;margin-bottom:4px;">SCOPE OF ANALYSIS:</div>
        <div class="scope-box">
          ${(scopeLetterData.analysisScope || '').replace(/\\n/g, '<br/>')}
        </div>
        <p style="margin: 8px 0;">
          It is requested that the evidentiary media may kindly be examined in the forensic lab and official Forensic Report (Chain of Custody) be prepared and furnished at the earliest.
        </p>
        <div class="sign-block">
          <br/>
          <strong>${officerName}</strong><br/>
          ${officerDesig}<br/>
          ${officerPhone ? `<span>Contact: <strong>${officerPhone}</strong></span><br/>` : ''}
          National Cyber Crime Investigation Agency<br/>
          ${rcName}
        </div>

        <!-- ── 1. Endorsement: Circle Incharge to DD Forensic ── -->
        <div class="endorsement-block">
          <div class="endorsement-head">
            <div>
              <strong>To,</strong><br/>
              <strong>Deputy Director (Forensics),</strong><br/>
              National Cyber Crime Investigation Agency<br/>
              ${zoneName}
            </div>
            <div style="text-align:right;">
              <strong>Dated:</strong> ${ciDateStr}
            </div>
          </div>
          <div style="margin: 8px 0;">
            <strong>Remarks / Order:</strong>
            <div style="margin-top: 3px; padding: 4px 8px; border-bottom: 1px dotted #555; min-height: 20px; font-style: italic;">
              ${ciRemarks}
            </div>
          </div>
          <div class="sign-block" style="margin-top: 18px;">
            <br/>
            <strong>Circle Incharge</strong><br/>
            National Cyber Crime Investigation Agency<br/>
            ${rcName}
          </div>
        </div>

        <!-- ── 2. Endorsement: DD Forensic to AD Forensic ── -->
        <div class="endorsement-block">
          <div class="endorsement-head">
            <div>
              <strong>To,</strong><br/>
              <strong>Assistant Director (Forensics),</strong><br/>
              National Cyber Crime Investigation Agency<br/>
              ${zoneName}
            </div>
            <div style="text-align:right;">
              <strong>Dated:</strong> ${ddDateStr}
            </div>
          </div>
          <div style="margin: 8px 0;">
            <strong>Remarks / Order:</strong>
            <div style="margin-top: 3px; padding: 4px 8px; border-bottom: 1px dotted #555; min-height: 20px; font-style: italic;">
              ${ddRemarks}
            </div>
          </div>
          <div class="sign-block" style="margin-top: 18px;">
            <br/>
            <strong>Assistant Director (Forensics)</strong><br/>
            National Cyber Crime Investigation Agency<br/>
            ${zoneName}
          </div>
        </div>
      </body>
      </html>
    `;
    writePrintWindow(win, html);
    } catch (err) {
      closePrintWindow(win);
      alert(err?.response?.data?.message || err?.message || 'Could not print scope letter.');
    }
  };

  const handleMarkToDdForensicFromModal = async () => {
    if (!scopeLetterTarget) return;
    if (!scopeLetterData.items || scopeLetterData.items.length === 0) {
      alert('Enquiry Officer ne abhi tak koi seized items enter nahi kiye. Jab EO Scope Letter submit karega tabhi Circle Incharge aage DD Forensic ko mark kar sakta hai.');
      return;
    }
    if (!scopeLetterData.analysisScope?.trim()) {
      alert('Scope of Analysis text hona zaroori hai.');
      return;
    }

    setScopeLetterActionSaving(true);
    try {
      const enq = scopeLetterData.enquiry || scopeLetterTarget;
      const linked = scopeLetterData.linkedRequests || [];

      if (linked.length > 0) {
        await api.post(`/forensic/requests/${linked[0].id}/forward-to-forensic`, {
          remarks: scopeLetterData.ciRemarks || 'Approved & Forwarded by Circle Incharge',
        });
      } else {
        const cleanItems = scopeLetterData.items.map(it => ({
          item_type: it.item_type || 'other',
          make_model: it.make_model || null,
          imei: it.imei || null,
          imei2: it.imei2 || null,
          serial_no: it.serial_no || null,
          storage_capacity: it.storage_capacity || null,
          condition: it.condition || null,
          quantity: it.quantity || 1,
          description: it.description || null,
        }));

        const fd = new FormData();
        fd.append('enquiry_id', enq.id);
        fd.append('destination', 'forensic');
        fd.append('priority', 'normal');
        fd.append('status', 'forwarded_to_forensic');
        fd.append('brief_contents', `Enquiry #${enq.enquiry_number || enq.id} - ${enq.complaint?.complainant_name || ''}`);
        fd.append('analysis_scope', scopeLetterData.analysisScope.trim());
        fd.append('note', scopeLetterData.ciRemarks || `Approved and forwarded by Circle Incharge (${user?.name}) to DD Forensic Lab.`);
        fd.append('items', JSON.stringify(cleanItems));

        await api.post('/forensic-requests', fd);
      }

      alert('Enquiry Officer ka Scope Letter & Seized Evidence DD Forensic Lab ko forward ho gaya hai!');
      setScopeLetterTarget(null);
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || err.message || 'Action failed.');
    } finally {
      setScopeLetterActionSaving(false);
    }
  };

  const handleSendBackScopeLetterFromModal = async () => {
    if (!scopeLetterTarget) return;
    const remarks = prompt('Scope letter mein kya deficiency ya kami hai? Remarks darj karein:');
    if (remarks === null) return;
    if (!remarks.trim()) {
      alert('Remarks likhna zaroori hai.');
      return;
    }

    setScopeLetterActionSaving(true);
    try {
      const linked = scopeLetterData.linkedRequests || [];
      if (linked.length > 0) {
        await api.post(`/forensic/requests/${linked[0].id}/send-back`, {
          remarks: remarks.trim(),
        });
      }
      alert(`Scope letter deficiency remarks ke sath Enquiry Officer ko wapas bhej diya gaya: "${remarks.trim()}"`);
      setScopeLetterTarget(null);
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || err.message || 'Send back failed.');
    } finally {
      setScopeLetterActionSaving(false);
    }
  };

  const filteredList = list;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Enquiries</h1>
          <p className="page-subtitle">Manage enquiry records</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          {canCreateEnquiry(user) && (
            <Link to="/enquiries/create" className="btn btn-primary btn-sm">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> New Enquiry
            </Link>
          )}
        </div>
      </div>

      <div className="mini-stats-row" style={{marginBottom:20}}>
        <div className="mini-stat"><div className="mini-stat-value">{stats.total}</div><div className="mini-stat-label">Total Enquiries</div></div>
        <div className="mini-stat"><div className="mini-stat-value">{stats.pending}</div><div className="mini-stat-label">Pending</div></div>
        <div className="mini-stat"><div className="mini-stat-value">{stats.progress}</div><div className="mini-stat-label">Working</div></div>
        <div className="mini-stat"><div className="mini-stat-value">{stats.approved}</div><div className="mini-stat-label">Complete</div></div>
      </div>

      <div className="filters-bar" style={{display:'flex',gap:12,alignItems:'center',padding:'12px 16px',background:'#fff',borderRadius:8,marginBottom:16,flexWrap:'wrap',boxShadow:'0 2px 12px rgba(0,0,0,0.06)',border:'1px solid rgba(1,92,148,0.15)'}}>
        <span className="filter-label" style={{fontSize:12,fontWeight:700,color:'#2b2b2b',textTransform:'uppercase',letterSpacing:'0.5px'}}>Filter</span>
        <input type="text" className="filter-select" placeholder="Search by complaint name or ID..." style={{height:34,padding:'0 12px',width:260,border:'1.5px solid #264078',borderRadius:8,fontSize:13}} value={search} onChange={e => { setSearch(e.target.value); setPage(1); }} />
        <select className="filter-select" style={{height:34,padding:'0 12px',border:'1.5px solid #264078',borderRadius:8,fontSize:13,background:'#fff',minWidth:160}} value={statusFilter} onChange={e => { setStatusFilter(e.target.value); setPage(1); }}>
          <option value="">All Statuses</option>
          {SIMPLE_STATUSES.map(s => <option key={s.value} value={s.value}>{s.name}</option>)}
        </select>
      </div>

      {loading ? <LoadingSkeleton type="table" columns={6} rows={8} /> : (
        <div className="card">
          <div className="card-body" style={{padding:0}}>
            <div className="table-responsive">
              <table className="data-table">
                <thead>
                  <tr><th>#</th><th>Complaint</th><th>Status</th><th>Progress</th><th>Enquiry Officer</th><th>Created</th><th style={{textAlign:'center'}}>Actions</th></tr>
                </thead>
                <tbody>
                  {filteredList.map((e, i) => (
                    <tr key={e.id}>
                      <td><span className="table-id">#{e.enquiry_number || e.id}</span></td>
                      <td><span style={{fontSize:13,fontWeight:500}}>{e.complaint?.complainant_name || e.direct_info?.complainant_name || e.complaint_id}</span><br /><span style={{fontSize:11,color:'#6c757d'}}>{e.complaint?.tracking_no || e.direct_info?.reference_no || ''}</span></td>
                      <td>
                        <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                          <span className={`badge ${STATUS_COLORS[toSimpleStatus(e.status)] || 'badge-pending'}`}>{simpleStatusLabel(e.status)}</span>
                          {(e.has_unserved_notice || e.status === 'referred_court') && (
                            <span title="Summon non-appearance / unserved" style={{ fontSize: 16, lineHeight: 1 }}>⭐</span>
                          )}
                          {e.notice_count > 0 && (
                            <span className="badge badge-info" style={{ fontSize: 11 }}>{e.notice_count} summon{e.notice_count > 1 ? 's' : ''}</span>
                          )}
                        </div>
                      </td>
                      <td style={{ minWidth: 180 }}>
                        <WorkflowProgress
                          percent={enquiryProgress(e.status).percent}
                          stage={enquiryProgress(e.status).stage}
                          compact
                        />
                      </td>
                      <td>{e.officer?.name || '-'}</td>
                      <td><span style={{fontSize:12,color:'#6c757d'}}>{new Date(e.created_at).toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'})}</span></td>
                      <td style={{textAlign:'center'}}>
                        <div style={{display:'flex',gap:6,justifyContent:'center',flexWrap:'wrap'}}>
                          <button
                            type="button"
                            onClick={() => setChatTarget(e)}
                            className="btn btn-sm"
                            style={{background:'rgba(1,92,148,0.12)',color:'#015C94',border:'none',borderRadius:8,width:36,height:36,display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer',fontSize:14}}
                            title="Case Discussion & Team Chat"
                          >
                            💬
                          </button>

                          <Link
                            to={`/enquiries/${e.id}/edit`}
                            className="btn btn-sm"
                            style={{
                              background: '#015C94',
                              color: '#fff',
                              borderRadius: 8,
                              height: 36,
                              display: 'inline-flex',
                              alignItems: 'center',
                              gap: 5,
                              padding: '0 12px',
                              fontSize: 12,
                              fontWeight: 600,
                              textDecoration: 'none'
                            }}
                            title="View / Review complete Enquiry details"
                          >
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            {hasRole('circle_incharge') || hasRole('admin') || hasRole('ad_legal') || hasRole('dd_legal') || hasRole('additional_director') || hasRole('director_general')
                              ? 'View / Review'
                              : (isSubmittedToCircle(e.status) ? 'View Enquiry' : 'Edit Enquiry')}
                          </Link>

                          {(hasRole('circle_incharge') || hasRole('admin') || hasRole('ad_legal') || hasRole('dd_legal') || hasRole('additional_director') || hasRole('director_general')) && (
                            <button
                              type="button"
                              onClick={() => openScopeLetterModal(e)}
                              className="btn btn-sm"
                              style={{
                                background: 'rgba(1,92,148,0.12)',
                                color: '#015C94',
                                border: '1.5px solid rgba(1,92,148,0.3)',
                                borderRadius: 8,
                                height: 36,
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: 5,
                                padding: '0 10px',
                                cursor: 'pointer',
                                fontSize: 12,
                                fontWeight: 700,
                              }}
                              title="View / Review Forensic Scope Letter & Mark to DD Forensic"
                            >
                              🔬 Scope Letter
                            </button>
                          )}

                          {(['registered'].includes(e.status)) && (hasRole('admin') || hasRole('circle_incharge')) && (
                            <button onClick={() => openAssign(e)} className="btn btn-sm" style={{background:'rgba(1,92,148,0.12)',color:'#015C94',border:'none',borderRadius:8,height:36,display:'inline-flex',alignItems:'center',gap:5,padding:'0 10px',cursor:'pointer',fontSize:12,fontWeight:600}} title="Assign Officer">
                              Assign
                            </button>
                          )}

                          {(['assigned','in_progress','pending','working'].includes(e.status)) && user?.id === e.enquiry_officer_id && (
                            <button onClick={() => openCfr(e)} className="btn btn-sm" style={{background:'rgba(56,161,105,0.12)',color:'#38a169',border:'none',borderRadius:8,height:36,display:'inline-flex',alignItems:'center',gap:5,padding:'0 10px',cursor:'pointer',fontSize:12,fontWeight:600}} title="Submit CFR">
                              Submit CFR
                            </button>
                          )}

                          {e.status === 'cfr_submitted' && (hasRole('admin') || hasRole('circle_incharge') || hasRole('ad_legal') || hasRole('dd_legal') || hasRole('additional_director') || hasRole('director_general')) && (
                            <button onClick={() => openApprove(e)} className="btn btn-sm" style={{background:'rgba(214,158,46,0.15)',color:'#b7791f',border:'1px solid #d69e2e',borderRadius:8,height:36,display:'inline-flex',alignItems:'center',gap:5,padding:'0 10px',cursor:'pointer',fontSize:12,fontWeight:700}} title="Review & Approve CFR">
                              📋 Review CFR
                            </button>
                          )}

                          {canRegisterCase && enquiryReadyForCaseRegistration(e) && (
                            <button onClick={() => openRegisterCase(e)} className="btn btn-sm" style={{background:'rgba(5,150,105,0.15)',color:'#059669',border:'1px solid #059669',borderRadius:8,height:36,display:'inline-flex',alignItems:'center',gap:5,padding:'0 10px',cursor:'pointer',fontSize:12,fontWeight:700}} title="Register Case/FIR">
                              ⚖️ Register Case
                            </button>
                          )}

                          {e.case_file_id || e.case_file?.id ? (
                            <Link to={`/cases/${e.case_file_id || e.case_file.id}/edit`} className="btn btn-sm" style={{background:'rgba(56,161,105,0.12)',color:'#38a169',border:'none',borderRadius:8,height:36,display:'inline-flex',alignItems:'center',gap:5,padding:'0 10px',cursor:'pointer',fontSize:12,fontWeight:600,textDecoration:'none'}} title="View registered case">
                              View Case
                            </Link>
                          ) : null}

                          {(hasRole('admin') || hasRole('circle_incharge')) && (
                            <button onClick={() => openChange(e)} className="btn btn-sm" style={{background:'rgba(128,90,213,0.12)',color:'#805ad5',border:'none',borderRadius:8,height:36,display:'inline-flex',alignItems:'center',gap:5,padding:'0 10px',cursor:'pointer',fontSize:12,fontWeight:600}} title="Change Officer">
                              Change
                            </button>
                          )}

                          {hasRole('admin') && (
                            <button onClick={() => setDeleteTarget(e)} className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:8,width:36,height:36,display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}} title="Delete">
                              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                  {filteredList.length === 0 && <tr><td colSpan={7} style={{textAlign:'center',padding:24,color:'#6c757d'}}>No enquiries found</td></tr>}
                </tbody>
              </table>
            </div>
          </div>
          {lastPage > 1 && (
            <div style={{display:'flex',justifyContent:'center',alignItems:'center',gap:8,padding:'12px 16px',borderTop:'1px solid #f0f0f0'}}>
              <button disabled={page <= 1} onClick={() => setPage(p => Math.max(1, p - 1))} style={{padding:'6px 14px',borderRadius:6,border:'1px solid #dee2e6',background: page <= 1 ? '#f8f9fa' : '#fff',color: page <= 1 ? '#adb5bd' : '#495057',cursor: page <= 1 ? 'default' : 'pointer',fontSize:13}}>Prev</button>
              <span style={{fontSize:13,color:'#6c757d'}}>Page {page} of {lastPage}</span>
              <button disabled={page >= lastPage} onClick={() => setPage(p => Math.min(lastPage, p + 1))} style={{padding:'6px 14px',borderRadius:6,border:'1px solid #dee2e6',background: page >= lastPage ? '#f8f9fa' : '#fff',color: page >= lastPage ? '#adb5bd' : '#495057',cursor: page >= lastPage ? 'default' : 'pointer',fontSize:13}}>Next</button>
            </div>
          )}
        </div>
      )}

      <ConfirmModal open={!!deleteTarget} title="Delete Enquiry" message={`Delete enquiry #${deleteTarget?.id}?`} confirmLabel="Delete" onConfirm={handleDelete} onCancel={() => setDeleteTarget(null)} />

      {/* ── Assign Modal ── */}
      {assignTarget && (
        <div className="modal-overlay" onClick={() => setAssignTarget(null)}>
          <div className="modal-container" style={{maxWidth:480}} onClick={e => e.stopPropagation()}>
            <div className="modal-header"><h3>Assign Enquiry Officer</h3><button className="modal-close" onClick={() => setAssignTarget(null)}>&times;</button></div>
            <div className="modal-body">
              <p style={{marginBottom:12,fontSize:13,color:'#666'}}>Enquiry: <strong>#{assignTarget.enquiry_number || assignTarget.id}</strong></p>
              <div className="cf-group">
                <label className="cf-label">Enquiry Officer <span className="required">*</span></label>
                <SearchableSelect value={assignOfficerId} onChange={setAssignOfficerId} options={officers} placeholder="Select officer..." valueKey="id" formatLabel={o => o.name + (o.designation ? ' (' + o.designation + ')' : '')} />
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setAssignTarget(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleAssign} disabled={assignSaving || !assignOfficerId}>{assignSaving ? 'Assigning...' : 'Assign'}</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Submit CFR Modal ── */}
      {cfrTarget && (
        <div className="modal-overlay" onClick={() => setCfrTarget(null)}>
          <div className="modal-container" style={{maxWidth:540}} onClick={e => e.stopPropagation()}>
            <div className="modal-header"><h3>Submit CFR</h3><button className="modal-close" onClick={() => setCfrTarget(null)}>&times;</button></div>
            <div className="modal-body">
              <div className="cf-group">
                <label className="cf-label">CFR Summary <span className="required">*</span></label>
                <textarea className="cf-input" rows={5} value={cfrForm.cfr_summary} onChange={e => setCfrForm({...cfrForm, cfr_summary: e.target.value})} placeholder="Detailed findings summary..." required />
              </div>
              <div className="cf-group">
                <label className="cf-label">Recommendation <span className="required">*</span></label>
                <select className="cf-input" value={cfrForm.recommendation} onChange={e => setCfrForm({...cfrForm, recommendation: e.target.value})} required>
                  <option value="">Select recommendation...</option>
                  <option value="closure">Closure</option>
                  <option value="transfer">Transfer</option>
                  <option value="convert_to_case">Convert to Case</option>
                </select>
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setCfrTarget(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleCfr} disabled={cfrSaving || !cfrForm.cfr_summary || !cfrForm.recommendation}>{cfrSaving ? 'Submitting...' : 'Submit CFR'}</button>
            </div>
          </div>
        </div>
      )}

      {/* ── Approve/Review Modal ── */}
      {approveTarget && (
        <div className="modal-overlay" onClick={() => setApproveTarget(null)}>
          <div className="modal-container" style={{maxWidth:640}} onClick={e => e.stopPropagation()}>
            <div className="modal-header"><h3>Review Enquiry</h3><button className="modal-close" onClick={() => setApproveTarget(null)}>&times;</button></div>
            <div className="modal-body" style={{maxHeight:'70vh',overflowY:'auto'}}>
              {approveTarget.cfr_summary && (
                <div className="cf-group">
                  <label className="cf-label">Officer's CFR Summary</label>
                  <div style={{padding:'10px 14px',background:'#f9f9f9',borderRadius:8,fontSize:13,color:'#333',whiteSpace:'pre-wrap',border:'1px solid #e5e5e5'}}>{approveTarget.cfr_summary}</div>
                </div>
              )}
              <div className="cf-group">
                <label className="cf-label">Decision <span className="required">*</span></label>
                <select className="cf-input" value={approveForm.decision} onChange={e => setApproveForm({...approveForm, decision: e.target.value})} required>
                  <option value="agree">Agree — Approve & Move Forward</option>
                  <option value="review">Review — Send Back to Officer</option>
                </select>
              </div>
              <div className="cf-group">
                <label className="cf-label">Final Recommendation <span className="required">*</span></label>
                <select className="cf-input" value={approveForm.recommendation} onChange={e => setApproveForm({...approveForm, recommendation: e.target.value, closure_reason: '', merge_complaint_id: '', transfer_department: '', transfer_circle: ''})} required>
                  <option value="">Select recommendation...</option>
                  <option value="closure">Closure</option>
                  <option value="merge">Merge</option>
                  <option value="transfer">Transfer</option>
                  <option value="convert_to_case">Case Registration</option>
                </select>
              </div>
              {approveForm.recommendation === 'closure' && (
                <div className="cf-group">
                  <label className="cf-label">Closure Reason <span className="required">*</span></label>
                  <select className="cf-input" value={approveForm.closure_reason} onChange={e => setApproveForm({...approveForm, closure_reason: e.target.value})} required>
                    <option value="">Select reason...</option>
                    <option value="non_pursuance">Non-Pursuance by Complainant</option>
                    <option value="irrelevant">Irrelevant</option>
                    <option value="invalid">Invalid</option>
                    <option value="lack_of_evidence">Lack of Evidence</option>
                    <option value="compromise">Compromise (Parties Settled)</option>
                  </select>
                </div>
              )}
              {approveForm.recommendation === 'merge' && (
                <div className="cf-group">
                  <label className="cf-label">Merge with Complaint ID</label>
                  <input className="cf-input" value={approveForm.merge_complaint_id} onChange={e => setApproveForm({...approveForm, merge_complaint_id: e.target.value})} placeholder="Enter complaint ID" />
                </div>
              )}
              {approveForm.recommendation === 'transfer' && (
                <>
                  <div className="cf-group">
                    <label className="cf-label">Department Name</label>
                    <input className="cf-input" value={approveForm.transfer_department} onChange={e => setApproveForm({...approveForm, transfer_department: e.target.value})} placeholder="e.g. NCCIA, Police" />
                  </div>
                  <div className="cf-group">
                    <label className="cf-label">Circle</label>
                    <select className="cf-input" value={approveForm.transfer_circle} onChange={e => setApproveForm({...approveForm, transfer_circle: e.target.value})}>
                      <option value="">Select circle...</option>
                      {circles.map(c => <option key={c.id} value={c.name}>{c.name}</option>)}
                    </select>
                  </div>
                </>
              )}
              <div className="cf-group">
                <label className="cf-label">Remarks</label>
                <textarea className="cf-input" rows={3} value={approveForm.remarks} onChange={e => setApproveForm({...approveForm, remarks: e.target.value})} placeholder="Any additional remarks..." />
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setApproveTarget(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleApprove} disabled={approveSaving || !approveForm.decision || !approveForm.recommendation}>
                {approveSaving ? 'Processing...' : (approveForm.decision === 'agree' ? 'Approve' : 'Send Back')}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Register Case Modal ── */}
      {registerTarget && (
        <div className="modal-overlay" onClick={() => setRegisterTarget(null)}>
          <div className="modal-container" style={{maxWidth:540}} onClick={e => e.stopPropagation()}>
            <div className="modal-header"><h3>Register Case / FIR</h3><button className="modal-close" onClick={() => setRegisterTarget(null)}>&times;</button></div>
            <div className="modal-body">
              <p style={{marginBottom:12,fontSize:13,color:'#666'}}>
                Enquiry <strong>#{registerTarget.enquiry_number || registerTarget.id}</strong> ko case/FIR mein convert karein. FIR number auto generate hoga.
              </p>
              {registerTarget.cfr_summary && (
                <div className="cf-group">
                  <label className="cf-label">CFR Summary</label>
                  <div style={{padding:'10px 14px',background:'#f9f9f9',borderRadius:8,fontSize:13,color:'#333',whiteSpace:'pre-wrap',border:'1px solid #e5e5e5',maxHeight:120,overflowY:'auto'}}>{registerTarget.cfr_summary}</div>
                </div>
              )}
              <div className="cf-group">
                <label className="cf-label">Investigation Officer (optional)</label>
                <SearchableSelect value={registerForm.investigation_officer_id} onChange={v => setRegisterForm({...registerForm, investigation_officer_id: v})} options={ioOfficers} placeholder="Select IO..." valueKey="id" formatLabel={o => o.name + (o.designation ? ' (' + o.designation + ')' : '')} />
              </div>
              <div className="cf-group">
                <label className="cf-label">Remarks</label>
                <textarea className="cf-input" rows={3} value={registerForm.remarks} onChange={e => setRegisterForm({...registerForm, remarks: e.target.value})} placeholder="Optional remarks for case registration..." />
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setRegisterTarget(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleRegisterCase} disabled={registerSaving}>
                {registerSaving ? 'Registering...' : 'Register Case / FIR'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Change Officer Modal ── */}
      {changeTarget && (
        <div className="modal-overlay" onClick={() => setChangeTarget(null)}>
          <div className="modal-container" style={{maxWidth:480}} onClick={e => e.stopPropagation()}>
            <div className="modal-header"><h3>Change Enquiry Officer</h3><button className="modal-close" onClick={() => setChangeTarget(null)}>&times;</button></div>
            <div className="modal-body">
              <p style={{marginBottom:12,fontSize:13,color:'#666'}}>Current officer: <strong>{changeTarget.officer?.name || 'N/A'}</strong></p>
              <div className="cf-group">
                <label className="cf-label">New Officer <span className="required">*</span></label>
                <SearchableSelect value={changeOfficerId} onChange={setChangeOfficerId} options={officers} placeholder="Select officer..." valueKey="id" formatLabel={o => o.name + (o.designation ? ' (' + o.designation + ')' : '')} />
              </div>
            </div>
            <div className="modal-footer">
              <button className="btn btn-outline" onClick={() => setChangeTarget(null)}>Cancel</button>
              <button className="btn btn-primary" onClick={handleChange} disabled={changeSaving || !changeOfficerId}>{changeSaving ? 'Changing...' : 'Change Officer'}</button>
            </div>
          </div>
        </div>
      )}
      {/* ── Case Chat Modal ── */}
      <CaseChatModal
        open={!!chatTarget}
        onClose={() => setChatTarget(null)}
        type="enquiry"
        id={chatTarget?.id}
        caseNumber={chatTarget?.enquiry_number || (chatTarget?.id ? `ENQ-${chatTarget.id}` : '')}
        title={chatTarget?.complaint?.complainant_name || chatTarget?.direct_info?.complainant_name || ''}
        officers={chatTarget?.officer ? [{ name: chatTarget.officer.name, role_label: 'Enquiry Officer' }] : []}
      />

      {/* ── Scope Letter Review & Approval Modal ── */}
      {scopeLetterTarget && (
        <div className="modal-overlay" onClick={() => setScopeLetterTarget(null)}>
          <div className="modal-container" style={{ maxWidth: 820 }} onClick={e => e.stopPropagation()}>
            <div className="modal-header" style={{ background: '#f8fafc', borderBottom: '1.5px solid #e2e8f0' }}>
              <div>
                <h3 style={{ margin: 0, color: '#015C94', display: 'flex', alignItems: 'center', gap: 8 }}>
                  🔬 Forensic Scope Letter & Seized Evidence Review
                </h3>
                <span style={{ fontSize: 12, color: '#64748b' }}>
                  Enquiry: <strong>#{scopeLetterTarget.enquiry_number || scopeLetterTarget.id}</strong> &middot; Complainant: <strong>{scopeLetterTarget.complaint?.complainant_name || scopeLetterTarget.direct_info?.complainant_name || 'N/A'}</strong>
                </span>
              </div>
              <button className="modal-close" onClick={() => setScopeLetterTarget(null)}>&times;</button>
            </div>

            <div className="modal-body" style={{ maxHeight: '72vh', overflowY: 'auto', padding: '20px' }}>
              {scopeLetterLoading ? (
                <div style={{ textAlign: 'center', padding: '30px', color: '#64748b' }}>
                  <p>Loading Scope Letter and Seized Devices...</p>
                </div>
              ) : (
                <>
                  {/* Meta summary card */}
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 12, background: '#f1f5f9', padding: '12px 14px', borderRadius: 8, marginBottom: 16, fontSize: 13 }}>
                    <div><span style={{ color: '#64748b', fontSize: 11, display: 'block' }}>Tracking / Ref No</span><strong>{scopeLetterData.enquiry?.complaint?.tracking_no || scopeLetterData.enquiry?.direct_info?.reference_no || scopeLetterData.enquiry?.tracking_no || '—'}</strong></div>
                    <div><span style={{ color: '#64748b', fontSize: 11, display: 'block' }}>Enquiry Officer</span><strong>{scopeLetterData.enquiry?.officer?.name || '—'}</strong></div>
                    <div><span style={{ color: '#64748b', fontSize: 11, display: 'block' }}>Routing Stage</span><span className="badge badge-info" style={{ fontSize: 11 }}>Under CI Review</span></div>
                  </div>

                  {/* Devices table */}
                  <div style={{ marginBottom: 16 }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8 }}>
                      <label className="cf-label" style={{ margin: 0, fontSize: 13, fontWeight: 700, color: '#1e293b' }}>
                        📦 Seized Digital Devices ({scopeLetterData.items.length})
                      </label>
                    </div>

                    {scopeLetterData.items.length > 0 ? (
                      <div className="table-responsive" style={{ border: '1px solid #e2e8f0', borderRadius: 8, overflow: 'hidden' }}>
                        <table className="data-table" style={{ margin: 0 }}>
                          <thead style={{ background: '#f8fafc' }}>
                            <tr>
                              <th style={{ padding: '8px 10px', fontSize: 12 }}>#</th>
                              <th style={{ padding: '8px 10px', fontSize: 12 }}>Item Type</th>
                              <th style={{ padding: '8px 10px', fontSize: 12 }}>Make / Model</th>
                              <th style={{ padding: '8px 10px', fontSize: 12 }}>IMEI / Serial</th>
                              <th style={{ padding: '8px 10px', fontSize: 12 }}>Storage</th>
                              <th style={{ padding: '8px 10px', fontSize: 12 }}>Condition</th>
                              <th style={{ padding: '8px 10px', fontSize: 12 }}>Description</th>
                            </tr>
                          </thead>
                          <tbody>
                            {scopeLetterData.items.map((it, idx) => (
                              <tr key={idx}>
                                <td style={{ padding: '8px 10px', fontSize: 12 }}>{idx + 1}</td>
                                <td style={{ padding: '8px 10px', fontSize: 12, fontWeight: 600 }}>{it.item_type || 'Device'}</td>
                                <td style={{ padding: '8px 10px', fontSize: 12 }}>{it.make_model || '—'}</td>
                                <td style={{ padding: '8px 10px', fontSize: 12, fontFamily: 'monospace' }}>{it.imei || it.serial_no || '—'}</td>
                                <td style={{ padding: '8px 10px', fontSize: 12 }}>{it.storage_capacity || '—'}</td>
                                <td style={{ padding: '8px 10px', fontSize: 12 }}><span className="badge badge-pending" style={{ fontSize: 10 }}>{it.condition || 'Sealed'}</span></td>
                                <td style={{ padding: '8px 10px', fontSize: 12, color: '#64748b' }}>{it.description || '—'}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    ) : (
                      <div style={{ padding: '16px', background: '#fffbeb', border: '1.5px solid #fef3c7', borderRadius: 8, fontSize: 13, color: '#92400e', lineHeight: 1.5 }}>
                        ⚠️ <strong>Scope Letter Not Submitted:</strong> Enquiry Officer (EO) ne abhi tak is enquiry mein digital devices ya Scope Letter submit nahi kiya hai. Sirf EO ka create/submit kiya hua Scope Letter hi Circle Incharge aage DD Forensic ko mark kar sakta hai.
                      </div>
                    )}
                  </div>

                  {/* Scope textarea */}
                  <div className="cf-group" style={{ marginBottom: 0 }}>
                    <label className="cf-label required" style={{ fontSize: 13, fontWeight: 700 }}>
                      Analysis Scope (For Forensic Lab)
                    </label>
                    <div style={{
                      padding: 12,
                      background: '#f1f5f9',
                      borderRadius: 6,
                      border: '1px solid #cbd5e1',
                      fontSize: 13,
                      lineHeight: 1.5,
                      color: '#0f172a',
                      whiteSpace: 'pre-wrap',
                      minHeight: '80px'
                    }}>
                      {scopeLetterData.analysisScope || <span style={{ color: '#94a3b8', fontStyle: 'italic' }}>Enquiry Officer dwara darj kiya gaya examination scope...</span>}
                    </div>
                    <span className="cf-hint" style={{ fontSize: 11, color: '#64748b', marginTop: 4, display: 'block' }}>
                      {scopeLetterData.hasEoSubmitted ? 'EO ke scope mein changes nahi ho sakti, yeh seedha lab jayega.' : 'EO ke Scope Letter submit karne ke baad text yahan show hoga.'}
                    </span>
                  </div>

                  {scopeLetterData.hasEoSubmitted && (
                    <div className="cf-field" style={{ marginTop: 16 }}>
                      <label className="cf-label">Circle Incharge Remarks (Optional)</label>
                      <input
                        type="text"
                        className="cf-input"
                        placeholder="e.g. Approved and forwarded to DD Forensic..."
                        value={scopeLetterData.ciRemarks || ''}
                        onChange={e => setScopeLetterData({ ...scopeLetterData, ciRemarks: e.target.value })}
                        disabled={!scopeLetterData.hasEoSubmitted}
                      />
                    </div>
                  )}
                </>
              )}
            </div>

            <div className="modal-footer" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', background: '#f8fafc', borderTop: '1.5px solid #e2e8f0' }}>
              <button
                type="button"
                className="btn btn-outline"
                style={{ color: '#015C94', borderColor: '#015C94', fontWeight: 600 }}
                onClick={handlePrintScopeLetterFromModal}
                disabled={scopeLetterLoading || !scopeLetterData.hasEoSubmitted}
              >
                🖨️ Print Scope Letter
              </button>

              <div style={{ display: 'flex', gap: 8 }}>
                <button
                  type="button"
                  className="btn btn-outline"
                  onClick={() => setScopeLetterTarget(null)}
                >
                  Cancel
                </button>
                {scopeLetterData.hasEoSubmitted && (
                  <button
                    type="button"
                    className="btn btn-outline"
                    style={{ color: '#d97706', borderColor: '#d97706', fontWeight: 700 }}
                    disabled={scopeLetterLoading || scopeLetterActionSaving}
                    onClick={handleSendBackScopeLetterFromModal}
                    title="Send back to EO with deficiency remarks"
                  >
                    ↩️ Send Back to EO
                  </button>
                )}
                <button
                  type="button"
                  className="btn btn-primary"
                  style={{ background: scopeLetterData.hasEoSubmitted ? '#059669' : '#94a3b8', color: '#fff', fontWeight: 700, cursor: scopeLetterData.hasEoSubmitted ? 'pointer' : 'not-allowed' }}
                  disabled={scopeLetterLoading || scopeLetterActionSaving || !scopeLetterData.hasEoSubmitted}
                  onClick={handleMarkToDdForensicFromModal}
                  title={scopeLetterData.hasEoSubmitted ? 'Approve and mark EO scope letter to DD Forensic Lab' : 'EO ke Scope Letter submit karne ke baad hi mark kar sakte hain'}
                >
                  {scopeLetterActionSaving ? 'Forwarding…' : '📤 Mark to DD Forensic'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import { formatDisplayDateTime } from '../utils/datetime';
import { hasAnyRole, hasRole, isForensicAdmin } from '../utils/permissions';
import { useAuth } from '../contexts/AuthContext';
import { generateOfficialDocumentQr } from '../utils/qrcode';
import { preparePrintWindow, writePrintWindow, closePrintWindow } from '../utils/print';

const ITEM_LABELS = {
  'cd_dvd':      { label: 'CD/DVD',           icon: '\uD83D\uDCC0' },
  'computer':    { label: 'Computer/Desktop',  icon: '\uD83D\uDDA5\uFE0F' },
  'dvr':         { label: 'DVR',               icon: '\uD83D\uDCF9' },
  'hdd':         { label: 'Hard Disk - HDD',   icon: '\uD83D\uDCBD' },
  'ipad_tablet': { label: 'iPad/Tablet',        icon: '\uD83D\uDCF1' },
  'laptop':      { label: 'Laptop',             icon: '\uD83D\uDCBB' },
  'memory_card': { label: 'Memory Card',        icon: '\uD83D\uDDC2\uFE0F' },
  'phone':       { label: 'Mobile Phone',       icon: '\uD83D\uDCF1' },
  'other':       { label: 'Other',              icon: '\uD83D\uDCE6' },
  'sim':         { label: 'SIM Card',           icon: '\uD83D\uDCF6' },
  'usb':         { label: 'USB',                icon: '\uD83D\uDD0C' },
};
function itemLabel(type) {
  const m = ITEM_LABELS[type] || ITEM_LABELS['other'];
  return `${m.icon} ${m.label}`;
}

const OFFENCE_LABELS = {
  financial_fraud: 'Financial / Banking Fraud',
  online_scam:     'Online Scam / Phishing',
  crypto_fraud:    'Cryptocurrency Fraud',
  extortion:       'Cyber Extortion / Blackmail',
  cyberstalking:   'Cyberstalking',
  impersonation:   'Impersonation / Identity Theft',
  defamation:      'Online Defamation / Fake Profile',
  harassment:      'Online Harassment / Threats',
  non_consensual:  'Non-Consensual Content',
  hacking:         'Hacking / Unauthorized Access',
  malware:         'Malware / Ransomware Attack',
  data_breach:     'Data Breach / Theft',
  ddos:            'DDoS / System Disruption',
  anti_state:      'Anti-State / Terrorism Content',
  hate_speech:     'Hate Speech / Extremism',
};

function formatOffenceCategory(offenceKey) {
  if (!offenceKey) return '';
  return OFFENCE_LABELS[offenceKey] || offenceKey.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

const STATUS_META = {
  submitted:             { label: 'Pending CI Review',             color: '#e5a100', bg: '#fef3c7', icon: '\u23F3' },
  forwarded_to_forensic: { label: 'Pending AD Review',             color: '#e5a100', bg: '#fef3c7', icon: '\u23F3' },
  assigned:              { label: 'Assigned to AD Forensic',                color: '#2563eb', bg: '#dbeafe', icon: '\uD83D\uDC64' },
  in_progress:           { label: 'Lab Examination',               color: '#7c3aed', bg: '#ede9fe', icon: '\uD83D\uDD2C' },
  submitted_to_ad:       { label: 'Submitted to Director for Approval',               color: '#d97706', bg: '#fef3c7', icon: '\uD83D\uDCDD' },
  report_ready:          { label: 'Report Approved (EO Notified)', color: '#059669', bg: '#d1fae5', icon: '\u2705' },
  handed_over:           { label: 'Handed Over to EO',             color: '#64748b', bg: '#f1f5f9', icon: '\uD83D\uDCE4' },
};

export default function ForensicRequestDetail() {
  const { id } = useParams();
  const { user } = useAuth();
  const [row, setRow] = useState(null);
  const [officers, setOfficers] = useState([]);
  const [assignedTo, setAssignedTo] = useState('');
  const [assignPriority, setAssignPriority] = useState('normal');
  const [remarks, setRemarks] = useState('');
  const [handoverRemarks, setHandoverRemarks] = useState('');
  const [findings, setFindings] = useState('');
  const [labNotes, setLabNotes] = useState('');
  const [reportFile, setReportFile] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [msg, setMsg] = useState('');
  const [err, setErr] = useState('');

  const isAdmin = isForensicAdmin(user) || hasAnyRole(user, ['admin', 'admin_forensic', 'director_general']);
  const isDd    = hasAnyRole(user, ['dd_forensic', 'admin_forensic', 'admin', 'director_general']);
  const isAd    = hasAnyRole(user, ['ad_forensic', 'admin_forensic', 'admin', 'dd_forensic', 'forensic_team', 'examiner', 'forensic_examiner', 'director_general']) || isForensicUser(user);
  const [custodyRemarks, setCustodyRemarks] = useState('');
  const [additionalScopeCategory, setAdditionalScopeCategory] = useState('');
  const [customLabNo, setCustomLabNo] = useState('');
  const [custodyReceivingDateTime, setCustodyReceivingDateTime] = useState('');

  // Scope Letter Live Editable States (for EO / IO / CI / AD)
  const [scopeCaseType, setScopeCaseType] = useState('enquiry'); // 'enquiry' | 'case'
  const [scopeBriefContents, setScopeBriefContents] = useState('');
  const [scopeBackgroundText, setScopeBackgroundText] = useState('');

  // Hierarchical Send Back Modal
  const [sendBackModal, setSendBackModal] = useState(false);
  const [sendBackTarget, setSendBackTarget] = useState('dd'); // 'dd' | 'ci' | 'eo'
  const [sendBackRemarks, setSendBackRemarks] = useState('');
  const [f31QrSvg, setF31QrSvg] = useState('');

  useEffect(() => {
    if (!row?.id) return;
    const code = (row.request_no || row.report_code || `FR-${row.id || '001'}`).toUpperCase();
    generateOfficialDocumentQr({
      type: 'custody',
      id: row.id,
      size: 62,
      fallback: {
        type: 'Chain of Custody',
        number: code,
        complainant: row.enquiry?.complaint?.complainant_name || row.external_person_name || '',
        officer: row.enquiry?.officer?.name || '',
        date: row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB') : '',
      },
    }).then(qr => setF31QrSvg(qr.svg));
  }, [row]);

  const submitSendBack = async () => {
    if (!sendBackRemarks.trim()) {
      alert('Please enter reason / remarks for sending back.');
      return;
    }
    setBusy(true); setErr(''); setMsg('');
    try {
      let endpoint = `/forensic/requests/${id}/send-back`;
      if (sendBackTarget === 'dd') endpoint = `/forensic/requests/${id}/send-back-to-dd`;
      else if (sendBackTarget === 'ci') endpoint = `/forensic/requests/${id}/send-back-to-ci`;
      else if (sendBackTarget === 'eo') endpoint = `/forensic/requests/${id}/send-back`;

      const r = await api.post(endpoint, { remarks: sendBackRemarks });
      setRow(r.data.data);
      setMsg(r.data.message || 'Request successfully sent back.');
      setSendBackModal(false);
      setSendBackRemarks('');
    } catch (e) {
      setErr(e.response?.data?.message || 'Failed to send back');
    } finally {
      setBusy(false);
    }
  };

  const updateReqStatus = async (newStatus) => {
    setBusy(true); setErr(''); setMsg('');
    try {
      const r = await api.post(`/forensic/requests/${id}/status`, { status: newStatus });
      setRow(r.data.data);
      setMsg(r.data.message || `Status updated to ${newStatus}`);
    } catch (e) {
      setErr(e.response?.data?.message || 'Failed to update status');
    } finally {
      setBusy(false);
    }
  };

  const updateItemCondition = async (itemId, newCondition) => {
    setBusy(true); setErr(''); setMsg('');
    try {
      const r = await api.post(`/forensic/requests/${id}/items/${itemId}/condition`, { condition: newCondition });
      setRow(r.data.data);
      setMsg('Item condition updated');
    } catch (e) {
      setErr(e.response?.data?.message || 'Failed to update condition');
    } finally {
      setBusy(false);
    }
  };

  const load = () => {
    setLoading(true); setErr('');
    api.get(`/forensic/requests/${id}`)
      .then(r => {
        const d = r.data.data;
        setRow(d);
        setFindings(d.findings || '');
        setLabNotes(d.lab_notes || '');
        setAssignPriority(d.priority || 'normal');
        setCustodyRemarks(d.handover_remarks || d.findings || '');
        
        const isCaseDetected = Boolean(d.case_number || d.case_id || (d.enquiry?.enquiry_number && d.enquiry.enquiry_number.toUpperCase().includes('FIR')));
        setScopeCaseType(isCaseDetected ? 'case' : 'enquiry');
        
        const autoBrief = d.enquiry?.verification_report?.brief_allegation
          || d.enquiry?.brief_contents
          || d.enquiry?.complaint?.description
          || d.enquiry?.direct_info?.brief_facts
          || d.enquiry?.direct_info?.description
          || d.brief_contents
          || 'alleged cybercrime offences';
        setScopeBriefContents(autoBrief);
        setScopeBackgroundText('');

        const now = new Date();
        const defaultRecDate = d.submitted_at ? formatDisplayDateTime(d.submitted_at) : (d.created_at ? formatDisplayDateTime(d.created_at) : now.toLocaleDateString('en-GB') + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }));
        setCustodyReceivingDateTime(defaultRecDate);
      })
      .catch(e => setErr(e.response?.data?.message || 'Failed to load request details'))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, [id]);

  useEffect(() => {
    if (isDd || isAd || isAdmin) {
      api.get('/forensic/team-officers').then(r => setOfficers(r.data.data || [])).catch(() => {});
    }
  }, [isDd, isAd, isAdmin]);

  const assign = async () => {
    setBusy(true); setErr(''); setMsg('');
    try {
      const r = await api.post(`/forensic/requests/${id}/assign`, {
        assigned_to: Number(assignedTo),
        remarks: remarks || undefined,
        priority: assignPriority || 'normal',
      });
      setRow(r.data.data); setMsg(r.data.message);
    } catch (e) { setErr(e.response?.data?.message || 'Assignment failed'); }
    finally { setBusy(false); }
  };

  const postFd = async (url) => {
    const fd = new FormData();
    if (findings) fd.append('findings', findings);
    if (labNotes) fd.append('lab_notes', labNotes);
    if (reportFile) fd.append('report_file', reportFile);
    const r = await api.post(url, fd);
    setRow(r.data.data); setMsg(r.data.message); setReportFile(null);
  };

  const saveFindings     = async () => { setBusy(true); setErr(''); setMsg(''); try { await postFd(`/forensic/requests/${id}/findings`); } catch(e){ setErr(e.response?.data?.message||'Save failed'); } finally { setBusy(false); } };
  const submitToAd       = async () => { setBusy(true); setErr(''); setMsg(''); try { await postFd(`/forensic/requests/${id}/submit-to-ad`); } catch(e){ setErr(e.response?.data?.message||'Submit failed'); } finally { setBusy(false); } };
  const approveAndNotify = async () => { setBusy(true); setErr(''); setMsg(''); try { await postFd(`/forensic/requests/${id}/mark-ready`); } catch(e){ setErr(e.response?.data?.message||'Approve failed'); } finally { setBusy(false); } };
  const handOver         = async () => { setBusy(true); setErr(''); setMsg(''); try { const r = await api.post(`/forensic/requests/${id}/hand-over`, { handover_remarks: handoverRemarks||undefined }); setRow(r.data.data); setMsg(r.data.message); } catch(e){ setErr(e.response?.data?.message||'Handover failed'); } finally { setBusy(false); } };

  const handlePrintF31 = () => {
    if (!isAd) {
      alert('Chain of Custody sirf Assistant Director (AD) Forensic print kar sakta hai.');
      return;
    }
    const area = document.getElementById('f31PrintArea');
    if (!area) return;
    const w = window.open('', '_blank', 'width=920,height=750');
    w.document.write(`<!DOCTYPE html><html><head><title>Chain of Custody</title>
    <style>*{margin:0;padding:0;box-sizing:border-box;}body{font-family:Arial,sans-serif;font-size:11.5px;color:#000;padding:15px;}
    table{width:100%;border-collapse:collapse;margin-bottom:12px;}td,th{border:1px solid #000;padding:5px 7px;vertical-align:top;}
    th{background:#d0d0d0;font-weight:bold;text-align:center;}.lbl{font-weight:bold;background:#f5f5f5;}
    .sec{background:#c0c0c0;font-weight:bold;text-align:center;padding:5px;}
    @media print{body{padding:8px;}*{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}</style></head><body>${area.innerHTML}</body></html>`);
    w.document.close(); w.focus(); setTimeout(() => w.print(), 400);
  };

  const handlePrintScopeLetter = async () => {
    if (!row) return;
    const win = preparePrintWindow();
    if (!win) return;
    try {
    const rawNo = row.enquiry?.enquiry_number || row.enquiry?.complaint?.tracking_no || `ENQ-${row.enquiry_id}`;
    const enqRegDate = row.enquiry?.reg_date ? new Date(row.enquiry.reg_date).toLocaleDateString('en-GB') : (row.enquiry?.created_at ? new Date(row.enquiry.created_at).toLocaleDateString('en-GB') : '');
    const enqNoDisplay = enqRegDate ? `${rawNo} dated ${enqRegDate}` : rawNo;
    const isCase = scopeCaseType === 'case';
    const caseLabelUpper = isCase ? 'CASE FIR' : 'ENQUIRY';
    const caseLabelFull = isCase ? `CASE FIR NO. ${enqNoDisplay}` : `ENQUIRY NO. ${enqNoDisplay}`;

    const compName = row.enquiry?.complaint?.complainant_name || row.enquiry?.direct_info?.complainant_name || 'Complainant';
    const complainantName = compName;
    const compCnic = row.enquiry?.complaint?.cnic || row.enquiry?.direct_info?.cnic || '—';
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-GB') + ' ' + now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    const officerName = row.submitter?.name || row.enquiry?.officer?.name || 'Enquiry Officer';
    const officerDesig = row.submitter?.designation || row.enquiry?.officer?.designation || 'Enquiry Officer';
    const officerPhone = row.submitter?.phone || row.enquiry?.officer?.phone || row.enquiry?.officer?.contact_no || '';
    const rawCircle = row.submitter?.circle?.city || row.enquiry?.complaint?.circle?.city || row.submitter?.circle?.name || row.enquiry?.complaint?.circle?.name || 'Lahore';
    const cleanCity = rawCircle.replace(/circle|zone|nccia-rc|nccia|-/gi, '').trim().toUpperCase() || 'LAHORE';
    const circleCity = cleanCity;
    const circleName = (row.submitter?.circle?.name || row.enquiry?.complaint?.circle?.name || 'Headquarters / Main').replace(/\s*Circle\s*$/i, '');
    const rcName = `NCCIA-RC ${cleanCity}`;
    const zoneName = `NCCIA - ZONE ${cleanCity}`;
    
    const accusedList = row.enquiry?.accused_persons || [];
    const accRows = accusedList.length > 0 
      ? accusedList.map((a, i) => `<div><strong>${i+1}. ${a.name || '—'}</strong> S/O ${a.father_name || '—'} R/O ${a.postal_address || a.permanent_address || '—'}</div>`).join('')
      : '<div>1. Name S/o R/o ________________________________________________</div>';

    const accusedNames = (row.enquiry?.accused_persons || row.enquiry?.accusedPersons || []).map(a => a.name).filter(Boolean);

    const itemRows = (row.items && row.items.length > 0 ? row.items : [{ item_type: 'Device', make_model: 'Seized Device', quantity: 1 }]).map((it, idx) => {
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
        || (accusedNames[idx] ? `Accused: ${accusedNames[idx]}` : (accusedNames.length > 0 ? `Accused: ${accusedNames[0]}` : (complainantName ? `Case: ${complainantName}` : '—')));

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

    const qr = await generateOfficialDocumentQr({
      type: 'forensic',
      id: row.id,
      size: 64,
      fallback: {
        type: 'Forensic Scope Letter',
        number: row.request_no || rawNo,
        complainant: complainantName,
        circle: circleName,
        date: dateStr,
      },
    });
    const qrSvg = qr.svg;
    const qrCaption = qr.caption || (row.request_no || rawNo || `ENQ-${row.enquiry_id || '001'}`).toUpperCase();

    const html = `
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset="utf-8"/>
        <title>Forensic Scope Letter - ${rawNo}</title>
        <style>
          @page { size: A4 portrait; margin: 15mm 15mm; }
          body { font-family: Arial, Helvetica, sans-serif; color: #000; background: #fff; margin: 0; padding: 0; line-height: 1.45; font-size: 13px; }
          .meta-row { display: flex; justify-content: space-between; margin-bottom: 12px; font-size: 13px; }
          .to-sec { margin-bottom: 12px; }
          .subj { font-weight: 800; text-transform: uppercase; border-bottom: 1.5px solid #000; padding-bottom: 4px; margin: 12px 0; font-size: 13px; }
        </style>
      </head>
      <body>
        <table style="width:100%; border-collapse:collapse; border-bottom:2px solid #000; padding-bottom:8px; margin-bottom:16px;">
          <tr>
            <td style="width:80px; vertical-align:middle; text-align:left; border:none; padding:0 8px 8px 0;">
              <img src="/images/images.jpg" alt="NCCIA" style="width:52px; height:52px; object-fit:contain;" />
              <div style="margin-top:4px;">${qrSvg}</div>
              <div style="font-family:monospace; font-size:8.5px; font-weight:bold; margin-top:2px; text-align:center;">${qrCaption}</div>
            </td>
            <td style="vertical-align:middle; text-align:center; border:none; padding:0 8px 8px 8px;">
              <div style="font-size:22px; font-weight:800; letter-spacing:4px; line-height:1.1;">NCCIA</div>
              <div style="font-size:11px; font-weight:700; text-transform:uppercase; margin-top:2px;">National Cyber Crime Investigation Agency</div>
              <div style="font-size:10.5px; font-weight:600; margin-top:2px;">Ministry of Interior &bull; Government of Pakistan</div>
              <div style="font-size:12.5px; font-weight:600; margin-top:3px;">CYBER CRIME REPORTING CENTRE (CCRC) ${circleName.toUpperCase()}</div>
            </td>
            <td style="width:80px; vertical-align:middle; text-align:right; border:none; padding:0 0 8px 8px;">
              <img src="/images/pak-govt-logo.png" alt="Ministry of Interior" style="width:52px; height:52px; object-fit:contain;" />
            </td>
          </tr>
        </table>

        <div class="meta-row">
          <div><strong>${caseLabelUpper} No:</strong> ${enqNoDisplay}</div>
          <div><strong>Dated:</strong> ${dateStr}</div>
        </div>

        <div class="to-sec">
          <strong>THE INCHARGE</strong><br/>
          <strong>NCCIA, CCRC, ${circleName.toUpperCase()}</strong>
        </div>

        <div class="subj">
          SUBJECT: REQUEST FOR PROVIDING FORENSIC ANALYSIS REPORT IN ${caseLabelFull} OF PS. NCCIA, CCRC, ${circleName.toUpperCase()}.
        </div>

        <div><strong>SIR,</strong></div>
        <p style="margin-top:8px;">
          <strong>BRIEF:</strong> ${scopeBackgroundText && scopeBackgroundText.trim() ? scopeBackgroundText.trim() : `THE SUBJECT CASE HAS BEEN REGISTERED AT CCRC ${circleName}, NCCIA IN ${caseLabelUpper} NO. <strong>${enqNoDisplay}</strong>, AND THE BELOW EVIDENTIARY MEDIA REQUIRES FORENSIC ANALYSIS TO CONCLUDE THE INVESTIGATION ON MERIT. THE SUBJECT-CITED ${caseLabelUpper} HAS BEEN REGISTERED AGAINST THE ACCUSED PERSON,`}
        </p>

        <div style="margin: 6px 0 10px 16px; line-height: 1.6;">
          ${accRows}
        </div>

        <p>THE BRIEF CONTENTS OF THE CASE ARE THAT THE ALLEGED PERSON IS INVOLVED IN <strong>${scopeBriefContents || row.brief_contents || 'alleged cybercrime offences'}</strong>.</p>

        <p>DURING THE COURSE OF ENQUIRY/INVESTIGATION, THE RELEVANT EVIDENTIARY MEDIA WAS TAKEN INTO POSSESSION FOR FORENSIC EXAMINATION. THE DETAIL OF THE EVIDENTIARY MEDIA IS AS UNDER:</p>

        <div style="font-weight:800; text-decoration:underline; margin: 14px 0 6px 0;">EVIDENTIARY MEDIA RECOVERED</div>
        <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
          <thead>
            <tr>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;width:35px;">SR. NO.</th>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;">RECOVERED / SEIZED FROM</th>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;">TYPE OF EVIDENTIARY DEVICE</th>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;">MAKE / MODEL</th>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;">IMEI / SERIAL NO</th>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;">STORAGE</th>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;">CONDITION</th>
              <th style="border:1px solid #000;padding:6px;background:#f1f5f9;">DESCRIPTION</th>
            </tr>
          </thead>
          <tbody>
            ${itemRows}
          </tbody>
        </table>

        <div style="font-weight:800; text-decoration:underline; margin: 14px 0 6px 0;">SCOPE FOR FORENSIC ANALYSIS</div>
        <p><strong>YOU ARE REQUESTED TO CONDUCT FORENSIC EXAMINATION AND PROVIDE REPORT ON THE FOLLOWING SCOPE:</strong></p>
        <div style="padding: 10px; border: 1px solid #cbd5e1; background: #f8fafc; white-space: pre-wrap; font-size: 13px; line-height: 1.5; margin-bottom: 12px; min-height: 60px;">
          ${row.analysis_scope || 'Conduct comprehensive forensic examination and data extraction...'}
        </div>

        <p style="margin-top:10px;">
          It is therefore requested that the allied forensic analysis report, as per the above scope, may kindly be furnished at the earliest to enable the undersigned to finalize the instant case/enquiry on merit, please.
        </p>

        <div style="margin-top:16px;">
          <strong>ENCLOSURES:</strong><br/>
          1. COPY OF ${caseLabelUpper} NO. <strong>${enqNoDisplay}</strong><br/>
          2. COPY OF SEIZURE MEMO (RECOVERY MEMO) OF EVIDENTIARY DEVICE
        </div>

        <div style="margin-top: 30px; margin-left: auto; width: 280px; text-align: right; line-height: 1.35;">
          <strong>${officerName}</strong><br/>
          ${officerDesig}<br/>
          ${officerPhone ? `<span>Contact: <strong>${officerPhone}</strong></span><br/>` : ''}
          National Cyber Crime Investigation Agency<br/>
          ${rcName}
        </div>

        <!-- ── 1. Endorsement: Circle Incharge to DD Forensic ── -->
        <div style="margin-top: 22px; border-top: 1.5px dashed #000; padding-top: 12px; page-break-inside: avoid; font-size: 12px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <div>
              <strong>To,</strong><br/>
              <strong>Deputy Director (Forensics),</strong><br/>
              National Cyber Crime Investigation Agency<br/>
              ${zoneName}
            </div>
            <div style="text-align:right;">
              <strong>Dated:</strong> ${row.created_at ? new Date(row.created_at).toLocaleDateString('en-GB') : dateStr}
            </div>
          </div>
          <div style="margin: 8px 0;">
            <strong>Remarks / Order:</strong>
            <div style="margin-top: 3px; padding: 4px 8px; border-bottom: 1px dotted #555; min-height: 20px; font-style: italic;">
              ${(row.note || 'Approved & Forwarded for Forensic Examination.').trim()}
            </div>
          </div>
          <div style="margin-top: 18px; margin-left: auto; width: 280px; text-align: right; line-height: 1.35;">
            <br/>
            <strong>Circle Incharge</strong><br/>
            National Cyber Crime Investigation Agency<br/>
            ${rcName}
          </div>
        </div>

        <!-- ── 2. Endorsement: DD Forensic to AD Forensic ── -->
        <div style="margin-top: 20px; border-top: 1.5px dashed #000; padding-top: 12px; page-break-inside: avoid; font-size: 12px;">
          <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
            <div>
              <strong>To,</strong><br/>
              <strong>Assistant Director (Forensics),</strong><br/>
              National Cyber Crime Investigation Agency<br/>
              ${zoneName}
            </div>
            <div style="text-align:right;">
              <strong>Dated:</strong> ${row.assigned_to_examiner_at ? new Date(row.assigned_to_examiner_at).toLocaleDateString('en-GB') : dateStr}
            </div>
          </div>
          <div style="margin: 8px 0;">
            <strong>Remarks / Order:</strong>
            <div style="margin-top: 3px; padding: 4px 8px; border-bottom: 1px dotted #555; min-height: 20px; font-style: italic;">
              ${(row.examiner_assignment_notes || row.forensic_remarks || 'Marked to AD (Forensics) / Forensic Examiner for examination and detailed forensic report.').trim()}
            </div>
          </div>
          <div style="margin-top: 18px; margin-left: auto; width: 280px; text-align: right; line-height: 1.35;">
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

  if (loading) return <div className="page-content"><LoadingSkeleton type="form" /></div>;
  if (err && !row) return (
    <div className="page-content">
      <div style={{color:'#e53e3e',padding:40,textAlign:'center',background:'#fff',borderRadius:12,border:'1px solid #fee2e2'}}>
        <div style={{fontSize:18,fontWeight:700,marginBottom:8}}>⚠️ {err}</div>
        <p style={{color:'#64748b',fontSize:13,marginBottom:16}}>Could not retrieve forensic request record.</p>
        <button type="button" className="btn btn-primary btn-sm" onClick={load}>Retry Loading</button>
      </div>
    </div>
  );
  if (!row) return null;

  const canAssign        = (isDd || isAdmin) && row.destination === 'forensic';
  const canWorkFindings  = (isAd || isDd || isAdmin) && row.status !== 'handed_over';
  const canApproveAd     = (isAd || isDd || isAdmin) && row.status !== 'handed_over';
  const canHandOver      = (isAd || isDd || isAdmin) && (row.status === 'report_ready' || row.status === 'in_progress');
  const sm               = STATUS_META[row.status] || { label: row.status, color: '#64748b', bg: '#f1f5f9', icon: '' };
  const isExternal       = Boolean(row.is_external || (!row.enquiry_id && !row.case_id && (row.external_organization || row.external_ref)));
  const displayOrg       = row.external_organization || row.submitter?.circle?.name || row.enquiry?.complaint?.circle?.name || 'External / Main';
  const displayPerson    = row.external_person_name || row.submitter?.name || '—';
  const displayContact   = row.external_person_contact || row.submitter?.phone || row.submitter?.email || '—';
  const circleCity       = (row.submitter?.circle?.city || row.enquiry?.complaint?.circle?.city || row.submitter?.circle?.name || row.enquiry?.complaint?.circle?.name || 'Lahore').replace(/\s*Circle\s*$/i, '');
  const circleName       = row.external_organization || row.submitter?.circle?.name || row.enquiry?.complaint?.circle?.name || 'Headquarters / Main';
  const zoneName         = row.enquiry?.complaint?.zone?.name || 'NCCIA';
  const caseRef          = isExternal
    ? (row.external_ref ? `External: ${row.external_ref}` : (row.external_category ? `External: ${row.external_category}` : 'Direct External Seizure'))
    : (row.enquiry?.enquiry_number
      ? `Enquiry #${row.enquiry.enquiry_number}`
      : (row.caseFile?.fir_no ? `FIR #${row.caseFile.fir_no}` : 'Direct Case Seizure'));
  const accusedList      = row.enquiry?.accused_persons || row.enquiry?.accusedPersons || [];
  const complainantName  = row.enquiry?.complaint?.complainant_name || row.external_person_name;

  const rawEoCategory = row?.external_category
    || (row?.enquiry?.complaint?.offence_type ? formatOffenceCategory(row.enquiry.complaint.offence_type) : '')
    || row?.enquiry?.charge_against
    || (row?.enquiry?.direct_info?.crime_category ? formatOffenceCategory(row.enquiry.direct_info.crime_category) : '')
    || 'Blackmailing, Harassment';

  const eoCategory = (rawEoCategory.startsWith('Enquiry #') || rawEoCategory.startsWith('FIR #') || !rawEoCategory.trim())
    ? 'Blackmailing, Harassment'
    : rawEoCategory;

  const displayScopeCategory = additionalScopeCategory
    ? `${eoCategory}, ${additionalScopeCategory}`
    : eoCategory;

  const displayScopeText = isExternal
    ? (row.external_scope || row.note || 'AS PER THE LETTER ATTACHED')
    : (row.note || 'Seized evidence memo submitted for examination');
  const receivedDateTime = row.created_at
    ? new Date(row.created_at).toLocaleString('en-PK',{day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})
    : '—';

  return (
    <div className="page-content" id="forensicPrintArea">

      {/* Header */}
      <div className="page-header" style={{marginBottom:16}}>
        <div className="page-title-group">
          <div className="page-label">
            <Link to="/forensic/requests" style={{color:'inherit',textDecoration:'none'}}>← Forensic Seizure Register</Link>
          </div>
          <div style={{display:'flex',alignItems:'center',gap:10,flexWrap:'wrap'}}>
            <h1 className="page-title" style={{margin:0}}>{row.request_no}</h1>
            <span style={{fontSize:12,fontWeight:700,background:sm.bg,color:sm.color,padding:'4px 12px',borderRadius:20}}>
              {sm.icon} {sm.label}
            </span>
            {row.priority === 'urgent' && (
              <span style={{fontSize:11,background:'#fee2e2',color:'#b91c1c',padding:'3px 8px',borderRadius:6,fontWeight:800}}>⚡ URGENT</span>
            )}
          </div>
          <p className="page-subtitle">Seizure Evidence Provenance &amp; Forensic Chain of Custody Record</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions" style={{display:'flex',gap:8}}>
          <button type="button" className="btn btn-outline btn-sm" onClick={handlePrintScopeLetter} style={{color:'#d97706',borderColor:'#d97706'}}>
            🖨️ Print Scope Letter
          </button>
          {isAd && (
            <button type="button" className="btn btn-primary btn-sm" onClick={handlePrintF31} style={{background:'#015C94'}}>
              🖨️ Print Chain of Custody
            </button>
          )}
          <Link to="/forensic" className="btn btn-outline">Dashboard</Link>
        </div>
      </div>

      {msg && (
        <div style={{padding:'12px 18px',marginBottom:16,background:'#ecfdf5',border:'1px solid #6ee7b7',color:'#065f46',borderRadius:10,fontWeight:600,display:'flex',alignItems:'center',justifyContent:'space-between'}}>
          <span>✅ {msg}</span>
          <button type="button" onClick={()=>setMsg('')} style={{background:'none',border:'none',cursor:'pointer',color:'#065f46',fontWeight:'bold'}}>×</button>
        </div>
      )}
      {err && (
        <div style={{padding:'12px 18px',marginBottom:16,background:'#fef2f2',border:'1px solid #fca5a5',color:'#991b1b',borderRadius:10,fontWeight:600,display:'flex',alignItems:'center',justifyContent:'space-between'}}>
          <span>⚠️ {err}</span>
          <button type="button" onClick={()=>setErr('')} style={{background:'none',border:'none',cursor:'pointer',color:'#991b1b',fontWeight:'bold'}}>×</button>
        </div>
      )}

      {/* Scope Letter Parameters (EO / IO / CI Workbench — only shown to Enquiry / Investigation / Circle Incharge officers) */}
      {!isAd && !isDd && (
        <div className="card" style={{marginBottom:18,border:'1.5px solid #fed7aa',background:'#fffaf5'}}>
          <div className="card-header" style={{padding:'10px 18px',background:'#ffedd5',display:'flex',justifyContent:'space-between',alignItems:'center'}}>
            <div className="card-title" style={{fontSize:13,fontWeight:700,color:'#c2410c'}}>
              📄 Scope Letter: Parameters (EO / IO / CI Workbench)
            </div>
            <span style={{fontSize:11,color:'#ea580c',fontWeight:600}}>Editable before printing Scope Letter</span>
          </div>
          <div className="card-body" style={{padding:'14px 18px'}}>
            <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(220px,1fr))',gap:14,marginBottom:12}}>
              <div className="cf-field">
                <label className="cf-label required" style={{fontSize:12,fontWeight:700,color:'#9a3412'}}>
                  Case Category / Type
                </label>
                <select
                  className="cf-input"
                  value={scopeCaseType}
                  onChange={e => setScopeCaseType(e.target.value)}
                  style={{fontWeight:700}}
                >
                  <option value="enquiry">Enquiry (ENQUIRY NO.)</option>
                  <option value="case">Case FIR (CASE FIR NO.)</option>
                </select>
              </div>
              <div className="cf-field" style={{gridColumn:'span 2'}}>
                <label className="cf-label" style={{fontSize:12,fontWeight:700,color:'#9a3412'}}>
                  Alleged Offences / Brief Case Contents (Editable)
                </label>
                <input
                  className="cf-input"
                  placeholder="e.g. alleged cybercrime offences / Section 13, 14 PECA 2016..."
                  value={scopeBriefContents}
                  onChange={e => setScopeBriefContents(e.target.value)}
                />
              </div>
            </div>

            <div className="cf-field">
              <label className="cf-label" style={{fontSize:12,fontWeight:700,color:'#9a3412'}}>
                Custom Scope Letter Background Paragraph (Optional — overrides default)
              </label>
              <textarea
                className="cf-input"
                rows={2}
                placeholder="Leave blank to use official standard background text, or type custom text here..."
                value={scopeBackgroundText}
                onChange={e => setScopeBackgroundText(e.target.value)}
              />
            </div>
          </div>
        </div>
      )}

      {/* Chain of Custody Open Remarks & Category Editor for AD */}
      {isAd && (
        <div className="card" style={{marginBottom:18,border:'1.5px solid #93c5fd',background:'#f0f9ff'}}>
          <div className="card-header" style={{padding:'10px 18px',background:'#e0f2fe',display:'flex',justifyContent:'space-between',alignItems:'center'}}>
            <div className="card-title" style={{fontSize:13,fontWeight:700,color:'#0369a1'}}>
              📝 Chain of Custody: Scope Categories &amp; Remarks (AD Forensic Workbench)
            </div>
            <span style={{fontSize:11,color:'#0284c7',fontWeight:600}}>Editable before printing Chain of Custody</span>
          </div>
          <div className="card-body" style={{padding:'14px 18px'}}>
            <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(220px,1fr))',gap:14,marginBottom:12}}>
              <div className="cf-field">
                <label className="cf-label" style={{fontSize:12,fontWeight:700,color:'#334155'}}>
                  Original Scope Category (from EO/IO)
                </label>
                <input className="cf-input" value={eoCategory} readOnly style={{background:'#f1f5f9',cursor:'not-allowed',fontWeight:600}} />
              </div>
              <div className="cf-field">
                <label className="cf-label" style={{fontSize:12,fontWeight:700,color:'#0369a1'}}>
                  Add Additional Category (Optional)
                </label>
                <input
                  className="cf-input"
                  placeholder="e.g. Cyber Extortion, Financial Fraud..."
                  value={additionalScopeCategory}
                  onChange={e=>setAdditionalScopeCategory(e.target.value)}
                />
              </div>
              <div className="cf-field">
                <label className="cf-label" style={{fontSize:12,fontWeight:700,color:'#0369a1'}}>
                  Lab No. Base / Register No.
                </label>
                <input
                  className="cf-input"
                  placeholder="e.g. 260007 or 36671"
                  value={customLabNo}
                  onChange={e=>setCustomLabNo(e.target.value)}
                />
              </div>
              <div className="cf-field">
                <label className="cf-label" style={{fontSize:12,fontWeight:700,color:'#0369a1'}}>
                  Date &amp; Time of Receiving (Editable)
                </label>
                <input
                  className="cf-input"
                  placeholder="e.g. 23/08/2026 11:30 AM"
                  value={custodyReceivingDateTime}
                  onChange={e=>setCustodyReceivingDateTime(e.target.value)}
                />
              </div>
            </div>
            <div className="cf-field">
              <label className="cf-label" style={{fontSize:12,fontWeight:700,color:'#334155'}}>
                Chain of Custody Remarks (Open Field for Print)
              </label>
              <textarea
                className="cf-input"
                rows={2}
                placeholder="Enter remarks for Chain of Custody print..."
                value={custodyRemarks}
                onChange={e=>setCustodyRemarks(e.target.value)}
              />
            </div>
          </div>
        </div>
      )}

      {/* Report Code Banner */}
      {row.report_code && (
        <div style={{padding:'18px 24px',marginBottom:20,borderRadius:14,background:'linear-gradient(135deg,#065f46 0%,#0081a7 100%)',color:'#fff',display:'flex',justifyContent:'space-between',alignItems:'center',flexWrap:'gap',gap:14,boxShadow:'0 8px 24px rgba(6,95,70,0.22)'}}>
          <div>
            <div style={{fontSize:12,textTransform:'uppercase',letterSpacing:1,opacity:0.9}}>Official Forensic Report Tracking Code</div>
            <div style={{fontSize:32,fontWeight:900,letterSpacing:1.5,marginTop:2,fontFamily:'monospace'}}>{row.report_code}</div>
            <div style={{fontSize:12,opacity:0.85,marginTop:4}}>EO presents this code at Forensic Lab for physical report collection (By-Hand).</div>
          </div>
          <div style={{background:'rgba(255,255,255,0.15)',padding:'10px 18px',borderRadius:10,textAlign:'center',border:'1px solid rgba(255,255,255,0.3)'}}>
            <div style={{fontSize:11,textTransform:'uppercase',letterSpacing:0.8}}>Status</div>
            <div style={{fontSize:16,fontWeight:800,color:'#a7f3d0'}}>
              {row.status==='handed_over'?'Handed to EO':row.status==='report_ready'?'Ready for Handover':row.status==='submitted_to_ad'?'Submitted to AD':'In Analysis'}
            </div>
          </div>
        </div>
      )}

      {/* Overview Cards */}
      <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(280px,1fr))',gap:16,marginBottom:20}}>
        {/* Card 1: Case & Submitter */}
        <div className="card">
          <div className="card-header" style={{padding:'12px 18px'}}>
            <div className="card-title" style={{fontSize:13.5,fontWeight:700}}>📁 Originating Case &amp; Submitter</div>
            <span style={{fontSize:11,background:isExternal?'#e0e7ff':'#e0f2fe',color:isExternal?'#3730a3':'#0369a1',padding:'2px 8px',borderRadius:10,fontWeight:700}}>
              {isExternal ? 'External Authority' : 'Internal CCRC'}
            </span>
          </div>
          <div className="card-body" style={{padding:'14px 18px',fontSize:13}}>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Case / Reference:</span>
              <strong style={{color:'#0f172a'}}>{caseRef}</strong>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Organization / Circle:</span>
              <span style={{color:'#0f172a',fontWeight:600}}>{displayOrg}</span>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Forwarded / Submitted By:</span>
              <span style={{color:'#0f172a',fontWeight:600}}>{displayPerson}</span>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Contact:</span>
              <span style={{color:'#0f172a'}}>{displayContact}</span>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Scope / Crime Category:</span>
              <span style={{color:'#0f172a',fontWeight:700}}>{displayScopeCategory}</span>
            </div>
            {complainantName && (
              <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
                <span style={{color:'#64748b',fontWeight:600}}>Complainant:</span>
                <span style={{color:'#0f172a',fontWeight:600}}>{complainantName}</span>
              </div>
            )}
            <div style={{display:'flex',justifyContent:'space-between'}}>
              <span style={{color:'#64748b',fontWeight:600}}>Seizure Logged:</span>
              <span style={{color:'#0f172a'}}>{receivedDateTime}</span>
            </div>
          </div>
        </div>

        {/* Card 2: Lab Status & Custody */}
        <div className="card">
          <div className="card-header" style={{padding:'12px 18px'}}>
            <div className="card-title" style={{fontSize:13.5,fontWeight:700}}>🔬 Lab Pipeline &amp; Custody</div>
            {row.report_code && (
              <span style={{fontSize:11,background:'#dcfce7',color:'#15803d',padding:'2px 8px',borderRadius:10,fontWeight:800}}>
                Code: {row.report_code}
              </span>
            )}
          </div>
          <div className="card-body" style={{padding:'14px 18px',fontSize:13}}>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Assigned AD Forensic:</span>
              <strong style={{color:row.assignee?'#0f172a':'#e5a100'}}>{row.assignee?.name || 'Awaiting Assignment'}</strong>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Assigned Date:</span>
              <span style={{color:'#0f172a'}}>{row.assigned_at ? formatDisplayDateTime(row.assigned_at) : '—'}</span>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Examination Began:</span>
              <span style={{color:'#0f172a'}}>{row.opened_at ? formatDisplayDateTime(row.opened_at) : '—'}</span>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:8,borderBottom:'1px solid #f1f5f9',paddingBottom:6}}>
              <span style={{color:'#64748b',fontWeight:600}}>Report Finalized:</span>
              <span style={{color:'#0f172a'}}>{row.report_ready_at ? formatDisplayDateTime(row.report_ready_at) : '—'}</span>
            </div>
            <div style={{display:'flex',justifyContent:'space-between',marginBottom:12}}>
              <span style={{color:'#64748b',fontWeight:600}}>Physical Handover:</span>
              <span style={{color:'#0f172a'}}>{row.handed_over_at ? `Handed Over (${formatDisplayDateTime(row.handed_over_at)})` : 'In Lab Vault'}</span>
            </div>
            {row.attachment_path && (
              <a href={row.attachment_url} target="_blank" rel="noreferrer" className="btn btn-outline btn-sm" style={{display:'inline-flex',alignItems:'center',gap:6,fontSize:11.5}}>
                📄 View Attached Seizure Memo
              </a>
            )}
          </div>
        </div>
      </div>

      {/* Audit Trail & Findings */}
      <div className="card" style={{marginBottom:20}}>
        <div className="card-header" style={{padding:'12px 18px'}}>
          <div className="card-title" style={{fontSize:13.5,fontWeight:700}}>⛓️ Chain of Custody &amp; Findings</div>
        </div>
        <div className="card-body" style={{padding:'16px 20px'}}>
           <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(180px,1fr))',gap:14,marginBottom:20}}>
            {[
              {step:'1. Dispatched',       name:row.submitter?.name||'EO',                                                          date:row.created_at,    color:'#0097a7'},
              {step:'2. AD Assigned',      name:row.assignee?.name?`AD: ${row.assignee.name}`:'Pending',                            date:row.assigned_at,   color:row.assigned_at?'#2563eb':'#cbd5e1'},
              {step:'3. Lab Opened',       name:row.opened_at?'Analysis Active':'Waiting',                                   date:row.opened_at,     color:row.opened_at?'#7c3aed':'#cbd5e1'},
              {step:'4. Report Ready',     name:row.report_ready_at?'Approved':'Pending',                                     date:row.report_ready_at,color:row.report_ready_at?'#059669':'#cbd5e1'},
            ].map((s,i)=>(
              <div key={i} style={{borderLeft:`3px solid ${s.color}`,paddingLeft:12}}>
                <div style={{fontSize:11,color:'#64748b'}}>{s.step}</div>
                <div style={{fontSize:13,fontWeight:700,color:'#0f172a'}}>{s.name}</div>
                <div style={{fontSize:11,color:'#64748b'}}>{s.date?formatDisplayDateTime(s.date):'—'}</div>
              </div>
            ))}
          </div>
          {(row.findings||row.lab_notes)&&(
             <div style={{background:'#f8fafc',padding:14,borderRadius:8,fontSize:13,lineHeight:1.6}}>
               <strong style={{display:'block',marginBottom:4,color:'#0f172a'}}>AD Forensic Findings:</strong>
               <div style={{whiteSpace:'pre-wrap',color:'#334155'}}>{row.findings||'N/A'}</div>
             </div>
          )}
        </div>
      </div>

      {/* Mandatory Submission Checklist Verification Card */}
      <div className="card" style={{marginBottom:20,border:'1.5px solid #dbeafe',background:'#f8fafc'}}>
        <div className="card-header" style={{padding:'12px 18px',background:'#eff6ff',display:'flex',justifyContent:'space-between',alignItems:'center'}}>
          <div className="card-title" style={{fontSize:13.5,fontWeight:800,color:'#1e40af'}}>
            📋 Forensic Submission Checklist &amp; Enclosures
          </div>
          {row.routed_to && (
            <span style={{background:'#fef3c7',color:'#92400e',padding:'3px 10px',borderRadius:12,fontSize:11.5,fontWeight:800,border:'1px solid #fde68a'}}>
              🏛️ Routed to: {row.routed_to}
            </span>
          )}
        </div>
        <div className="card-body" style={{padding:'14px 18px'}}>
          <div style={{display:'flex',gap:16,flexWrap:'wrap'}}>
            <div style={{display:'flex',alignItems:'center',gap:6,fontSize:13,background:row.checklist_tech_report!==false?'#dcfce7':'#fee2e2',padding:'6px 12px',borderRadius:6,border:row.checklist_tech_report!==false?'1px solid #86efac':'1px solid #fca5a5',color:row.checklist_tech_report!==false?'#166534':'#991b1b',fontWeight:700}}>
              {row.checklist_tech_report!==false ? '✅' : '❌'} Technical Report: {row.checklist_tech_report!==false ? 'Attached / Verified' : 'Not Attached'}
            </div>
            <div style={{display:'flex',alignItems:'center',gap:6,fontSize:13,background:row.checklist_seizure_memo!==false?'#dcfce7':'#fee2e2',padding:'6px 12px',borderRadius:6,border:row.checklist_seizure_memo!==false?'1px solid #86efac':'1px solid #fca5a5',color:row.checklist_seizure_memo!==false?'#166534':'#991b1b',fontWeight:700}}>
              {row.checklist_seizure_memo!==false ? '✅' : '❌'} Seizure Memo: {row.checklist_seizure_memo!==false ? 'Attached / Verified' : 'Not Attached'}
            </div>
            {(row.case_id || isExternal || row.checklist_fir_copy) && (
              <div style={{display:'flex',alignItems:'center',gap:6,fontSize:13,background:row.checklist_fir_copy?'#dcfce7':'#f1f5f9',padding:'6px 12px',borderRadius:6,border:row.checklist_fir_copy?'1px solid #86efac':'1px solid #cbd5e1',color:row.checklist_fir_copy?'#166534':'#475569',fontWeight:700}}>
                {row.checklist_fir_copy ? '✅' : 'ℹ️'} FIR / Court Copy: {row.checklist_fir_copy ? 'Attached' : 'N/A'}
              </div>
            )}
            {isExternal && (
              <div style={{display:'flex',alignItems:'center',gap:6,fontSize:13,background:row.checklist_scope_letter?'#dcfce7':'#f1f5f9',padding:'6px 12px',borderRadius:6,border:row.checklist_scope_letter?'1px solid #86efac':'1px solid #cbd5e1',color:row.checklist_scope_letter?'#166534':'#475569',fontWeight:700}}>
                {row.checklist_scope_letter ? '✅' : 'ℹ️'} Scope Letter: {row.checklist_scope_letter ? 'Received &amp; Verified' : 'N/A'}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Audio Forensics Examination Details (Islamabad HQ Routing) */}
      {(row.audio_script || row.audio_source_path || row.audio_sample_path || String(row.external_category || '').toLowerCase().includes('audio') || String(row.external_category || '').toLowerCase().includes('voice')) && (
        <div className="card" style={{marginBottom:20,border:'1.5px solid #fde68a',background:'#fffdf5'}}>
          <div className="card-header" style={{padding:'12px 18px',background:'#fef3c7',display:'flex',justifyContent:'space-between',alignItems:'center'}}>
            <div className="card-title" style={{fontSize:13.5,fontWeight:800,color:'#92400e',display:'flex',alignItems:'center',gap:6}}>
              🎙️ Audio / Voice Forensic Examination (Islamabad HQ)
            </div>
            <span style={{fontSize:11.5,fontWeight:700,background:'#b45309',color:'#fff',padding:'2px 8px',borderRadius:10}}>
              Specialized Voice Analysis
            </span>
          </div>
          <div className="card-body" style={{padding:'16px 18px'}}>
            <div style={{marginBottom:14,padding:'10px 14px',background:'#fffbeb',borderRadius:8,border:'1px solid #fde68a',fontSize:12.5,color:'#92400e'}}>
              🏛️ <strong>Official Notice:</strong> Audio forensics, voice biometric matching and acoustic spectrogram examinations are processed at <strong>NCCIA Forensic HQ, Islamabad</strong>.
            </div>

            {row.audio_script && (
              <div style={{marginBottom:16}}>
                <strong style={{fontSize:13,color:'#0f172a',display:'block',marginBottom:6}}>📝 Written Transcript / Audio Script:</strong>
                <div style={{padding:'12px 14px',background:'#fff',border:'1px solid #e2e8f0',borderRadius:8,fontSize:13,lineHeight:1.6,whiteSpace:'pre-wrap',color:'#1e293b'}}>
                  {row.audio_script}
                </div>
              </div>
            )}

            <div style={{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(280px,1fr))',gap:14}}>
              {row.audio_source_path && (
                <div style={{padding:12,background:'#fff',border:'1px solid #cbd5e1',borderRadius:8}}>
                  <strong style={{fontSize:12.5,color:'#0f172a',display:'block',marginBottom:6}}>🎵 1. Source Audio File (Disputed/Questioned):</strong>
                  <audio controls style={{width:'100%',height:36,marginBottom:8}} src={row.audio_source_url}>
                    Your browser does not support the audio element.
                  </audio>
                  <a href={row.audio_source_url} target="_blank" rel="noreferrer" className="btn btn-outline btn-sm" style={{fontSize:11,display:'inline-flex',alignItems:'center',gap:4}}>
                    📥 Download Source Audio
                  </a>
                </div>
              )}

              {row.audio_sample_path && (
                <div style={{padding:12,background:'#fff',border:'1px solid #cbd5e1',borderRadius:8}}>
                  <strong style={{fontSize:12.5,color:'#0f172a',display:'block',marginBottom:6}}>🎙️ 2. Sample Audio File (Known Voice):</strong>
                  <audio controls style={{width:'100%',height:36,marginBottom:8}} src={row.audio_sample_url}>
                    Your browser does not support the audio element.
                  </audio>
                  <a href={row.audio_sample_url} target="_blank" rel="noreferrer" className="btn btn-outline btn-sm" style={{fontSize:11,display:'inline-flex',alignItems:'center',gap:4}}>
                    📥 Download Sample Audio
                  </a>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Evidence Inventory */}
      <div className="card" style={{marginBottom:20}}>
        <div className="card-header" style={{padding:'14px 18px',background:'#f8fafc',display:'flex',justifyContent:'space-between',alignItems:'center'}}>
          <div className="card-title" style={{fontSize:14,fontWeight:800,color:'#0f172a'}}>
            🗄️ Seized Evidence Items ({row.items?.length||0} items)
          </div>
          {isAd && <span style={{fontSize:11.5,fontWeight:600,color:'#0284c7'}}>💡 Device physical condition can be updated by AD Forensic</span>}
        </div>
        <div className="card-body" style={{padding:0}}>
          <div style={{overflowX:'auto'}}>
            <table className="data-table" style={{width:'100%',minWidth:700}}>
              <thead>
                <tr>
                  <th style={{padding:12}}>Category</th>
                  <th>Item / Model</th>
                  <th>Identifiers</th>
                  <th>Qty</th>
                  <th>Condition</th>
                </tr>
              </thead>
              <tbody>
                {(row.items||[]).map((it,i)=>(
                  <tr key={it.id||i}>
                    <td style={{padding:12,fontWeight:600}}>{itemLabel(it.item_type)}</td>
                    <td>{it.make_model||'—'}</td>
                    <td>{it.imei||it.serial_no||'—'}</td>
                    <td>{it.quantity||1}</td>
                    <td>
                      {isAd ? (
                        <select
                          className="cf-input"
                          style={{padding:'4px 8px',fontSize:12,width:'auto',fontWeight:600}}
                          value={it.condition || 'sealed'}
                          onChange={e => updateItemCondition(it.id, e.target.value)}
                          disabled={busy}
                        >
                          <option value="sealed">Sealed / Evidence Bag</option>
                          <option value="good">Intact / Good</option>
                          <option value="damaged">Damaged / Broken</option>
                          <option value="locked">PIN / Pattern Locked</option>
                        </select>
                      ) : (
                        <span style={{fontSize:12,fontWeight:600,color:'#475569'}}>
                          {it.condition === 'good' ? 'Intact / Good' : it.condition === 'damaged' ? 'Damaged / Broken' : it.condition === 'locked' ? 'PIN / Pattern Locked' : 'Sealed / Evidence Bag'}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Action 1: DD Assign to AD Forensic */}
      {canAssign && (
        <div className="card" style={{marginBottom:20,border:'1.5px solid #bfdbfe'}}>
          <div className="card-header" style={{background:'#eff6ff'}}><div className="card-title" style={{color:'#1e40af'}}>🏛️ DD Forensic: Mark &amp; Assign to Assistant Director (AD) Forensic</div></div>
          <div className="card-body">
            <div style={{display:'grid',gridTemplateColumns:'1fr 1fr',gap:14,marginBottom:12}}>
              <div className="cf-field">
                <label className="cf-label required">Select AD Forensic</label>
                <select className="cf-input" value={assignedTo} onChange={e=>setAssignedTo(e.target.value)}>
                  <option value="">— Select AD Forensic —</option>
                  {officers.map(o=><option key={o.id} value={o.id}>{o.name} ({o.designation||'AD Forensic'})</option>)}
                </select>
              </div>
              <div className="cf-field">
                <label className="cf-label">Priority Level</label>
                <select className="cf-input" value={assignPriority} onChange={e=>setAssignPriority(e.target.value)}>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">⚡ Urgent (Court / High Sensitivity)</option>
                </select>
              </div>
            </div>
            <div className="cf-field" style={{marginBottom:14}}>
              <label className="cf-label">Directives &amp; Examination Instructions</label>
              <input className="cf-input" placeholder="e.g. Physical extraction of WhatsApp chats, CDRs..." value={remarks} onChange={e=>setRemarks(e.target.value)} />
            </div>
            <div style={{display:'flex',gap:10,flexWrap:'wrap'}}>
              <button type="button" className="btn btn-primary" disabled={busy||!assignedTo} onClick={assign}>{busy?'Assigning…':'Mark / Assign to AD Forensic'}</button>
              <button type="button" className="btn btn-outline" style={{borderColor:'#e11d48',color:'#e11d48'}} onClick={() => { setSendBackTarget('ci'); setSendBackModal(true); }}>↩️ Send Back to Circle Incharge</button>
              <button type="button" className="btn btn-outline" style={{borderColor:'#e11d48',color:'#e11d48'}} onClick={() => { setSendBackTarget('eo'); setSendBackModal(true); }}>↩️ Send Back to EO</button>
            </div>
          </div>
        </div>
      )}

      {/* Action 2: AD Forensic Workbench */}
      {canWorkFindings && (
        <div className="card" style={{marginBottom:20,border:'1.5px solid #ddd6fe'}}>
          <div className="card-header" style={{background:'#f5f3ff'}}><div className="card-title" style={{color:'#6d28d9'}}>🔬 AD Forensic Workbench: Record Findings &amp; Analysis</div></div>
          <div className="card-body">
            
            {/* Quick Status Update Bar */}
            <div style={{display:'flex',alignItems:'center',gap:12,marginBottom:16,padding:'10px 14px',background:'#f1f5f9',borderRadius:8,flexWrap:'wrap'}}>
              <span style={{fontSize:13,fontWeight:700,color:'#334155'}}>Status Update:</span>
              <div style={{display:'flex',gap:8,flexWrap:'wrap'}}>
                <button
                  type="button"
                  className={`btn btn-sm ${row.status === 'assigned' ? 'btn-primary' : 'btn-outline'}`}
                  style={row.status === 'assigned' ? {background:'#2563eb'} : {}}
                  disabled={busy}
                  onClick={() => updateReqStatus('assigned')}
                >
                  ⏳ Pending
                </button>
                <button
                  type="button"
                  className={`btn btn-sm ${row.status === 'in_progress' ? 'btn-primary' : 'btn-outline'}`}
                  style={row.status === 'in_progress' ? {background:'#7c3aed'} : {}}
                  disabled={busy}
                  onClick={() => updateReqStatus('in_progress')}
                >
                  🔬 Working (In Progress)
                </button>
                <button
                  type="button"
                  className={`btn btn-sm ${row.status === 'report_ready' ? 'btn-primary' : 'btn-outline'}`}
                  style={row.status === 'report_ready' ? {background:'#059669'} : {}}
                  disabled={busy}
                  onClick={() => updateReqStatus('report_ready')}
                >
                  ✅ Completed
                </button>
              </div>
            </div>

            <div className="cf-field" style={{marginBottom:12}}>
              <label className="cf-label">Forensic Examination Findings</label>
              <textarea className="cf-input" rows={4} placeholder="Enter extraction results, hash values, chat logs, call records..." value={findings} onChange={e=>setFindings(e.target.value)} />
            </div>
            <div className="cf-field" style={{marginBottom:12}}>
              <label className="cf-label">Internal Lab Notes &amp; Tool Logs</label>
              <textarea className="cf-input" rows={2} placeholder="Tools used: UFED, Oxygen, Magnet AXIOM, EnCase, FTK Imager..." value={labNotes} onChange={e=>setLabNotes(e.target.value)} />
            </div>
            <div className="cf-field" style={{marginBottom:16}}>
              <label className="cf-label">Upload Lab Report PDF / Archive <span style={{fontSize:11,color:'#64748b',fontWeight:400}}>(Optional — not mandatory)</span></label>
              <input type="file" className="cf-input" accept=".pdf,.doc,.docx,.zip" onChange={e=>setReportFile(e.target.files?.[0]||null)} />
            </div>
            <div style={{display:'flex',gap:10,flexWrap:'wrap'}}>
              <button type="button" className="btn btn-outline" disabled={busy} onClick={saveFindings}>{busy?'Saving…':'Save Findings Draft'}</button>
              <button type="button" className="btn btn-primary" style={{background:'#7c3aed'}} disabled={busy} onClick={approveAndNotify}>{busy?'Approving & Notifying…':'✅ Finalize Report & Notify EO'}</button>
              <button type="button" className="btn btn-outline" style={{borderColor:'#e11d48',color:'#e11d48'}} onClick={() => { setSendBackTarget('dd'); setSendBackModal(true); }}>↩️ Return / Send Back to DD Forensic</button>
            </div>
          </div>
        </div>
      )}

      {/* Action 3: Custody Handover */}
      {canHandOver && (
        <div className="card" style={{marginBottom:20,border:'1.5px solid #cbd5e1'}}>
          <div className="card-header" style={{background:'#f8fafc'}}><div className="card-title" style={{color:'#334155'}}>📦 Forensic Lab: Physical Evidence Handover to EO</div></div>
          <div className="card-body">
            <p style={{fontSize:13,color:'#334155',marginBottom:10}}>Confirm physical signed lab report and sealed evidence bag handed over to EO. Report code: <strong>{row.report_code}</strong></p>
            <div className="cf-field" style={{marginBottom:14}}>
              <label className="cf-label">Handover Remarks</label>
              <input className="cf-input" placeholder="e.g. Physical sealed packet handed to EO with signature on register." value={handoverRemarks} onChange={e=>setHandoverRemarks(e.target.value)} />
            </div>
            <button type="button" className="btn btn-primary" style={{background:'#059669'}} disabled={busy} onClick={handOver}>{busy?'Confirming…':'Confirm Custody Handover to EO'}</button>
          </div>
        </div>
      )}

      {/* Printable F-31 Chain of Custody */}
      <div id="f31PrintArea" style={{display:'none'}}>
        <table style={{width:'100%',borderCollapse:'collapse',borderBottom:'2px solid #000',paddingBottom:8,marginBottom:12}}>
          <tbody>
            <tr>
              <td style={{width:80,verticalAlign:'middle',textAlign:'left',border:'none',padding:0}}>
                <img src="/images/images.jpg" alt="NCCIA" style={{width:52,height:52,objectFit:'contain'}} />
                {f31QrSvg ? (
                  <div dangerouslySetInnerHTML={{ __html: f31QrSvg }} />
                ) : (
                  <div style={{fontSize:9,fontWeight:700,textAlign:'center'}}>QR</div>
                )}
                <div style={{fontFamily:'monospace',fontSize:8,fontWeight:'bold',marginTop:1,textAlign:'center'}}>
                  {row.request_no || row.report_code || 'NCCIA-FL'}
                </div>
              </td>
              <td style={{verticalAlign:'middle',textAlign:'center',border:'none',padding:'0 10px'}}>
                <div style={{fontSize:22,fontWeight:800,letterSpacing:4,lineHeight:1.05}}>NCCIA</div>
                <div style={{fontSize:11,fontWeight:700,textTransform:'uppercase',marginTop:2}}>National Cyber Crime Investigation Agency</div>
                <div style={{fontSize:10.5,fontWeight:600,color:'#222',marginTop:1}}>Ministry of Interior • Government of Pakistan</div>
                <div style={{fontSize:10.5,fontWeight:700,color:'#1a3d6b',marginTop:2}}>Forensic Laboratory • NCCIA - ZONE {circleCity}</div>
                <div style={{fontSize:13,fontWeight:800,textDecoration:'underline',marginTop:4,letterSpacing:0.5}}>CHAIN OF CUSTODY</div>
              </td>
              <td style={{width:80,verticalAlign:'middle',textAlign:'right',border:'none',padding:0}}>
                <img src="/images/pak-govt-logo.png" alt="Ministry of Interior" style={{width:52,height:52,objectFit:'contain'}} />
              </td>
            </tr>
          </tbody>
        </table>

        <table style={{width:'100%',borderCollapse:'collapse',marginBottom:14}}>
          <tbody>
            <tr>
              <td style={{border:'1px solid #000',padding:'5px 7px',fontWeight:'bold',background:'#f5f5f5',width:'38%'}}>Laboratory Case File No.</td>
              <td style={{border:'1px solid #000',padding:'5px 7px',width:'22%'}}>{row.request_no}</td>
              <td style={{border:'1px solid #000',padding:'5px 7px',fontWeight:'bold',background:'#f5f5f5',width:'18%'}}>Date &amp; Time of Receiving</td>
              <td style={{border:'1px solid #000',padding:'5px 7px',fontWeight:'bold'}}>{custodyReceivingDateTime || receivedDateTime || '_________________________'}</td>
            </tr>
            <tr>
              <td style={{border:'1px solid #000',padding:'5px 7px',fontWeight:'bold',background:'#f5f5f5',verticalAlign:'top'}}>Name of the Organization from which the equipment is received</td>
              <td colSpan={3} style={{border:'1px solid #000',padding:'5px 7px'}}>
                <div><strong>Organization:</strong> {displayOrg}</div>
                <div><strong>Name:</strong> {displayPerson}</div>
                <div><strong>Contact No.:</strong> {displayContact}</div>
                {!isExternal && complainantName && <div><strong>Complainant:</strong> {complainantName}</div>}
              </td>
            </tr>
            <tr>
              <td style={{border:'1px solid #000',padding:'5px 7px',fontWeight:'bold',background:'#f5f5f5',verticalAlign:'top'}}>Type of evidence to be required by the said organization</td>
              <td colSpan={3} style={{border:'1px solid #000',padding:'5px 7px'}}>
                <div><strong>Scope: Category:</strong> {displayScopeCategory}</div>
                <div style={{marginTop:6,fontFamily:'monospace',fontWeight:'bold',letterSpacing:0.5}}>AS PER THE LETTER ATTACHED</div>
              </td>
            </tr>
            <tr>
              <td style={{border:'1px solid #000',padding:'5px 7px',fontWeight:'bold',background:'#f5f5f5'}}>Remarks</td>
              <td colSpan={3} style={{border:'1px solid #000',padding:'5px 7px',minHeight:24}}>
                {custodyRemarks || ''}
              </td>
            </tr>
          </tbody>
        </table>

        <div style={{fontWeight:'bold',background:'#c0c0c0',textAlign:'center',fontSize:12,padding:5,border:'1px solid #000',borderBottom:'none'}}>Detail of Electronic Equipment(s) Received:</div>
        <table style={{width:'100%',borderCollapse:'collapse',marginBottom:14}}>
          <thead>
            <tr>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',width:35,textAlign:'center'}}>No</th>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',textAlign:'center'}}>Description of the Evidence</th>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',width:'34%',textAlign:'center'}}>Serial No / IMEI No.</th>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',width:'22%',textAlign:'center'}}>Remarks</th>
            </tr>
          </thead>
          <tbody>
            {(row.items||[]).length>0?(row.items||[]).map((it,i)=>{
              const catName = it.item_type ? (ITEM_LABELS[it.item_type]?.label || it.item_type) : 'Mobile Phone';
              let brandStr = it.make_model || 'Oppo Reno 14F';
              let modelStr = it.model || '';
              if (it.make_model && it.make_model.includes('/')) {
                const parts = it.make_model.split('/');
                brandStr = parts[0].trim();
                modelStr = parts[1].trim();
              }
              const imeis = [];
              if (it.imei) imeis.push(it.imei);
              if (it.imei2) imeis.push(it.imei2);

              const labInputParts = customLabNo ? customLabNo.split(/[,;\n]+/).map(s => s.trim()).filter(Boolean) : [];
              let itemLabNo = '';
              if (labInputParts.length > i) {
                itemLabNo = labInputParts[i];
              } else {
                const rawBase = (customLabNo || (row.report_code ? row.report_code.replace(/[^0-9]/g, '') : (row.request_no ? row.request_no.replace(/[^0-9]/g, '') : '36671'))).trim();
                itemLabNo = (row.items || []).length > 1
                  ? `${rawBase}/${String(i + 1).padStart(2, '0')}`
                  : rawBase;
              }

                const accusedNames = (row.enquiry?.accused_persons || row.enquiry?.accusedPersons || []).map(a => a.name).filter(Boolean);
                const seizedFromPerson = it.seized_from
                  || (accusedNames[i] ? accusedNames[i] : (accusedNames.length > 0 ? accusedNames[0] : (complainantName || '—')));

                return (
                  <tr key={i}>
                    <td style={{border:'1px solid #000',padding:'5px 7px',textAlign:'center',verticalAlign:'top',fontWeight:'bold'}}>{i+1}</td>
                    <td style={{border:'1px solid #000',padding:'5px 7px',verticalAlign:'top',lineHeight:1.5}}>
                      <div><strong>Category:</strong> {catName}</div>
                      <div><strong>Brand:</strong> {brandStr}</div>
                      {modelStr && <div><strong>Model:</strong> {modelStr}</div>}
                      {it.storage_capacity && <div style={{fontSize:10.5,color:'#444'}}>Storage: {it.storage_capacity} GB</div>}
                      {seizedFromPerson && <div style={{marginTop:3,fontSize:11,color:'#0f172a'}}><strong>Seized From:</strong> {seizedFromPerson}</div>}
                    </td>
                    <td style={{border:'1px solid #000',padding:'5px 7px',verticalAlign:'top',lineHeight:1.5}}>
                      {(() => {
                        const isMobile = ['phone', 'mobile_phone', 'sim', 'tablet', 'ipad'].includes((it.item_type || '').toLowerCase());
                        const hasImei1 = Boolean(it.imei && it.imei.trim());
                        const hasImei2 = Boolean(it.imei2 && it.imei2.trim());
                        if (isMobile) {
                          return (
                            <>
                              {hasImei1 && hasImei2 ? (
                                <>
                                  <div><strong>IMEI 1:</strong> {it.imei.trim()}</div>
                                  <div><strong>IMEI 2:</strong> {it.imei2.trim()}</div>
                                </>
                              ) : (hasImei1 || hasImei2) ? (
                                <div><strong>IMEI:</strong> {(it.imei || it.imei2).trim()}</div>
                              ) : null}
                              {it.serial_no && <div><strong>Serial No:</strong> {it.serial_no.trim()}</div>}
                              {!hasImei1 && !hasImei2 && !it.serial_no && '—'}
                            </>
                          );
                        } else {
                          const serialVal = (it.serial_no || it.imei || it.imei2 || '').trim();
                          return serialVal ? (
                            <div><strong>Serial No:</strong> {serialVal}</div>
                          ) : '—';
                        }
                      })()}
                    </td>
                    <td style={{border:'1px solid #000',padding:'5px 7px',verticalAlign:'middle',textAlign:'center'}}>
                      <div style={{fontWeight:'bold',fontSize:10.5}}>[Lab No: {itemLabNo || '—'}]</div>
                    </td>
                  </tr>
                );
            }):(
              <tr>
                <td style={{border:'1px solid #000',padding:'5px 7px',textAlign:'center',height:40}}>1</td>
                <td style={{border:'1px solid #000',padding:'5px 7px'}}></td>
                <td style={{border:'1px solid #000',padding:'5px 7px'}}></td>
                <td style={{border:'1px solid #000',padding:'5px 7px'}}></td>
              </tr>
            )}
          </tbody>
        </table>

        <div style={{fontWeight:'bold',background:'#c0c0c0',textAlign:'center',fontSize:12,padding:5,border:'1px solid #000',borderBottom:'none'}}>Chain of Custody Log:</div>
        <table style={{width:'100%',borderCollapse:'collapse'}}>
          <thead>
            <tr>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',width:35,textAlign:'center'}}>S. No</th>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',textAlign:'center'}}>Received From / Signature / Date &amp; Time</th>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',textAlign:'center'}}>Received By / Signature / Date &amp; Time</th>
              <th style={{border:'1px solid #000',padding:'5px 7px',background:'#d0d0d0',width:'22%',textAlign:'center'}}>Remarks</th>
            </tr>
          </thead>
          <tbody>
            {[
              {
                from_name: row.submitter?.name||'—',
                from_des: (row.submitter?.designation||'Enquiry Officer')+' · '+circleName,
                from_date: null,
                to_name: row.assignee?.name||row.adReviewer?.name||'AD Forensic',
                to_des: 'Assistant Director Forensic',
                to_date: custodyReceivingDateTime || receivedDateTime,
                remark:'',
              },
              {
                from_name: row.assignee?.name||row.adReviewer?.name||'AD Forensic',
                from_des: 'Assistant Director Forensic',
                from_date: row.opened_at?formatDisplayDateTime(row.opened_at):(row.assigned_at?formatDisplayDateTime(row.assigned_at):null),
                to_name: row.assignee?.name||'AD Forensic',
                to_des: 'Forensic Examination',
                to_date: row.report_ready_at?formatDisplayDateTime(row.report_ready_at):null,
                remark: '',
              },
              {
                from_name: row.assignee?.name||row.adReviewer?.name||'AD Forensic',
                from_des: 'Assistant Director Forensic',
                from_date: row.handed_over_at?formatDisplayDateTime(row.handed_over_at):null,
                to_name: row.handedTo?.name||row.submitter?.name||'—',
                to_des: 'Enquiry Officer',
                to_date: row.handed_over_at?formatDisplayDateTime(row.handed_over_at):null,
                remark: '',
              },
            ].map((r2,i)=>(
              <tr key={i}>
                <td style={{border:'1px solid #000',padding:'5px 7px',textAlign:'center',verticalAlign:'top'}}>{i+1}</td>
                <td style={{border:'1px solid #000',padding:'5px 7px',verticalAlign:'top',height:65}}>
                  <div><strong>{r2.from_name}</strong></div>
                  <div style={{fontSize:10,color:'#555'}}>{r2.from_des}</div>
                  <div style={{fontSize:10}}>{r2.from_date||' '}</div>
                  <div style={{marginTop:8,borderTop:'1px solid #aaa',paddingTop:4,fontSize:10}}>Signature: ________________</div>
                </td>
                <td style={{border:'1px solid #000',padding:'5px 7px',verticalAlign:'top',height:65}}>
                  <div><strong>{r2.to_name}</strong></div>
                  <div style={{fontSize:10,color:'#555'}}>{r2.to_des}</div>
                  <div style={{fontSize:10}}>{r2.to_date||' '}</div>
                  <div style={{marginTop:8,borderTop:'1px solid #aaa',paddingTop:4,fontSize:10}}>Signature: ________________</div>
                </td>
                <td style={{border:'1px solid #000',padding:'5px 7px',verticalAlign:'top'}}>{r2.remark}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Hierarchical Send Back Modal */}
      {sendBackModal && (
        <div style={{
          position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.5)', backdropFilter: 'blur(3px)',
          display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 9999, padding: 16,
        }}>
          <div style={{ background: '#fff', borderRadius: 14, maxWidth: 520, width: '100%', padding: 22, boxShadow: '0 20px 40px rgba(0,0,0,0.2)' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 14 }}>
              <h2 style={{ fontSize: 16, fontWeight: 700, color: '#e11d48', margin: 0 }}>
                ↩️ Return / Send Back Request ({row.request_no})
              </h2>
              <button type="button" onClick={() => setSendBackModal(false)} style={{ border: 'none', background: 'none', fontSize: 20, cursor: 'pointer' }}>×</button>
            </div>

            <div className="cf-field" style={{ marginBottom: 14 }}>
              <label className="cf-label required" style={{ fontSize: 12, fontWeight: 700 }}>Send Back Destination Hierarchy</label>
              <select
                className="cf-input"
                value={sendBackTarget}
                onChange={e => setSendBackTarget(e.target.value)}
                style={{ fontWeight: 600 }}
              >
                {isAd && <option value="dd">1. Send Back to Deputy Director (DD) Forensic</option>}
                {(isDd || isAdmin || isAd) && <option value="ci">2. Send Back to Circle Incharge (CI)</option>}
                <option value="eo">3. Send Back to Enquiry Officer / IO (EO)</option>
              </select>
            </div>

            <div className="cf-field" style={{ marginBottom: 16 }}>
              <label className="cf-label required" style={{ fontSize: 12, fontWeight: 700 }}>
                Reason / Deficiency Remarks
              </label>
              <textarea
                className="cf-input"
                rows={3}
                placeholder="State the reason for return (e.g. Device password missing, scope clarification needed, chain of custody correction)..."
                value={sendBackRemarks}
                onChange={e => setSendBackRemarks(e.target.value)}
                required
              />
            </div>

            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 10 }}>
              <button type="button" className="btn btn-outline" onClick={() => setSendBackModal(false)}>Cancel</button>
              <button
                type="button"
                className="btn btn-primary"
                style={{ background: '#e11d48', borderColor: '#e11d48' }}
                disabled={busy || !sendBackRemarks.trim()}
                onClick={submitSendBack}
              >
                {busy ? 'Returning…' : 'Confirm & Dispatch Notification'}
              </button>
            </div>
          </div>
        </div>
      )}

    </div>
  );
}

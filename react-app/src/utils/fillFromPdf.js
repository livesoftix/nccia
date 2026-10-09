import api from '../api';
import { extractTextFromPdf } from './pdfOcr';

export function digitsPhone(raw) {
  if (!raw) return '';
  let d = String(raw).replace(/\D/g, '');
  if (d.startsWith('92') && d.length > 10) d = d.slice(2);
  if (d.startsWith('0')) d = d.replace(/^0+/, '');
  return d.slice(0, 10);
}

export function titleGender(raw) {
  const g = String(raw || '').toLowerCase();
  if (g.startsWith('f')) return 'Female';
  if (g.startsWith('o')) return 'Other';
  if (g.startsWith('m')) return 'Male';
  return '';
}

export async function ocrAndParsePdf(file, onStatus) {
  onStatus?.('Scanning PDF document…');
  const ocrText = await extractTextFromPdf(file, onStatus, {
    maxPages: 3,
    stopWhenUseful: true,
  });

  if (!ocrText || ocrText.trim().length < 15) {
    throw new Error('PDF se text nahi parha ja saka. Scan quality check karein.');
  }

  onStatus?.('Parsing verification report details…');
  const r = await api.post('/complaint-pdf-imports/preview', {
    ocr_text: ocrText,
    filename: file.name,
  });

  return {
    extracted: r.data?.extracted || {},
    fieldsOk: r.data?.fields_ok !== false,
    error: r.data?.error || null,
    ocrText,
  };
}

export function matchLookupValue(options, raw) {
  const q = String(raw || '').toLowerCase().replace(/\s+/g, ' ').trim();
  if (!q || !Array.isArray(options) || !options.length) return '';
  const valOf = (o) => String(o?.value ?? o?.name ?? o ?? '').trim();
  const nameOf = (o) => String(o?.name ?? o?.value ?? o ?? '').trim();
  const exact = options.find(o => valOf(o).toLowerCase() === q || nameOf(o).toLowerCase() === q);
  if (exact) return valOf(exact);
  const partial = options.find(o => {
    const n = nameOf(o).toLowerCase();
    const v = valOf(o).toLowerCase();
    return (n && (n.includes(q) || q.includes(n))) || (v && (v.includes(q) || q.includes(v)));
  });
  return partial ? valOf(partial) : '';
}

export function formatCnic(raw) {
  const d = String(raw || '').replace(/\D/g, '');
  if (d.length !== 13) return String(raw || '').trim();
  return `${d.slice(0, 5)}-${d.slice(5, 12)}-${d.slice(12)}`;
}

export function isLikelyPersonName(raw) {
  const n = String(raw || '').trim();
  if (!n || n.length < 3 || n.length > 80) return false;
  if (/^(defraud|during|the |accuse|online|city|amount|brief)/i.test(n)) return false;
  if ((n.match(/\s+/g) || []).length > 6) return false;
  return /[A-Za-z]{2,}/.test(n);
}

function compactFilled(obj) {
  const out = {};
  Object.entries(obj).forEach(([k, v]) => {
    if (v === '' || v == null) return;
    if (Array.isArray(v) && v.length === 0) return;
    out[k] = v;
  });
  return out;
}

function dateOnly(d) {
  if (!d) return '';
  const s = String(d);
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
  return '';
}

function dateToDateTimeLocal(d) {
  const day = dateOnly(d);
  return day ? `${day}T00:00` : '';
}

function mapOccupation(raw) {
  const v = String(raw || '').toLowerCase();
  if (!v) return '';
  if (/private/.test(v)) return 'Private Employee';
  if (/gov|government/.test(v)) return 'Government Servant';
  if (/business|shop/.test(v)) return 'Business';
  if (/student/.test(v)) return 'Student';
  if (/unemploy/.test(v)) return 'Unemployed';
  if (/house/.test(v)) return 'Housewife';
  return '';
}

export function mapExtractToComplaintForm(extracted) {
  const phone = digitsPhone(extracted.victim_phone);
  const accusedList = [];

  if (Array.isArray(extracted.accused) && extracted.accused.length > 0) {
    extracted.accused.forEach((a) => {
      accusedList.push({
        name: isLikelyPersonName(a.name) ? a.name : (a.name || ''),
        father_name: a.father_name || '',
        mobile_no: digitsPhone(a.mobile_no || a.phone),
        cnic: formatCnic(a.cnic),
        email: a.email || '',
        social_media_url: a.social_media_url || '',
        other_info: a.bank_account ? `${a.bank_name || 'Bank'}: ${a.bank_account}` : (a.other_info || ''),
        description: a.description || extracted.crime_description || '',
        cnic_front: '',
        cnic_back: '',
        picture: '',
        passport_attachment: '',
      });
    });
  } else if (isLikelyPersonName(extracted.accused_name) || extracted.accused_cnic || extracted.accused_phone) {
    accusedList.push({
      name: isLikelyPersonName(extracted.accused_name) ? extracted.accused_name : '',
      father_name: '',
      mobile_no: digitsPhone(extracted.accused_phone),
      cnic: formatCnic(extracted.accused_cnic),
      email: '',
      social_media_url: '',
      other_info: '',
      description: extracted.crime_description || '',
      cnic_front: '',
      cnic_back: '',
      picture: '',
      passport_attachment: '',
    });
  }

  return compactFilled({
    complainant_name: extracted.victim_name || '',
    father_name: extracted.victim_father_name || '',
    cnic: formatCnic(extracted.victim_cnic),
    gender: titleGender(extracted.victim_gender),
    contact_no: phone,
    whatsapp_no: phone,
    email: extracted.victim_email || '',
    profession: extracted.victim_occupation || '',
    address: extracted.victim_address || '',
    post_address: extracted.victim_permanent_address || extracted.victim_address || '',
    district: extracted.city || '',
    description: extracted.crime_description || extracted.recommendation_full || '',
    amount_involved: extracted.amount_involved != null ? String(extracted.amount_involved) : '',
    occurrence_date: dateOnly(extracted.verification_date || extracted.assignment_date),
    report_date: dateOnly(extracted.assignment_date || extracted.verification_date),
    diary_no: extracted.inquiry_no || extracted.tracking_no || '',
    received_via: 'Online Platform',
    received_from: 'General Public',
    cmu: 'CCRC',
    initial_accused: accusedList,
  });
}

export function mapExtractToVerificationReport(extracted) {
  const accusedList = [];

  if (Array.isArray(extracted.accused) && extracted.accused.length > 0) {
    extracted.accused.forEach((a) => {
      accusedList.push({
        name: isLikelyPersonName(a.name) ? a.name : (a.name || ''),
        father_name: a.father_name || '',
        phone: digitsPhone(a.mobile_no || a.phone),
        email: a.email || '',
        country_code: '+92',
        cnic: formatCnic(a.cnic),
        address: a.address || '',
        post_address: a.post_address || '',
        nationality: 'Pakistani',
        passport_no: a.passport_no || '',
        photo: null,
        cnic_front: '',
        cnic_back: '',
        passport_attachment: '',
        picture: '',
        other_attachment: '',
      });
    });
  } else if (isLikelyPersonName(extracted.accused_name) || extracted.accused_cnic || extracted.accused_phone) {
    accusedList.push({
      name: isLikelyPersonName(extracted.accused_name) ? extracted.accused_name : '',
      father_name: '',
      phone: digitsPhone(extracted.accused_phone),
      email: '',
      country_code: '+92',
      cnic: formatCnic(extracted.accused_cnic),
      address: '',
      post_address: '',
      nationality: 'Pakistani',
      passport_no: '',
      photo: null,
      cnic_front: '',
      cnic_back: '',
      passport_attachment: '',
      picture: '',
      other_attachment: '',
    });
  }

  return compactFilled({
    tracking_no: extracted.tracking_no || '',
    assignment_date: dateToDateTimeLocal(extracted.assignment_date),
    verification_date: dateToDateTimeLocal(extracted.verification_date),
    victim_name: extracted.victim_name || '',
    victim_father_name: extracted.victim_father_name || '',
    victim_occupation: extracted.victim_occupation || '',
    victim_gender: String(extracted.victim_gender || '').toLowerCase() || '',
    victim_cnic: formatCnic(extracted.victim_cnic),
    victim_phone: digitsPhone(extracted.victim_phone),
    victim_email: extracted.victim_email || '',
    crime_category: extracted.crime_category || '',
    city: extracted.city || '',
    crime_description: extracted.crime_description || '',
    accused_known: accusedList.length ? '1' : '0',
    accused: accusedList,
    recommendation: extracted.recommendation || '',
    recommendation_short: extracted.recommendation_short || '',
    recommendation_full: extracted.recommendation_full || '',
    inquiry_no: extracted.inquiry_no || '',
  });
}

export function mapExtractToDirectInfo(extracted, current = {}) {
  const phone = digitsPhone(extracted.victim_phone);
  const mobile = phone ? (phone.length === 10 ? `0${phone}` : phone) : '';
  const occ = dateToDateTimeLocal(extracted.verification_date || extracted.assignment_date);
  const rec = dateToDateTimeLocal(extracted.assignment_date || extracted.verification_date);

  const mappedAccused = [];
  if (Array.isArray(extracted.accused) && extracted.accused.length > 0) {
    extracted.accused.forEach((a) => {
      const aPhone = digitsPhone(a.mobile_no || a.phone);
      mappedAccused.push({
        name: isLikelyPersonName(a.name) ? a.name : (a.name || ''),
        father_name: a.father_name || '',
        cnic: String(a.cnic || '').replace(/\D/g, '').slice(0, 13),
        gender: '',
        contact_no: aPhone ? (aPhone.length === 10 ? `0${aPhone}` : aPhone) : '',
        whatsapp_no: aPhone ? (aPhone.length === 10 ? `0${aPhone}` : aPhone) : '',
        email: a.email || '',
        postal_address: a.address || '',
        permanent_address: a.post_address || '',
        religion: '',
        district_domicile: '',
        identification_mark: '',
        occupation: '',
        is_government: false,
        department_name: '',
        designation: '',
        description: a.description || extracted.crime_description || '',
      });
    });
  }

  return {
    ...current,
    ...compactFilled({
      complainant_name: extracted.victim_name || '',
      parentage: extracted.victim_father_name || '',
      cnic: String(extracted.victim_cnic || '').replace(/\D/g, '').slice(0, 13),
      gender: titleGender(extracted.victim_gender),
      mobile_no: mobile,
      whatsapp_no: mobile,
      email: extracted.victim_email || '',
      postal_address: extracted.victim_address || '',
      occupation: mapOccupation(extracted.victim_occupation),
      reference_no: extracted.tracking_no || extracted.inquiry_no || '',
      city: extracted.city || '',
      occurrence_city: extracted.city || '',
      crime_type: extracted.crime_category || '',
      gist_allegation: extracted.crime_description || extracted.recommendation_full || '',
      amount_pkr: extracted.amount_involved != null ? String(extracted.amount_involved) : '',
      occurrence_date: occ,
      report_date: rec,
      received_on: rec,
      registration_date: rec,
    }),
    ...(mappedAccused.length > 0 ? { accused_persons: mappedAccused } : {}),
  };
}

export function mapExtractToVipAccused(extracted) {
  if (!isLikelyPersonName(extracted.accused_name) && !extracted.accused_cnic && !extracted.accused_phone) {
    return null;
  }
  const phone = digitsPhone(extracted.accused_phone);
  return {
    name: isLikelyPersonName(extracted.accused_name) ? extracted.accused_name : '',
    father_name: '',
    cnic: String(extracted.accused_cnic || '').replace(/\D/g, '').slice(0, 13),
    contact_no: phone ? (phone.length === 10 ? `0${phone}` : phone) : '',
    email: '',
    address: '',
    nationality: 'Pakistani',
    passport_no: '',
    description: extracted.crime_description || '',
    cnic_front: '',
    cnic_back: '',
    passport_attachment: '',
    picture: '',
    other_attachment: '',
    cnic_front_url: '',
    cnic_back_url: '',
    passport_attachment_url: '',
    picture_url: '',
    other_attachment_url: '',
  };
}

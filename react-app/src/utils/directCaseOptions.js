/** Shared options + schema for VIP / direct registration (match legacy CMS forms). */

export const HIGH_PROFILE_TYPES = [
  { value: 'vip', name: 'VIP' },
  { value: 'vvip', name: 'VVIP' },
  { value: 'high_profile', name: 'High Profile' },
  { value: 'political', name: 'Political' },
  { value: 'media_sensitive', name: 'Media Sensitive' },
  { value: 'court_directed', name: 'Court Directed' },
  { value: 'other', name: 'Other' },
];

export const DEPARTMENT_TYPES = [
  { value: 'Individual', name: 'Individual' },
  { value: 'Police', name: 'Police' },
  { value: 'NCCIA', name: 'NCCIA' },
  { value: 'NAB', name: 'NAB' },
  { value: 'Court', name: 'Court' },
  { value: 'Ministry / Federal Dept', name: 'Ministry / Federal Dept' },
  { value: 'Provincial Department', name: 'Provincial Department' },
  { value: 'Intelligence Agency', name: 'Intelligence Agency' },
  { value: 'Other Agency', name: 'Other Agency' },
];

export const DIRECT_RECEIVED_VIA = [
  { value: 'Individually', name: 'Individually' },
  { value: 'Email', name: 'Email' },
  { value: 'Telephone', name: 'Telephone' },
  { value: 'Postal Service', name: 'Postal Service' },
  { value: 'Departmental Referral', name: 'Departmental Referral' },
  { value: 'Letter / Dispatch', name: 'Letter / Dispatch' },
  { value: 'Court Order', name: 'Court Order' },
  { value: 'HQ Directive', name: 'HQ Directive' },
  { value: 'Online Portal', name: 'Online Portal' },
  { value: 'Other', name: 'Other' },
];

export const COMPLAINT_TYPES = [
  { value: 'Complaint', name: 'Complaint' },
  { value: 'Enquiry', name: 'Enquiry' },
  { value: 'Verification', name: 'Verification' },
  { value: 'Direct FIR', name: 'Direct FIR' },
  { value: 'Suomoto', name: 'Suomoto' },
  { value: 'Court Direction', name: 'Court Direction' },
];

export const PRIORITY_OPTIONS = [
  { value: 'Normal', name: 'Normal' },
  { value: 'High', name: 'High' },
  { value: 'Urgent', name: 'Urgent' },
  { value: 'Critical', name: 'Critical' },
];

export const GENDER_OPTIONS = [
  { value: 'Male', name: 'Male' },
  { value: 'Female', name: 'Female' },
  { value: 'Other', name: 'Other' },
];

export const NATIONALITY_OPTIONS = [
  { value: 'PAKISTAN', name: 'PAKISTAN' },
  { value: 'Afghanistan', name: 'Afghanistan' },
  { value: 'India', name: 'India' },
  { value: 'Other', name: 'Other' },
];

export const OCCUPATION_OPTIONS = [
  { value: 'Student', name: 'Student' },
  { value: 'Business', name: 'Business' },
  { value: 'Government Servant', name: 'Government Servant' },
  { value: 'Private Employee', name: 'Private Employee' },
  { value: 'Housewife', name: 'Housewife' },
  { value: 'Lawyer', name: 'Lawyer' },
  { value: 'Doctor', name: 'Doctor' },
  { value: 'Engineer', name: 'Engineer' },
  { value: 'Unemployed', name: 'Unemployed' },
  { value: 'Other', name: 'Other' },
];

export const OFFICE_NAME_OPTIONS = [
  { value: 'Cyber Crime Reporting Center, Lahore', name: 'Cyber Crime Reporting Center, Lahore' },
  { value: 'Cyber Crime Reporting Center, Islamabad', name: 'Cyber Crime Reporting Center, Islamabad' },
  { value: 'Cyber Crime Reporting Center, Karachi', name: 'Cyber Crime Reporting Center, Karachi' },
  { value: 'Cyber Crime Reporting Center, Peshawar', name: 'Cyber Crime Reporting Center, Peshawar' },
  { value: 'Cyber Crime Reporting Center, Quetta', name: 'Cyber Crime Reporting Center, Quetta' },
  { value: 'NCCIA HQ', name: 'NCCIA HQ' },
];

export const CRIME_MEDIUM_OPTIONS = [
  { value: 'Social Media Accounts', name: 'Social Media Accounts' },
  { value: 'Gmail', name: 'Gmail' },
  { value: 'ATM', name: 'ATM' },
  { value: 'Credit Card', name: 'Credit Card' },
  { value: 'IBFT', name: 'IBFT' },
  { value: 'Online Banking', name: 'Online Banking' },
  { value: 'Jazz Cash', name: 'Jazz Cash' },
  { value: 'Easy Paisa', name: 'Easy Paisa' },
  { value: 'Email', name: 'Email' },
  { value: 'Website', name: 'Website' },
  { value: 'Others', name: 'Others' },
];

export const CITY_OPTIONS = [
  'Lahore', 'Islamabad', 'Rawalpindi', 'Karachi', 'Peshawar', 'Quetta',
  'Faisalabad', 'Multan', 'Gujranwala', 'Sialkot', 'Hyderabad', 'Sukkur',
  'Abbottabad', 'Mardan', 'Other',
].map(c => ({ value: c, name: c }));

export const DESIGNATION_OPTIONS = [
  { value: 'Assistant Director', name: 'Assistant Director' },
  { value: 'Deputy Director', name: 'Deputy Director' },
  { value: 'Inspector', name: 'Inspector' },
  { value: 'Sub Inspector', name: 'Sub Inspector' },
  { value: 'Circle Incharge', name: 'Circle Incharge' },
  { value: 'Moharrar', name: 'Moharrar' },
  { value: 'Investigation Officer', name: 'Investigation Officer' },
  { value: 'Enquiry Officer', name: 'Enquiry Officer' },
  { value: 'Verification Officer', name: 'Verification Officer' },
  { value: 'Other', name: 'Other' },
];

export const CASE_CATEGORIES = [
  'Audio / Voice Forensics',
  'Audio Sampling/Collection',
  'Financial Fraud',
  'AHTC',
  'Anti-State',
  'ATM/Credit Card Skimming',
  'Blackmailing',
  'Blasphemy',
  'Child Pornography',
  'Crypto Currency',
  'Cyber Stalking',
  'Dark Web',
  'Defamation',
  'Departmental Inquiry',
  'Fake Documents',
  'Hacking',
  'Harassment',
  'Hate Speech',
  'Hawala / Hundi',
  'Illegal Drugs',
  'Illegal SIM Activation',
  'Impersonation',
  'Infringement / Copyrights',
  'Money Laundering',
  'Murder',
  'Social Media',
  'Spamming',
  'Terrorism (CTW)',
  'Threatening',
  'Voice Analysis',
  'VOIP (Illegal Gateway Exchange)',
  'Other',
].map(c => ({ value: c, name: c }));

export const emptyDirectInfo = () => ({
  // Header / case meta
  serial_number: '',
  complaint_type: 'Complaint',
  office_name: 'Cyber Crime Reporting Center, Lahore',
  high_profile_type: '',
  priority: 'Normal',
  department_type: 'Individual',
  received_via: 'Individually',
  reference_no: '',
  reference_date: '',
  occurrence_date: '',
  report_date: '',
  received_on: '',
  registration_date: '',
  dispatch_date: '',
  // Person
  complainant_name: '',
  parentage: '',
  gender: 'Male',
  cnic: '',
  passport_no: '',
  nationality: 'PAKISTAN',
  occupation: 'Student',
  mobile_no: '',
  whatsapp_no: '',
  email: '',
  postal_address: '',
  city: '',
  // Crime
  case_category: 'Financial Fraud',
  crime_type: '',
  crime_medium: '',
  amount_pkr: '0',
  place_of_occurrence: '',
  occurrence_city: '',
  gist_allegation: '',
  sections_of_laws: '',
  steps_taken: '',
  copy_to: '',
  letter_dispatch_no: '',
  financial_investigation_ref: '',
  registration_officer: '',
  officer_designation: '',
  // Circle (internal)
  circle_id: '',
  circle_code: '',
});

export function normalizeDirectInfo(raw = {}) {
  const base = emptyDirectInfo();
  return {
    ...base,
    ...raw,
    // aliases from older payload
    complainant_name: raw.complainant_name || raw.full_name || base.complainant_name,
    reference_no: raw.reference_no || base.reference_no,
    high_profile_type: raw.high_profile_type || base.high_profile_type,
    department_type: raw.department_type || base.department_type,
    received_via: raw.received_via || base.received_via,
    priority: raw.priority || raw.priority_type || base.priority,
  };
}

/** Build direct_info JSON for API (keep circle fields). */
export function buildDirectInfoPayload(direct, circles = []) {
  const circle = circles.find(c => String(c.id) === String(direct.circle_id));
  return {
    ...normalizeDirectInfo(direct),
    circle_id: direct.circle_id || null,
    circle_code: circle?.code || direct.circle_code || null,
  };
}

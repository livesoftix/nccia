// Role → feature matrix (sync with RolesAndPermissionsSeeder + NCCIA flowchart)
export const ROLE_FEATURES = {
  admin:                ['dashboard', 'analytics', 'department_progress', 'complaints', 'verifications', 'reports', 'enquiries', 'io_records', 'dac_cases', 'court_cases', 'users', 'circles', 'offence_types', 'reference', 'sms_logs', 'profile', 'login_history', 'dsr_reports', 'do_letters'],
  circle_incharge:      ['dashboard', 'analytics', 'department_progress', 'complaints', 'verifications', 'reports', 'enquiries', 'io_records', 'dac_cases', 'court_cases', 'users', 'offence_types', 'reference', 'sms_logs', 'profile', 'dsr_reports', 'do_letters'],
  front_desk_officer:   ['dashboard', 'complaints', 'reference', 'profile'],
  operator:             ['dashboard', 'complaints', 'reference', 'profile'],
  verification_officer: ['dashboard', 'verifications', 'reports', 'reference', 'profile'],
  enquiry_officer:      ['dashboard', 'enquiries', 'dac_cases', 'reference', 'profile'],
  investigation_officer:['dashboard', 'dac_cases', 'court_cases', 'reference', 'profile'],
  moharrar:             ['dashboard', 'dac_cases', 'court_cases', 'reference', 'profile'],
  reader_branch:        ['dashboard', 'enquiries', 'reference', 'profile'],
  ad_legal:             ['dashboard', 'department_progress', 'enquiries', 'dac_cases', 'reference', 'profile'],
  dd_legal:             ['dashboard', 'department_progress', 'enquiries', 'dac_cases', 'reference', 'profile'],
  additional_director:  ['dashboard', 'department_progress', 'enquiries', 'dac_cases', 'reference', 'profile'],
  ad_administration:    ['dashboard', 'dsr_reports', 'do_letters', 'reference', 'sms_logs', 'profile'],
  director_general:     ['dashboard', 'analytics', 'department_progress', 'complaints', 'verifications', 'reports', 'enquiries', 'io_records', 'dac_cases', 'court_cases', 'users', 'circles', 'offence_types', 'reference', 'sms_logs', 'profile', 'login_history', 'dsr_reports', 'do_letters'],
};

/** Who fills / does what (flowchart WS_FLOW_CHART) */
export const ROLE_DUTIES = {
  operator: {
    stage: 'Complaint (CMU) — Complete Registration only',
    fills: [
      'Complete Registration form (complainant + complaint + scrutiny)',
      'When Scrutiny = Complete: Assign Verification Officer on the same form',
      'No verification reports / verification list / analytics for this role',
    ],
  },
  circle_incharge: {
    stage: 'Complaint → Verification → Enquiry → Case',
    fills: [
      'Complaint analysis & workload analysis',
      'Assign / change Verification Officer',
      'Approve verification report (Agree / Review)',
      'Assign Enquiry Officer & approve CFR',
      'Assign Investigation Officer & approve case CFR',
    ],
  },
  verification_officer: {
    stage: 'Verification only',
    fills: [
      'Call / notify complainant (appearance message / WhatsApp)',
      'Verification process & victim verification report',
      'VO Comments on verification report',
      'Recommendations: Enquiry Registration / Closure / Merge / Transfer',
      'Submit verification report for Circle approval',
    ],
  },
  enquiry_officer: {
    stage: 'Enquiry',
    fills: [
      'DAC request, bank record, search & seize',
      'Summons, diaries, seizures, recoveries',
      'Submit CFR + recommendations',
    ],
  },
  investigation_officer: {
    stage: 'Case / Court',
    fills: [
      'Mobile/bank record, summons, case diaries',
      'Seizures, forensics, raids, arrest / remand',
      'CFR / challan recommendations & court reports',
    ],
  },
  reader_branch: {
    stage: 'Enquiry registration',
    fills: ['Generate / record Enquiry Number'],
  },
  moharrar: {
    stage: 'Case / FIR',
    fills: ['Generate / record FIR Number', 'Case registry support'],
  },
  ad_legal: {
    stage: 'Legal',
    fills: ['Legal opinion on enquiry / case / court reports'],
  },
  dd_legal: {
    stage: 'Legal',
    fills: ['Legal opinion on enquiry / case / court reports'],
  },
  additional_director: {
    stage: 'Legal / Approval',
    fills: ['Opinion / approval on legal reports'],
  },
  ad_administration: {
    stage: 'Administration',
    fills: ['Compile Daily Situation Report (DSR)', 'Compile monthly D.O. Letter', 'Submit to Circle Incharge'],
  },
  admin: {
    stage: 'All stages',
    fills: ['Full system administration & all module access'],
  },
  director_general: {
    stage: 'Oversight',
    fills: ['Monitor all modules, analytics, higher directions'],
  },
};

export function hasRole(user, roleName) {
  if (!user) return false;
  let target = String(roleName).toLowerCase().replace(/[\s-]+/g, '_');
  if (target === 'front_desk_officer' || target === 'front_desk') target = 'operator';
  let userRole = String(user.role || '').toLowerCase().replace(/[\s-]+/g, '_');
  if (userRole === 'front_desk_officer' || userRole === 'front_desk') userRole = 'operator';
  if (userRole === target) return true;
  const userDesig = String(user.designation || '').toLowerCase().replace(/[\s-]+/g, '_');
  if (userDesig === target || userDesig.includes(target)) return true;
  if (target === 'operator' && (userDesig.includes('front_desk') || userDesig.includes('front desk'))) return true;
  if (Array.isArray(user.roles)) {
    return user.roles.some(r => {
      let name = String(r?.name || r || '').toLowerCase().replace(/[\s-]+/g, '_');
      if (name === 'front_desk_officer' || name === 'front_desk') name = 'operator';
      return name === target;
    });
  }
  return false;
}

export function hasAnyRole(user, roleNames) {
  if (!user || !Array.isArray(roleNames)) return false;
  return roleNames.some(r => hasRole(user, r));
}

/** Circle Incharge + Legal chain: fill legal opinions and approve CFR. */
export const LEGAL_REVIEW_ROLES = [
  'admin', 'circle_incharge', 'ad_legal', 'dd_legal', 'additional_director', 'director_general',
];

/** Executive monitoring dashboard roles: DG, Additional Director, DD Legal, AD Legal, Admin */
export const EXECUTIVE_MONITORING_ROLES = [
  'admin', 'director_general', 'additional_director', 'dd_legal', 'ad_legal', 'circle_incharge',
];

export function canViewDepartmentProgress(user) {
  if (!user) return false;
  return hasAnyRole(user, EXECUTIVE_MONITORING_ROLES) || canView('department_progress', user);
}

export function canFillLegalAndApprove(user) {
  return hasAnyRole(user, LEGAL_REVIEW_ROLES);
}

/** Laws, Rules, SOPs, User Manuals — create / edit / delete */
export function canManageReference(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge', 'director_general']);
}

export function canSendManualSms(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge', 'director_general']);
}

export const CASE_CFR_REVIEW_STATUSES = [
  'cfr_submitted', 'legal_review_dd', 'legal_review_ad', 'legal_review_dg',
];

/** Saved diary / bank / arrest rows: only Circle Incharge (and admin) may edit or delete. */
export function canUnlockSavedDiary(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge']);
}

export function isSavedDiaryLocked(record, user) {
  return Boolean(record?.id) && !canUnlockSavedDiary(user);
}

/** Forensic portal roles — isolated from the main NCCIA modules. */
export const FORENSIC_ROLES = ['admin_forensic', 'dd_forensic', 'ad_forensic', 'desk_forensic', 'forensic_team'];

export function isForensicUser(user) {
  return hasAnyRole(user, FORENSIC_ROLES);
}

export function isForensicAdmin(user) {
  return hasRole(user, 'admin_forensic');
}

export function canView(feature, user) {
  if (!user) return false;
  // Operator: Complete Registration only (ignore stale permission grants)
  if (hasRole(user, 'operator') && !hasAnyRole(user, ['admin', 'circle_incharge', 'director_general'])) {
    return ['dashboard', 'complaints', 'profile'].includes(feature);
  }
  const permissions = user.permissions?.map?.(p => p.name || p) || [];
  if (permissions.includes(feature)) return true;
  const roles = user.roles?.map?.(r => r.name || r) || [user.role || ''];
  return roles.some(r => ROLE_FEATURES[r]?.includes(feature));
}

export function canCreateComplaint(user) {
  // Anyone with complaints module create access (shared Complete Registration form)
  return hasAnyRole(user, ['admin', 'circle_incharge', 'operator', 'director_general']);
}

export function canCreateEnquiry(user) {
  return hasAnyRole(user, [
    'admin', 'circle_incharge', 'operator', 'reader_branch', 'moharrar',
    'enquiry_officer', 'director_general', 'ad_legal', 'dd_legal', 'additional_director',
  ]) || canView('enquiries', user);
}

export function canAssignVerification(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge', 'operator']);
}

/** Sidebar / create: VIP Direct Verification */
export function canSeeDirectVerification(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge', 'verification_officer', 'director_general']);
}

/** Sidebar / create: VIP Direct Enquiry */
export function canSeeDirectEnquiry(user) {
  return hasAnyRole(user, [
    'admin', 'circle_incharge', 'enquiry_officer', 'reader_branch',
    'director_general', 'ad_legal', 'dd_legal', 'additional_director',
  ]) || canView('enquiries', user);
}

/** Sidebar / create: VIP Direct FIR (DAC) */
export function canSeeDirectFir(user) {
  return hasAnyRole(user, [
    'admin', 'circle_incharge', 'moharrar', 'investigation_officer',
    'enquiry_officer', 'director_general', 'ad_legal', 'dd_legal', 'additional_director',
  ]) || canView('dac_cases', user);
}

/** VO may open direct verification create (assigned to self); CI/admin assign freely */
export function canCreateDirectVerification(user) {
  return canSeeDirectVerification(user);
}

/** Enquiry → Case/FIR registration (Circle Incharge, legal chain, Moharrar, DG). */
export function canRegisterCaseFromEnquiry(user) {
  return hasAnyRole(user, [
    'admin', 'circle_incharge', 'moharrar',
    'ad_legal', 'dd_legal', 'additional_director', 'director_general',
  ]);
}

export function canUpdateCase(user) {
  return hasAnyRole(user, [
    'admin', 'circle_incharge', 'moharrar',
    'ad_legal', 'dd_legal', 'additional_director', 'director_general',
    'investigation_officer',
  ]);
}

export function canViewVerificationReportInEnquiry(user) {
  return hasAnyRole(user, [
    'admin', 'circle_incharge', 'enquiry_officer', 'investigation_officer',
    'moharrar', 'reader_branch', 'ad_legal', 'dd_legal', 'additional_director', 'director_general',
  ]);
}

export const ENQUIRY_CASE_REGISTER_STATUSES = [
  'cfr_submitted', 'approved', 'referred_court', 'in_progress', 'working', 'complete',
  'legal_review_dd', 'legal_review_ad', 'legal_review_dg',
];

export function enquiryReadyForCaseRegistration(enquiry) {
  if (!enquiry || enquiry.case_file_id || enquiry.case_file?.id) {
    return false;
  }
  return ENQUIRY_CASE_REGISTER_STATUSES.includes(enquiry.status);
}

export function canEditVerificationReport(user, report = null) {
  if (!user) return false;
  if (hasAnyRole(user, ['admin', 'circle_incharge', 'ad_legal', 'dd_legal', 'additional_director', 'director_general'])) {
    return true;
  }
  if (hasRole(user, 'verification_officer')) {
    const status = report?.complaint?.verification?.status;
    return !status || ['assigned', 'in_progress', 'sent_back'].includes(status);
  }
  return false;
}

export function canEditEnquiry(user, enquiry = null) {
  if (!user) return false;
  if (hasAnyRole(user, ['admin', 'circle_incharge', 'ad_legal', 'dd_legal', 'additional_director', 'director_general'])) {
    return true;
  }
  if (hasRole(user, 'enquiry_officer')) {
    return String(enquiry?.officer_id || enquiry?.enquiry_officer_id) === String(user.id);
  }
  return false;
}

export function getRoleDuties(user) {
  const roles = user?.roles?.map?.(r => r.name || r) || [user?.role].filter(Boolean);
  const primary = roles[0] || 'operator';
  return { role: primary, ...(ROLE_DUTIES[primary] || ROLE_DUTIES.operator) };
}

/** Approval settings (mandatory CI approval vs open) — admin/DG edit, CI view. */
export function canViewApprovalSettings(user) {
  return hasAnyRole(user, ['admin', 'director_general', 'circle_incharge']);
}
export function canManageApprovals(user) {
  return hasAnyRole(user, ['admin', 'director_general']);
}

/** Warrant / 87-88 approval requests — officers request, CI/admin/DG approve. */
export function canUseWarrantRequests(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge', 'enquiry_officer', 'investigation_officer', 'director_general']);
}

/** Proclamation (s.87) & property attachment (s.88) — case-stage roles. */
export function canUseProclamations(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge', 'investigation_officer', 'moharrar', 'ad_legal', 'dd_legal', 'additional_director', 'director_general']);
}

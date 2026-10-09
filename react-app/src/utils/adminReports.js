import { hasAnyRole } from './permissions';

export const REPORT_STATUS_LABELS = {
  draft: 'Draft (ADA)',
  submitted_to_ci: 'With Circle Incharge',
  ci_approved: 'CI Approved',
  forwarded_to_hq: 'Forwarded to HQ',
  hq_acknowledged: 'HQ Acknowledged',
  sent_back: 'Sent Back to ADA',
};

export function canCompileAdminReports(user) {
  return hasAnyRole(user, ['admin', 'ad_administration']);
}

export function canReviewAdminReports(user) {
  return hasAnyRole(user, ['admin', 'circle_incharge']);
}

export function canAckHqReports(user) {
  return hasAnyRole(user, ['admin', 'director_general']);
}

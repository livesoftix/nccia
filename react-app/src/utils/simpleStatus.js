export const SIMPLE_STATUSES = [
  { value: 'pending', name: 'Pending' },
  { value: 'working', name: 'Working' },
  { value: 'complete', name: 'Complete' },
];

export const PRIORITY_OPTIONS = [
  { value: 'normal', name: 'Normal' },
  { value: 'high', name: 'High' },
  { value: 'critical', name: 'Critical' },
];

const TO_SIMPLE = {
  registered: 'pending',
  assigned: 'pending',
  pending: 'pending',
  in_progress: 'working',
  working: 'working',
  cfr_submitted: 'working',
  legal_review_dd: 'working',
  legal_review_ad: 'working',
  legal_review_dg: 'working',
  referred_court: 'working',
  approved: 'complete',
  closed: 'complete',
  complete: 'complete',
  transferred: 'complete',
  converted_to_case: 'complete',
  merged: 'complete',
  challan_submitted: 'complete',
};

export const SUBMITTED_TO_CIRCLE_STATUSES = [
  'cfr_submitted', 'legal_review_dd', 'legal_review_ad', 'legal_review_dg',
  'approved', 'closed', 'converted_to_case', 'transferred', 'referred_court',
  'merged', 'challan_submitted', 'complete', 'case_registered',
];

const WORKFLOW_LOCK = SUBMITTED_TO_CIRCLE_STATUSES;

export function isSubmittedToCircle(status) {
  return SUBMITTED_TO_CIRCLE_STATUSES.includes(status);
}

export function toSimpleStatus(status) {
  return TO_SIMPLE[status] || 'pending';
}

export function fromSimpleStatus(simple, current) {
  if (WORKFLOW_LOCK.includes(current) && toSimpleStatus(current) === simple) {
    return current;
  }
  if (simple === 'pending') {
    return current === 'registered' ? 'registered' : 'assigned';
  }
  if (simple === 'working') {
    return 'in_progress';
  }
  return 'complete';
}

export function simpleStatusLabel(status) {
  const map = { pending: 'Pending', working: 'Working', complete: 'Complete' };
  return map[toSimpleStatus(status)] || 'Pending';
}

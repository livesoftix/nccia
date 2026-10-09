export function seizeItemKey(it = {}) {
  const parts = [it.item_type, it.imei, it.imei2, it.serial_no, it.make_model]
    .map((x) => String(x || '').trim().toLowerCase());
  const joined = parts.join('|');
  if (joined === '||||') {
    return `desc:${String(it.description || '').trim().toLowerCase()}`;
  }
  return joined;
}

export function isSeizeItemLocked(it) {
  return Boolean(it && (it.locked || it.submitted));
}

export function activityHasLockedSeizeItems(activity) {
  return (activity?.seize_items || []).some(isSeizeItemLocked);
}

export function lockSeizeItemsAgainstForensic(seizeItems, forensicRequests) {
  const keys = new Set();
  (forensicRequests || []).forEach((fr) => {
    (fr.items || []).forEach((fi) => keys.add(seizeItemKey(fi)));
  });
  let changed = false;
  const next = (seizeItems || []).map((it) => {
    if (isSeizeItemLocked(it)) return it;
    if (keys.has(seizeItemKey(it))) {
      changed = true;
      return { ...it, locked: true, submitted: true };
    }
    return it;
  });
  return changed ? next : (seizeItems || []);
}

export function applyForensicLocksToActivities(activities, forensicRequests, seizureTypes = ['seizures', 'seizure']) {
  let changed = false;
  const next = (activities || []).map((a) => {
    if (!seizureTypes.includes(a.type)) return a;
    const items = lockSeizeItemsAgainstForensic(a.seize_items, forensicRequests);
    if (items === a.seize_items) return a;
    changed = true;
    return { ...a, seize_items: items };
  });
  return changed ? next : (activities || []);
}

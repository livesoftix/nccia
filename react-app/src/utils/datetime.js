/** Format ISO / Date / SQL value for <input type="datetime-local"> without timezone shifts */
export function toLocalInput(value) {
  if (!value) return '';
  if (value instanceof Date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${value.getFullYear()}-${pad(value.getMonth() + 1)}-${pad(value.getDate())}T${pad(value.getHours())}:${pad(value.getMinutes())}`;
  }
  const s = String(value).trim();
  // If ISO string with timezone indicator (Z or +00:00), parse as Date so browser applies local timezone offset!
  if (s.endsWith('Z') || /[+-]\d{2}:\d{2}$/.test(s)) {
    const d = new Date(s);
    if (!Number.isNaN(d.getTime())) {
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }
  }
  // Match DD/MM/YYYY or DD-MM-YYYY [HH:mm]
  const dmyMatch = s.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})(?:[T\s](\d{2}):(\d{2}))?/);
  if (dmyMatch && (Number(dmyMatch[1]) > 12 || dmyMatch[0].includes('/'))) {
    const pad = (n) => String(n).padStart(2, '0');
    const hh = dmyMatch[4] !== undefined ? dmyMatch[4] : '00';
    const mm = dmyMatch[5] !== undefined ? dmyMatch[5] : '00';
    return `${dmyMatch[3]}-${pad(dmyMatch[2])}-${pad(dmyMatch[1])}T${hh}:${mm}`;
  }
  // Match YYYY-MM-DD HH:mm:ss or YYYY-MM-DDTHH:mm
  const match = s.match(/^(\d{4})[-/](\d{2})[-/](\d{2})[T\s](\d{2}):(\d{2})/);
  if (match) {
    return `${match[1]}-${match[2]}-${match[3]}T${match[4]}:${match[5]}`;
  }
  // Match only date YYYY-MM-DD
  const dateMatch = s.match(/^(\d{4})[-/](\d{2})[-/](\d{2})/);
  if (dateMatch) {
    return `${dateMatch[1]}-${dateMatch[2]}-${dateMatch[3]}T00:00`;
  }
  const d = new Date(value);
  if (!Number.isNaN(d.getTime())) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }
  return '';
}

export function formatDisplayDateTime(value) {
  if (!value) return '—';
  const s = String(value).trim();
  const match = s.match(/^(\d{4})[-/](\d{2})[-/](\d{2})[T\s](\d{2}):(\d{2})/);
  if (match) {
    const year = parseInt(match[1], 10);
    const month = parseInt(match[2], 10) - 1;
    const day = parseInt(match[3], 10);
    const hours = parseInt(match[4], 10);
    const mins = parseInt(match[5], 10);
    const d = new Date(year, month, day, hours, mins);
    return d.toLocaleString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}


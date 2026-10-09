/**
 * NCCIA end-to-end API smoke test.
 * Usage:
 *   node scripts/smoke-site.mjs
 *   BASE_URL=https://nccia.real-erp.net EMAIL=... PASSWORD=... node scripts/smoke-site.mjs
 */
import { createRequire } from 'module';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const require = createRequire(path.join(ROOT, 'react-app', 'package.json'));

const axios = require('axios');
const { CookieJar } = require('tough-cookie');
const { wrapper } = require('axios-cookiejar-support');
const FormData = require('form-data');

const BASE_URL = (process.env.BASE_URL || 'https://nccia.real-erp.net').replace(/\/$/, '');
const EMAIL = process.env.EMAIL || 'admin@admin.com';
const PASSWORD = process.env.PASSWORD || 'password';

const results = [];
function ok(name, detail = '') {
  results.push({ name, pass: true, detail });
  console.log(`PASS  ${name}${detail ? ' — ' + detail : ''}`);
}
function fail(name, detail = '') {
  results.push({ name, pass: false, detail });
  console.error(`FAIL  ${name}${detail ? ' — ' + detail : ''}`);
}
function skip(name, detail = '') {
  results.push({ name, pass: null, detail });
  console.log(`SKIP  ${name}${detail ? ' — ' + detail : ''}`);
}

function xsrfFromJar(jar) {
  const cookies = jar.toJSON().cookies || [];
  const xsrf = cookies.find((c) => c.key === 'XSRF-TOKEN');
  return xsrf ? decodeURIComponent(xsrf.value) : null;
}

function printSummary() {
  const passed = results.filter((x) => x.pass === true).length;
  const failed = results.filter((x) => x.pass === false).length;
  const skipped = results.filter((x) => x.pass === null).length;
  console.log(`\n=== Summary: ${passed} passed, ${failed} failed, ${skipped} skipped ===\n`);
}

async function main() {
  const jar = new CookieJar();
  const api = wrapper(axios.create({
    baseURL: BASE_URL,
    jar,
    withCredentials: true,
    timeout: 60000,
    headers: {
      Accept: 'application/json',
      'User-Agent': 'NCCIA-SmokeTest/1.0',
      'X-Requested-With': 'XMLHttpRequest',
    },
    validateStatus: () => true,
  }));

  console.log(`\n=== NCCIA smoke test @ ${BASE_URL} ===\n`);

  {
    const r = await api.get('/login');
    if (r.status === 200) ok('GET /login', String(r.status));
    else fail('GET /login', String(r.status));
  }

  {
    const r = await api.get('/sanctum/csrf-cookie');
    if (r.status === 204 || r.status === 200) ok('GET /sanctum/csrf-cookie', String(r.status));
    else fail('GET /sanctum/csrf-cookie', String(r.status));
  }

  let user = null;
  {
    const token = xsrfFromJar(jar);
    const r = await api.post('/api/login', { email: EMAIL, password: PASSWORD }, {
      headers: {
        'Content-Type': 'application/json',
        ...(token ? { 'X-XSRF-TOKEN': token } : {}),
      },
    });
    if (r.status === 200 && r.data?.user) {
      user = r.data.user;
      ok('POST /api/login', user.email || user.name);
    } else {
      fail('POST /api/login', `${r.status} ${JSON.stringify(r.data?.message || r.data).slice(0, 200)}`);
      printSummary();
      process.exit(1);
    }
  }

  const authHeaders = () => {
    const token = xsrfFromJar(jar);
    return token ? { 'X-XSRF-TOKEN': token } : {};
  };

  for (const ep of [
    '/api/dashboard',
    '/api/sidebar-counts',
    '/api/complaints?per_page=5',
    '/api/verifications?per_page=5',
    '/api/enquiries?per_page=5',
    '/api/cases?per_page=5',
    '/api/notifications',
  ]) {
    const r = await api.get(ep, { headers: authHeaders() });
    if (r.status === 200) ok(`GET ${ep}`, String(r.status));
    else fail(`GET ${ep}`, `${r.status} ${JSON.stringify(r.data?.message || '').slice(0, 120)}`);
  }

  let complaintId = null;
  let tracking = null;
  {
    const r = await api.get('/api/complaints?status=complete&per_page=5', { headers: authHeaders() });
    const rows = r.data?.data || r.data || [];
    const list = Array.isArray(rows) ? rows : (rows.data || []);
    if (r.status === 200 && list.length) {
      complaintId = list[0].id;
      tracking = list[0].tracking_no;
      ok('Find complete complaint for enquiry', `#${complaintId} ${tracking || ''}`);
    } else {
      fail('Find complete complaint for enquiry', `${r.status} empty=${!list.length}`);
    }
  }

  const tmpDir = path.join(ROOT, 'storage', 'app', 'smoke-tmp');
  fs.mkdirSync(tmpDir, { recursive: true });
  const files = {
    attach1: path.join(tmpDir, 'smoke-attach-1.txt'),
    attach2: path.join(tmpDir, 'smoke-attach-2.txt'),
    tech: path.join(tmpDir, 'smoke-technical.pdf'),
    forensic: path.join(tmpDir, 'smoke-forensic.pdf'),
    accusedCnic: path.join(tmpDir, 'smoke-cnic.txt'),
  };
  fs.writeFileSync(files.attach1, 'SMOKE attachment 1 ' + new Date().toISOString());
  fs.writeFileSync(files.attach2, 'SMOKE attachment 2 ' + new Date().toISOString());
  fs.writeFileSync(files.tech, '%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
  fs.writeFileSync(files.forensic, '%PDF-1.1\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n');
  fs.writeFileSync(files.accusedCnic, 'SMOKE CNIC SCAN');

  let enquiryId = null;
  if (complaintId) {
    const fd = new FormData();
    fd.append('complaint_id', String(complaintId));
    if (tracking) fd.append('tracking_no', tracking);
    fd.append('status', 'registered');
    fd.append('technical_report', 'SMOKE technical report findings ' + Date.now());
    fd.append('forensic_report', 'SMOKE forensic report findings ' + Date.now());
    fd.append('activities', JSON.stringify([]));
    fd.append('accused', JSON.stringify([{
      name: 'Smoke Accused',
      cnic: '35202-1234567-1',
      father_name: 'Smoke Father',
      gender: 'male',
      contact_no: '3001234567',
    }]));
    fd.append('witnesses', JSON.stringify([{
      name: 'Smoke Witness',
      cnic: '35202-7654321-1',
      contact_no: '3007654321',
      address: 'Smoke address',
    }]));
    fd.append('notices', JSON.stringify([]));
    fd.append('attachments', JSON.stringify([
      { title: 'Smoke Att 1', attachment_type: 'other', attachment_date: new Date().toISOString().slice(0, 10) },
    ]));
    fd.append('enquiry_attachment_files[0]', fs.createReadStream(files.attach1), {
      filename: 'smoke-attach-1.txt',
      contentType: 'text/plain',
    });
    fd.append('requisitions', JSON.stringify([]));
    fd.append('legal_opinions', JSON.stringify([]));
    fd.append('approvals', JSON.stringify([]));
    fd.append('technical_report_attachment', fs.createReadStream(files.tech), {
      filename: 'smoke-technical.pdf',
      contentType: 'application/pdf',
    });

    const r = await api.post('/api/enquiries', fd, {
      headers: { ...authHeaders(), ...fd.getHeaders() },
      maxBodyLength: Infinity,
    });
    const data = r.data?.data || r.data;
    if ((r.status === 201 || r.status === 200) && (data?.id || data?.data?.id)) {
      enquiryId = data.id || data.data?.id;
      ok('POST /api/enquiries (create + 1 attachment + tech report)', `id=${enquiryId}`);
    } else {
      fail('POST /api/enquiries', `${r.status} ${JSON.stringify(r.data).slice(0, 400)}`);
    }
  } else {
    skip('POST /api/enquiries', 'No complete complaint available');
  }

  if (enquiryId) {
    const fd = new FormData();
    fd.append('_method', 'PUT');
    fd.append('status', 'in_progress');
    fd.append('technical_report', 'SMOKE technical report UPDATED ' + Date.now());
    fd.append('forensic_report', 'SMOKE forensic report UPDATED ' + Date.now());
    fd.append('activities', JSON.stringify([
      { type: 'seize', description: 'Smoke seize activity', activity_date: new Date().toISOString().slice(0, 10) },
    ]));
    fd.append('accused', JSON.stringify([{
      name: 'Smoke Accused',
      cnic: '35202-1234567-1',
      father_name: 'Smoke Father',
      gender: 'male',
      contact_no: '3001234567',
    }]));
    fd.append('witnesses', JSON.stringify([{
      name: 'Smoke Witness',
      cnic: '35202-7654321-1',
      contact_no: '3007654321',
    }]));
    fd.append('notices', JSON.stringify([]));
    fd.append('attachments', JSON.stringify([
      { title: 'Smoke Att 1 keep', attachment_type: 'other', attachment_date: new Date().toISOString().slice(0, 10) },
      { title: 'Smoke Att 2', attachment_type: 'other', attachment_date: new Date().toISOString().slice(0, 10) },
    ]));
    fd.append('enquiry_attachment_files[1]', fs.createReadStream(files.attach2), {
      filename: 'smoke-attach-2.txt',
      contentType: 'text/plain',
    });
    fd.append('requisitions', JSON.stringify([]));
    fd.append('legal_opinions', JSON.stringify([]));
    fd.append('approvals', JSON.stringify([]));
    fd.append('forensic_report_attachment', fs.createReadStream(files.forensic), {
      filename: 'smoke-forensic.pdf',
      contentType: 'application/pdf',
    });
    fd.append('accused_cnic_attachments[0]', fs.createReadStream(files.accusedCnic), {
      filename: 'smoke-cnic.txt',
      contentType: 'text/plain',
    });

    const r = await api.post(`/api/enquiries/${enquiryId}`, fd, {
      headers: { ...authHeaders(), ...fd.getHeaders() },
      maxBodyLength: Infinity,
    });
    if (r.status === 200) ok('PUT enquiry (2nd attachment + forensic + activity)', String(r.status));
    else fail('PUT enquiry', `${r.status} ${JSON.stringify(r.data).slice(0, 400)}`);
  }

  if (enquiryId) {
    const r = await api.get(`/api/enquiries/${enquiryId}`, { headers: authHeaders() });
    const d = r.data?.data || r.data || {};
    if (r.status !== 200) {
      fail('GET enquiry show', String(r.status));
    } else {
      const atts = d.enquiry_attachments || d.attachments || [];
      const techOk = !!(d.technical_report && String(d.technical_report).includes('SMOKE'));
      const forOk = !!(d.forensic_report && String(d.forensic_report).includes('SMOKE'));
      const techFile = !!d.technical_report_attachment;
      const forFile = !!d.forensic_report_attachment;
      const attCount = Array.isArray(atts) ? atts.length : 0;
      const accused = d.accused_persons || d.accused || [];
      const activities = d.activities || [];

      if (techOk) ok('technical_report persisted', String(d.technical_report).slice(0, 60));
      else fail('technical_report persisted', String(d.technical_report || ''));

      if (forOk) ok('forensic_report persisted', String(d.forensic_report).slice(0, 60));
      else fail('forensic_report persisted', String(d.forensic_report || ''));

      if (techFile) ok('technical_report_attachment saved', d.technical_report_attachment);
      else fail('technical_report_attachment saved', 'missing');

      if (forFile) ok('forensic_report_attachment saved', d.forensic_report_attachment);
      else fail('forensic_report_attachment saved', 'missing');

      if (attCount >= 1) ok('enquiry attachments present', `count=${attCount}`);
      else fail('enquiry attachments present', `count=${attCount}`);

      if (accused.length >= 1) ok('accused saved', accused[0]?.name || '');
      else fail('accused saved', 'empty');

      if (activities.length >= 1) ok('activities saved', activities[0]?.type || '');
      else fail('activities saved', 'empty');
    }
  }

  {
    const fd = new FormData();
    fd.append('file', fs.createReadStream(files.tech), {
      filename: '261-26.PDF',
      contentType: 'application/pdf',
    });
    fd.append('ocr_text', [
      'VERIFICATION REPORT',
      'Tracking No: CCW-SMOKE-TEST-001',
      'Name: Smoke Complainant S/O Smoke Father',
      'Gender: Male',
      'CNIC No: 3520212345671',
      'Mobile Number: 03001234567',
      'Current Address: Smoke Street Lahore',
      'BRIEF DESCRIPTION Online Job Frauds',
      'City of Occurrence: Lahore',
      'Amount Involved: 50000',
      'RECOMMENDATIONS: Permission to Register Enquiry',
    ].join('\n'));
    const r = await api.post('/api/adp/extract', fd, {
      headers: { ...authHeaders(), ...fd.getHeaders() },
      maxBodyLength: Infinity,
      timeout: 180000,
    });
    if (r.status === 200 && r.data?.data) ok('POST /api/adp/extract', r.data.ai_provider || 'ok');
    else fail('POST /api/adp/extract', `${r.status} ${JSON.stringify(r.data).slice(0, 250)}`);
  }

  {
    const r = await api.get('/api/forensic/requests', { headers: authHeaders() });
    if (r.status === 200) ok('GET /api/forensic/requests', String(r.status));
    else if (r.status === 403) skip('GET /api/forensic/requests', '403 (role not forensic)');
    else fail('GET /api/forensic/requests', String(r.status));
  }

  {
    const r = await api.get('/react/index.html');
    if (r.status === 200 && String(r.data).includes('index-')) ok('GET /react/index.html', 'bundle present');
    else fail('GET /react/index.html', String(r.status));
  }

  printSummary();
  process.exit(results.some((x) => x.pass === false) ? 1 : 0);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});

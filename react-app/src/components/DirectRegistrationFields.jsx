import { useEffect, useState } from 'react';
import api from '../api';
import PdfAutoFillBar from './PdfAutoFillBar';
import { mapExtractToDirectInfo } from '../utils/fillFromPdf';
import {
  HIGH_PROFILE_TYPES,
  DEPARTMENT_TYPES,
  DIRECT_RECEIVED_VIA,
  COMPLAINT_TYPES,
  PRIORITY_OPTIONS,
  GENDER_OPTIONS,
  NATIONALITY_OPTIONS,
  OCCUPATION_OPTIONS,
  OFFICE_NAME_OPTIONS,
  CRIME_MEDIUM_OPTIONS,
  CITY_OPTIONS,
  DESIGNATION_OPTIONS,
  CASE_CATEGORIES,
} from '../utils/directCaseOptions';

/**
 * Legacy CMS-style VIP / Direct registration fields (Case + Enquiry screenshots).
 * @param {{ direct: object, setDirect: function, errors?: object, title?: string, subtitle?: string, showCaseExtras?: boolean }} props
 */
export default function DirectRegistrationFields({
  direct,
  setDirect,
  errors = {},
  title = 'Direct Case Details',
  subtitle = 'VIP / departmental entry — same fields as CMS registration form',
  showCaseExtras = true,
}) {
  const [crimeTypes, setCrimeTypes] = useState([]);
  const [circles, setCircles] = useState([]);

  useEffect(() => {
    api.get('/lookup/offence-types').then(r => {
      const d = r.data.data || r.data || [];
      setCrimeTypes((Array.isArray(d) ? d : []).map(o => ({
        value: o.name || o.title || String(o.id),
        name: o.name || o.title || String(o.id),
      })));
    }).catch(() => {});
    api.get('/lookup/circles').then(r => {
      const d = r.data.data || r.data || [];
      setCircles(Array.isArray(d) ? d : []);
    }).catch(() => {});
  }, []);

  const set = (field) => (e) => setDirect(d => ({ ...d, [field]: e.target.value }));

  const handlePdfFilled = (extracted) => {
    setDirect(d => mapExtractToDirectInfo(extracted, d));
  };

  const Field = ({ label, name, required, children, span }) => (
    <div className="cf-field" style={span ? { gridColumn: span } : undefined}>
      <label className={`cf-label${required ? ' required' : ''}`}>{label}</label>
      {children}
      {errors[name] && <div className="cf-error">{Array.isArray(errors[name]) ? errors[name][0] : errors[name]}</div>}
    </div>
  );

  const Select = ({ name, options, required, placeholder }) => (
    <select className="cf-input" value={direct[name] || ''} onChange={set(name)} required={required}>
      <option value="">{placeholder || `Choose ${name.replace(/_/g, ' ')}`}</option>
      {options.map(o => <option key={o.value} value={o.value}>{o.name}</option>)}
    </select>
  );

  return (
    <div className="cf-section" style={{ marginTop: 16, borderTop: '1px dashed #dbe2ea', paddingTop: 16 }}>
      <div className="cf-section-header">
        <div className="cf-section-icon" style={{ background: '#0E7C7B' }}>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.5">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
        <div>
          <div className="cf-section-title">{title}</div>
          <div className="cf-section-sub">{subtitle}</div>
        </div>
      </div>

      <div style={{ marginBottom: 16 }}>
        <PdfAutoFillBar
          onFilled={handlePdfFilled}
          hint="Upload PDF verification report / letter to auto-fill direct registration details."
        />
      </div>

      {/* Meta — Case Registration Form */}
      <div className="cf-row-3">
        <Field label="Reference No / Tracking No" name="reference_no" required>
          <input type="text" className="cf-input" value={direct.reference_no} onChange={set('reference_no')} placeholder="e.g. VIP-2026-0001 / Letter No." required />
          {errors.direct_info && <div className="cf-error">{Array.isArray(errors.direct_info) ? errors.direct_info[0] : errors.direct_info}</div>}
        </Field>
        <Field label="Serial Number" name="serial_number">
          <input type="text" className="cf-input" value={direct.serial_number} onChange={set('serial_number')} placeholder="Serial number" />
        </Field>
        <Field label="Complaint Type" name="complaint_type" required>
          <Select name="complaint_type" options={COMPLAINT_TYPES} required placeholder="Choose Complaint Type" />
        </Field>
      </div>

      <div className="cf-row-3">
        <Field label="Office Name" name="office_name" required>
          <Select name="office_name" options={OFFICE_NAME_OPTIONS} required placeholder="Choose Office" />
        </Field>
        <Field label="High Profile Type" name="high_profile_type">
          <Select name="high_profile_type" options={HIGH_PROFILE_TYPES} placeholder="Choose High Profile Type" />
        </Field>
        <Field label="Priority" name="priority" required>
          <Select name="priority" options={PRIORITY_OPTIONS} required placeholder="Choose Priority" />
        </Field>
      </div>

      <div className="cf-row-3">
        <Field label="Occurrence Date" name="occurrence_date" required>
          <input type="datetime-local" className="cf-input" value={direct.occurrence_date} onChange={set('occurrence_date')} />
        </Field>
        <Field label="Report Date" name="report_date" required>
          <input type="datetime-local" className="cf-input" value={direct.report_date} onChange={set('report_date')} />
        </Field>
        <Field label="Received On" name="received_on" required>
          <input type="datetime-local" className="cf-input" value={direct.received_on} onChange={set('received_on')} />
        </Field>
      </div>

      <div className="cf-row-3">
        <Field label="Registration Date" name="registration_date" required>
          <input type="datetime-local" className="cf-input" value={direct.registration_date} onChange={set('registration_date')} />
        </Field>
        {showCaseExtras && (
          <Field label="Dispatch Date" name="dispatch_date">
            <input type="datetime-local" className="cf-input" value={direct.dispatch_date} onChange={set('dispatch_date')} />
          </Field>
        )}
        <Field label="Department Type (From)" name="department_type" required>
          <Select name="department_type" options={DEPARTMENT_TYPES} required placeholder="Choose Department Type" />
        </Field>
        {!showCaseExtras && (
          <Field label="Received Via" name="received_via" required>
            <Select name="received_via" options={DIRECT_RECEIVED_VIA} required placeholder="Choose Received Via" />
          </Field>
        )}
      </div>

      <div className="cf-row-3">
        {showCaseExtras && (
          <Field label="Received Via" name="received_via" required>
            <Select name="received_via" options={DIRECT_RECEIVED_VIA} required placeholder="Choose Received Via" />
          </Field>
        )}
        <Field label="Reference Date" name="reference_date">
          <input type="datetime-local" className="cf-input" value={direct.reference_date} onChange={set('reference_date')} />
        </Field>
        <Field label="Circle" name="circle_id">
          <select className="cf-input" value={direct.circle_id || ''} onChange={set('circle_id')}>
            <option value="">— Select Circle —</option>
            {circles.map(c => <option key={c.id} value={c.id}>{c.name}{c.code ? ` (${c.code})` : ''}</option>)}
          </select>
        </Field>
      </div>

      {/* Person */}
      <div className="cf-row-2">
        <Field label="Full Name" name="complainant_name" required>
          <input type="text" className="cf-input" value={direct.complainant_name} onChange={set('complainant_name')} placeholder="Enter full name" required />
        </Field>
        <Field label="Parentage (S/O -)" name="parentage" required>
          <input type="text" className="cf-input" value={direct.parentage} onChange={set('parentage')} placeholder="Enter parentage" />
        </Field>
      </div>

      <div className="cf-row-3">
        <Field label="Gender" name="gender" required>
          <Select name="gender" options={GENDER_OPTIONS} required placeholder="Choose Gender" />
        </Field>
        <Field label="CNIC" name="cnic" required>
          <input type="text" className="cf-input" value={direct.cnic} onChange={set('cnic')} placeholder="XXXXXXXXXXXXX" />
        </Field>
        <Field label="Passport No." name="passport_no">
          <input type="text" className="cf-input" value={direct.passport_no} onChange={set('passport_no')} placeholder="XXXXXXXXXXXXX" />
        </Field>
      </div>

      <div className="cf-row-3">
        <Field label="Nationality" name="nationality">
          <Select name="nationality" options={NATIONALITY_OPTIONS} placeholder="Choose Nationality" />
        </Field>
        <Field label="Select Occupation" name="occupation">
          <Select name="occupation" options={OCCUPATION_OPTIONS} placeholder="Select Occupation" />
        </Field>
        <Field label="Mobile No." name="mobile_no" required>
          <input type="text" className="cf-input" value={direct.mobile_no} onChange={set('mobile_no')} placeholder="03XXXXXXXXX" />
        </Field>
      </div>

      <div className="cf-row-3">
        <Field label="Mobile No. (WhatsApp)" name="whatsapp_no">
          <input type="text" className="cf-input" value={direct.whatsapp_no} onChange={set('whatsapp_no')} />
        </Field>
        <Field label="Email Address" name="email" span="span 2">
          <input type="email" className="cf-input" value={direct.email} onChange={set('email')} placeholder="Enter email address" />
        </Field>
      </div>

      <div className="cf-row-2">
        <Field label="Postal Address" name="postal_address" required>
          <textarea className="cf-input" rows={3} value={direct.postal_address} onChange={set('postal_address')} placeholder="Enter address" />
        </Field>
        <Field label="City" name="city" required>
          <Select name="city" options={CITY_OPTIONS} required placeholder="Choose City" />
        </Field>
      </div>

      {/* Crime */}
      <div className="cf-row-3">
        <Field label="Case Category" name="case_category" required>
          <Select name="case_category" options={CASE_CATEGORIES} required placeholder="Choose Case Category" />
        </Field>
        <Field label="Crime Type" name="crime_type" required>
          <Select
            name="crime_type"
            options={crimeTypes.length ? crimeTypes : [{ value: 'Other', name: 'Other' }]}
            required
            placeholder="Choose Crime Type"
          />
        </Field>
        <Field label="Crime Medium" name="crime_medium" required>
          <Select name="crime_medium" options={CRIME_MEDIUM_OPTIONS} required placeholder="Choose Crime Medium" />
        </Field>
      </div>

      <div className="cf-row-2">
        <Field label="Amount (PKR)" name="amount_pkr">
          <input type="number" className="cf-input" value={direct.amount_pkr} onChange={set('amount_pkr')} min="0" />
        </Field>
        <Field label="Occurrence (City)" name="occurrence_city" required>
          <Select name="occurrence_city" options={CITY_OPTIONS} required placeholder="Choose City" />
        </Field>
      </div>

      <Field label="Gist Allegation" name="gist_allegation" required>
        <textarea className="cf-input" rows={4} value={direct.gist_allegation} onChange={set('gist_allegation')} placeholder="Summary of allegation / complaint" />
      </Field>

      <div className="cf-row-3">
        <Field label="Sections of Laws" name="sections_of_laws" required>
          <textarea className="cf-input" rows={3} value={direct.sections_of_laws} onChange={set('sections_of_laws')} />
        </Field>
        <Field label="Steps Taken" name="steps_taken" required>
          <textarea className="cf-input" rows={3} value={direct.steps_taken} onChange={set('steps_taken')} />
        </Field>
        <Field label="Copy To" name="copy_to">
          <textarea className="cf-input" rows={3} value={direct.copy_to} onChange={set('copy_to')} />
        </Field>
      </div>

      <div className="cf-row-3">
        <Field label="Letter / Dispatch No." name="letter_dispatch_no">
          <input type="text" className="cf-input" value={direct.letter_dispatch_no} onChange={set('letter_dispatch_no')} />
        </Field>
        <Field label="Reference No (Financial Investigation)" name="financial_investigation_ref">
          <input type="text" className="cf-input" value={direct.financial_investigation_ref} onChange={set('financial_investigation_ref')} />
        </Field>
        <Field label="Registration Officer" name="registration_officer">
          <input type="text" className="cf-input" value={direct.registration_officer} onChange={set('registration_officer')} />
        </Field>
      </div>

      <div className="cf-row-2">
        <Field label="Select Officer Designation" name="officer_designation">
          <Select name="officer_designation" options={DESIGNATION_OPTIONS} placeholder="Select Designation" />
        </Field>
      </div>
    </div>
  );
}

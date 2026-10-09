import { useState, useEffect } from 'react';
import api from '../api';
import LoadingSkeleton from '../components/LoadingSkeleton';
import { openPrintWindow } from '../utils/print';

const EMPTY_PROC = {
  accused_name: '', accused_father_name: '', accused_address: '', offence: '',
  court_name: '', proclaimed_on: '', appear_by_date: '', publication_place: '',
};
const EMPTY_ATT = {
  property_type: 'movable', property_description: '', location: '',
  estimated_value: '', attachment_date: '', order_no: '',
};

export default function Proclamations() {
  const [cases, setCases] = useState([]);
  const [caseId, setCaseId] = useState('');
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState('');
  const [busy, setBusy] = useState(false);
  const [showProc, setShowProc] = useState(false);
  const [proc, setProc] = useState(EMPTY_PROC);
  const [attachFor, setAttachFor] = useState(null);
  const [att, setAtt] = useState(EMPTY_ATT);

  useEffect(() => {
    api.get('/cases', { params: { page: 1 } }).then(r => {
      const d = r.data.data || r.data; setCases(Array.isArray(d) ? d : (d?.data || []));
    }).catch(() => {});
  }, []);

  const loadProclamations = (id) => {
    if (!id) { setList([]); return; }
    setLoading(true);
    api.get(`/cases/${id}/proclamations`)
      .then(r => setList(r.data.data || r.data))
      .catch(() => setList([]))
      .finally(() => setLoading(false));
  };

  const onPickCase = (id) => {
    setCaseId(id); setNotice(''); setShowProc(false); setAttachFor(null);
    loadProclamations(id);
  };

  const submitProc = async (e) => {
    e.preventDefault();
    setBusy(true); setNotice('');
    try {
      const r = await api.post(`/cases/${caseId}/proclamations`, proc);
      setNotice(r.data.message || 'Proclamation recorded.');
      setProc(EMPTY_PROC); setShowProc(false);
      loadProclamations(caseId);
    } catch (err) {
      setNotice(err?.response?.data?.message || 'Could not record the proclamation.');
    } finally { setBusy(false); }
  };

  const submitAtt = async (e) => {
    e.preventDefault();
    setBusy(true); setNotice('');
    try {
      const r = await api.post(`/proclamations/${attachFor.id}/attachment`, att);
      setNotice(r.data.message || 'Attachment recorded.');
      setAtt(EMPTY_ATT); setAttachFor(null);
      loadProclamations(caseId);
    } catch (err) {
      setNotice(err?.response?.data?.message || 'Could not record the attachment.');
    } finally { setBusy(false); }
  };

  const printDoc = async (url) => {
    setNotice('');
    try {
      const r = await api.get(url);
      if (r.data?.html) openPrintWindow(r.data.html);
      else setNotice('Nothing to print.');
    } catch (err) {
      setNotice(err?.response?.data?.message || 'Printing is not allowed yet (approval may be required).');
    }
  };

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Proclamation & Attachment (87 / 88 CrPC)</h1>
          <p className="page-subtitle">Proclaim an absconder (s.87) and attach property (s.88) against a registered case</p>
          <div className="title-underline"></div>
        </div>
      </div>

      {notice && <div className="card" style={{marginBottom:16}}><div className="card-body" style={{color:'#2563eb'}}>{notice}</div></div>}

      <div className="card" style={{marginBottom:20}}>
        <div className="card-body">
          <div className="cf-field" style={{marginBottom:0}}>
            <label className="cf-label required">Case (FIR)</label>
            <select className="cf-input" value={caseId} onChange={e => onPickCase(e.target.value)}>
              <option value="">— select a case —</option>
              {cases.map(c => <option key={c.id} value={c.id}>{c.fir_no || `Case #${c.id}`}</option>)}
            </select>
          </div>
        </div>
      </div>

      {caseId && (
        <>
          <div style={{display:'flex',justifyContent:'flex-end',marginBottom:12}}>
            <button className="btn btn-primary btn-sm" onClick={() => { setShowProc(!showProc); setNotice(''); }}>
              {showProc ? 'Cancel' : 'New Proclamation (s.87)'}
            </button>
          </div>

          {showProc && (
            <div className="card" style={{marginBottom:20}}>
              <div className="card-header"><div className="card-title">Proclamation u/s 87 CrPC</div></div>
              <div className="card-body">
                <form onSubmit={submitProc}>
                  <div className="cf-field"><label className="cf-label required">Accused name</label>
                    <input className="cf-input" value={proc.accused_name} onChange={e => setProc({...proc, accused_name: e.target.value})} required /></div>
                  <div className="cf-field"><label className="cf-label">Father / guardian name</label>
                    <input className="cf-input" value={proc.accused_father_name} onChange={e => setProc({...proc, accused_father_name: e.target.value})} /></div>
                  <div className="cf-field"><label className="cf-label">Accused address</label>
                    <textarea className="cf-input" rows={2} value={proc.accused_address} onChange={e => setProc({...proc, accused_address: e.target.value})} /></div>
                  <div className="cf-field"><label className="cf-label">Offence</label>
                    <input className="cf-input" value={proc.offence} onChange={e => setProc({...proc, offence: e.target.value})} /></div>
                  <div className="cf-field"><label className="cf-label">Court name</label>
                    <input className="cf-input" value={proc.court_name} onChange={e => setProc({...proc, court_name: e.target.value})} /></div>
                  <div className="cf-field"><label className="cf-label">Proclaimed on</label>
                    <input type="date" className="cf-input" value={proc.proclaimed_on} onChange={e => setProc({...proc, proclaimed_on: e.target.value})} /></div>
                  <div className="cf-field"><label className="cf-label required">Appear by date</label>
                    <input type="date" className="cf-input" value={proc.appear_by_date} onChange={e => setProc({...proc, appear_by_date: e.target.value})} required />
                    <small style={{color:'#6c757d'}}>Section 87: must be at least 30 days after the proclamation date.</small></div>
                  <div className="cf-field"><label className="cf-label">Publication place</label>
                    <input className="cf-input" value={proc.publication_place} onChange={e => setProc({...proc, publication_place: e.target.value})} /></div>
                  <div style={{display:'flex',gap:10}}>
                    <button type="submit" className="btn btn-primary btn-sm" disabled={busy}>Save Proclamation</button>
                    <button type="button" className="btn btn-outline btn-sm" onClick={() => { setShowProc(false); setProc(EMPTY_PROC); }}>Cancel</button>
                  </div>
                </form>
              </div>
            </div>
          )}

          {loading ? <LoadingSkeleton type="table" columns={3} rows={4} /> : (
            <div className="card">
              <div className="card-body" style={{padding: list.length ? 16 : 0}}>
                {list.length === 0 && <div style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No proclamations for this case yet.</div>}
                {list.map(p => (
                  <div key={p.id} style={{border:'1px solid #e2e8f0',borderRadius:10,padding:16,marginBottom:14}}>
                    <div style={{display:'flex',justifyContent:'space-between',alignItems:'flex-start',gap:12,flexWrap:'wrap'}}>
                      <div>
                        <div style={{fontWeight:600}}>{p.accused_name} <span style={{fontWeight:400,color:'#6c757d'}}>— {p.offence || 'offence n/a'}</span></div>
                        <div style={{fontSize:13,color:'#6c757d',marginTop:4}}>
                          Proclaimed {p.proclaimed_on || '-'} · Appear by {p.appear_by_date || '-'} · {p.court_name || 'court n/a'}
                        </div>
                      </div>
                      <div style={{display:'flex',gap:6}}>
                        <button className="btn btn-outline btn-sm" onClick={() => printDoc(`/proclamations/${p.id}/print`)}>Print s.87</button>
                        <button className="btn btn-primary btn-sm" onClick={() => { setAttachFor(p); setAtt(EMPTY_ATT); setNotice(''); }}>Add Attachment (s.88)</button>
                      </div>
                    </div>

                    {(p.attachments || []).length > 0 && (
                      <div style={{marginTop:12,borderTop:'1px dashed #e2e8f0',paddingTop:10}}>
                        {p.attachments.map(a => (
                          <div key={a.id} style={{display:'flex',justifyContent:'space-between',alignItems:'center',gap:10,padding:'6px 0',fontSize:13}}>
                            <span><strong style={{textTransform:'capitalize'}}>{a.property_type}</strong> — {a.property_description}{a.estimated_value ? ` (est. ${a.estimated_value})` : ''}</span>
                            <button className="btn btn-outline btn-sm" onClick={() => printDoc(`/attachments/${a.id}/print`)}>Print s.88</button>
                          </div>
                        ))}
                      </div>
                    )}

                    {attachFor?.id === p.id && (
                      <form onSubmit={submitAtt} style={{marginTop:12,borderTop:'1px dashed #e2e8f0',paddingTop:12}}>
                        <div className="cf-field"><label className="cf-label required">Property type</label>
                          <select className="cf-input" value={att.property_type} onChange={e => setAtt({...att, property_type: e.target.value})}>
                            <option value="movable">Movable</option>
                            <option value="immovable">Immovable</option>
                            <option value="both">Both</option>
                          </select></div>
                        <div className="cf-field"><label className="cf-label required">Property description</label>
                          <textarea className="cf-input" rows={2} value={att.property_description} onChange={e => setAtt({...att, property_description: e.target.value})} required /></div>
                        <div className="cf-field"><label className="cf-label">Location</label>
                          <input className="cf-input" value={att.location} onChange={e => setAtt({...att, location: e.target.value})} /></div>
                        <div className="cf-field"><label className="cf-label">Estimated value</label>
                          <input type="number" min="0" className="cf-input" value={att.estimated_value} onChange={e => setAtt({...att, estimated_value: e.target.value})} /></div>
                        <div className="cf-field"><label className="cf-label">Attachment date</label>
                          <input type="date" className="cf-input" value={att.attachment_date} onChange={e => setAtt({...att, attachment_date: e.target.value})} /></div>
                        <div className="cf-field"><label className="cf-label">Order no.</label>
                          <input className="cf-input" value={att.order_no} onChange={e => setAtt({...att, order_no: e.target.value})} /></div>
                        <div style={{display:'flex',gap:10}}>
                          <button type="submit" className="btn btn-primary btn-sm" disabled={busy}>Save Attachment</button>
                          <button type="button" className="btn btn-outline btn-sm" onClick={() => { setAttachFor(null); setAtt(EMPTY_ATT); }}>Cancel</button>
                        </div>
                      </form>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}
        </>
      )}
    </div>
  );
}

import { useState, useEffect, useMemo } from 'react';
import api from '../api';
import ConfirmModal from '../components/ConfirmModal';
import LoadingSkeleton from '../components/LoadingSkeleton';

export default function Circles() {
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [zones, setZones] = useState([]);
  const [showForm, setShowForm] = useState(false);
  const [editItem, setEditItem] = useState(null);
  const [form, setForm] = useState({
    name: '',
    code: '',
    zone_id: '',
    address: '',
    phone: '',
    jurisdiction: '',
  });
  const [saving, setSaving] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [search, setSearch] = useState('');
  const [selectedZone, setSelectedZone] = useState('all');

  const fetchData = () => {
    setLoading(true);
    api.get('/circles')
      .then(r => setList(Array.isArray(r.data) ? r.data : (r.data.data || [])))
      .finally(() => setLoading(false));

    api.get('/lookup/zones')
      .then(r => setZones(r.data || []))
      .catch(() => {});
  };

  useEffect(() => {
    fetchData();
  }, []);

  const resetForm = () => {
    setForm({ name: '', code: '', zone_id: '', address: '', phone: '', jurisdiction: '' });
    setEditItem(null);
    setShowForm(false);
  };

  const openEdit = (item) => {
    setEditItem(item);
    setForm({
      name: item.name || '',
      code: item.code || '',
      zone_id: item.zone_id || '',
      address: item.address || '',
      phone: item.phone || '',
      jurisdiction: item.jurisdiction || '',
    });
    setShowForm(true);
  };

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    try {
      if (editItem) {
        await api.put(`/circles/${editItem.id}`, form);
      } else {
        await api.post('/circles', form);
      }
      resetForm();
      fetchData();
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to save circle');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await api.delete(`/circles/${deleteTarget.id}`);
      setList(list.filter(c => c.id !== deleteTarget.id));
      setDeleteTarget(null);
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete circle');
    }
  };

  const filteredList = useMemo(() => {
    const q = search.trim().toLowerCase();
    return list.filter(c => {
      const matchZone = selectedZone === 'all' || String(c.zone_id) === String(selectedZone);
      if (!matchZone) return false;
      if (!q) return true;
      return (
        c.name?.toLowerCase().includes(q) ||
        c.code?.toLowerCase().includes(q) ||
        c.zone?.name?.toLowerCase().includes(q) ||
        c.address?.toLowerCase().includes(q) ||
        c.phone?.toLowerCase().includes(q) ||
        c.jurisdiction?.toLowerCase().includes(q)
      );
    });
  }, [list, search, selectedZone]);

  if (loading) return <div className="page-content"><LoadingSkeleton type="table" columns={5} rows={8} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">Regional Circles &amp; Operational Hubs</h1>
          <p className="page-subtitle">Official NCCIA regional circles, district jurisdictions, and field offices across Pakistan</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <button className="btn btn-primary btn-sm" onClick={() => { resetForm(); setShowForm(true); }}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> Add Circle
          </button>
        </div>
      </div>

      {/* Summary Stat Cards */}
      <div style={{display:'grid', gridTemplateColumns:'repeat(auto-fit, minmax(200px, 1fr))', gap:'12px', marginBottom:'16px'}}>
        <div style={{background:'#fff', borderRadius:'10px', padding:'14px 18px', border:'1px solid #e2e8f0', boxShadow:'0 1px 3px rgba(0,0,0,0.04)'}}>
          <div style={{fontSize:'11.5px', color:'#64748b', fontWeight:600, textTransform:'uppercase', letterSpacing:'0.5px'}}>Total Operational Circles</div>
          <div style={{fontSize:'24px', fontWeight:800, color:'#0f172a', marginTop:'4px'}}>{list.length}</div>
          <div style={{fontSize:'11px', color:'#10b981', marginTop:'2px'}}>● Active PECA Jurisdictions</div>
        </div>
        <div style={{background:'#fff', borderRadius:'10px', padding:'14px 18px', border:'1px solid #e2e8f0', boxShadow:'0 1px 3px rgba(0,0,0,0.04)'}}>
          <div style={{fontSize:'11.5px', color:'#64748b', fontWeight:600, textTransform:'uppercase', letterSpacing:'0.5px'}}>Active Regional Zones</div>
          <div style={{fontSize:'24px', fontWeight:800, color:'#0f172a', marginTop:'4px'}}>{zones.length || 6}</div>
          <div style={{fontSize:'11px', color:'#64748b', marginTop:'2px'}}>Punjab, KPK, Balochistan, GB, Sindh, HQ</div>
        </div>
        <div style={{background:'#fff', borderRadius:'10px', padding:'14px 18px', border:'1px solid #e2e8f0', boxShadow:'0 1px 3px rgba(0,0,0,0.04)'}}>
          <div style={{fontSize:'11.5px', color:'#64748b', fontWeight:600, textTransform:'uppercase', letterSpacing:'0.5px'}}>Headquarters</div>
          <div style={{fontSize:'18px', fontWeight:800, color:'#1e40af', marginTop:'6px'}}>Islamabad (HQ)</div>
          <div style={{fontSize:'11px', color:'#2563eb', marginTop:'2px'}}>National Oversight &amp; Multi-Tenancy Root</div>
        </div>
      </div>

      <div className="card">
        <div className="card-header" style={{flexWrap:'wrap', gap:'10px', padding:'12px 18px'}}>
          <div className="card-title">
            <div className="card-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            Regional Circles &amp; District Coverages
          </div>

          <div style={{display:'flex', alignItems:'center', gap:'10px', flexWrap:'wrap', marginLeft:'auto'}}>
            {/* Zone Filter */}
            <select
              value={selectedZone}
              onChange={e => setSelectedZone(e.target.value)}
              className="filter-select"
              style={{height:'32px', fontSize:12, padding:'0 10px'}}
            >
              <option value="all">All Zones / Provinces</option>
              {zones.map(z => (
                <option key={z.id} value={z.id}>{z.name}</option>
              ))}
            </select>

            {/* Search input */}
            <input
              type="text"
              className="filter-select"
              placeholder="Search circle, district, address…"
              value={search}
              onChange={e => setSearch(e.target.value)}
              style={{height:'32px', padding:'0 10px', fontSize:12, minWidth:'220px'}}
            />

            <span style={{fontSize:'11.5px', color:'#6c757d'}}>
              Showing: <strong style={{color:'#2b2b2b'}}>{filteredList.length}</strong> of {list.length}
            </span>
          </div>
        </div>

        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th style={{width:'50px'}}>#</th>
                  <th style={{width:'220px'}}>Regional Hub / Circle</th>
                  <th style={{width:'150px'}}>Zone / Province</th>
                  <th style={{width:'280px'}}>Office Location &amp; Contact</th>
                  <th>District Jurisdictions</th>
                  <th style={{width:'90px', textAlign:'right'}}>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredList.map((c) => {
                  const isHq = c.code === 'ISB' || c.code === 'HQ' || (c.name || '').toLowerCase().includes('islamabad');
                  const districts = (c.jurisdiction || '')
                    .split(',')
                    .map(d => d.trim())
                    .filter(Boolean);

                  return (
                    <tr key={c.id}>
                      <td><span className="table-id">#{c.id}</span></td>
                      <td>
                        <div style={{display:'flex', flexDirection:'column', gap:3}}>
                          <div style={{display:'flex', alignItems:'center', gap:6}}>
                            <span style={{fontSize:'13px', fontWeight:700, color:'#0f172a'}}>{c.name}</span>
                            {isHq && (
                              <span style={{background:'#dbeafe', color:'#1e40af', fontSize:10, fontWeight:700, padding:'2px 6px', borderRadius:4}}>
                                HQ
                              </span>
                            )}
                          </div>
                          <span className="badge badge-active" style={{width:'fit-content', fontSize:10, padding:'1px 6px', letterSpacing:'0.5px'}}>
                            CODE: {c.code}
                          </span>
                        </div>
                      </td>
                      <td>
                        <span style={{fontSize:'12.5px', fontWeight:600, color:'#334155'}}>
                          {c.zone?.name || 'Unassigned Zone'}
                        </span>
                      </td>
                      <td>
                        <div style={{display:'flex', flexDirection:'column', gap:4}}>
                          {c.address ? (
                            <div style={{fontSize:'12px', color:'#1e293b', display:'flex', alignItems:'flex-start', gap:4}}>
                              <span style={{fontSize:'12px'}}>📍</span>
                              <span style={{lineHeight:'1.35'}}>{c.address}</span>
                            </div>
                          ) : (
                            <span style={{fontSize:'11.5px', color:'#94a3b8', fontStyle:'italic'}}>Address pending</span>
                          )}
                          {c.phone && (
                            <div style={{fontSize:'11.5px', color:'#0369a1', display:'flex', alignItems:'center', gap:4}}>
                              <span>📞</span>
                              <span style={{fontWeight:600}}>{c.phone}</span>
                            </div>
                          )}
                        </div>
                      </td>
                      <td>
                        {districts.length > 0 ? (
                          <div style={{display:'flex', flexWrap:'wrap', gap:'4px', maxWidth:'480px'}}>
                            {districts.map((dist, di) => (
                              <span
                                key={di}
                                onClick={() => setSearch(dist)}
                                title={`Click to filter by ${dist}`}
                                style={{
                                  background: search && dist.toLowerCase().includes(search.toLowerCase()) ? '#fef08a' : '#f1f5f9',
                                  color: '#334155',
                                  fontSize:'11px',
                                  padding:'2px 7px',
                                  borderRadius:'4px',
                                  border:'1px solid #e2e8f0',
                                  cursor:'pointer',
                                  transition:'background 0.15s'
                                }}
                              >
                                {dist}
                              </span>
                            ))}
                            <span style={{fontSize:'10px', color:'#94a3b8', alignSelf:'center', marginLeft:2}}>
                              ({districts.length} {districts.length === 1 ? 'district' : 'districts'})
                            </span>
                          </div>
                        ) : (
                          <span style={{fontSize:'11.5px', color:'#94a3b8', fontStyle:'italic'}}>All provincial areas</span>
                        )}
                      </td>
                      <td style={{textAlign:'right'}}>
                        <div style={{display:'inline-flex', gap:'6px'}}>
                          <button onClick={() => openEdit(c)} className="btn btn-outline btn-sm btn-icon" title="Edit Circle &amp; Jurisdiction">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                          </button>
                          <button onClick={() => setDeleteTarget(c)} className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:'8px',width:'36px',height:'36px',display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}} title="Delete Circle">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
                {filteredList.length === 0 && (
                  <tr>
                    <td colSpan={6} style={{textAlign:'center', padding:'32px', color:'#6c757d'}}>
                      <div style={{fontSize:'14px', fontWeight:600, color:'#475569'}}>No regional circles found</div>
                      <div style={{fontSize:'12px', marginTop:4}}>Try changing your search term or zone filter.</div>
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {showForm && (
        <div style={{position:'fixed',inset:0,zIndex:9999,display:'flex',alignItems:'center',justifyContent:'center',background:'rgba(0,0,0,0.5)',padding:20}} onClick={resetForm}>
          <div style={{background:'#fff',borderRadius:14,width:'100%',maxWidth:540,boxShadow:'0 24px 60px rgba(0,0,0,0.25)',overflow:'hidden'}} onClick={e => e.stopPropagation()}>
            <div style={{padding:'18px 24px',borderBottom:'1px solid #e2e8f0',background:'#f8fafc',display:'flex',justifyContent:'space-between',alignItems:'center'}}>
              <div>
                <h3 style={{margin:0,fontSize:16,fontWeight:700,color:'#0f172a'}}>
                  {editItem ? 'Edit Regional Circle &amp; Jurisdiction' : 'Add New Regional Circle'}
                </h3>
                <p style={{margin:'2px 0 0',fontSize:12,color:'#64748b'}}>
                  Configure operational station details, jurisdiction districts, and address
                </p>
              </div>
              <button onClick={resetForm} style={{background:'none',border:'none',fontSize:22,cursor:'pointer',color:'#94a3b8'}}>&times;</button>
            </div>

            <form onSubmit={handleSave} style={{padding:'20px 24px'}}>
              <div style={{display:'grid', gridTemplateColumns:'2fr 1fr', gap:'12px'}}>
                <div className="cf-field">
                  <label className="cf-label required">Circle Name</label>
                  <input
                    className="cf-input"
                    type="text"
                    placeholder="e.g. NCCIA Gujranwala"
                    value={form.name}
                    onChange={e => setForm(f => ({...f, name: e.target.value}))}
                    required
                  />
                </div>
                <div className="cf-field">
                  <label className="cf-label required">Station Code</label>
                  <input
                    className="cf-input"
                    type="text"
                    placeholder="e.g. GRW"
                    value={form.code}
                    onChange={e => setForm(f => ({...f, code: e.target.value.toUpperCase()}))}
                    required
                    maxLength={10}
                  />
                </div>
              </div>

              <div style={{display:'grid', gridTemplateColumns:'1fr 1fr', gap:'12px', marginTop:'8px'}}>
                <div className="cf-field">
                  <label className="cf-label">Regional Zone / Province</label>
                  <select
                    className="cf-input"
                    value={form.zone_id}
                    onChange={e => setForm(f => ({...f, zone_id: e.target.value}))}
                  >
                    <option value="">— Select Zone —</option>
                    {zones.map(z => <option key={z.id} value={z.id}>{z.name} ({z.code})</option>)}
                  </select>
                </div>
                <div className="cf-field">
                  <label className="cf-label">Official Contact / Phone</label>
                  <input
                    className="cf-input"
                    type="text"
                    placeholder="e.g. 055-9200001"
                    value={form.phone}
                    onChange={e => setForm(f => ({...f, phone: e.target.value}))}
                  />
                </div>
              </div>

              <div className="cf-field" style={{marginTop:'8px'}}>
                <label className="cf-label">Station Physical Address</label>
                <input
                  className="cf-input"
                  type="text"
                  placeholder="e.g. Regional Directorate NCCIA, Shabo Road..."
                  value={form.address}
                  onChange={e => setForm(f => ({...f, address: e.target.value}))}
                />
              </div>

              <div className="cf-field" style={{marginTop:'8px'}}>
                <label className="cf-label">District Jurisdictions (Comma separated)</label>
                <textarea
                  className="cf-input"
                  rows={3}
                  placeholder="e.g. Gujranwala, Gujrat, Sialkot, Mandi Bahauddin, Narowal, Hafizabad, Wazirabad"
                  value={form.jurisdiction}
                  onChange={e => setForm(f => ({...f, jurisdiction: e.target.value}))}
                  style={{padding:'8px 10px', fontSize:12}}
                />
                <span style={{fontSize:'11px', color:'#64748b', marginTop:3, display:'block'}}>
                  Specify all districts under this circle's authority. These are used for territorial routing and verification.
                </span>
              </div>

              <div style={{display:'flex',gap:10,justifyContent:'flex-end',marginTop:'18px',borderTop:'1px solid #e2e8f0',paddingTop:'14px'}}>
                <button type="button" className="btn btn-outline btn-sm" onClick={resetForm}>Cancel</button>
                <button type="submit" className="btn btn-primary btn-sm" disabled={saving}>
                  {saving ? 'Saving...' : (editItem ? 'Update Circle' : 'Create Circle')}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      <ConfirmModal
        open={!!deleteTarget}
        title="Delete Regional Circle"
        message={`Are you sure you want to delete ${deleteTarget?.name} (${deleteTarget?.code})? Staff and complaints mapped to this circle may be affected.`}
        confirmLabel="Delete Circle"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}

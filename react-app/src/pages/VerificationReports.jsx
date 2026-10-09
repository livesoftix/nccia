import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import ConfirmModal from '../components/ConfirmModal';
import { formatDisplayDateTime } from '../utils/datetime';
import { useAuth } from '../contexts/AuthContext';
import { canEditVerificationReport } from '../utils/permissions';

export default function VerificationReports() {
  const { user } = useAuth();
  const isAdmin = !!user?.roles?.some(r => (r.name || r) === 'admin');
  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [selected, setSelected] = useState([]);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [deleting, setDeleting] = useState(false);

  const fetchReports = () => {
    api.get('/verifications/reports-list').then(r => {
      setList(r.data.data || r.data);
      setLoading(false);
    }).catch(() => setLoading(false));
  };

  useEffect(() => {
    fetchReports();
    const interval = setInterval(fetchReports, 90000);
    return () => clearInterval(interval);
  }, []);

  const filteredList = list.filter(r => {
    const q = search.trim().toLowerCase();
    if (!q) return true;
    return (
      (r.tracking_no || '').toLowerCase().includes(q) ||
      (r.victim_name || '').toLowerCase().includes(q) ||
      (r.victim_cnic || '').toLowerCase().includes(q) ||
      (r.complaint?.complainant_name || '').toLowerCase().includes(q) ||
      (r.complaint?.tracking_no || '').toLowerCase().includes(q) ||
      (r.creator?.name || '').toLowerCase().includes(q)
    );
  });

  const selectedAll = isAdmin && filteredList.length > 0 && selected.length === filteredList.length;
  const selectedSome = isAdmin && selected.length > 0 && selected.length < filteredList.length;

  const handleBulkDelete = async () => {
    if (!isAdmin || !selected.length) return;
    setDeleting(true);
    try {
      await api.post('/verifications/reports/bulk-delete', { ids: selected });
      setSelected([]);
      fetchReports();
    } catch (e) {
      alert(e.response?.data?.message || 'Delete failed');
    } finally {
      setDeleting(false);
      setDeleteOpen(false);
    }
  };

  if (loading) return <div style={{ padding: 40, textAlign: 'center', color: '#888' }}>Loading...</div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <div className="page-label">Verifications</div>
          <h1 className="page-title">Verification Reports</h1>
          <p className="page-subtitle">Saved victim verification report records</p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions" style={{display:'flex', gap:8}}>
          {isAdmin && selected.length > 0 && (
            <button
              type="button"
              className="btn btn-sm"
              style={{background:'rgba(229,62,62,0.12)', color:'#e53e3e', border:'none', borderRadius:8, height:36, padding:'0 12px', fontWeight:600, cursor:'pointer'}}
              onClick={() => setDeleteOpen(true)}
            >
              Delete Selected ({selected.length})
            </button>
          )}
          <Link to="/verifications/reports/create" className="btn btn-primary btn-sm">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            New Report
          </Link>
        </div>
      </div>

      <div className="tab-nav" style={{display:'flex',gap:'2px',marginBottom:'16px',padding:'4px',background:'#f3f0f0',borderRadius:'8px'}}>
        <Link to="/verifications" className="tab-nav-item" style={{display:'flex',alignItems:'center',gap:'6px',padding:'8px 16px',borderRadius:'6px',fontSize:'13px',fontWeight:600,color:'#6c757d',background:'transparent'}}>
          Verifications
        </Link>
        <Link to="/verifications/reports" className="tab-nav-item active" style={{display:'flex',alignItems:'center',gap:'6px',padding:'8px 16px',borderRadius:'6px',fontSize:'13px',fontWeight:600,color:'#015C94',background:'#fff',boxShadow:'0 2px 4px rgba(0,0,0,0.05)'}}>
          Reports
        </Link>
      </div>

      <div className="card" style={{marginBottom:'20px'}}>
        <div className="card-header">
          <div className="card-title">
            <div className="card-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
            Saved Reports
          </div>
          <div className="section-actions" style={{display:'flex', gap:10, alignItems:'center'}}>
            <input
              type="text"
              className="filter-select"
              placeholder="Search tracking / victim / CNIC…"
              value={search}
              onChange={e => setSearch(e.target.value)}
              style={{height:34, padding:'0 12px', width:240, border:'1.5px solid #264078', borderRadius:8, fontSize:13}}
            />
            <span style={{fontSize:'11.5px',color:'#6c757d'}}>Total: <strong style={{color:'#2b2b2b'}}>{filteredList.length}</strong></span>
          </div>
        </div>
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  {isAdmin && (
                    <th style={{width:40}}>
                      <input
                        type="checkbox"
                        checked={selectedAll}
                        ref={el => { if (el) el.indeterminate = selectedSome; }}
                        onChange={e => setSelected(e.target.checked ? filteredList.map(r => r.id) : [])}
                      />
                    </th>
                  )}
                  <th>#</th>
                  <th>Tracking No.</th>
                  <th>Complaint</th>
                  <th>Victim Name</th>
                  <th>CNIC</th>
                  <th>Crime Category</th>
                  <th>City</th>
                  <th>Created By</th>
                  <th>Date</th>
                  <th style={{textAlign:'center'}}>Action</th>
                </tr>
              </thead>
              <tbody>
                {filteredList.map((r, i) => (
                  <tr key={r.id}>
                    {isAdmin && (
                      <td>
                        <input
                          type="checkbox"
                          checked={selected.includes(r.id)}
                          onChange={e => {
                            const s = new Set(selected);
                            e.target.checked ? s.add(r.id) : s.delete(r.id);
                            setSelected([...s]);
                          }}
                        />
                      </td>
                    )}
                    <td><span style={{fontSize:'12px',color:'#6c757d'}}>{i + 1}</span></td>
                    <td><span className="table-id">{r.tracking_no}</span></td>
                    <td>
                      <div style={{display:'flex',flexDirection:'column',gap:'2px'}}>
                        <span style={{fontSize:'13px',fontWeight:500}}>{r.complaint?.complainant_name || 'N/A'}</span>
                        <span style={{fontSize:'11.5px',color:'#6c757d',fontFamily:'monospace'}}>{r.complaint?.tracking_no || ''}</span>
                      </div>
                    </td>
                    <td><span style={{fontSize:'13px',fontWeight:500}}>{r.victim_name}</span></td>
                    <td><span style={{fontSize:'12px',fontFamily:'monospace',color:'#6c757d'}}>{r.victim_cnic}</span></td>
                    <td><span className="badge badge-active">{r.crime_category}</span></td>
                    <td><span style={{fontSize:'12.5px'}}>{r.city}</span></td>
                    <td><span style={{fontSize:'12.5px'}}>{r.creator?.name || 'N/A'}</span></td>
                    <td><span style={{fontSize:'12px',color:'#6c757d'}}>{formatDisplayDateTime(r.created_at)}</span></td>
                    <td style={{textAlign:'center', whiteSpace:'nowrap'}}>
                      {canEditVerificationReport(user, r) && (
                        <Link to={`/verifications/reports/${r.id}/edit`} className="btn btn-outline btn-sm btn-icon" title="Edit Report" style={{marginRight: 4}}>
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </Link>
                      )}
                      <a href={`/verifications/reports/${r.id}/pdf`} target="_blank" rel="noreferrer" className="btn btn-outline btn-sm btn-icon" title="Download PDF">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="12 10 12 18 15 15"/></svg>
                      </a>
                    </td>
                  </tr>
                ))}
                {filteredList.length === 0 && <tr><td colSpan={11} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No reports found</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <ConfirmModal
        open={deleteOpen}
        title="Delete Verification Reports"
        message={`Delete ${selected.length} selected report(s)? This cannot be undone.`}
        confirmLabel={deleting ? 'Deleting…' : 'Delete'}
        onConfirm={handleBulkDelete}
        onCancel={() => setDeleteOpen(false)}
      />
    </div>
  );
}

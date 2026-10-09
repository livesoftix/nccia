import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
import api from '../api';
import ConfirmModal from '../components/ConfirmModal';
import LoadingSkeleton from '../components/LoadingSkeleton';
import OfficerHandoverModal from '../components/OfficerHandoverModal';
import { ROLE_FEATURES } from '../utils/permissions';
import { useAuth } from '../contexts/AuthContext';

const ALL_FEATURES = [
  { key: 'dashboard', label: 'Dashboard' },
  { key: 'analytics', label: 'Analytics' },
  { key: 'complaints', label: 'Complaints' },
  { key: 'verifications', label: 'Verifications' },
  { key: 'reports', label: 'Verification Reports' },
  { key: 'enquiries', label: 'Enquiries' },
  { key: 'io_records', label: 'IO Records' },
  { key: 'dac_cases', label: 'DAC Cases' },
  { key: 'court_cases', label: 'Court Cases' },
  { key: 'dsr_reports', label: 'DSR (Daily Situation Report)' },
  { key: 'do_letters', label: 'D.O. Letters (Monthly)' },
  { key: 'users', label: 'Users Management' },
  { key: 'circles', label: 'Circles' },
  { key: 'offence_types', label: 'Crime Categories' },
  { key: 'sms_logs', label: 'SMS & WhatsApp Logs' },
  { key: 'login_history', label: 'Login History' },
  { key: 'reference', label: 'Reference / Law Books' },
  { key: 'profile', label: 'Profile' },
];

export default function Users() {
  const { user: currentUser } = useAuth();
  const isCi = currentUser?.role === 'circle_incharge' || currentUser?.roles?.some(r => (r.name || r) === 'circle_incharge');
  const isSuper = currentUser?.role === 'admin' || currentUser?.role === 'director_general' || currentUser?.roles?.some(r => ['admin', 'director_general'].includes(r.name || r));

  const [list, setList] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [search, setSearch] = useState('');
  const [permsTarget, setPermsTarget] = useState(null);
  const [savingPerm, setSavingPerm] = useState(null);
  const [resetTarget, setResetTarget] = useState(null);
  const [resetPwd, setResetPwd] = useState('');
  const [savingReset, setSavingReset] = useState(false);
  const [handoverTarget, setHandoverTarget] = useState(null);

  const fetchData = useCallback((p = page) => {
    setLoading(true);
    api.get('/users', { params: { page: p } }).then(r => {
      const d = r.data.data || r.data;
      if (Array.isArray(d)) {
        setList(d);
      } else if (d?.data) {
        setList(d.data);
        setLastPage(d.last_page || 1);
        setPage(d.current_page || 1);
      } else {
        setList([]);
      }
    }).finally(() => setLoading(false));
  }, [page]);

  useEffect(() => { fetchData(); }, [fetchData]);

  const handleDelete = async () => {
    if (!deleteTarget) return;
    try {
      await api.delete(`/users/${deleteTarget.id}`);
      setList(list.filter(u => u.id !== deleteTarget.id));
      setDeleteTarget(null);
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to delete user');
    }
  };

  const userPerms = (u) => (u.permissions || []).map(p => p.name || p);

  const hasPerm = (u, feat) => userPerms(u).includes(feat);

  const roleGrants = (u) => {
    const roles = u.roles?.map?.(r => r.name || r) || [u.role || ''];
    const granted = new Set();
    roles.forEach(r => {
      const feats = ROLE_FEATURES[r] || [];
      feats.forEach(f => granted.add(f));
    });
    return granted;
  };

  const [permError, setPermError] = useState(null);

  const togglePerm = async (u, feat) => {
    setSavingPerm(feat);
    setPermError(null);
    try {
      if (hasPerm(u, feat)) {
        const r = await api.post(`/users/${u.id}/revoke-permission`, { permission: feat });
        setList(list.map(x => x.id === u.id ? r.data.user : x));
        setPermsTarget(r.data.user);
      } else {
        const r = await api.post(`/users/${u.id}/grant-permission`, { permission: feat });
        setList(list.map(x => x.id === u.id ? r.data.user : x));
        setPermsTarget(r.data.user);
      }
    } catch (err) {
      setPermError(err.response?.data?.message || err.response?.data?.error || err.message || 'An unexpected error occurred');
    } finally {
      setSavingPerm(null);
    }
  };

  const handleResetPassword = async () => {
    if (!resetTarget || !resetPwd) return;
    if ([...resetPwd].length < 15 || [...resetPwd].length > 128) {
      alert('Use a password of 15–128 characters.');
      return;
    }
    setSavingReset(true);
    try {
      await api.post(`/users/${resetTarget.id}/reset-password`, { password: resetPwd });
      alert(`Password reset successfully for ${resetTarget.name}`);
      setResetTarget(null);
      setResetPwd('');
    } catch (err) {
      alert(err.response?.data?.message || 'Failed to reset password');
    } finally {
      setSavingReset(false);
    }
  };

  const filteredList = list.filter(u =>
    u.name?.toLowerCase().includes(search.toLowerCase()) ||
    u.email?.toLowerCase().includes(search.toLowerCase())
  );

  if (loading) return <div className="page-content"><LoadingSkeleton type="table" columns={5} rows={10} /></div>;

  return (
    <div className="page-content">
      <div className="page-header">
        <div className="page-title-group">
          <h1 className="page-title">{isCi && !isSuper ? 'Station Staff & Officers' : 'Users'}</h1>
          <p className="page-subtitle">
            {isCi && !isSuper
              ? `Manage station officers and personnel for ${currentUser?.circle?.name || 'your station'}`
              : 'Manage all system users, permissions & access'}
          </p>
          <div className="title-underline"></div>
        </div>
        <div className="page-actions">
          <Link to="/users/create" className="btn btn-primary btn-sm">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg> {isCi && !isSuper ? 'Add Officer' : 'Add User'}
          </Link>
        </div>
      </div>

      <div className="card">
        <div className="card-header">
          <div className="card-title">
            <div className="card-icon">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            </div>
            {isCi && !isSuper ? 'Station Personnel' : 'All Users'}
          </div>
          <div className="section-actions">
            <input type="text" className="filter-select" placeholder="Search name or email…" value={search} onChange={e => setSearch(e.target.value)} style={{height:'32px',padding:'0 10px',fontSize:12}} />
            <span style={{fontSize:'11.5px',color:'#6c757d',marginLeft:8}}>Total: <strong style={{color:'#2b2b2b'}}>{filteredList.length}</strong></span>
          </div>
        </div>
        <div className="card-body" style={{padding:0}}>
          <div className="table-responsive">
            <table className="data-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Designation</th>
                  {isSuper && <th>Permissions</th>}
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredList.map((u, i) => (
                  <tr key={u.id}>
                    <td><span className="table-id">#{u.id}</span></td>
                    <td>
                      <div style={{display:'flex',alignItems:'center',gap:6,flexWrap:'wrap'}}>
                        <span style={{fontSize:'13px',fontWeight:600}}>{u.name}</span>
                        {u.status === 'suspended' && (
                          <span style={{background:'#fee2e2',color:'#b91c1c',fontSize:10,fontWeight:800,padding:'2px 6px',borderRadius:4}}>
                            ⛔ SUSPENDED
                          </span>
                        )}
                      </div>
                    </td>
                    <td>{u.email}</td>
                    <td>
                      {u.roles?.length > 0 ? (
                        <span className="badge badge-active">{u.roles[0].name === 'operator' ? 'Front Desk Officer (FDO)' : u.roles[0].name.replace(/_/g, ' ')}</span>
                      ) : (
                        <span className="badge badge-pending">No Role</span>
                      )}
                    </td>
                    <td>{u.designation || '-'}</td>
                    {isSuper && (
                      <td>
                        <button onClick={() => setPermsTarget(u)} className="btn btn-outline btn-sm" title="Manage Permissions" style={{fontSize:11,whiteSpace:'nowrap'}}>
                          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" style={{marginRight:4}}><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                          Permissions
                        </button>
                      </td>
                    )}
                    <td>
                      <div style={{display:'flex',gap:'6px',alignItems:'center'}}>
                        <button
                          onClick={() => setHandoverTarget(u)}
                          className="btn btn-sm"
                          title="Officer Lifecycle: Transfer, Suspend or Handover Caseload"
                          style={{
                            background: u.status === 'suspended' ? '#fee2e2' : '#e0f2fe',
                            color: u.status === 'suspended' ? '#b91c1c' : '#0369a1',
                            border: '1px solid',
                            borderColor: u.status === 'suspended' ? '#fca5a5' : '#bae6fd',
                            borderRadius: '8px', height: '36px',
                            padding: '0 10px', display: 'inline-flex', alignItems: 'center',
                            gap: 4, cursor: 'pointer', fontSize: 11.5, fontWeight: 700, whiteSpace: 'nowrap'
                          }}
                        >
                          <span>🔄 Handover</span>
                        </button>
                        <Link to={`/users/${u.id}/edit`} className="btn btn-outline btn-sm btn-icon" title="Edit">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </Link>
                        <button onClick={() => { setResetTarget(u); setResetPwd(''); }} className="btn btn-sm" title="Reset Password" style={{background:'rgba(37,99,235,0.12)',color:'#2563eb',border:'none',borderRadius:'8px',width:'36px',height:'36px',display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}}>
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                        </button>
                        <button onClick={() => setDeleteTarget(u)} className="btn btn-sm" style={{background:'rgba(229,62,62,0.15)',color:'#e53e3e',border:'none',borderRadius:'8px',width:'36px',height:'36px',display:'inline-flex',alignItems:'center',justifyContent:'center',cursor:'pointer'}} title="Delete">
                          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
                {filteredList.length === 0 && <tr><td colSpan={isSuper ? 7 : 6} style={{textAlign:'center',padding:'24px',color:'#6c757d'}}>No users found</td></tr>}
              </tbody>
            </table>
          </div>
        </div>
        {lastPage > 1 && (
          <div style={{display:'flex',justifyContent:'center',alignItems:'center',gap:8,padding:'12px 16px',borderTop:'1px solid #f0f0f0'}}>
            <button disabled={page <= 1} onClick={() => setPage(p => Math.max(1, p - 1))} style={{padding:'6px 14px',borderRadius:6,border:'1px solid #dee2e6',background: page <= 1 ? '#f8f9fa' : '#fff',color: page <= 1 ? '#adb5bd' : '#495057',cursor: page <= 1 ? 'default' : 'pointer',fontSize:13}}>Prev</button>
            <span style={{fontSize:13,color:'#6c757d'}}>Page {page} of {lastPage}</span>
            <button disabled={page >= lastPage} onClick={() => setPage(p => Math.min(lastPage, p + 1))} style={{padding:'6px 14px',borderRadius:6,border:'1px solid #dee2e6',background: page >= lastPage ? '#f8f9fa' : '#fff',color: page >= lastPage ? '#adb5bd' : '#495057',cursor: page >= lastPage ? 'default' : 'pointer',fontSize:13}}>Next</button>
          </div>
        )}
      </div>

      {/* Permissions Modal */}
      {permsTarget && (
        <div style={{position:'fixed',inset:0,background:'rgba(0,0,0,0.45)',zIndex:9999,display:'flex',alignItems:'center',justifyContent:'center',padding:20}} onClick={() => setPermsTarget(null)}>
          <div style={{background:'#fff',borderRadius:14,maxWidth:600,width:'100%',maxHeight:'80vh',overflow:'auto',boxShadow:'0 24px 60px rgba(0,0,0,0.2)'}} onClick={e => e.stopPropagation()}>
            <div style={{display:'flex',justifyContent:'space-between',alignItems:'center',padding:'18px 22px',borderBottom:'1px solid #f0f0f0'}}>
              <div>
                <div style={{fontSize:16,fontWeight:600}}>Feature Permissions</div>
                <div style={{fontSize:12,color:'#6c757d',marginTop:2}}>{permsTarget.name} &middot; {permsTarget.role || permsTarget.roles?.[0]?.name || 'No Role'}</div>
              </div>
              <button onClick={() => setPermsTarget(null)} style={{background:'none',border:'none',fontSize:22,cursor:'pointer',color:'#999',padding:'0 4px'}}>&times;</button>
            </div>
            <div style={{padding:'10px 22px 22px'}}>
              {ALL_FEATURES.map(f => {
                const grantedByRole = roleGrants(permsTarget).has(f.key);
                const directlyAssigned = hasPerm(permsTarget, f.key);
                const enabled = grantedByRole || directlyAssigned;
                const busy = savingPerm === f.key;
                return (
                  <div key={f.key} style={{display:'flex',alignItems:'center',justifyContent:'space-between',padding:'10px 0',borderBottom:'1px solid #f5f5f5'}}>
                    <div style={{display:'flex',alignItems:'center',gap:8}}>
                      <span style={{fontSize:14,fontWeight:500}}>{f.label}</span>
                      {grantedByRole && <span style={{fontSize:11,color:'#6c757d',background:'#f0f0f0',padding:'2px 8px',borderRadius:4}}>by role</span>}
                      {directlyAssigned && !grantedByRole && <span style={{fontSize:11,color:'#2563eb',background:'rgba(37,99,235,0.1)',padding:'2px 8px',borderRadius:4}}>direct</span>}
                    </div>
                    <div style={{display:'flex',alignItems:'center',gap:8}}>
                      <button
                        onClick={() => togglePerm(permsTarget, f.key)}
                        disabled={busy}
                        style={{
                          width:44,height:24,borderRadius:12,border:'none',cursor:'pointer',
                          background: directlyAssigned ? '#2563eb' : (grantedByRole ? '#93c5fd' : '#d1d5db'),
                          position:'relative', transition:'background 0.2s', opacity: busy ? 0.6 : 1
                        }}
                        title={directlyAssigned ? 'Remove direct access' : 'Grant direct access'}
                      >
                        <div style={{
                          position:'absolute',top:2,width:20,height:20,borderRadius:'50%',background:'#fff',boxShadow:'0 1px 3px rgba(0,0,0,0.2)',
                          left: (directlyAssigned || grantedByRole) ? 22 : 2, transition:'left 0.2s'
                        }}/>
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
            {permError && <div style={{margin:'0 22px 12px',padding:'10px 14px',background:'rgba(229,62,62,0.1)',border:'1px solid #e53e3e',borderRadius:8,fontSize:12,color:'#e53e3e'}}>{permError}</div>}
            <div style={{padding:'12px 22px',borderTop:'1px solid #f0f0f0',textAlign:'right',fontSize:12,color:'#6c757d'}}>
              <strong>Grant Access</strong> = toggle ON &middot; <strong>Remove Access</strong> = toggle OFF &middot; Features with "by role" are inherited from the user's role but can be overridden.
            </div>
          </div>
        </div>
      )}

      {/* Reset Password Modal */}
      {resetTarget && (
        <div style={{position:'fixed',inset:0,background:'rgba(0,0,0,0.45)',zIndex:9999,display:'flex',alignItems:'center',justifyContent:'center',padding:20}} onClick={() => { setResetTarget(null); setResetPwd(''); }}>
          <div style={{background:'#fff',borderRadius:14,maxWidth:420,width:'100%',boxShadow:'0 24px 60px rgba(0,0,0,0.2)'}} onClick={e => e.stopPropagation()}>
            <div style={{display:'flex',justifyContent:'space-between',alignItems:'center',padding:'18px 22px',borderBottom:'1px solid #f0f0f0'}}>
              <div style={{fontSize:16,fontWeight:600}}>Reset Password</div>
              <button onClick={() => { setResetTarget(null); setResetPwd(''); }} style={{background:'none',border:'none',fontSize:22,cursor:'pointer',color:'#999',padding:'0 4px'}}>&times;</button>
            </div>
            <div style={{padding:'18px 22px'}}>
              <p style={{fontSize:13,color:'#6c757d',marginBottom:14}}>
                Set a new password for <strong>{resetTarget.name}</strong> ({resetTarget.email})
              </p>
              <label className="cf-label required" style={{fontSize:12}}>New Password</label>
              <input
                type="password"
                className="cf-input"
                value={resetPwd}
                onChange={e => setResetPwd(e.target.value)}
                placeholder="15–128 characters"
                autoComplete="new-password"
                minLength={15}
                maxLength={128}
                autoFocus
                style={{marginTop:6}}
              />
              <div style={{display:'flex',gap:10,justifyContent:'flex-end',marginTop:18}}>
                <button onClick={() => { setResetTarget(null); setResetPwd(''); }} className="btn btn-outline btn-sm">Cancel</button>
                <button onClick={handleResetPassword} disabled={savingReset || !resetPwd} className="btn btn-primary btn-sm">
                  {savingReset ? 'Resetting...' : 'Reset Password'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Officer Lifecycle & Handover Modal */}
      {handoverTarget && (
        <OfficerHandoverModal
          officer={handoverTarget}
          isOpen={Boolean(handoverTarget)}
          onClose={() => setHandoverTarget(null)}
          onSuccess={() => fetchData()}
        />
      )}

      <ConfirmModal
        open={!!deleteTarget}
        title="Delete User"
        message={`Delete user ${deleteTarget?.name} (${deleteTarget?.email})? This will remove their portal access.`}
        confirmLabel="Delete"
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}

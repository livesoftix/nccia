import { useState } from 'react';

export default function ConfirmModal({ open, title, message, confirmLabel, cancelLabel, variant, onConfirm, onCancel }) {
  if (!open) return null;

  const bgColor = variant === 'warning' ? 'rgba(229,161,0,0.15)' : 'rgba(229,62,62,0.15)';
  const iconColor = variant === 'warning' ? '#e5a100' : '#e53e3e';
  const btnBg = variant === 'warning' ? '#e5a100' : '#e53e3e';

  return (
    <div style={{
      position: 'fixed', inset: 0, zIndex: 9999,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      background: 'rgba(0,0,0,0.5)', padding: 20
    }} onClick={onCancel}>
      <div style={{
        background: '#fff', borderRadius: 12, width: '100%', maxWidth: 380,
        boxShadow: '0 20px 60px rgba(0,0,0,0.2)', textAlign: 'center', padding: 0
      }} onClick={e => e.stopPropagation()}>
        <div style={{ padding: '28px 24px 20px' }}>
          <div style={{
            width: 48, height: 48, borderRadius: '50%', display: 'flex',
            alignItems: 'center', justifyContent: 'center', margin: '0 auto 14px',
            background: bgColor, color: iconColor, fontSize: 22
          }}>
            {variant === 'warning' ? (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            ) : (
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            )}
          </div>
          <h3 style={{ margin: '0 0 8px', fontSize: 16, fontWeight: 600, color: '#1a1a1a' }}>{title || 'Confirm'}</h3>
          <p style={{ margin: 0, fontSize: 14, color: '#6c757d' }}>{message || 'Are you sure?'}</p>
        </div>
        <div style={{
          display: 'flex', gap: 10, justifyContent: 'center',
          padding: '16px 24px 24px'
        }}>
          <button onClick={onCancel} style={{
            padding: '8px 20px', borderRadius: 8, border: '1.5px solid #dee2e6',
            background: '#fff', color: '#495057', cursor: 'pointer', fontWeight: 600, fontSize: 13
          }}>{cancelLabel || 'Cancel'}</button>
          <button onClick={onConfirm} style={{
            padding: '8px 20px', borderRadius: 8, border: 'none',
            background: btnBg, color: '#fff', cursor: 'pointer', fontWeight: 600, fontSize: 13
          }}>{confirmLabel || 'Delete'}</button>
        </div>
      </div>
    </div>
  );
}

import CaseChatPanel from './CaseChatPanel';

export default function CaseChatModal({
  open,
  onClose,
  type = 'enquiry',
  id,
  caseNumber = '',
  title = '',
  officers = [],
}) {
  if (!open || !id) return null;

  return (
    <div
      style={{
        position: 'fixed',
        inset: 0,
        zIndex: 9999,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'rgba(15, 23, 42, 0.65)',
        backdropFilter: 'blur(3px)',
        padding: 16,
      }}
      onClick={onClose}
    >
      <div
        style={{
          width: '100%',
          maxWidth: 680,
          background: '#fff',
          borderRadius: 14,
          boxShadow: '0 25px 60px rgba(0,0,0,0.3)',
          overflow: 'hidden',
          display: 'flex',
          flexDirection: 'column',
          animation: 'fadeIn 0.2s ease',
        }}
        onClick={e => e.stopPropagation()}
      >
        <div style={{
          padding: '12px 18px',
          background: '#015C94',
          color: '#fff',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'space-between',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <span style={{ fontSize: 18 }}>💬</span>
            <span style={{ fontWeight: 700, fontSize: 15 }}>
              Case Discussion · #{caseNumber || `${type.toUpperCase()}-${id}`}
            </span>
          </div>
          <button
            onClick={onClose}
            style={{
              background: 'rgba(255,255,255,0.15)',
              border: 'none',
              borderRadius: '50%',
              width: 28,
              height: 28,
              color: '#fff',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              fontSize: 16,
              lineHeight: 1,
            }}
          >
            ✕
          </button>
        </div>

        <div style={{ padding: 12 }}>
          <CaseChatPanel
            type={type}
            id={id}
            caseNumber={caseNumber}
            title={title}
            officers={officers}
            compact={true}
          />
        </div>
      </div>
    </div>
  );
}

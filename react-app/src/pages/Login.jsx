import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useNavigate, Link } from 'react-router-dom';
import { isForensicUser } from '../utils/permissions';
import LoginCaptcha from '../components/LoginCaptcha';

export default function Login() {
  const { login, error, retryAfter, mfaRequired, clearError, user } = useAuth();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [mfaCode, setMfaCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [captcha, setCaptcha] = useState({ ready: false, token: '' });
  const [captchaReset, setCaptchaReset] = useState(0);
  const [showPwd, setShowPwd] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const canvasRef = useRef(null);
  const animRef = useRef(null);
  const timerRef = useRef(null);

  useEffect(() => {
    if (user) { navigate(isForensicUser(user) ? '/forensic' : '/', { replace: true }); }
  }, [user, navigate]);

  useEffect(() => {
    if (retryAfter > 0) {
      setCountdown(retryAfter);
      timerRef.current = setInterval(() => {
        setCountdown(prev => {
          if (prev <= 1) {
            clearInterval(timerRef.current);
            clearError();
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
      return () => clearInterval(timerRef.current);
    }
  }, [retryAfter, clearError]);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    let W, H, dots = [];
    const resize = () => { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; };
    window.addEventListener('resize', resize);
    resize();
    const makeDot = () => ({
      x: Math.random() * W, y: Math.random() * H,
      r: Math.random() * 2.2 + 0.5,
      vx: (Math.random() - 0.5) * 0.3,
      vy: (Math.random() - 0.5) * 0.3,
      a: Math.random() * 0.5 + 0.1,
      color: Math.random() > 0.6 ? '#2B2B2B' : '#264078'
    });
    for (let i = 0; i < 70; i++) dots.push(makeDot());
    const animate = () => {
      ctx.clearRect(0, 0, W, H);
      dots.forEach(d => {
        d.x += d.vx; d.y += d.vy;
        if (d.x < 0) d.x = W; if (d.x > W) d.x = 0;
        if (d.y < 0) d.y = H; if (d.y > H) d.y = 0;
        ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
        ctx.fillStyle = d.color + Math.round(d.a * 255).toString(16).padStart(2, '0');
        ctx.fill();
      });
      for (let i = 0; i < dots.length; i++) {
        for (let j = i + 1; j < dots.length; j++) {
          const dx = dots[i].x - dots[j].x, dy = dots[i].y - dots[j].y, dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 90) {
            ctx.beginPath(); ctx.moveTo(dots[i].x, dots[i].y); ctx.lineTo(dots[j].x, dots[j].y);
            ctx.strokeStyle = `rgba(38,64,120,${0.08 * (1 - dist / 90)})`; ctx.lineWidth = 0.7; ctx.stroke();
          }
        }
      }
      animRef.current = requestAnimationFrame(animate);
    };
    animate();
    return () => { window.removeEventListener('resize', resize); if (animRef.current) cancelAnimationFrame(animRef.current); };
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (submitting || countdown > 0 || !captcha.ready) return;
    setSubmitting(true);
    try {
      await login(email, password, mfaCode, captcha.token);
      setPassword('');
      setMfaCode('');
    } catch { /* Authentication errors are displayed by AuthContext. */ } finally {
      setCaptchaReset(value => value + 1);
      setSubmitting(false);
    }
  };

  return (
    <div style={{minHeight:'100vh',background:'#fff',display:'flex',alignItems:'center',justifyContent:'center',fontFamily:"'DM Sans', sans-serif",position:'relative',overflow:'hidden'}}>
      <div style={{position:'fixed',inset:0,background:'#fff',zIndex:0}}></div>
      <canvas ref={canvasRef} id="particles" style={{position:'fixed',inset:0,zIndex:1,pointerEvents:'none'}}></canvas>

      <div className="card" style={{position:'relative',zIndex:10,display:'flex',width:'min(900px, 95vw)',minHeight:'560px',borderRadius:'24px',overflow:'hidden',boxShadow:'0 20px 40px rgba(0,0,0,0.2), 0 0 0 1px #2B2B2B',animation:'cardRise .7s cubic-bezier(.22,1,.36,1) both'}}>
        <div className="panel-left" style={{flex:1,background:'#2B2B2B',padding:'48px 36px',display:'flex',flexDirection:'column',alignItems:'center',justifyContent:'center',position:'relative',overflow:'hidden',color:'#fff'}}>
          <div style={{position:'absolute',width:'320px',height:'320px',borderRadius:'50%',border:'1.5px solid rgba(255,255,255,0.2)',top:'-80px',left:'-80px',animation:'rotateSlow 18s linear infinite'}}></div>
          <div style={{position:'absolute',width:'220px',height:'220px',borderRadius:'50%',border:'1.5px solid rgba(255,255,255,0.15)',bottom:'-60px',right:'-60px',animation:'rotateSlow 22s linear infinite reverse'}}></div>
          <div style={{display:'flex',alignItems:'center',gap:16,marginBottom:20}}>
            <div className="logo-shield" style={{width:'120px',height:'120px',background:'rgba(255,255,255,0.15)',backdropFilter:'blur(4px)',borderRadius:'24px',border:'2px solid rgba(255,255,255,0.5)',display:'flex',alignItems:'center',justifyContent:'center',boxShadow:'0 8px 20px rgba(0,0,0,0.3)'}}>
              <img src="/images/NCCIA.webp" alt="NCCIA Logo" style={{width:'130px',height:'130px',objectFit:'cover',borderRadius:'22px'}} />
            </div>
          </div>
          <div className="brand-name" style={{fontFamily:"'Playfair Display', serif",fontSize:'1.8rem',fontWeight:700,letterSpacing:'.06em',color:'#fff',textShadow:'0 2px 8px rgba(0,0,0,0.3)',textAlign:'center'}}>NCCIA</div>
          <div className="brand-sub" style={{fontSize:'.68rem',fontWeight:400,letterSpacing:'.18em',textTransform:'uppercase',color:'#fff',marginTop:'4px',textAlign:'center'}}>National Cyber Crime Investigation Agency</div>
          <div className="divider-line" style={{width:'48px',height:'1.5px',background:'rgba(255,255,255,0.5)',margin:'22px 0'}}></div>
          <ul className="info-list" style={{listStyle:'none',width:'100%',display:'flex',flexDirection:'column',gap:'14px'}}>
            <li style={{display:'flex',alignItems:'flex-start',gap:'12px',fontSize:'.85rem',lineHeight:1.5,color:'rgba(255,255,255,0.95)'}}>
              <span className="icon-dot" style={{width:'28px',height:'28px',flexShrink:0,background:'rgba(0,0,0,0.25)',borderRadius:'8px',display:'flex',alignItems:'center',justifyContent:'center'}}><svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.8" width="16" height="16"><ellipse cx="9" cy="6" rx="5" ry="2" /><path d="M4 6v5c0 1.1 2.24 2 5 2s5-.9 5-2V6" /><path d="M4 11v5c0 1.1 2.24 2 5 2s5-.9 5-2v-5" /><circle cx="19" cy="10" r="2.5" /><path d="M19 12.5v3" /><line x1="19" y1="15.5" x2="19" y2="18" /><line x1="17.5" y1="18" x2="20.5" y2="18" /></svg></span>
              <span>Secure access to classified intelligence databases and Complain management systems</span>
            </li>
            <li style={{display:'flex',alignItems:'flex-start',gap:'12px',fontSize:'.85rem',lineHeight:1.5,color:'rgba(255,255,255,0.95)'}}>
              <span className="icon-dot" style={{width:'28px',height:'28px',flexShrink:0,background:'rgba(0,0,0,0.25)',borderRadius:'8px',display:'flex',alignItems:'center',justifyContent:'center'}}><svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2" width="16" height="16"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /><path d="M9 12l2 2 4-4" /></svg></span>
              <span>IP address is being monitored for security purposes</span>
            </li>
            <li style={{display:'flex',alignItems:'flex-start',gap:'12px',fontSize:'.85rem',lineHeight:1.5,color:'rgba(255,255,255,0.95)'}}>
              <span className="icon-dot" style={{width:'28px',height:'28px',flexShrink:0,background:'rgba(0,0,0,0.25)',borderRadius:'8px',display:'flex',alignItems:'center',justifyContent:'center'}}><svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2" width="16" height="16"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" /><circle cx="12" cy="16" r="1" /></svg></span>
              <span>Unauthorized access is strictly prohibited</span>
            </li>
            <li style={{display:'flex',alignItems:'flex-start',gap:'12px',fontSize:'.85rem',lineHeight:1.5,color:'rgba(255,255,255,0.95)'}}>
              <span className="icon-dot" style={{width:'28px',height:'28px',flexShrink:0,background:'rgba(0,0,0,0.25)',borderRadius:'8px',display:'flex',alignItems:'center',justifyContent:'center'}}><svg viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="1.8" width="16" height="16"><path d="M12 20v-4" opacity="0.7" /><line x1="10" y1="20" x2="14" y2="20" strokeWidth="2" /><line x1="12" y1="4" x2="12" y2="16" strokeWidth="2" /><line x1="5" y1="8" x2="19" y2="8" strokeWidth="1.8" /><path d="M5 8 C3.5 9.5, 3.5 13, 5 14" strokeWidth="1.5" /><circle cx="5" cy="11" r="0.8" fill="white" stroke="none" /><path d="M19 8 C20.5 9.5, 20.5 13, 19 14" strokeWidth="1.5" /><circle cx="19" cy="11" r="0.8" fill="white" stroke="none" /><circle cx="12" cy="9.5" r="1.2" fill="white" stroke="white" strokeWidth="1" opacity="0.9" /></svg></span>
              <span>Confidentiality of the access ID and password is solely the responsibility of the concerned person.</span>
            </li>
          </ul>
          <div className="contact-strip" style={{marginTop:'28px',padding:'14px 20px',background:'rgba(0,0,0,0.25)',borderRadius:'14px',border:'1px solid rgba(255,255,255,0.3)',width:'100%',textAlign:'center',fontSize:'.75rem',color:'rgba(255,255,255,0.9)'}}>
            <strong style={{color:'#fff'}}>Helpdesk:</strong> +1 (800) NCCIA-HQ<br />
            <strong style={{color:'#fff'}}>Email:</strong> support@NCCIA.gov.pk
          </div>
        </div>
        <div className="panel-right" style={{flex:1,background:'#fff',padding:'48px 42px',display:'flex',flexDirection:'column',justifyContent:'center',position:'relative',overflow:'hidden'}}>
          <div className="blob blob-1" style={{position:'absolute',borderRadius:'50%',filter:'blur(60px)',pointerEvents:'none',opacity:0.12,width:'200px',height:'200px',background:'#2B2B2B',top:'-60px',right:'-60px',animation:'blobDrift 8s ease-in-out infinite alternate'}}></div>
          <div className="blob blob-2" style={{position:'absolute',borderRadius:'50%',filter:'blur(60px)',pointerEvents:'none',opacity:0.12,width:'150px',height:'150px',background:'#264078',bottom:'-40px',left:'-40px',animation:'blobDrift 11s ease-in-out infinite alternate-reverse'}}></div>
          <div className="form-header" style={{marginBottom:'32px'}}>
            <h2 style={{fontFamily:"'Playfair Display', serif",fontSize:'1.65rem',color:'#2B2B2B',fontWeight:700}}>Welcome Back</h2>
            <p style={{marginTop:'6px',fontSize:'.83rem',color:'#264078'}}>Sign in to your NCCIA officer account</p>
          </div>
          <form onSubmit={handleSubmit}>
            {mfaRequired && <div className="form-group" style={{marginBottom:20}}>
              <label htmlFor="mfa-code">Authenticator or recovery code</label>
              <input id="mfa-code" type="text" autoComplete="one-time-code" autoFocus maxLength={20}
                value={mfaCode} onChange={e => setMfaCode(e.target.value.trim())} required
                style={{width:'100%',padding:12,border:'1.5px solid #264078',borderRadius:11,marginTop:7}} />
            </div>}
            {error && (
              <div style={{background: retryAfter ? 'rgba(229,62,62,0.1)' : 'rgba(43,43,43,0.08)',border:`1px solid ${retryAfter ? '#e53e3e' : '#2B2B2B'}`,borderRadius:'10px',padding:'10px 14px',marginBottom:'18px',fontSize:'13px',color: retryAfter ? '#e53e3e' : '#2B2B2B',fontWeight:500}}>
                {error}
                {countdown > 0 && <div style={{marginTop:'6px',fontSize:'18px',fontWeight:700,letterSpacing:'1px'}}>⏱ {countdown}s</div>}
              </div>
            )}
            <div className="form-group" style={{marginBottom:'20px'}}>
              <label htmlFor="email" style={{display:'block',fontSize:'.75rem',fontWeight:600,letterSpacing:'.06em',textTransform:'uppercase',color:'#2B2B2B',marginBottom:'7px'}}>Email Address</label>
              <div className="input-wrap" style={{position:'relative'}}>
                <span className="icon" style={{position:'absolute',left:'14px',top:'50%',transform:'translateY(-50%)',color:'#264078',pointerEvents:'none',display:'flex'}}>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="18" height="18"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="M22 4L12 13L2 4" /></svg>
                </span>
                <input type="email" id="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="officer@NCCIA.gov.pk" autoComplete="email" required style={{width:'100%',padding:'12px 14px 12px 42px',border:'1.5px solid #264078',borderRadius:'11px',fontSize:'.88rem',fontFamily:"'DM Sans', sans-serif",color:'#2B2B2B',background:'#fff',outline:'none'}} />
              </div>
            </div>
            <div className="form-group" style={{marginBottom:'20px'}}>
              <label htmlFor="password" style={{display:'block',fontSize:'.75rem',fontWeight:600,letterSpacing:'.06em',textTransform:'uppercase',color:'#2B2B2B',marginBottom:'7px'}}>Password</label>
              <div className="input-wrap" style={{position:'relative'}}>
                <span className="icon" style={{position:'absolute',left:'14px',top:'50%',transform:'translateY(-50%)',color:'#264078',pointerEvents:'none',display:'flex'}}>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" width="18" height="18"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0110 0v4" /></svg>
                </span>
                <input type={showPwd ? 'text' : 'password'} id="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="••••••••••••" autoComplete="current-password" required style={{width:'100%',padding:'12px 14px 12px 42px',border:'1.5px solid #264078',borderRadius:'11px',fontSize:'.88rem',fontFamily:"'DM Sans', sans-serif",color:'#2B2B2B',background:'#fff',outline:'none'}} />
                <button type="button" className="eye-toggle" onClick={() => setShowPwd(!showPwd)} aria-label="Show password" style={{position:'absolute',right:'13px',top:'50%',transform:'translateY(-50%)',background:'none',border:'none',cursor:'pointer',color:'#264078',padding:'4px',display:'flex'}}>
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                </button>
              </div>
            </div>
            <div className="row-options" style={{display:'flex',alignItems:'center',justifyContent:'space-between',marginBottom:'24px',fontSize:'.8rem'}}>
              <span style={{color:'#2B2B2B'}}>Sessions expire automatically for security.</span>
              <Link to="/forgot-password" className="forgot-link" style={{color:'#264078',textDecoration:'none',fontWeight:500}}>Forgot password?</Link>
            </div>
            <LoginCaptcha onChange={setCaptcha} resetKey={captchaReset} />
            <button type="submit" disabled={submitting || countdown > 0 || !captcha.ready} className="btn-login" style={{width:'100%',padding:'13px',background:'#015C94',border:'none',borderRadius:'12px',color:'#fff',fontFamily:"'DM Sans', sans-serif",fontSize:'.92rem',fontWeight:600,letterSpacing:'.04em',cursor:'pointer',boxShadow:'0 4px 12px rgba(0,0,0,0.3)'}}>Sign In to Portal</button>
          </form>
          <p className="help-text" style={{marginTop:'20px',textAlign:'center',fontSize:'.78rem',color:'#264078'}}>Need access? <a href="#" style={{color:'#2B2B2B',textDecoration:'none',fontWeight:500}}>Request officer credentials</a></p>
          <div className="security-badge" style={{marginTop:'22px',display:'flex',alignItems:'center',justifyContent:'center',gap:'6px',fontSize:'.72rem',color:'#264078'}}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" width="14" height="14"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
            <span>256-bit SSL Encrypted · Secure Government Portal</span>
          </div>
        </div>
      </div>

      <style>{`
        @keyframes cardRise { from { opacity: 0; transform: translateY(32px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes rotateSlow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        @keyframes pulsePing { 0%, 100% { opacity: .6; transform: scale(1); } 50% { opacity: .2; transform: scale(1.06); } }
        @keyframes blobDrift { from { transform: translate(0, 0) scale(1); } to { transform: translate(20px, 15px) scale(1.1); } }
        .btn-login:disabled { opacity: .55; cursor: not-allowed !important; }
        input:focus { border-color: #2B2B2B !important; box-shadow: 0 0 0 3px rgba(0,0,0,0.1) !important; }
        @media (max-width: 680px) { .card { flex-direction: column; width: 96vw; min-height: auto; border-radius: 18px; } .panel-left { padding: 36px 28px; } .info-list, .contact-strip { display: none; } .panel-right { padding: 36px 28px; } .brand-name { font-size: 1.6rem; } }
      `}</style>
    </div>
  );
}

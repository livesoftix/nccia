<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCCIA – Secure Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
*,
*::before,
*::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}
:root {
    --primary-black: #2B2B2B;
    --primary-white: #ffffff;
    --primary-darkblue: #2B2B2B;
    --text-dark: #2B2B2B;
    --text-mid: #264078;
    --text-muted: #264078;
    --border: #264078;
    --input-focus: #2B2B2B;
    --error: #2B2B2B;
    --success: #264078;
}
body {
    min-height: 100vh;
    background: var(--primary-white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'DM Sans', sans-serif;
    overflow: hidden;
    position: relative;
}
.bg-canvas {
    position: fixed;
    inset: 0;
    background: var(--primary-white);
    z-index: 0;
}
canvas#particles {
    position: fixed;
    inset: 0;
    z-index: 1;
    pointer-events: none;
}
.card {
    position: relative;
    z-index: 10;
    display: flex;
    width: min(900px, 95vw);
    min-height: 560px;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 0 0 1px var(--primary-darkblue);
    animation: cardRise .7s cubic-bezier(.22, 1, .36, 1) both;
}
@keyframes cardRise {
    from { opacity: 0; transform: translateY(32px) scale(.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.panel-left {
    flex: 1;
    background: var(--primary-darkblue);
    padding: 48px 36px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
    color: var(--primary-white);
}
.panel-left::before {
    content: '';
    position: absolute;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    border: 1.5px solid rgba(255, 255, 255, 0.2);
    top: -80px;
    left: -80px;
    animation: rotateSlow 18s linear infinite;
}
.panel-left::after {
    content: '';
    position: absolute;
    width: 220px;
    height: 220px;
    border-radius: 50%;
    border: 1.5px solid rgba(255, 255, 255, 0.15);
    bottom: -60px;
    right: -60px;
    animation: rotateSlow 22s linear infinite reverse;
}
@keyframes rotateSlow {
    from { transform: rotate(0deg); }
    to   { transform: rotate(360deg); }
}
.logo-wrap {
    position: relative;
    margin-bottom: 28px;
    animation: fadeDown .6s .2s both;
}
.logo-shield {
    width: 90px;
    height: 90px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(4px);
    border-radius: 24px;
    border: 2px solid rgba(255, 255, 255, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}
.logo-shield img {
    width: 110px;
    height: 110px;
    object-fit: cover;
    border-radius: 22px;
}
.logo-ring {
    position: absolute;
    inset: -8px;
    border-radius: 32px;
    border: 1.5px solid rgba(255, 255, 255, 0.4);
    animation: pulsePing 2.4s ease-in-out infinite;
}
@keyframes pulsePing {
    0%, 100% { opacity: .6; transform: scale(1); }
    50%      { opacity: .2; transform: scale(1.06); }
}
.brand-name {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    letter-spacing: .06em;
    color: var(--primary-white);
    text-shadow: 0 2px 8px rgba(0,0,0,0.3);
    text-align: center;
}
.brand-sub {
    font-size: .72rem;
    font-weight: 400;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: var(--primary-white);
    margin-top: 4px;
    text-align: center;
}
.divider-line {
    width: 48px;
    height: 1.5px;
    background: rgba(255, 255, 255, 0.5);
    margin: 22px 0;
}
.info-list {
    list-style: none;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.info-list li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    font-size: .85rem;
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.95);
    animation: fadeDown .5s calc(.45s + var(--i)*.08s) both;
}
.info-list li .icon-dot {
    width: 28px;
    height: 28px;
    flex-shrink: 0;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.contact-strip {
    margin-top: 28px;
    padding: 14px 20px;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    width: 100%;
    text-align: center;
    font-size: .75rem;
    color: rgba(255, 255, 255, 0.9);
}
.contact-strip strong {
    color: var(--primary-white);
}
@keyframes fadeDown {
    from { opacity: 0; transform: translateY(-12px); }
    to   { opacity: 1; transform: translateY(0); }
}
.panel-right {
    flex: 1;
    background: var(--primary-white);
    padding: 48px 42px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(60px);
    pointer-events: none;
    opacity: 0.12;
}
.blob-1 {
    width: 200px;
    height: 200px;
    background: #2B2B2B;
    top: -60px;
    right: -60px;
    animation: blobDrift 8s ease-in-out infinite alternate;
}
.blob-2 {
    width: 150px;
    height: 150px;
    background: #264078;
    bottom: -40px;
    left: -40px;
    animation: blobDrift 11s ease-in-out infinite alternate-reverse;
}
@keyframes blobDrift {
    from { transform: translate(0, 0) scale(1); }
    to   { transform: translate(20px, 15px) scale(1.1); }
}
.form-header {
    margin-bottom: 32px;
}
.form-header h2 {
    font-family: 'Playfair Display', serif;
    font-size: 1.65rem;
    color: #2B2B2B;
    font-weight: 700;
}
.form-header p {
    margin-top: 6px;
    font-size: .83rem;
    color: #264078;
}
.form-group {
    margin-bottom: 20px;
}
label {
    display: block;
    font-size: .75rem;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #2B2B2B;
    margin-bottom: 7px;
}
.input-wrap {
    position: relative;
}
.input-wrap .icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #264078;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
}
.input-wrap .icon svg {
    width: 18px;
    height: 18px;
    stroke: #264078;
    fill: none;
    stroke-width: 1.8;
}
input[type="email"],
input[type="password"],
input[type="text"] {
    width: 100%;
    padding: 12px 14px 12px 42px;
    border: 1.5px solid #264078;
    border-radius: 11px;
    font-size: .88rem;
    font-family: 'DM Sans', sans-serif;
    color: #2B2B2B;
    background: #ffffff;
    transition: border-color .2s, box-shadow .2s;
    outline: none;
}
input:focus {
    border-color: #2B2B2B;
    box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
}
input:focus ~ .icon svg {
    stroke: #2B2B2B;
}
.eye-toggle {
    position: absolute;
    right: 13px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    color: #264078;
    padding: 4px;
    transition: color .2s;
    display: flex;
    align-items: center;
}
.eye-toggle:hover {
    color: #2B2B2B;
}
.row-options {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    font-size: .8rem;
}
.checkbox-wrap {
    display: flex;
    align-items: center;
    gap: 7px;
    cursor: pointer;
    color: #2B2B2B;
}
.checkbox-wrap input[type="checkbox"] {
    accent-color: #264078;
}
.forgot-link {
    color: #264078;
    text-decoration: none;
    font-weight: 500;
}
.forgot-link:hover {
    color: #2B2B2B;
    text-decoration: underline;
}
.btn-login {
    width: 100%;
    padding: 13px;
    background: #015C94;
    border: none;
    border-radius: 12px;
    color: #ffffff;
    font-family: 'DM Sans', sans-serif;
    font-size: .92rem;
    font-weight: 600;
    letter-spacing: .04em;
    cursor: pointer;
    transition: transform .15s, background .2s, box-shadow .2s;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
.btn-login:hover {
    background: #264078;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
}
.btn-login:active {
    transform: translateY(0);
}
.help-text {
    margin-top: 20px;
    text-align: center;
    font-size: .78rem;
    color: #264078;
}
.help-text a {
    color: #2B2B2B;
    text-decoration: none;
    font-weight: 500;
}
.help-text a:hover {
    text-decoration: underline;
}
.security-badge {
    margin-top: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-size: .72rem;
    color: #264078;
}
.security-badge svg {
    width: 14px;
    height: 14px;
    stroke: #2B2B2B;
}
@media (max-width: 680px) {
    .card {
        flex-direction: column;
        width: 96vw;
        min-height: auto;
        border-radius: 18px;
    }
    .panel-left {
        padding: 36px 28px;
    }
    .info-list, .contact-strip {
        display: none;
    }
    .panel-right {
        padding: 36px 28px;
    }
    .brand-name {
        font-size: 1.6rem;
    }
}
    </style>
</head>
<body>
<div class="bg-canvas"></div>
<canvas id="particles"></canvas>
<div class="card">
    <div class="panel-left">
        <div class="logo-wrap">
            <div class="logo-shield">
                <img src="{{ asset('images/NCCIA.webp') }}" alt="NCCIA Logo">
            </div>
            <div class="logo-ring"></div>
        </div>
        <div class="brand-name">NCCIA</div>
        <div class="brand-sub">National Cyber Crime Investigation Agency</div>
        <div class="divider-line"></div>
        <ul class="info-list">
            <li style="--i:0">
                <span class="icon-dot"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><ellipse cx="9" cy="6" rx="5" ry="2" /><path d="M4 6v5c0 1.1 2.24 2 5 2s5-.9 5-2V6" /><path d="M4 11v5c0 1.1 2.24 2 5 2s5-.9 5-2v-5" /><circle cx="19" cy="10" r="2.5" /><path d="M19 12.5v3" /><line x1="19" y1="15.5" x2="19" y2="18" /><line x1="17.5" y1="18" x2="20.5" y2="18" /></svg></span>
                <span>Secure access to classified intelligence databases and Complain management systems</span>
            </li>
            <li style="--i:1">
                <span class="icon-dot"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /><path d="M9 12l2 2 4-4" /></svg></span>
                <span>IP address is being monitored for security purposes</span>
            </li>
            <li style="--i:2">
                <span class="icon-dot"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0110 0v4" /><circle cx="12" cy="16" r="1" /></svg></span>
                <span>Unauthorized access is strictly prohibited</span>
            </li>
            <li style="--i:3">
                <span class="icon-dot"><svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><path d="M12 20v-4" opacity="0.7" /><line x1="10" y1="20" x2="14" y2="20" stroke-width="2" /><line x1="12" y1="4" x2="12" y2="16" stroke-width="2" /><line x1="5" y1="8" x2="19" y2="8" stroke-width="1.8" /><path d="M5 8 C3.5 9.5, 3.5 13, 5 14" stroke-width="1.5" /><circle cx="5" cy="11" r="0.8" fill="white" stroke="none" /><path d="M19 8 C20.5 9.5, 20.5 13, 19 14" stroke-width="1.5" /><circle cx="19" cy="11" r="0.8" fill="white" stroke="none" /><circle cx="12" cy="9.5" r="1.2" fill="white" stroke="white" stroke-width="1" opacity="0.9" /></svg></span>
                <span>Confidentiality of the access ID and password is solely the responsibility of the concerned person.</span>
            </li>
        </ul>
        <div class="contact-strip">
            <strong>Helpdesk:</strong> +1 (800) NCCIA-HQ<br>
            <strong>Email:</strong> support@NCCIA.gov.pk
        </div>
    </div>
    <div class="panel-right">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="form-header">
            <h2>Welcome Back</h2>
            <p>Sign in to your NCCIA officer account</p>
        </div>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            @if ($errors->any())
            <div style="background:rgba(43,43,43,0.08);border:1px solid #2B2B2B;border-radius:10px;padding:10px 14px;margin-bottom:18px;font-size:13px;color:#2B2B2B;font-weight:500;">
                {{ $errors->first('email') }}
            </div>
            @endif
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="M22 4L12 13L2 4" /></svg></span>
                    <input type="email" id="email" name="email" placeholder="officer@NCCIA.gov.pk" autocomplete="email" value="{{ old('email') }}" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0110 0v4" /></svg></span>
                    <input type="password" id="password" name="password" placeholder="••••••••••••" autocomplete="current-password" required>
                    <button class="eye-toggle" onclick="togglePwd(this)" type="button" aria-label="Show password">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                    </button>
                </div>
            </div>
            <div class="row-options">
                <label class="checkbox-wrap">
                    <input type="checkbox" name="remember"> Remember this device
                </label>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>
            <button type="submit" class="btn-login">Sign In to Portal</button>
        </form>
        <p class="help-text">Need access? <a href="#">Request officer credentials</a></p>
        <div class="security-badge">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
            <span>256-bit SSL Encrypted · Secure Government Portal</span>
        </div>
    </div>
</div>
<script>
    const canvas = document.getElementById('particles');
    const ctx = canvas.getContext('2d');
    let W, H, dots = [];
    function resize() { W = canvas.width = window.innerWidth; H = canvas.height = window.innerHeight; }
    window.addEventListener('resize', resize);
    resize();
    function makeDot() {
        const colorChoice = Math.random() > 0.6 ? '#2B2B2B' : '#264078';
        return { x: Math.random() * W, y: Math.random() * H, r: Math.random() * 2.2 + 0.5, vx: (Math.random() - 0.5) * 0.3, vy: (Math.random() - 0.5) * 0.3, a: Math.random() * 0.5 + 0.1, color: colorChoice };
    }
    for (let i = 0; i < 70; i++) dots.push(makeDot());
    function animate() {
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
        requestAnimationFrame(animate);
    }
    animate();
    function togglePwd(btn) { const inp = document.getElementById('password'); inp.type = inp.type === 'password' ? 'text' : 'password'; }
</script>
</body>
</html>

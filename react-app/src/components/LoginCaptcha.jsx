import { useEffect, useRef, useState } from 'react';
import api from '../api';

let scriptPromise;
function loadRecaptcha() {
  if (window.grecaptcha?.render) return Promise.resolve(window.grecaptcha);
  if (scriptPromise) return scriptPromise;
  scriptPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    const fail = () => {
      clearTimeout(timer);
      script.remove();
      scriptPromise = null;
      reject(new Error('CAPTCHA could not load. Check your connection and retry.'));
    };
    const timer = setTimeout(fail, 15000);
    window.ncciaRecaptchaReady = () => {
      clearTimeout(timer);
      resolve(window.grecaptcha);
    };
    script.src = 'https://www.google.com/recaptcha/api.js?onload=ncciaRecaptchaReady&render=explicit';
    script.async = true;
    script.defer = true;
    script.onerror = fail;
    document.head.appendChild(script);
  });
  return scriptPromise;
}

export default function LoginCaptcha({ onChange, resetKey }) {
  const container = useRef(null);
  const widget = useRef(null);
  const [status, setStatus] = useState('loading');
  const [message, setMessage] = useState('Loading human verification…');
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    let active = true;
    const host = container.current;
    onChange({ ready: false, token: '' });
    setStatus('loading');
    setMessage('Loading human verification…');
    const fail = (text) => {
      if (!active) return;
      onChange({ ready: false, token: '' });
      setStatus('error');
      setMessage(text);
    };
    (async () => {
      try {
        const { data } = await api.get('/auth/captcha', { timeout: 10000 });
        if (!active) return;
        if (data.enabled === false) {
          setStatus('disabled');
          onChange({ ready: true, token: '' });
          return;
        }
        if (data.enabled !== true || !data.site_key) throw new Error('Invalid CAPTCHA configuration');
        const captcha = await loadRecaptcha();
        if (!active) return;
        const element = document.createElement('div');
        host.replaceChildren(element);
        widget.current = captcha.render(element, {
          sitekey: data.site_key,
          size: host.clientWidth < 304 ? 'compact' : 'normal',
          callback: token => {
            if (!active) return;
            setStatus('widget');
            setMessage('');
            onChange({ ready: true, token });
          },
          'expired-callback': () => {
            if (!active) return;
            onChange({ ready: false, token: '' });
            setMessage('Verification expired. Please complete the CAPTCHA again.');
          },
          'error-callback': () => fail('CAPTCHA connection failed. Please retry.'),
        });
        setStatus('widget');
        setMessage('');
      } catch {
        fail('Human verification could not load. Check your connection and retry.');
      }
    })();
    return () => {
      active = false;
      if (widget.current !== null && window.grecaptcha?.reset) {
        window.grecaptcha.reset(widget.current);
      }
      widget.current = null;
      host.replaceChildren();
    };
  }, [onChange, attempt]);

  useEffect(() => {
    if (widget.current !== null && window.grecaptcha?.reset) {
      window.grecaptcha.reset(widget.current);
      onChange({ ready: false, token: '' });
      setMessage('');
    }
  }, [resetKey, onChange]);

  return (
    <div hidden={status === 'disabled'} style={{ marginBottom: 20 }}>
      <div ref={container} />
      {message && <p role={status === 'error' ? 'alert' : 'status'} style={{ fontSize: 13, marginTop: 8 }}>{message}</p>}
      {status === 'error' && <button type="button" onClick={() => setAttempt(value => value + 1)}>Retry verification</button>}
    </div>
  );
}

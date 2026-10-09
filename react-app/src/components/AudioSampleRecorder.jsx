import { useState, useEffect, useRef } from 'react';

export default function AudioSampleRecorder({
  label = 'Voice Sample (30s)',
  file = null,
  onChange,
  disabled = false,
  targetDuration = 30,
}) {
  const [mode, setMode] = useState('record');
  const [isRecording, setIsRecording] = useState(false);
  const [isPaused, setIsPaused] = useState(false);
  const [isStarting, setIsStarting] = useState(false); // loading state
  const [recordingSeconds, setRecordingSeconds] = useState(0);
  const [audioLevel, setAudioLevel] = useState(0);
  const [previewUrl, setPreviewUrl] = useState(null);
  const [errorMsg, setErrorMsg] = useState('');
  const [diagInfo, setDiagInfo] = useState(''); // diagnostic detail

  const mediaRecorderRef = useRef(null);
  const audioChunksRef = useRef([]);
  const timerRef = useRef(null);
  const streamRef = useRef(null);
  const audioCtxRef = useRef(null);
  const analyserRef = useRef(null);
  const animFrameRef = useRef(null);

  useEffect(() => {
    if (file instanceof Blob || file instanceof File) {
      const url = URL.createObjectURL(file);
      setPreviewUrl(url);
      return () => URL.revokeObjectURL(url);
    } else if (typeof file === 'string' && file) {
      setPreviewUrl(file.startsWith('http') || file.startsWith('/') ? file : `/${file}`);
    } else {
      setPreviewUrl(null);
    }
  }, [file]);

  useEffect(() => {
    return () => { cleanupAudio(); };
  }, []);

  const cleanupAudio = () => {
    if (timerRef.current) { clearInterval(timerRef.current); timerRef.current = null; }
    if (animFrameRef.current) { cancelAnimationFrame(animFrameRef.current); animFrameRef.current = null; }
    if (streamRef.current) { streamRef.current.getTracks().forEach(t => t.stop()); streamRef.current = null; }
    if (audioCtxRef.current && audioCtxRef.current.state !== 'closed') {
      try { audioCtxRef.current.close(); } catch (e) {}
      audioCtxRef.current = null;
    }
    analyserRef.current = null;
    setAudioLevel(0);
  };

  const startRecording = async () => {
    setErrorMsg('');
    setDiagInfo('');
    setIsStarting(true);
    audioChunksRef.current = [];
    setRecordingSeconds(0);
    setAudioLevel(0);

    // ── Step 1: Check secure context + API availability ──
    if (!window.isSecureContext) {
      setIsStarting(false);
      setErrorMsg('❌ Microphone requires HTTPS. Open this site with https:// (not http://), then try again.');
      setMode('upload');
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setIsStarting(false);
      setErrorMsg('Browser does not support microphone recording. Use "Attach File" below.');
      setMode('upload');
      return;
    }

    // ── Step 2: Request microphone ──
    let stream = null;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
    } catch (err) {
      setIsStarting(false);
      cleanupAudio();

      const errDetail = `[${err.name}] ${err.message}`;
      setDiagInfo(errDetail);

      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        setErrorMsg('❌ Microphone is blocked. Go to browser address bar → 🔒 → Microphone → Allow, then refresh page.');
        setMode('upload');
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        setErrorMsg('❌ No microphone found on this device. Please use "Attach File" below to upload audio.');
        setMode('upload');
      } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
        setErrorMsg('❌ Microphone is busy / used by another app (Teams, Zoom, etc.). Close other apps then try again.');
        setMode('upload');
      } else {
        setErrorMsg(`❌ Error: ${err.name} — ${err.message}. Use "Attach File" below.`);
        setMode('upload');
      }
      return;
    }

    // ── Step 3: Got stream — Start recording ──
    streamRef.current = stream;

    // VU Meter (optional — failure here does NOT block recording)
    try {
      const AudioCtx = window.AudioContext || window.webkitAudioContext;
      if (AudioCtx) {
        const audioCtx = new AudioCtx();
        if (audioCtx.state === 'suspended') await audioCtx.resume();
        audioCtxRef.current = audioCtx;
        const source = audioCtx.createMediaStreamSource(stream);
        const analyser = audioCtx.createAnalyser();
        analyser.fftSize = 128;
        analyser.smoothingTimeConstant = 0.4;
        source.connect(analyser);
        analyserRef.current = analyser;
        const dataArray = new Uint8Array(analyser.frequencyBinCount);
        const tick = () => {
          if (!analyserRef.current) return;
          analyserRef.current.getByteFrequencyData(dataArray);
          const avg = dataArray.reduce((a, b) => a + b, 0) / dataArray.length;
          setAudioLevel(Math.min(100, Math.round((avg / 128) * 100)));
          animFrameRef.current = requestAnimationFrame(tick);
        };
        tick();
      }
    } catch (eVis) {
      console.warn('VU meter failed (non-critical):', eVis);
    }

    // MediaRecorder setup
    const mimes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg', 'audio/mp4'];
    let mimeType = mimes.find(m => {
      try { return MediaRecorder.isTypeSupported(m); } catch (e) { return false; }
    }) || '';

    let recorder;
    try {
      recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
    } catch (e) {
      recorder = new MediaRecorder(stream);
    }
    mediaRecorderRef.current = recorder;

    recorder.ondataavailable = (e) => {
      if (e.data && e.data.size > 0) audioChunksRef.current.push(e.data);
    };

    recorder.onstop = () => {
      const blob = new Blob(audioChunksRef.current, { type: recorder.mimeType || 'audio/webm' });
      const ext = recorder.mimeType?.includes('ogg') ? 'ogg' : recorder.mimeType?.includes('mp4') ? 'mp4' : 'webm';
      const fname = `${label.toLowerCase().replace(/[^a-z0-9]/g, '_')}_${Date.now()}.${ext}`;
      const audioFile = new File([blob], fname, { type: blob.type });
      if (onChange) onChange(audioFile);
      cleanupAudio();
      setIsRecording(false);
      setIsPaused(false);
    };

    try { recorder.start(250); } catch (e) {
      try { recorder.start(); } catch (e2) { console.warn('recorder.start failed:', e2); }
    }

    setIsStarting(false);
    setIsRecording(true);
    setIsPaused(false);

    let currentSec = 0;
    timerRef.current = setInterval(() => {
      currentSec += 1;
      setRecordingSeconds(currentSec);
      if (currentSec >= targetDuration) stopRecording();
    }, 1000);
  };

  const stopRecording = () => {
    if (timerRef.current) { clearInterval(timerRef.current); timerRef.current = null; }
    if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
      try { mediaRecorderRef.current.requestData(); } catch (e) {}
      mediaRecorderRef.current.stop();
    }
    setIsRecording(false);
    setIsPaused(false);
  };

  const pauseRecording = () => {
    if (mediaRecorderRef.current?.state === 'recording') {
      mediaRecorderRef.current.pause();
      if (timerRef.current) clearInterval(timerRef.current);
      setIsPaused(true);
    }
  };

  const resumeRecording = () => {
    if (mediaRecorderRef.current?.state === 'paused') {
      mediaRecorderRef.current.resume();
      setIsPaused(false);
      timerRef.current = setInterval(() => {
        setRecordingSeconds(prev => {
          const next = prev + 1;
          if (next >= targetDuration) stopRecording();
          return next;
        });
      }, 1000);
    }
  };

  const fmt = (sec) => `${String(Math.floor(sec / 60)).padStart(2, '0')}:${String(sec % 60).padStart(2, '0')}`;
  const progress = Math.min(100, Math.round((recordingSeconds / targetDuration) * 100));

  return (
    <div style={{
      border: isRecording ? '2px solid #ef4444' : '1.5px solid #cbd5e1',
      borderRadius: 10,
      padding: 14,
      background: isRecording ? '#fff5f5' : '#ffffff',
      transition: 'all 0.2s',
      boxShadow: isRecording ? '0 0 0 3px rgba(239,68,68,0.15)' : 'none',
    }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10, flexWrap: 'wrap', gap: 6 }}>
        <label className="cf-label required" style={{ margin: 0, fontWeight: 700, fontSize: 13, color: '#1e293b' }}>
          🎙️ {label}
        </label>
{!disabled && !isRecording && !isStarting && (
          <div style={{ display: 'flex', gap: 4, background: '#f1f5f9', padding: 2, borderRadius: 6 }}>
            <button type="button" onClick={() => { startRecording(); setErrorMsg(''); setDiagInfo(''); }}
              style={{ background: isRecording ? '#dc2626' : '#015C94', color: isRecording ? '#fff' : '#64748b', border: 'none', padding: '4px 10px', borderRadius: 4, fontSize: 11.5, fontWeight: 700, cursor: isRecording ? 'default' : 'pointer' }}>
              🎙️ Live Mic
            </button>
            {!isRecording && (
              <button type="button" onClick={() => setMode('upload')}
                style={{ background: mode === 'upload' ? '#015C94' : 'transparent', color: mode === 'upload' ? '#fff' : '#64748b', border: 'none', padding: '4px 10px', borderRadius: 4, fontSize: 11.5, fontWeight: 700, cursor: 'pointer' }}>
                📁 Attach File / USB
              </button>
            )}
          </div>
        )}
      </div>

      {/* Error message */}
      {errorMsg && (
        <div style={{ padding: '8px 12px', background: '#fee2e2', color: '#991b1b', borderRadius: 6, fontSize: 12, marginBottom: 6, border: '1px solid #fca5a5', lineHeight: 1.5 }}>
          {errorMsg}
        </div>
      )}

      {/* Diagnostic detail (small, gray) */}
      {diagInfo && (
        <div style={{ padding: '4px 10px', background: '#f8fafc', color: '#64748b', borderRadius: 4, fontSize: 10.5, marginBottom: 8, fontFamily: 'monospace', border: '1px solid #e2e8f0', wordBreak: 'break-all' }}>
          🔍 {diagInfo}
        </div>
      )}

      {/* Loading / initializing state */}
      {isStarting && (
        <div style={{ textAlign: 'center', padding: '18px 10px', background: '#f0f9ff', borderRadius: 8, border: '1px solid #bae6fd', color: '#0369a1', fontSize: 13, fontWeight: 600 }}>
          <span style={{ display: 'inline-block', marginRight: 8 }}>⏳</span>
          Initializing microphone... Please allow access if browser prompts.
        </div>
      )}

      {/* RECORDING IN PROGRESS */}
      {isRecording && !isStarting && (
        <div style={{ padding: 14, background: '#ffffff', borderRadius: 8, border: '1px solid #fca5a5', textAlign: 'center' }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, marginBottom: 8 }}>
            <span style={{ width: 12, height: 12, borderRadius: '50%', background: isPaused ? '#f59e0b' : '#ef4444', display: 'inline-block', animation: isPaused ? 'none' : 'pulse 1s infinite' }} />
            <span style={{ fontWeight: 800, fontSize: 16, color: isPaused ? '#b45309' : '#b91c1c' }}>
              {isPaused ? '⏸️ Paused:' : '🔴 Recording:'} {fmt(recordingSeconds)} / {fmt(targetDuration)}
            </span>
          </div>

          {/* VU Meter */}
          <div style={{ marginBottom: 10 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 10.5, color: '#64748b', marginBottom: 2 }}>
              <span>Live Mic Signal:</span>
              <span style={{ fontWeight: 700, color: audioLevel > 15 ? '#059669' : '#94a3b8' }}>
                {audioLevel > 15 ? '🎤 Voice Detected' : '🤫 Speak into mic...'}
              </span>
            </div>
            <div style={{ width: '100%', height: 8, background: '#e2e8f0', borderRadius: 4, overflow: 'hidden' }}>
              <div style={{ width: `${audioLevel}%`, height: '100%', background: audioLevel > 70 ? '#ef4444' : audioLevel > 35 ? '#eab308' : '#22c55e', transition: 'width 0.08s ease-out' }} />
            </div>
          </div>

          {/* Progress bar */}
          <div style={{ width: '100%', height: 6, background: '#fecaca', borderRadius: 3, overflow: 'hidden', marginBottom: 12 }}>
            <div style={{ width: `${progress}%`, height: '100%', background: '#ef4444', transition: 'width 0.3s' }} />
          </div>

          <div style={{ display: 'flex', justifyContent: 'center', gap: 8, flexWrap: 'wrap' }}>
            {isPaused ? (
              <button type="button" onClick={resumeRecording} style={{ background: '#0284c7', color: '#fff', border: 'none', borderRadius: 6, padding: '6px 14px', fontWeight: 700, fontSize: 12, cursor: 'pointer' }}>▶️ Resume</button>
            ) : (
              <button type="button" onClick={pauseRecording} style={{ background: '#f59e0b', color: '#fff', border: 'none', borderRadius: 6, padding: '6px 14px', fontWeight: 700, fontSize: 12, cursor: 'pointer' }}>⏸️ Pause</button>
            )}
            <button type="button" onClick={stopRecording} style={{ background: '#ef4444', color: '#fff', border: 'none', borderRadius: 6, padding: '6px 18px', fontWeight: 700, fontSize: 12.5, cursor: 'pointer' }}>
              ⏹️ Stop & Save ({recordingSeconds}s)
            </button>
          </div>
        </div>
      )}

      {/* NOT RECORDING */}
      {!isRecording && !isStarting && (
        <>
          {previewUrl ? (
            <div style={{ padding: 10, background: '#f8fafc', borderRadius: 8, border: '1.5px solid #cbd5e1' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8, flexWrap: 'wrap' }}>
                <span style={{ fontSize: 12.5, fontWeight: 700, color: '#059669', display: 'flex', alignItems: 'center', gap: 4 }}>
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Ready: {file?.name || (typeof file === 'string' ? file.split('/').pop() : 'recorded_sample.webm')}
                </span>
                {!disabled && (
                  <button type="button" onClick={() => { onChange?.(null); setPreviewUrl(null); }}
                    style={{ marginLeft: 'auto', background: '#fee2e2', color: '#dc2626', border: 'none', borderRadius: 4, padding: '3px 10px', fontSize: 11, fontWeight: 700, cursor: 'pointer' }}>
                    ✕ Remove / Re-record
                  </button>
                )}
              </div>
              <audio controls src={previewUrl} style={{ width: '100%', height: 36 }} />
            </div>
          ) : mode === 'upload' ? (
            <div style={{ padding: 10, background: '#f8fafc', borderRadius: 8, border: '1.5px dashed #94a3b8' }}>
              {!disabled && (
                <input
                  type="file"
                  className="cf-input"
                  accept="audio/*,.wav,.mp3,.m4a,.aac,.ogg,.webm,.flac,.amr,.wma"
                  onChange={e => { const f = e.target.files?.[0] || null; if (f) onChange?.(f); }}
                  style={{ fontSize: 12, padding: '7px 10px', background: '#fff' }}
                />
              )}
              {disabled && <div style={{ fontSize: 12, color: '#94a3b8', fontStyle: 'italic' }}>No audio sample attached</div>}
              <span style={{ fontSize: 11, color: '#64748b', display: 'block', marginTop: 4 }}>
                📌 Select 30-second audio file from USB, phone extraction, or Windows Voice Recorder.
              </span>
            </div>
          ) : (
            <div style={{ padding: 12, background: '#f0f9ff', borderRadius: 8, border: '1px dashed #7dd3fc', textAlign: 'center', fontSize: 12.5, color: '#0369a1' }}>
              Click <strong>🎙️ Live Mic</strong> above to record a {targetDuration}-second voice sample.
            </div>
          )}
        </>
      )}
    </div>
  );
}

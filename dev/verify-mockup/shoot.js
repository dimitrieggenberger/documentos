// Mockup harness (never shipped): screenshots of out/*.html, desktop and phone.
// The pages load the live site's styles, so the browser goes through the
// container's proxy. Usage: node shoot.js
const { chromium } = require('playwright');
const path = require('path');
(async () => {
  const proxy = process.env.HTTPS_PROXY ? { server: process.env.HTTPS_PROXY } : undefined;
  const b = await chromium.launch({ proxy });
  const states = ['inicio', 'valido', 'vencido', 'sustituido', 'anulado', 'noexiste'];
  for (const [device, opts] of [['desktop', { viewport: { width: 1366, height: 900 } }],
      ['phone', { viewport: { width: 400, height: 860 }, deviceScaleFactor: 2, isMobile: true }]]) {
    const ctx = await b.newContext({ ...opts, ignoreHTTPSErrors: true });
    for (const s of states) {
      const p = await ctx.newPage();
      await p.goto('file://' + path.join(__dirname, 'out', s + '.html'), { waitUntil: 'networkidle', timeout: 60000 })
        .catch(() => {});
      await p.waitForTimeout(800);
      const overflow = await p.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
      await p.screenshot({ path: path.join(__dirname, 'out', `${s}-${device}.png`), fullPage: true });
      console.log(s, device, overflow ? 'HORIZONTAL OVERFLOW' : 'ok');
      await p.close();
    }
  }
  await b.close();
})();

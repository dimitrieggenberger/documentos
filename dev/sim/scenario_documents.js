// Browser scenario for official documents in the simulation Moodle, after
// dev/sim/seed_documents.php. The secretary prepares and issues a Constancia
// de Estudio, the director corrects and recalls, the public checks the code;
// it also checks the refusals. Screenshots go to dev/sim/out/.
//
// Usage: node dev/sim/scenario_documents.js   (NODE_PATH is exported by sim)
const { chromium } = require('playwright');
const path = require('path'), fs = require('fs');
const BASE = process.env.SIM_URL || 'http://localhost:8080';
const out = path.join(__dirname, 'out');
fs.mkdirSync(out, { recursive: true });
const L = BASE + '/local/documents/';
let failures = 0;
const check = (ok, what) => { console.log((ok ? 'ok   ' : 'FAIL ') + what); if (!ok) { failures++; } };

(async () => {
  const b = await chromium.launch();
  const login = async (user, password, phone) => {
    const ctx = await b.newContext(phone ? { viewport: { width: 400, height: 860 }, deviceScaleFactor: 2, isMobile: true }
      : { viewport: { width: 1366, height: 900 }, acceptDownloads: true });
    const p = await ctx.newPage();
    await p.goto(BASE + '/login/index.php');
    await p.fill('#username', user);
    await p.fill('#password', password);
    await Promise.all([p.waitForNavigation(), p.click('#loginbtn')]);
    return p;
  };
  const shot = (p, name) => p.screenshot({ path: path.join(out, name + '.png'), fullPage: true });

  // The secretary: registry, then a new Constancia de Estudio for Valentina Ríos.
  let p = await login('ecastillo', 'Sim-Docs-2026!');
  await p.goto(L + 'index.php');
  check(await p.locator('.local-documents-tile').count() === 4, 'registry: four tiles');
  await shot(p, 'documents-registry');
  await p.goto(L + 'create.php');
  check(await p.locator('.local-documents-typecard').count() >= 15, 'new: the document types as cards');
  await shot(p, 'documents-new');
  await p.click('.local-documents-typecard:has-text("Constancia de Estudio")');
  await p.fill('#ld-person', 'Ríos');
  await Promise.all([p.waitForNavigation(), p.press('#ld-person', 'Enter')]);
  check(await p.locator('.local-documents-people li').count() >= 1, 'new: the student is found');
  await Promise.all([p.waitForNavigation(), p.click('.local-documents-people li:has-text("Valentina") button')]);
  check(/edit\.php/.test(p.url()), 'draft: opened');
  check((await p.inputValue('#id_holderidnumber')).startsWith('0501-2010-'), 'draft: identity number from the profile');
  check(await p.inputValue('#id_gender') === 'F', 'draft: wording from the profile (la estudiante)');
  const sheet = await p.locator('.local-documents-sheet').innerText();
  check(/la estudiante/i.test(sheet) && /Décimo Grado/.test(sheet), 'draft: live preview with grade and agreement');
  await p.fill('#id_f_proposito', 'la Embajada de Alemania');
  await shot(p, 'documents-draft');
  await Promise.all([p.waitForNavigation(), p.click('#id_save')]);
  check(/la Embajada de Alemania/.test(await p.locator('.local-documents-sheet').innerText()), 'draft: saved field in preview');
  const [download] = await Promise.all([p.waitForEvent('download'), p.click('#id_preview')]);
  const preview = await download.path();
  check(fs.readFileSync(preview).slice(0, 4).toString() === '%PDF', 'draft: preview PDF');
  await p.goto(p.url().replace(/download\.php.*/, '') || p.url());
  await p.goto(L + 'index.php?status=draft');
  await Promise.all([p.waitForNavigation(), p.click('a:has-text("Continue draft"), a:has-text("Continuar borrador")')]);
  await Promise.all([p.waitForNavigation(), p.click('#id_issue')]);
  await shot(p, 'documents-issue-confirm');
  await Promise.all([p.waitForNavigation(), p.click('.modal-footer button.btn-primary, [role="main"] form button.btn-primary')]);
  check(/view\.php/.test(p.url()), 'issue: lands on the record');
  const number = (await p.locator('.local-documents-record .local-documents-number').innerText()).trim();
  check(/^CONSES-\d{3}-050100099M01-\d{4}-DCE-JM$/.test(number), 'issue: number follows the nomenclature: ' + number);
  const code = (await p.locator('.local-documents-record code').first().innerText()).trim();
  check(/^[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}$/.test(code), 'issue: verification code ' + code);
  check(await p.locator('a:has-text("Recall"), a:has-text("Anular")').count() === 0, 'secretary: no recall button');
  await shot(p, 'documents-record');
  const recordurl = p.url();

  // The public: the verification page without a login, and the JSON.
  const pub = await (await b.newContext()).newPage();
  await pub.goto(L + 'verify.php?c=' + code.toLowerCase().replace(/-/g, ' '));
  check(/valid|válido/i.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: valid');
  check(!/0501-2010-\d{5}/.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: identity number masked');
  await shot(pub, 'documents-verify-valid');
  const api = await pub.request.get(L + 'verify_api.php?c=' + code);
  const json = await api.json();
  check(json.status === 'valid' && json.number.includes(number.split('-')[1]), 'api: valid with number');

  // The director: correct (new version), then recall.
  p = await login('rgomez', 'Test123!');
  await p.goto(recordurl);
  await Promise.all([p.waitForNavigation(), p.click('button:has-text("Correct"), button:has-text("Corregir")')]).catch(() => {});
  if (await p.locator('.modal').count()) {
    await Promise.all([p.waitForNavigation(), p.click('.modal-footer .btn-primary')]);
  }
  check(/edit\.php/.test(p.url()), 'correct: a draft of version 2');
  await Promise.all([p.waitForNavigation(), p.click('#id_issue')]);
  await Promise.all([p.waitForNavigation(), p.click('[role="main"] form button.btn-primary')]);
  check((await p.locator('.local-documents-record .local-documents-number').innerText()).trim() === number,
    'correct: version 2 keeps the number');
  await pub.goto(L + 'verify.php?c=' + code);
  check(/replaced|sustituido/i.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: v1 superseded');
  await Promise.all([p.waitForNavigation(), p.click('a:has-text("Recall"), a:has-text("Anular")')]);
  await p.fill('#id_reason', 'Prueba de anulación');
  await p.fill('#id_note', 'Documento anulado a solicitud de la familia.');
  await Promise.all([p.waitForNavigation(), p.click('#id_submitbutton')]);
  const code2 = (await p.locator('.local-documents-record code').first().innerText()).trim();
  await pub.goto(L + 'verify.php?c=' + code2);
  check(/recalled|anulado/i.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: v2 recalled');
  await shot(pub, 'documents-verify-recalled');

  // Templates and series as the director.
  await p.goto(L + 'templates.php');
  check(await p.locator('table.generaltable tbody tr').count() >= 15, 'templates: listed');
  await p.goto(L + 'template_edit.php?id=1');
  await shot(p, 'documents-template');
  await p.goto(L + 'series.php');
  await shot(p, 'documents-series');

  // Refusals: a student and a teacher cannot open the registry.
  for (const user of ['vrios', 'lmendez']) {
    const s = await login(user, 'Test123!');
    const r = await s.goto(L + 'index.php');
    check(/nopermissions|required|permisos|permission/i.test(await s.content()) || r.status() >= 400,
      user + ': registry refused');
  }

  // Phone width: the verification page fits.
  const phone = await (await b.newContext({ viewport: { width: 400, height: 860 }, isMobile: true })).newPage();
  await phone.goto(L + 'verify.php?c=' + code2);
  const overflow = await phone.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
  check(!overflow, 'phone: verification page without horizontal scroll');
  await shot(phone, 'documents-verify-phone');

  await b.close();
  console.log(failures ? failures + ' failed' : 'all passed');
  process.exit(failures ? 1 : 0);
})();

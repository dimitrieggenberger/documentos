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
  await p.fill('#ld-person', 'Ríos Castro');
  await Promise.all([p.waitForNavigation(), p.press('#ld-person', 'Enter')]);
  check(await p.locator('.local-documents-people li').count() >= 1, 'new: the student is found');
  await Promise.all([p.waitForNavigation(), p.click('.local-documents-people li:has-text("Valentina Ríos Castro") button')]);
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
  check(/Rosa Gómez/.test(await p.locator('#fitem_id_signersstatic').innerText()), 'draft: the director must sign');
  check(/Enviar a firma|Send to sign/.test(await p.locator('#id_issue').inputValue()), 'draft: sent to sign, not issued');
  await Promise.all([p.waitForNavigation(), p.click('#id_issue')]);
  await shot(p, 'documents-send-confirm');
  await Promise.all([p.waitForNavigation(), p.click('.modal-footer button.btn-primary, [role="main"] form button.btn-primary')]);
  check(/view\.php/.test(p.url()), 'send: lands on the record');
  check(/Esperando firmas|Waiting for signatures/.test(await p.locator('.local-documents-record').innerText()),
    'send: waiting for signatures, no number yet');
  const recordurl = p.url();
  await shot(p, 'documents-pending');

  // The director: what waits for her signature, the document, approve.
  let d = await login('rgomez', 'Test123!');
  await d.goto(L + 'sign.php');
  check(await d.locator('.local-documents-task').count() >= 1, 'sign: the document waits for the director');
  await shot(d, 'documents-tosign');
  const sentid = new URL(recordurl).searchParams.get('id');
  check(await d.locator(`.local-documents-task a[href*="sign.php?id=${sentid}"]`).count() === 1, 'sign: this document is in the queue');
  await Promise.all([d.waitForNavigation(), d.locator(`.local-documents-task a[href*="sign.php?id=${sentid}"]`).click()]);
  check(await d.locator('#ld-approve').count() === 1 && await d.locator('#ld-reject').count() === 1, 'sign: approve or send back');
  await shot(d, 'documents-sign');
  await Promise.all([d.waitForNavigation(), d.click('#ld-approve')]);
  // Space shows the redirect message after the page loads: wait for it.
  check(await d.waitForFunction(() => /(emitido como|issued as) CONSES-\d{3}/i.test(document.body.innerText), null,
    { timeout: 8000 }).then(() => true).catch(() => false), 'sign: the last signature issues');

  await p.goto(recordurl);
  const number = (await p.locator('.local-documents-record .local-documents-number').innerText()).trim();
  check(/^CONSES-\d{3}-050100099M01-\d{4}-DCE-EX$/.test(number), 'issue: number follows the nomenclature: ' + number);
  const code = (await p.locator('.local-documents-record code').first().innerText()).trim();
  check(/^[0-9A-Z]{4}-[0-9A-Z]{4}-[0-9A-Z]{4}$/.test(code), 'issue: verification code ' + code);
  check(await p.locator('a:has-text("Recall"), a:has-text("Anular")').count() === 0, 'secretary: no recall button');
  check(/Firmado electrónicamente el|Signed electronically/.test(await p.locator('body').innerText()), 'record: the signature with its date');
  check(/(Desde|From) [0-9a-f.:]+/.test(await p.locator('body').innerText()), 'record: the evidence of the signature');
  await shot(p, 'documents-record');

  // The public: the verification page without a login, and the JSON.
  const pub = await (await b.newContext()).newPage();
  await pub.goto(L + 'verify.php?c=' + code.toLowerCase().replace(/-/g, ' '));
  check(/valid|válido/i.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: valid');
  check(!/0501-2010-\d{5}/.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: identity number masked');
  check(/Decreto 149-2013/.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: the law the signatures were given under');
  await shot(pub, 'documents-verify-valid');
  const api = await pub.request.get(L + 'verify_api.php?c=' + code);
  const json = await api.json();
  check(json.status === 'valid' && json.number.includes(number.split('-')[1]), 'api: valid with number');

  // The director: correct (new version: she is the only signer, so she issues it alone), then recall.
  p = d;
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

  // The administrator: issuing units linked to users, signatures per type, the text toolbar.
  const a = await login('admin', 'Sim-Admin-2026!');
  await a.goto(L + 'units.php');
  check(await a.locator('table.generaltable tbody tr').count() >= 3, 'units: listed');
  check(/Rosa Gómez/.test(await a.locator('table.generaltable').innerText()), 'units: DCE signed by the director');
  await shot(a, 'documents-units');
  await a.goto(L + 'units.php?id=' + await a.locator('a:has-text("Edit"), a:has-text("Editar")').first().getAttribute('href')
    .then(h => new URL(h, BASE).searchParams.get('id')));
  check(await a.locator('#id_userid').count() === 1 && await a.locator('#id_typeids').count() === 1,
    'unit: linked user and the documents it may issue');
  await shot(a, 'documents-unit');
  await a.goto(L + 'templates.php');
  await a.goto(await a.locator('tr:has-text("Certificación de Notas") a').first().getAttribute('href'));
  const signer1 = await a.locator('#id_signer1 option:checked').innerText();
  const signer2 = await a.locator('#id_signer2 option:checked').innerText();
  check(/SCE/.test(signer1) && /DCE/.test(signer2), 'template: certificación signed by Secretaría then Dirección');
  check(await a.locator('.local-documents-toolbar').count() >= 1, 'template: the text toolbar');
  await shot(a, 'documents-template');
  await a.goto(L + 'create.php');
  await Promise.all([a.waitForNavigation(), a.click('.local-documents-typecard:has-text("Oficio")')]);
  await Promise.all([a.waitForNavigation(), a.click('button:has-text("Iniciar borrador"), button:has-text("Start draft")')]);
  await a.click('#id_bodyhdr .ftoggler a, a[href="#id_bodyhdrcontainer"]').catch(() => {});
  check(await a.locator('.local-documents-toolbar').count() >= 2 && await a.locator('.local-documents-toolbar button').count() >= 8, 'draft: toolbar on the letter text and on the wording');
  check(await a.locator('#fitem_id_attachments').count() === 1, 'draft: attachments upload');
  await shot(a, 'documents-draft-letter');

  // Any unit issues circulars and memorandums: the Gerente General issues one alone, signed by himself.
  const g = await login('mfuentes', 'Sim-Docs-2026!');
  await g.goto(L + 'create.php');
  await Promise.all([g.waitForNavigation(), g.click('.local-documents-typecard:has-text("Circular")')]);
  await Promise.all([g.waitForNavigation(), g.click('button:has-text("Iniciar borrador"), button:has-text("Start draft")')]);
  check(await g.inputValue('#id_unitcode') === 'GG', 'circular: starts on the Gerencia General');
  check(/Mario Fuentes/.test(await g.locator('#fitem_id_signersstatic').innerText()), 'circular: signed by its own head');
  await g.fill('#id_f_asunto', 'Horario de fin de año');
  await g.fill('#id_f_texto', 'El último día de clases es el 28 de noviembre.');
  check(/^(Emitir|Issue)/.test(await g.locator('#id_issue').inputValue()), 'circular: the sole signer issues');
  await Promise.all([g.waitForNavigation(), g.click('#id_issue')]);
  await Promise.all([g.waitForNavigation(), g.click('[role="main"] form button.btn-primary')]);
  const circular = (await g.locator('.local-documents-record .local-documents-number').innerText()).trim();
  check(/^CIR-\d{3}-050100099M01-\d{4}-GG-EX$/.test(circular), 'circular: numbered for GG: ' + circular);

  // Signed by hand: the secretary issues a Constancia de Solvencia to print; nobody signs on the platform.
  const h = await login('ecastillo', 'Sim-Docs-2026!');
  await h.goto(L + 'create.php');
  await h.click('.local-documents-typecard:has-text("Constancia de Solvencia")');
  await h.fill('#ld-person', 'Ríos Castro');
  await Promise.all([h.waitForNavigation(), h.press('#ld-person', 'Enter')]);
  await Promise.all([h.waitForNavigation(), h.click('.local-documents-people li:has-text("Valentina Ríos Castro") button')]);
  check(/Firma autógrafa|Signed by hand/.test(await h.locator('#fitem_id_signersstatic').innerText()), 'handwritten: the draft says so');
  check(/^(Emitir|Issue)/.test(await h.locator('#id_issue').inputValue()), 'handwritten: issued by the secretary, not sent to sign');
  await Promise.all([h.waitForNavigation(), h.click('#id_issue')]);
  check(/firmar de puño y letra|signed by hand/.test(await h.locator('[role="main"]').innerText()), 'handwritten: the confirmation says to print and sign');
  await Promise.all([h.waitForNavigation(), h.click('[role="main"] form button.btn-primary')]);
  const solv = (await h.locator('.local-documents-record .local-documents-number').innerText()).trim();
  check(/^CONSSOL-\d{3}-/.test(solv), 'handwritten: numbered at once: ' + solv);
  check(/Firma autógrafa en el original|Signed by hand on the original/.test(await h.locator('body').innerText()),
    'handwritten: the record says where it is signed');
  const hid = new URL(h.url()).searchParams.get('id');
  const [hpdf] = await Promise.all([h.waitForEvent('download'), h.locator(`a[href$="download.php?id=${hid}"]`).first().click()]);
  fs.copyFileSync(await hpdf.path(), path.join(out, 'documents-solvencia-handwritten.pdf'));
  const hcode = (await h.locator('.local-documents-record code').first().innerText()).trim();
  await pub.goto(L + 'verify.php?c=' + hcode);
  check(/firma autógrafa/.test(await pub.locator('.local-documents-verify-result').innerText()), 'public: only the signed original is valid');
  await shot(pub, 'documents-verify-handwritten');
  await a.goto(L + 'templates.php');
  check(await a.locator('tr:has-text("Certificación de Notas") .badge:has-text("autógrafa"), tr:has-text("Certificación de Notas") .badge:has-text("Handwritten")').count() === 1,
    'templates: certificaciones marked as signed by hand');

  // A transfer file: the director builds Valentina's traslado with its companions.
  const r = await login('rgomez', 'Test123!');
  await r.goto(L + 'create.php');
  await r.click('.local-documents-typecard:has-text("Constancia de Traslado")');
  await r.fill('#ld-person', 'Ríos Castro');
  await Promise.all([r.waitForNavigation(), r.press('#ld-person', 'Enter')]);
  await Promise.all([r.waitForNavigation(), r.click('.local-documents-people li:has-text("Valentina Ríos Castro") button')]);
  const fileurl = r.url();
  await r.fill('#id_f_destino', 'Instituto Bilingüe Las Colinas');
  await Promise.all([r.waitForNavigation(), r.click('#id_save')]);
  check(await r.locator('.local-documents-companions-card').count() === 1, 'file: the traslado offers its companions');
  await Promise.all([r.waitForNavigation(), r.click('button:has-text("Preparar: Constancia de Conducta"), button:has-text("Prepare: Constancia de Conducta")')]);
  const solvvalue = await r.locator('select[name="companion"] option', { hasText: 'CONSSOL' }).first().getAttribute('value');
  await r.selectOption('select[name="companion"]', solvvalue);
  await Promise.all([r.waitForNavigation(), r.locator('form:has(select[name="companion"]) button[type="submit"]').click()]);
  await r.click('.local-documents-companions-card summary');
  const ext = r.locator('form:has(input[name="title"])');
  await ext.locator('input[name="title"]').fill('Certificación de estudios 2024');
  await ext.locator('input[name="number"]').fill('112-2024');
  await ext.locator('select[name="delivery"]').selectOption('family');
  await ext.locator('input[name="deliveredon"]').fill('2024-12-10');
  await Promise.all([r.waitForNavigation(), ext.locator('button[type="submit"]').click()]);
  const filelist = await r.locator('.local-documents-companions').innerText();
  check(/Constancia de conducta · por emitir/i.test(filelist) && /original con firma autógrafa/.test(filelist)
    && /entregado a la familia el 10 de diciembre de 2024/.test(filelist), 'file: three companions listed');
  check(/Documentos que acompañan/.test(await r.locator('.local-documents-sheet').innerText()), 'file: the index on the preview');
  await shot(r, 'documents-file-draft');
  await Promise.all([r.waitForNavigation(), r.click('#id_issue')]);
  check(/aún no está emitido|is not issued yet/.test(await r.locator('[role="main"]').innerText()), 'file: waits for the conducta');
  await r.goto(fileurl);
  await Promise.all([r.waitForNavigation(), r.locator('.local-documents-companions a:has-text("Abrir el borrador"), .local-documents-companions a:has-text("Open the draft")').first().click()]);
  await Promise.all([r.waitForNavigation(), r.click('#id_issue')]);
  await Promise.all([r.waitForNavigation(), r.click('[role="main"] form button.btn-primary')]);
  check(/CONSCOND-\d{3}/.test(await r.locator('.local-documents-record .local-documents-number').innerText()), 'file: the conducta issued');
  await r.goto(fileurl);
  await Promise.all([r.waitForNavigation(), r.click('#id_issue')]);
  await Promise.all([r.waitForNavigation(), r.click('[role="main"] form button.btn-primary')]);
  const filenumber = (await r.locator('.local-documents-record .local-documents-number').innerText()).trim();
  check(/^CONSTL-\d{3}-/.test(filenumber), 'file: the traslado issued: ' + filenumber);
  await shot(r, 'documents-file-record');
  const fid = new URL(r.url()).searchParams.get('id');
  const [fpdf] = await Promise.all([r.waitForEvent('download'), r.locator(`a[href$="download.php?id=${fid}"]`).first().click()]);
  fs.copyFileSync(await fpdf.path(), path.join(out, 'documents-traslado-file.pdf'));
  const fcode = (await r.locator('.local-documents-record code').first().innerText()).trim();
  await pub.goto(L + 'verify.php?c=' + fcode);
  const fverify = await pub.locator('.local-documents-verify-result').innerText();
  check(/CONSCOND/.test(fverify) && /CONSSOL/.test(fverify) && /112-2024/.test(fverify), 'public: the file lists its companions');
  await shot(pub, 'documents-verify-file');

  // An authorisation for families: the Gerente Académica asks a whole course for an excursion, a consent slip each.
  const ga = await login('lbonilla', 'Sim-Docs-2026!');
  await ga.goto(L + 'create.php');
  await Promise.all([ga.waitForNavigation(), ga.click('.local-documents-typecard:has-text("Autorización de salida o excursión")')]);
  await Promise.all([ga.waitForNavigation(), ga.click('button:has-text("Iniciar borrador"), button:has-text("Start draft")')]);
  const authurl = ga.url();
  check(await ga.inputValue('#id_unitcode') === 'GA', 'authorisation: issued by the Gerencia Académica');
  await ga.fill('#id_f_actividad', 'Visita al Museo de Antropología');
  await ga.fill('#id_f_lugar', 'Museo de Antropología e Historia de San Pedro Sula');
  await ga.check('#id_f_fecha_enabled', { force: true });
  await ga.selectOption('#id_f_fecha_day', '20');
  await ga.selectOption('#id_f_fecha_month', '11');
  await ga.fill('#id_f_horario', 'de 7:30 a 13:00');
  await ga.fill('#id_f_transporte', 'bus del centro');
  await ga.check('#id_f_devolver_antes_enabled', { force: true });
  await ga.selectOption('#id_f_devolver_antes_day', '14');
  await ga.selectOption('#id_f_devolver_antes_month', '11');
  await Promise.all([ga.waitForNavigation(), ga.click('#id_save')]);
  check(await ga.locator('.local-documents-recipients-card').count() === 1, 'authorisation: pick the students');
  const pick = await ga.locator('select[name="pick"] option', { hasText: 'Biología 10G.26' }).first().getAttribute('value');
  await ga.selectOption('select[name="pick"]', pick);
  await Promise.all([ga.waitForNavigation(), ga.locator('form:has(select[name="pick"]) button[type="submit"]').click()]);
  const recipients = await ga.locator('.local-documents-recipients li').count();
  check(recipients >= 5, 'authorisation: the course\'s students, ' + recipients);
  const colilla = await ga.locator('.local-documents-sheet-colilla').innerText();
  check(/AUTORIZO/.test(colilla) && /madre, padre o persona responsable legal/.test(colilla), 'authorisation: the slip on the preview');
  await shot(ga, 'documents-authorisation-draft');
  await Promise.all([ga.waitForNavigation(), ga.click('#id_issue')]);
  await Promise.all([ga.waitForNavigation(), ga.click('[role="main"] form button.btn-primary')]);
  const authnumber = (await ga.locator('.local-documents-record .local-documents-number').innerText()).trim();
  check(/^AUTOR-\d{3}-.*-GA-EX$/.test(authnumber), 'authorisation: issued alone by its head: ' + authnumber);
  check(await ga.locator('table.local-documents-slips tbody tr').count() === recipients, 'authorisation: a slip per student');
  const aid = new URL(ga.url()).searchParams.get('id');
  const [apdf] = await Promise.all([ga.waitForEvent('download'),
    ga.locator('table.local-documents-slips a:has-text("PDF para la familia"), table.local-documents-slips a:has-text("PDF for the family")').first().click()]);
  fs.copyFileSync(await apdf.path(), path.join(out, 'documents-authorisation-family.pdf'));
  const [allpdf] = await Promise.all([ga.waitForEvent('download'), ga.locator(`a[href$="download.php?id=${aid}"]`).first().click()]);
  fs.copyFileSync(await allpdf.path(), path.join(out, 'documents-authorisation-all.pdf'));
  const slipcode = (await ga.locator('table.local-documents-slips code').first().innerText()).trim();
  const slipname = (await ga.locator('table.local-documents-slips tbody tr').first().locator('td').first().innerText()).split('\n')[0].trim();
  await Promise.all([ga.waitForNavigation(), ga.locator('table.local-documents-slips a:has-text("Registrar la respuesta"), table.local-documents-slips a:has-text("Record the answer")').first().click()]);
  await ga.check('input[name="response"][value="yes"]', { force: true });
  await ga.fill('#id_note', 'Alergia al maní.');
  await Promise.all([ga.waitForNavigation(), ga.click('#id_submitbutton')]);
  check(/1 autorizan|1 authorise/.test(await ga.locator('#slips + div, body').first().innerText()), 'authorisation: the answer counted');
  await shot(ga, 'documents-authorisation-record');
  await pub.goto(L + 'verify.php?c=' + slipcode);
  const slipverify = await pub.locator('.local-documents-verify-result').innerText();
  check(/Colilla de autorización/.test(slipverify) && slipverify.includes(slipname) && !/maní/.test(slipverify),
    'public: the slip verifies for its student, without the answer');
  await shot(pub, 'documents-verify-slip');

  // The director is away: Pablo Ortiz signs in substitution of her.
  await a.goto(L + 'units.php');
  const dceid = new URL(await a.locator('tr:has-text("DCE") a:has-text("Edit"), tr:has-text("DCE") a:has-text("Editar")')
    .first().getAttribute('href'), BASE).searchParams.get('id');
  await a.goto(L + 'units.php?id=' + dceid);
  await a.click('#id_substitutehdr .ftoggler a, a[href="#id_substitutehdrcontainer"]').catch(() => {});
  await a.locator('#fitem_id_substituteid input[type="text"]').fill('Ortiz');
  await a.locator('#fitem_id_substituteid [role="option"]:has-text("Pablo Ortiz")').first().click();
  await shot(a, 'documents-unit-substitute');
  await Promise.all([a.waitForNavigation(), a.click('#id_submitbutton')]);
  check(/Sustituido por Pablo Ortiz|Substituted by Pablo Ortiz/.test(await a.locator('table.generaltable').innerText()),
    'units: the substitution in the list');
  await shot(a, 'documents-units-substitute');

  const sec = await login('ecastillo', 'Sim-Docs-2026!');
  await sec.goto(L + 'create.php');
  await Promise.all([sec.waitForNavigation(), sec.click('.local-documents-typecard:has-text("Oficio")')]);
  await Promise.all([sec.waitForNavigation(), sec.click('button:has-text("Iniciar borrador"), button:has-text("Start draft")')]);
  check(/Pablo Ortiz/.test(await sec.locator('#fitem_id_signersstatic').innerText()), 'substitute: asked to sign the oficio');
  await sec.fill('#id_f_destinatario', 'Sra. Ana Prueba');
  await sec.fill('#id_f_asunto', 'Constancia de reunión');
  await sec.fill('#id_f_texto', 'Confirmamos la reunión del lunes.');
  await Promise.all([sec.waitForNavigation(), sec.click('#id_issue')]);
  await Promise.all([sec.waitForNavigation(), sec.click('[role="main"] form button.btn-primary')]);
  const oficiourl = sec.url();
  const oficioid = new URL(oficiourl).searchParams.get('id');
  const o = await login('portiz', 'Test123!');
  await o.goto(L + 'sign.php');
  check(await o.locator(`.local-documents-task a[href*="sign.php?id=${oficioid}"]`).count() === 1, 'substitute: the oficio waits for him');
  await o.goto(L + 'sign.php?id=' + oficioid);
  await shot(o, 'documents-sign-substitute');
  await Promise.all([o.waitForNavigation(), o.click('#ld-approve')]);
  await sec.goto(oficiourl);
  check(/sustitución de funciones de Lic\. Rosa Gómez|substitution of Lic\. Rosa Gómez/.test(await sec.locator('body').innerText()),
    'substitute: the record says in whose substitution');
  await shot(sec, 'documents-record-substitute');
  const ocode = (await sec.locator('.local-documents-record code').first().innerText()).trim();
  await pub.goto(L + 'verify.php?c=' + ocode);
  check(/en sustitución de funciones de Lic\. Rosa Gómez/.test(await pub.locator('.local-documents-verify-result').innerText()),
    'public: the substitution is verifiable');
  const [opdf] = await Promise.all([sec.waitForEvent('download'),
    sec.locator(`a[href$="download.php?id=${oficioid}"]`).first().click()]);
  fs.copyFileSync(await opdf.path(), path.join(out, 'documents-oficio-substitute.pdf'));

  // Back to normal: the substitution ends.
  await a.goto(L + 'units.php?id=' + dceid);
  await a.click('#id_substitutehdr .ftoggler a, a[href="#id_substitutehdrcontainer"]').catch(() => {});
  await a.locator('#fitem_id_substituteid .form-autocomplete-selection [role="option"]').first().click();
  await Promise.all([a.waitForNavigation(), a.click('#id_submitbutton')]);
  check(!/Sustituido por|Substituted by/.test(await a.locator('table.generaltable').innerText()), 'units: substitution ended');

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

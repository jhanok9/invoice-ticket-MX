#!/usr/bin/env node
/**
 * Stamping script for PANADERÍAS ARTESANALES FOUGASSE
 * Portal: https://facturacion.fougasse.com.mx/
 *
 * Handles two scenarios:
 *  A) First time stamp  → fills wizard steps 1-4, captures auto-downloaded ZIP
 *  B) Already stamped   → portal shows "Descargar Comprobante" after RFC check,
 *                         script clicks it and captures the ZIP
 *
 * Downloads are intercepted by Playwright from the very first page event and
 * saved to uploads/ via dl.saveAs().  Chrome's download behaviour is restored
 * to 'default' before the script exits so the browser works normally.
 */

const { chromium } = require('playwright');
const path  = require('path');
const os    = require('os');
const fs    = require('fs');
const { execSync, spawn } = require('child_process');
const net   = require('net');

const CDP_PORT   = 9222;
const USER_DATA  = path.join(os.homedir(), '.invoice-tracker-chrome');
const CHROME_BIN = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

// ── helpers ──────────────────────────────────────────────────────────────────

function isPortOpen(port) {
  return new Promise(resolve => {
    const s = net.createConnection(port, 'localhost');
    s.on('connect', () => { s.destroy(); resolve(true); });
    s.on('error',   () => resolve(false));
    s.setTimeout(600, () => { s.destroy(); resolve(false); });
  });
}

async function waitForPort(port, maxMs = 15000) {
  const start = Date.now();
  while (Date.now() - start < maxMs) {
    if (await isPortOpen(port)) return true;
    await new Promise(r => setTimeout(r, 500));
  }
  return false;
}

function clearSessionData() {
  const dirs  = ['Sessions', 'Session Storage'].map(d => path.join(USER_DATA, 'Default', d));
  const files = ['Last Session', 'Last Tabs', 'Current Session', 'Current Tabs']
                  .map(f => path.join(USER_DATA, 'Default', f));
  for (const d of dirs)  try { fs.rmSync(d, { recursive: true, force: true }); } catch {}
  for (const f of files) try { fs.unlinkSync(f); } catch {}
}

async function connect() {
  if (await isPortOpen(CDP_PORT)) {
    try { return await chromium.connectOverCDP(`http://localhost:${CDP_PORT}`, { timeout: 4000 }); }
    catch {}
  }
  clearSessionData();
  if (fs.existsSync(CHROME_BIN)) {
    spawn(CHROME_BIN, [
      `--remote-debugging-port=${CDP_PORT}`,
      `--remote-allow-origins=http://localhost:${CDP_PORT}`,
      `--user-data-dir=${USER_DATA}`,
      '--no-first-run', '--no-restore-last-session',
      '--disable-session-crashed-bubble', '--disable-notifications',
      'about:blank',
    ], { detached: true, stdio: 'ignore' }).unref();
    if (await waitForPort(CDP_PORT, 15000)) {
      try { return await chromium.connectOverCDP(`http://localhost:${CDP_PORT}`, { timeout: 8000 }); }
      catch {}
    }
  }
  clearSessionData();
  return chromium.launchPersistentContext(USER_DATA, {
    channel: 'chrome', headless: false,
    args: [`--remote-debugging-port=${CDP_PORT}`, '--no-first-run',
           '--no-restore-last-session', '--disable-notifications'],
  });
}

// ── main ─────────────────────────────────────────────────────────────────────

async function run() {
  const ticket = JSON.parse(process.argv[2] || '{}');
  const {
    ticket_number, serie, total, purchase_date,
    company_rfc, company_email, zip_code, tax_regime,
    screenshot_dir = '/tmp/', screenshot_url = '/uploads/', ticket_id = 0,
  } = ticket;

  // path.resolve fixes the includes/../uploads/ path PHP sends
  const uploadDir        = path.resolve(screenshot_dir);
  const screenshotFile   = `stamp_${ticket_id}_${Date.now()}.png`;
  const screenshotPath   = path.join(uploadDir, screenshotFile);
  const screenshotPublic = screenshot_url + screenshotFile;

  const raw   = await connect();
  const isCDP = typeof raw.contexts === 'function';
  const ctx   = isCDP ? (raw.contexts()[0] ?? await raw.newContext()) : raw;
  const page  = await ctx.newPage();
  const snap  = () => page.screenshot({ path: screenshotPath }).catch(() => {});

  let cdpSession = null;
  if (isCDP) {
    try { cdpSession = await raw.newBrowserCDPSession(); } catch {}
  }

  async function disconnectPlaywright() {
    // 1. Restore Chrome's native download behaviour via CDP
    if (cdpSession) {
      try {
        await cdpSession.send('Browser.setDownloadBehavior', { behavior: 'default' });
        await new Promise(r => setTimeout(r, 400));
        await cdpSession.detach();
      } catch {}
      cdpSession = null;
    }
    // 2. Disconnect Playwright from Chrome.
    //    - CDP mode  → raw.close() drops the CDP connection; Chrome keeps running
    //                  with the Fougasse tab open and downloads working normally.
    //    - Fallback  → ctx.close() closes the Playwright-managed Chrome window.
    if (isCDP) {
      try { await raw.close(); } catch {}   // disconnects, does NOT close Chrome
    } else {
      try { await ctx.close(); } catch {}   // closes Chrome (fallback only)
    }
  }

  // ── Download queue — supports multiple sequential downloads ──────────────
  const dlQueue = [];
  let dlWaiter  = null;

  page.on('download', dl => {
    if (dlWaiter) {
      const fn = dlWaiter;
      dlWaiter = null;
      fn(dl);
    } else {
      dlQueue.push(dl);
    }
  });

  function waitForDownload(timeoutMs) {
    if (dlQueue.length > 0) return Promise.resolve(dlQueue.shift());
    return new Promise((resolve, reject) => {
      dlWaiter = resolve;
      setTimeout(() => {
        if (dlWaiter === resolve) { dlWaiter = null; reject(new Error('Download timeout')); }
      }, timeoutMs);
    });
  }

  async function saveDownload(dl) {
    const zipPath    = path.join(uploadDir, `cfdi_${ticket_id}_${Date.now()}.zip`);
    const extractDir = zipPath.replace(/\.zip$/, '_x');

    await dl.saveAs(zipPath);

    if (!fs.existsSync(zipPath)) throw new Error('saveAs completed but ZIP not found');

    fs.mkdirSync(extractDir, { recursive: true });
    execSync(`/usr/bin/unzip -o "${zipPath}" -d "${extractDir}"`);

    const files  = fs.readdirSync(extractDir);
    const ts     = Date.now();
    const xmlSrc = files.find(f => f.toLowerCase().endsWith('.xml'));
    const pdfSrc = files.find(f => f.toLowerCase().endsWith('.pdf'));

    const xmlDest = `cfdi_${ticket_id}_${ts}.xml`;
    const pdfDest = `cfdi_${ticket_id}_${ts}.pdf`;

    if (xmlSrc) fs.copyFileSync(path.join(extractDir, xmlSrc), path.join(uploadDir, xmlDest));
    if (pdfSrc) fs.copyFileSync(path.join(extractDir, pdfSrc), path.join(uploadDir, pdfDest));

    fs.rmSync(extractDir, { recursive: true, force: true });
    try { fs.unlinkSync(zipPath); } catch {}

    return {
      xml_path: xmlSrc ? screenshot_url + xmlDest : null,
      pdf_path: pdfSrc ? screenshot_url + pdfDest : null,
    };
  }

  try {
    await page.goto('https://facturacion.fougasse.com.mx/', {
      waitUntil: 'networkidle', timeout: 30000,
    });
    await snap();

    // ── STEP 1: Datos del Ticket ──────────────────────────────────────────
    await page.locator('#tienda').fill(serie);
    await page.locator('#ticket').fill(ticket_number);
    await page.locator('#importe').fill(String(total));

    await page.locator('#fecha_ticket').click();
    await page.locator('#fecha_ticket').type(purchase_date, { delay: 50 });
    await page.locator('#fecha_ticket').dispatchEvent('change');
    await snap();

    await page.locator('a[href="#next"]').click();

    const downloadBtnSel = 'button:has-text("Descargar"), a:has-text("Descargar")';

    // After clicking Next, wait for one of three outcomes (up to 12 s):
    //  'recover' → toast "¿Desea recuperar su factura? SI/NO" (already stamped)
    //  'modal'   → download button visible immediately
    //  'step2'   → wizard advances normally to step 2 (#register-rfc visible)
    const afterStep1 = await Promise.race([
      page.locator('#register-rfc').waitFor({ state: 'visible', timeout: 12000 }).then(() => 'step2'),
      page.locator('button:has-text("SI")').first().waitFor({ state: 'visible', timeout: 12000 }).then(() => 'recover'),
      page.locator(downloadBtnSel).first().waitFor({ state: 'visible', timeout: 12000 }).then(() => 'modal'),
    ]).catch(() => 'step2');

    await snap();

    if (afterStep1 === 'recover') {
      // "¿Desea recuperar su factura?" toast → click SI
      await page.locator('button:has-text("SI")').first().click();
      await page.waitForTimeout(1500);
      await snap();

      // After SI: portal may need RFC confirmed + Enviar, or show download directly
      const afterSI = await Promise.race([
        page.locator(downloadBtnSel).first().waitFor({ state: 'visible', timeout: 10000 }).then(() => 'ready'),
        page.locator('button:has-text("Enviar"), input[value*="Enviar"]').first()
          .waitFor({ state: 'visible', timeout: 10000 }).then(() => 'enviar'),
      ]).catch(() => 'ready');

      if (afterSI === 'enviar') {
        // Fill the RFC input in the recovery modal (#rfc_ticket_facturado)
        const rfcInput = page.locator('#rfc_ticket_facturado');
        const currentVal = await rfcInput.inputValue().catch(() => '');
        if (!currentVal) {
          await rfcInput.click();
          await rfcInput.fill(company_rfc);
        }
        await snap();
        await page.locator('button:has-text("Enviar"), input[value*="Enviar"]').first().click();
        await page.waitForTimeout(2000);
        await snap();
      }

      // Click Descargar Comprobante (portal may take a moment to show it after Enviar)
      await page.locator(downloadBtnSel).first().waitFor({ state: 'visible', timeout: 15000 });
      await page.locator(downloadBtnSel).first().click();
      const dlR = await waitForDownload(15000);
      await snap();
      const filesR = await saveDownload(dlR);
      await disconnectPlaywright();
      console.log(JSON.stringify({
        success: true,
        message: 'Comprobante recuperado y descargado. XML y PDF guardados.',
        screenshot: screenshotPublic,
        ...filesR,
      }));
      return;
    }

    if (afterStep1 === 'modal') {
      await page.locator(downloadBtnSel).first().click();
      const dlM = await waitForDownload(15000);
      await snap();
      const filesM = await saveDownload(dlM);
      await disconnectPlaywright();
      console.log(JSON.stringify({
        success: true,
        message: 'Comprobante descargado nuevamente. XML y PDF guardados.',
        screenshot: screenshotPublic,
        ...filesM,
      }));
      return;
    }

    // ── STEP 2: RFC check (normal new-stamp path) ─────────────────────────
    await page.locator('#register-rfc').fill(company_rfc);
    await page.locator('#verificarRFC').click();
    await snap();

    // Wait for EITHER #regimen options to load via AJAX (new stamp)
    // OR download button to appear (already stamped after RFC check)
    // Note: #regimen is never disabled — the signal is options.length > 1
    const scenario = await Promise.race([
      page.waitForFunction(() => {
        const s = document.querySelector('#regimen');
        return s && s.options.length > 1;
      }, { timeout: 15000 }).then(() => 'wizard'),
      page.locator(downloadBtnSel).first().waitFor({ state: 'visible', timeout: 15000 }).then(() => 'redownload'),
    ]).catch(() => 'wizard');

    await snap();

    if (scenario === 'redownload') {
      await page.locator(downloadBtnSel).first().click();
      const dl = await waitForDownload(15000);
      await snap();
      const files = await saveDownload(dl);
      await disconnectPlaywright();
      console.log(JSON.stringify({
        success: true,
        message: 'Comprobante descargado nuevamente. XML y PDF guardados.',
        screenshot: screenshotPublic,
        ...files,
      }));
      return;
    }

    // ── Normal wizard path ────────────────────────────────────────────────

    // Step 2 continued: fill régimen, uso, email, CP
    const regimenCode = (tax_regime || '').match(/^\d+/)?.[0] || '';
    if (regimenCode) {
      for (const opt of await page.locator('#regimen option').all()) {
        const t = await opt.textContent();
        if (t?.trim().includes(regimenCode)) {
          await page.locator('#regimen').selectOption(await opt.getAttribute('value'));
          break;
        }
      }
    }

    // uso_cfdi options load via AJAX after regimen is selected — wait for them
    await page.waitForFunction(() => {
      const s = document.querySelector('#uso_cfdi');
      return s && s.options.length > 1;
    }, { timeout: 10000 }).catch(() => {});

    await page.locator('#uso_cfdi').selectOption({ value: 'G03' }).catch(async () => {
      await page.locator('#uso_cfdi').selectOption({ label: /G03|gastos en general/i }).catch(() => {});
    });

    if (company_email) await page.locator('#email').fill(company_email);
    if (zip_code)      await page.locator('#codigo_postal').fill(zip_code);
    await snap();

    // Advance to step 3 — verify we actually left step 2 (wizard rejects if fields missing)
    await page.locator('a[href="#next"]').click();
    await page.waitForTimeout(1000);
    const stillOnStep2 = await page.locator('#regimen').isVisible().catch(() => false);
    if (stillOnStep2) throw new Error('Wizard rechazó el paso 2 — régimen o uso CFDI no seleccionados');
    await page.waitForTimeout(1000);
    await snap();

    // ── STEP 3: Vista Previa ──────────────────────────────────────────────
    await page.locator('a[href="#next"]').click();
    await page.waitForTimeout(2000);
    await snap();

    // ── STEP 4: Facturar ─────────────────────────────────────────────────
    // Checkbox is styled/hidden — force: true bypasses visibility check
    await page.locator('#chk_confirmar').check({ force: true });
    await page.waitForTimeout(500);
    // "Facturar" text exists on both the nav tab (has aria-controls) and the submit button.
    // Use JS to find and click the submit button directly — avoids Playwright selector ambiguity.
    await page.evaluate(() => {
      const btn = [...document.querySelectorAll('button, a, input[type="submit"]')].find(
        el => el.textContent.trim() === 'Facturar' && !el.hasAttribute('aria-controls')
      );
      if (btn) btn.click();
      else throw new Error('Botón Facturar no encontrado en el DOM');
    });

    // Portal shows "Esta a punto de facturar ¿desea continuar? SI/NO" before stamping
    const confirmSI = page.locator('button:has-text("SI")').first();
    const hasConfirm = await confirmSI.waitFor({ state: 'visible', timeout: 8000 })
      .then(() => true).catch(() => false);
    if (hasConfirm) {
      await confirmSI.click();
    }
    await snap();

    // Portal either auto-downloads a ZIP or shows individual PDF/XML links
    const pdfLinkSel = 'a:has-text("PDF"), button:has-text("PDF"), a[href$=".pdf"]';
    const xmlLinkSel = 'a:has-text("XML"), button:has-text("XML"), a[href$=".xml"]';

    const step4 = await Promise.race([
      waitForDownload(8000).then(dl => ({ kind: 'zip', dl })),
      page.locator(pdfLinkSel).first().waitFor({ state: 'visible', timeout: 30000 })
        .then(() => ({ kind: 'links' })),
    ]).catch(() => { throw new Error('Portal no respondió con descarga ni links PDF/XML'); });

    let files;
    if (step4.kind === 'zip') {
      files = await saveDownload(step4.dl);
    } else {
      // Click PDF and XML links individually and save each download
      const ts = Date.now();
      await snap();

      const pdfLink = page.locator(pdfLinkSel).first();
      const xmlLink = page.locator(xmlLinkSel).first();
      let pdfUrl = null, xmlUrl = null;

      if (await pdfLink.isVisible()) {
        await pdfLink.click();
        const dl = await waitForDownload(20000);
        const dest = path.join(uploadDir, `cfdi_${ticket_id}_${ts}.pdf`);
        await dl.saveAs(dest);
        pdfUrl = screenshot_url + `cfdi_${ticket_id}_${ts}.pdf`;
      }

      if (await xmlLink.isVisible()) {
        await xmlLink.click();
        const dl = await waitForDownload(20000);
        const dest = path.join(uploadDir, `cfdi_${ticket_id}_${ts}.xml`);
        await dl.saveAs(dest);
        xmlUrl = screenshot_url + `cfdi_${ticket_id}_${ts}.xml`;
      }

      files = { pdf_path: pdfUrl, xml_path: xmlUrl };
    }

    await snap();
    const success = !!(files.xml_path || files.pdf_path);

    await disconnectPlaywright();

    console.log(JSON.stringify({
      success,
      message: success
        ? 'Factura timbrada. XML y PDF guardados automáticamente.'
        : 'Formulario completado pero no llegó la descarga. Revisa el screenshot.',
      screenshot: screenshotPublic,
      ...files,
    }));

  } catch (err) {
    await snap();
    await disconnectPlaywright();
    console.log(JSON.stringify({
      success: false,
      message: `Error: ${err.message}`,
      screenshot: screenshotPublic,
    }));
  }
}

run();

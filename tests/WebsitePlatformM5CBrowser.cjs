// Local synthetic browser regression; NOT authenticated HTTP, real zoom, or screen-reader evidence.
// Supply an existing Playwright module through M5C_PLAYWRIGHT_MODULE if not on Node's module path.
const { chromium } = require(process.env.M5C_PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const http = require('node:http');
const root = path.resolve(__dirname, '..');
const documents = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'support/WebsitePlatformM5CViewFixture.php')], { encoding: 'utf8' }));
const css = fs.readFileSync(path.join(root, 'public/app/assets/css/design-system.css'), 'utf8');
const statusScript = fs.readFileSync(path.join(root, 'public/app/assets/js/customer-review-status.js'), 'utf8');
let assertions = 0;
function check(ok, message) { assertions++; assert.ok(ok, message); }

(async () => {
    // Loopback fixture server only; never serves the application or authenticates users.
    const server = http.createServer(async (request, response) => {
        const pathname = new URL(request.url, 'http://localhost').pathname;
        if (request.method === 'POST') {
            let body = '';
            for await (const chunk of request) body += chunk;
            const action = new URLSearchParams(body).get('action');
            const state = action === 'approve_revision' ? 'approved' : action === 'request_changes' ? 'changes' : 'receipt';
            response.writeHead(303, { location: '/' + state });
            return response.end();
        }
        if (pathname.endsWith('.css')) {
            response.writeHead(200, { 'Content-Type': 'text/css' });
            return response.end(css);
        }
        if (pathname.endsWith('/customer-review-status.js')) {
            response.writeHead(200, { 'Content-Type': 'text/javascript' });
            return response.end(statusScript);
        }
        if (pathname.endsWith('.svg') || pathname === '/favicon.ico') {
            response.writeHead(200, { 'Content-Type': 'image/svg+xml' });
            return response.end('<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32"/>');
        }
        const document = documents[pathname.slice(1)];
        response.writeHead(document ? 200 : 404, { 'Content-Type': 'text/html; charset=utf-8' });
        response.end(document || 'Unknown synthetic fixture');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const base = 'http://127.0.0.1:' + server.address().port;
    let browser;
    const errors = [], requests = new Set();
    try {
        browser = await chromium.launch({ channel: process.env.M5C_BROWSER_CHANNEL || 'msedge', headless: true });
        const context = await browser.newContext();
        const page = await context.newPage();
        let scriptGate = null;
        await page.addInitScript(() => {
            window.receiptMutations = [];
            new MutationObserver(records => {
                for (const record of records) {
                    if (record.target.nodeType === 1 && record.target.matches('.site-customer-announcer')) {
                        window.receiptMutations.push(record.target.textContent);
                    }
                }
            }).observe(document, { subtree: true, childList: true });
        });
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
        page.on('request', request => requests.add(request.url()));
        await context.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.origin === base && url.pathname.endsWith('/customer-review-status.js') && scriptGate) {
                scriptGate.requested();
                await scriptGate.wait;
            }
            if (url.origin !== base) {
                // The shared stylesheet's existing Google Fonts dependency is allowed.
                if (!['fonts.googleapis.com', 'fonts.gstatic.com'].includes(url.hostname)) {
                    errors.push('Unexpected external request: ' + url.hostname);
                    return route.abort();
                }
                return route.continue();
            }
            return route.continue();
        });
        async function open(state) {
            await page.goto(base + '/' + state);
            await page.evaluate(() => document.fonts.ready);
        }
        async function fit(label) {
            const result = await page.evaluate(() => {
                const content = document.querySelector('.account-content') || document.querySelector('main');
                const bounds = content.getBoundingClientRect();
                return {
                    viewport: innerWidth, page: document.documentElement.scrollWidth,
                    content: content.clientWidth, scroll: content.scrollWidth,
                    outside: [...content.querySelectorAll('select,textarea,button,iframe,fieldset')]
                        .filter(e => { const r = e.getBoundingClientRect(); return r.width && (r.right > bounds.right + 1 || r.left < bounds.left - 1); })
                        .map(e => e.tagName + ':' + (e.name || '')),
                };
            });
            check(result.page <= result.viewport && result.scroll <= result.content + 1 && result.outside.length === 0, label + ': ' + JSON.stringify(result));
        }
        // 640 is reflow readiness for a 1280-wide window at 200%; NOT actual browser zoom.
        for (const width of [360, 768, 1280, 640]) {
            await page.setViewportSize({ width, height: 900 });
            for (const state of Object.keys(documents)) {
                await open(state);
                await fit(width + '/' + state);
            }
        }
        await page.setViewportSize({ width: 360, height: 900 });
        await open('long');
        if (process.env.M5C_SCREENSHOT_DIR) {
            fs.mkdirSync(process.env.M5C_SCREENSHOT_DIR, { recursive: true });
            await page.screenshot({ path: path.join(process.env.M5C_SCREENSHOT_DIR, 'm5c-360-long.png'), fullPage: true });
        }
        await page.locator('.site-customer-submissions li p').last().evaluate(e => { e.textContent = 'W'.repeat(2000); });
        await fit('2000-character unbroken feedback');
        // A reserved desktop scrollbar gutter must not reintroduce narrow-width overflow.
        await page.addStyleTag({ content: 'html{scrollbar-gutter:stable}' });
        await fit('360 with scrollbar gutter');
        for (const [action, state] of [['feedback', 'receipt'], ['approve_revision', 'approved'], ['request_changes', 'changes']]) {
            await open('initial');
            check(await page.locator('[autofocus]').count() === 0, 'Initial navigation does not steal focus');
            const form = page.locator('form').filter({ has: page.locator('input[name="action"][value="' + action + '"]') });
            await form.locator('textarea').fill('Synthetic keyboard submission');
            await form.locator('button').focus();
            let release, requested;
            const requestSeen = new Promise(resolve => { requested = resolve; });
            scriptGate = { wait: new Promise(resolve => { release = resolve; }), requested };
            const post = page.waitForResponse(response => response.request().method() === 'POST');
            await Promise.all([page.waitForURL('**/' + state, { waitUntil: 'commit' }), page.keyboard.press('Enter')]);
            check((await post).status() === 303, 'Rendered POST redirects with 303: ' + action);
            await page.locator('[data-customer-review-receipt]').waitFor();
            await requestSeen;
            check(await page.locator('.site-customer-announcer').textContent() === '', 'Region exists empty before script executes');
            check(await page.locator('[data-customer-review-receipt]').evaluate(e => e !== document.activeElement && !e.hasAttribute('autofocus') && !e.hasAttribute('tabindex')), 'Visible receipt does not receive forced focus');
            release();
            scriptGate = null;
            await page.waitForLoadState('load');
            await page.waitForFunction(() => document.querySelector('.site-customer-announcer').textContent !== '');
            const text = await page.locator('[data-customer-review-receipt]').textContent();
            check(await page.locator('.site-customer-announcer').textContent() === text, 'Post-load announcement equals rendered plain text');
            check(await page.evaluate(() => document.activeElement === document.body), 'Announcement preserves normal document focus');
            // Re-executing the same asset must not publish the receipt twice.
            await page.addScriptTag({ url: base + '/assets/js/customer-review-status.js' });
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0))));
            check(JSON.stringify(await page.evaluate(() => window.receiptMutations)) === JSON.stringify([text]), 'Exactly one live-region population, including duplicate asset execution');
            if (process.env.M5C_SCREENSHOT_DIR && state === 'receipt') await page.screenshot({ path: path.join(process.env.M5C_SCREENSHOT_DIR, 'm5c-360-receipt.png') });
            await page.keyboard.press('Tab');
            check(await page.evaluate(() => document.activeElement === document.querySelector('a[href]')), 'Tab follows normal page navigation after redirect');
        }
        for (const state of ['initial', 'legacy']) {
            await open(state);
            // Even an accidentally loaded asset must remain inert outside generic success.
            await page.addScriptTag({ url: base + '/assets/js/customer-review-status.js' });
            check(await page.locator('[data-customer-review-receipt],.site-customer-announcer,[autofocus]').count() === 0, 'No generic marker/announcer/forced focus on ' + state);
            check(await page.evaluate(() => window.receiptMutations.length) === 0, 'No announcement on ' + state);
        }
        await open('hostile');
        await page.waitForFunction(() => document.querySelector('.site-customer-announcer').textContent !== '');
        check(await page.locator('.site-customer-announcer').textContent() === await page.locator('[data-customer-review-receipt]').textContent(), 'Hostile receipt copied exactly as text');
        check(await page.locator('.site-customer-announcer *,[data-customer-review-receipt] *,[onerror]').count() === 0, 'Hostile text creates no elements or attributes');
        check(await page.locator('[data-customer-review-receipt]').count() === 1, 'Hostile text cannot change static selector');
        const noJs = await browser.newContext({ javaScriptEnabled: false });
        const fallback = await noJs.newPage();
        await fallback.goto(base + '/receipt');
        check(await fallback.locator('[data-customer-review-receipt]').isVisible(), 'Visible success works without JavaScript');
        check(await fallback.locator('.site-customer-announcer').textContent() === '', 'No-JS region remains empty');
        await noJs.close();
        await open('error');
        await page.waitForFunction(() => document.activeElement?.getAttribute('role') === 'alert');
        await page.keyboard.press('Tab');
        check(await page.evaluate(() => document.activeElement.textContent === 'Reload Website Manager'), 'Error focus continues to recovery link');
        await open('initial');
        const controls = page.locator('.site-customer-review :is(a,select,textarea,button)');
        await controls.first().focus();
        for (let i = 0; i < await controls.count(); i++) {
            check(await controls.nth(i).evaluate(e => e === document.activeElement), 'Sequential keyboard reachability: ' + i);
            if (i > 0) check(await controls.nth(i).evaluate(e => getComputedStyle(e).outlineStyle === 'solid'), 'Visible keyboard focus: ' + i);
            await page.keyboard.press('Tab');
        }
        check(!await page.evaluate(() => document.activeElement.closest('.site-customer-review')), 'No review keyboard trap');
        await open('preview');
        const frame = page.frames().find(frame => frame !== page.mainFrame());
        check(!!frame, 'Private srcdoc frame loads');
        check(await frame.locator('a,form,script,input:not([disabled]),button:not([disabled])').count() === 0, 'Preview remains inert');
        check(await frame.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Preview content reflows');
        check(errors.length === 0, 'No console/page errors: ' + errors.join('; '));
        console.log(JSON.stringify({ result: 'PASS', assertions, browser: browser.version(), widths: [360,768,1280,640], consoleErrors: errors, networkHosts: [...new Set([...requests].map(url => new URL(url).hostname))], scope: 'DOM/live-region mutation evidence only — Narrator validation still required after deployment. Synthetic views and mocked redirects; authenticated browser, actual zoom and MySQL gates NOT RUN.' }, null, 2));
    } finally { if (browser) await browser.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });

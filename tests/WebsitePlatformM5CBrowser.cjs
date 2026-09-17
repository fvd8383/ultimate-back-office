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
        const probe = new URL(request.url, base).searchParams.has('script_probe');
        response.end(document ? document + (probe ? '<script>window.pageScriptRan=true</script>' : '') : 'Unknown synthetic fixture');
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
                    if (record.target.nodeType === 1 && record.target.matches('[data-customer-review-receipt]')) {
                        window.receiptMutations.push(record.target.textContent);
                    }
                }
            }).observe(document, { subtree: true, childList: true });
        });
        const accessibility = await context.newCDPSession(page);
        async function accessibleCopies(text) {
            const { nodes } = await accessibility.send('Accessibility.getFullAXTree');
            return nodes.filter(node => !node.ignored && node.role?.value === 'StaticText' && node.name?.value === text).length;
        }
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
        const normalTitle = '247SP Website Manager - Ultimate Back Office';
        const resultPrefixes = { receipt: 'Feedback sent', approved: 'Website approved', changes: 'Changes requested' };
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
            check(await page.title() === normalTitle, 'Initial GET retains exact original title');
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
            await page.locator('[data-customer-review-receipt]').waitFor({ state: 'attached' });
            await requestSeen;
            check(await page.title() === resultPrefixes[state] + ' - ' + normalTitle, 'Server-rendered result title is available before receipt JS: ' + state);
            const receipt = page.locator('[data-customer-review-receipt]');
            const source = page.locator('template[data-customer-review-receipt-source]');
            const text = await source.evaluate(e => e.content.textContent);
            check(await receipt.textContent() === '', 'Visible status exists empty before script executes');
            check(await source.count() === 1 && text.length > 0, 'Exactly one inert source contains expected text');
            check(await receipt.evaluate(e => {
                window.originalReceipt = e;
                const style = getComputedStyle(e);
                return e.getAttribute('role') === 'status' && e.getAttribute('aria-live') === 'polite' && e.getAttribute('aria-atomic') === 'true' && style.display !== 'none' && style.visibility === 'visible';
            }), 'Empty receipt is the visible-style status, never a hidden announcer');
            check(await accessibleCopies(text) === 0, 'No accessible receipt text before population, including template/noscript/static guidance: ' + action + ' / ' + text);
            check(await page.locator('noscript .site-customer-receipt').count() === 0, 'JS-enabled noscript does not create another receipt node');
            check(await page.locator('[data-customer-review-receipt]').evaluate(e => e !== document.activeElement && !e.hasAttribute('autofocus') && !e.hasAttribute('tabindex')), 'Visible receipt does not receive forced focus');
            release();
            scriptGate = null;
            await page.waitForLoadState('load');
            check(await page.title() === resultPrefixes[state] + ' - ' + normalTitle, 'POST/303/GET retains concise allowlisted result title: ' + state);
            await page.waitForFunction(() => document.querySelector('[data-customer-review-receipt]').textContent !== '');
            check(await receipt.textContent() === text, 'Post-load receipt equals inert source exactly');
            check(await receipt.evaluate(e => e === window.originalReceipt), 'The same established status node is populated');
            check(await receipt.isVisible(), 'Populated status is visibly rendered');
            check(await page.locator('[role="status"]').count() === 1 && await page.locator('[aria-live]').count() === 1 && await page.locator('.site-customer-announcer').count() === 0, 'Only one visible live status exists');
            check(await accessibleCopies(text) === 1, 'Exactly one accessible receipt text after population');
            check(await page.evaluate(() => document.activeElement === document.body), 'Announcement preserves normal document focus');
            // Re-executing the same asset must not publish the receipt twice.
            await page.addScriptTag({ url: base + '/assets/js/customer-review-status.js' });
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => setTimeout(resolve, 0))));
            check(JSON.stringify(await page.evaluate(() => window.receiptMutations)) === JSON.stringify([text]), 'Exactly one live-region population, including duplicate asset execution');
            check(await receipt.evaluate(e => e === window.originalReceipt) && await accessibleCopies(text) === 1, 'Re-execution neither replaces node nor inserts another accessible copy');
            if (process.env.M5C_SCREENSHOT_DIR && state === 'receipt') await page.screenshot({ path: path.join(process.env.M5C_SCREENSHOT_DIR, 'm5c-360-receipt.png') });
            await page.keyboard.press('Tab');
            check(await page.evaluate(() => document.activeElement === document.querySelector('a[href]')), 'Tab follows normal page navigation after redirect');
        }
        for (const state of ['initial', 'legacy', 'approved-get', 'changes-get']) {
            await open(state);
            check(await page.title() === normalTitle, 'Normal/later terminal/legacy GET has no transient prefix: ' + state);
            // Even an accidentally loaded asset must remain inert outside generic success.
            await page.addScriptTag({ url: base + '/assets/js/customer-review-status.js' });
            check(await page.locator('[data-customer-review-receipt],.site-customer-announcer,[autofocus]').count() === 0, 'No generic marker/announcer/forced focus on ' + state);
            check(await page.evaluate(() => window.receiptMutations.length) === 0, 'No announcement on ' + state);
            if (state.endsWith('-get')) {
                const label = state === 'approved-get' ? 'Approved by customer; awaiting internal review.' : 'Changes requested.';
                check(await accessibleCopies(label) === 1, 'Later terminal GET retains its persistent readable status: ' + state);
            }
        }
        await open('hostile');
        check(await page.title() === normalTitle, 'Unknown hostile receipt cannot alter title');
        await page.waitForFunction(() => document.querySelector('[data-customer-review-receipt]').textContent !== '');
        const hostile = '</template></noscript><script>window.receiptInjected=true</script><img src=x onerror=alert(1)>" data-customer-review-receipt="hostile & café';
        check(await page.locator('[data-customer-review-receipt]').textContent() === hostile && await page.locator('template[data-customer-review-receipt-source]').evaluate(e => e.content.textContent) === hostile, 'Hostile source survives textContent conversion exactly');
        check(await page.locator('[data-customer-review-receipt] *,[onerror],script:not([src])').count() === 0 && await page.evaluate(() => window.receiptInjected === undefined), 'Hostile text creates no elements, attributes or executable script');
        check(await page.locator('[data-customer-review-receipt]').count() === 1, 'Hostile text cannot change static selector');
        check(await accessibleCopies(hostile) === 1, 'Hostile receipt has one accessible copy');
        await open('hostile-feedback');
        await page.waitForFunction(() => document.querySelector('[data-customer-review-receipt]').textContent !== '');
        check(await page.title() === 'Feedback sent - ' + normalTitle, 'Hostile feedback projection retains allowlisted feedback title');
        check(await page.locator('title').count() === 1 && await page.locator('[onerror],script:not([src])').count() === 0 && await page.evaluate(() => window.receiptInjected === undefined), 'Hostile feedback creates no title, attribute or script injection');
        check(await accessibleCopies('Sent for consideration; this does not change your preview.') === 1, 'Detailed receipt remains one accessible copy alongside concise title');
        await page.goto(base + '/receipt?script_probe=1');
        check(await page.evaluate(() => window.pageScriptRan === true), 'Fixture script probe executes when JavaScript is enabled');
        const noJs = await browser.newContext({ javaScriptEnabled: false });
        const fallback = await noJs.newPage();
        let noJsScriptRequests = 0;
        fallback.on('request', request => { if (request.url().endsWith('/customer-review-status.js')) noJsScriptRequests++; });
        for (const state of ['receipt', 'approved', 'changes', 'hostile']) {
            await fallback.goto(base + '/' + state + '?script_probe=1');
            check(await fallback.title() === (resultPrefixes[state] ? resultPrefixes[state] + ' - ' + normalTitle : normalTitle), 'Server title does not depend on JavaScript: ' + state);
            const sourceText = await fallback.locator('template[data-customer-review-receipt-source]').evaluate(e => e.content.textContent);
            const visibleFallback = fallback.locator('noscript .site-customer-receipt');
            check(await visibleFallback.isVisible() && await visibleFallback.textContent() === sourceText, 'No-JS fallback shows exact escaped receipt: ' + state);
            check(await fallback.locator('[data-customer-review-receipt]').textContent() === '', 'No-JS status stays empty: ' + state);
            check(await fallback.evaluate(() => window.pageScriptRan === undefined && window.receiptInjected === undefined), 'Document scripts do not execute in disabled context: ' + state);
            const session = await noJs.newCDPSession(fallback);
            const { nodes } = await session.send('Accessibility.getFullAXTree');
            check(nodes.filter(node => !node.ignored && node.role?.value === 'StaticText' && node.name?.value === sourceText).length === 1, 'No-JS fallback is the sole accessible receipt: ' + state);
            await session.detach();
        }
        check(noJsScriptRequests === 0, 'Disabled browser makes no receipt-script request');
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
        console.log(JSON.stringify({ result: 'PASS', assertions, browser: browser.version(), widths: [360,768,1280,640], consoleErrors: errors, networkHosts: [...new Set([...requests].map(url => new URL(url).hostname))], scope: 'DOM/live-region mutation evidence only — actual Narrator audio validation required after deployment. Synthetic views and mocked redirects; authenticated browser, actual zoom and MySQL gates NOT RUN.' }, null, 2));
    } finally { if (browser) await browser.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode = 1; });

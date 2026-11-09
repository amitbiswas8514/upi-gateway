@php
    $finalCheck = $finalCheck ?? false;
    $serverReady = $serverReady ?? true;
    $nativeInstaller = $nativeInstaller ?? false;
    $serverFailures = $serverFailures ?? [];
@endphp
<style nonce="{{ $csp_nonce ?? '' }}">
    .installer-readiness { text-align: center; padding: 1.5rem 0 1.25rem; }
    .installer-readiness__icon { width: 4.75rem; height: 4.75rem; margin: 0 auto 1.125rem; border: 1px solid #c7d2fe; border-radius: 1.35rem; display: grid; place-items: center; background: #f5f7ff; color: #4f46e5; box-shadow: 0 10px 24px rgba(79,70,229,.1); transition: background .2s ease, color .2s ease, border-color .2s ease, box-shadow .2s ease; }
    .installer-readiness__spinner { width: 2rem; height: 2rem; border: 3px solid #c7d2fe; border-top-color: #4f46e5; border-radius: 9999px; animation: installer-spin .8s linear infinite; }
    .installer-readiness[data-state="ready"] .installer-readiness__icon { border-color: #a7f3d0; background: #ecfdf5; color: #059669; box-shadow: 0 10px 24px rgba(5,150,105,.1); }
    .installer-readiness[data-state="failed"] .installer-readiness__icon { border-color: #fecaca; background: #fef2f2; color: #dc2626; box-shadow: 0 10px 24px rgba(220,38,38,.08); }
    .installer-readiness__symbol { display: none; width: 2.25rem; height: 2.25rem; }
    .installer-readiness[data-state="ready"] .installer-readiness__spinner,
    .installer-readiness[data-state="failed"] .installer-readiness__spinner { display: none; }
    .installer-readiness[data-state="ready"] [data-ready-icon],
    .installer-readiness[data-state="failed"] [data-failed-icon] { display: block; }
    .installer-readiness__title { margin: 0; color: #0f172a; font-size: 1.2rem; font-weight: 750; letter-spacing: -.02em; }
    .installer-readiness__message { max-width: 27rem; margin: .55rem auto 0; color: #64748b; font-size: .9rem; line-height: 1.6; }
    .installer-diagnostics { display:none; margin:1.25rem auto 0; max-width:42rem; text-align:left; border:1px solid #fecaca; border-radius:14px; background:#fffafa; padding:1rem 1.1rem; color:#7f1d1d; }
    .installer-diagnostics[data-visible="true"] { display:block; }
    .installer-diagnostics h3 { margin:0 0 .55rem; font-size:.92rem; font-weight:750; color:#991b1b; }
    .installer-diagnostics p { margin:.35rem 0; font-size:.82rem; line-height:1.55; }
    .installer-diagnostics ul { margin:.5rem 0 0; padding-left:1.2rem; font-size:.82rem; line-height:1.65; }
    .installer-diagnostics code { overflow-wrap:anywhere; font-size:.76rem; }
    [data-deployment-continue][hidden], [data-deployment-submit][hidden], [data-hosting-recheck] { display: none !important; }
    @keyframes installer-spin { to { transform: rotate(360deg); } }
</style>

<div class="installer-readiness" data-installer-readiness data-state="checking" role="status" aria-live="polite">
    <div class="installer-readiness__icon" aria-hidden="true">
        <span class="installer-readiness__spinner"></span>
        <svg data-ready-icon class="installer-readiness__symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 7"/></svg>
        <svg data-failed-icon class="installer-readiness__symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 8v5"/><path d="M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg>
    </div>
    <p class="installer-readiness__title" data-hosting-result>Checking...</p>
    <p class="installer-readiness__message" data-hosting-message>We’re making sure everything is ready.</p>
    <section class="installer-diagnostics" data-installer-diagnostics aria-live="polite">
        <h3 data-diagnostic-title>What needs to be fixed?</h3>
        <div data-diagnostic-content></div>
    </section>
    <button type="button" data-hosting-recheck tabindex="-1" aria-hidden="true">Check again</button>
    <noscript><p class="installer-readiness__message">JavaScript is required to verify your server safely.</p></noscript>
</div>

<script nonce="{{ $csp_nonce ?? '' }}">
document.addEventListener('DOMContentLoaded', () => {
    const base = @json(rtrim($deploymentUrl, '/'));
    const finalCheck = @json($finalCheck);
    const serverReady = @json($serverReady);
    const nativeInstaller = @json($nativeInstaller);
    const serverFailures = @json(array_values($serverFailures));
    const installerCsrf = @json($deploymentCsrfToken ?? csrf_token());
    const installerDiagToken = @json($deploymentDiagnosticToken ?? (function_exists('session') ? session('installer.diagnostic_token') : null) ?? '');
    const panel = document.querySelector('[data-installer-readiness]');
    const output = panel.querySelector('[data-hosting-result]');
    const message = panel.querySelector('[data-hosting-message]');
    const recheck = panel.querySelector('[data-hosting-recheck]');
    const diagnostics = panel.querySelector('[data-installer-diagnostics]');
    const diagnosticTitle = panel.querySelector('[data-diagnostic-title]');
    const diagnosticContent = panel.querySelector('[data-diagnostic-content]');
    const links = document.querySelectorAll('[data-deployment-continue]');
    const submits = document.querySelectorAll('[data-deployment-submit]');
    const requirementHelp = {
        'PHP >= 8.2': 'Select PHP 8.2 or newer in Hostinger hPanel → PHP Configuration.',
        'Compatible PHP handler': 'The website must run using a supported PHP handler. Hostinger LiteSpeed is supported by this build; check the active PHP version/handler in hPanel.',
        'PDO Extension': 'Enable the PDO PHP extension in PHP Configuration.',
        'PDO MySQL (required)': 'Enable pdo_mysql in PHP extensions. This is required to connect to MySQL.',
        'OpenSSL Extension': 'Enable the OpenSSL PHP extension.',
        'Mbstring Extension': 'Enable the mbstring PHP extension.',
        'Tokenizer Extension': 'Enable the tokenizer PHP extension.',
        'JSON Extension': 'Enable the JSON PHP extension.',
        'cURL Extension': 'Enable the cURL PHP extension.',
        'Fileinfo Extension': 'Enable the fileinfo PHP extension.',
        'XML Extension': 'Enable the XML PHP extension.',
        'Ctype Extension': 'Enable the ctype PHP extension.',
        'BCMath Extension': 'Enable the bcmath PHP extension.',
        'storage/ writable': 'Make the storage directory writable by the website PHP user.',
        'storage/framework/cache writable': 'Make storage/framework/cache writable by the website PHP user.',
        'storage/framework/sessions writable': 'Make storage/framework/sessions writable by the website PHP user.',
        'storage/framework/views writable': 'Make storage/framework/views writable by the website PHP user.',
        'storage/logs writable': 'Make storage/logs writable by the website PHP user.',
        'bootstrap/cache/ writable': 'Make bootstrap/cache writable by the website PHP user.',
        '.env writable': 'Allow PHP to create or update the .env file in the application root.',
        'Session Support': 'Enable PHP sessions in the hosting PHP configuration.',
        'Production Assets (Vite build)': 'Upload the complete public/build directory, including manifest.json, from the production package.'
    };
    const stageHelp = {
        server: 'One or more server requirements failed. See the failed items listed below.',
        public_directory: 'The website root is exposing or incorrectly routing a private-file test. Set the domain document root to the application public directory and verify the root/public .htaccess files.',
        routing: 'The application routing test did not return the expected response. Check the domain document root, rewrite rules, and whether Laravel can boot without a PHP error.',
        assets: 'A public build file is missing, inaccessible, or served with the wrong content type. Re-upload public/build and check LiteSpeed/static-file rules.',
        private_files: 'A private application file is publicly accessible. Restore the supplied .htaccess rules and ensure the domain points to public/, not the project root.',
        session: 'The session cookie or CSRF test failed. Check PHP session support, session directory permissions, HTTPS configuration, and cookie path.',
        application_urls: 'The configured APP_URL or generated login/dashboard URLs do not match the website URL. Correct APP_URL in .env, then clear config cache.',
        installer_lock: 'The installer is not locked after completion. Do not leave the installer accessible; restore the supplied installer protection rules.'
    };
    const showDiagnostics = (title, paragraphs = [], items = []) => {
        diagnosticTitle.textContent = title;
        diagnosticContent.replaceChildren();
        paragraphs.forEach(value => { const p = document.createElement('p'); p.textContent = value; diagnosticContent.appendChild(p); });
        if (items.length) {
            const ul = document.createElement('ul');
            items.forEach(item => { const li = document.createElement('li'); li.textContent = item; ul.appendChild(li); });
            diagnosticContent.appendChild(ul);
        }
        diagnostics.dataset.visible = 'true';
    };
    const setStatus = (state, reason = null, detail = '') => {
        panel.dataset.state = state;
        output.textContent = state === 'ready' ? 'All checks passed' : state === 'failed' ? 'Setup not ready' : 'Checking...';
        message.textContent = state === 'ready'
            ? 'Your server is ready. You can continue safely.'
            : state === 'failed'
                ? (stageHelp[reason] || 'The readiness test failed. Review the diagnostic details below.')
                : 'We’re making sure everything is ready.';
        if (state !== 'failed') diagnostics.dataset.visible = 'false';
        if (state === 'failed' && detail) showDiagnostics('Exact check failure', ['Check stage: ' + reason, 'Technical detail: ' + detail]);
    };
    const fetchAt = (path, options = {}) => {
        const target = new URL(base + '/' + path);
        target.searchParams.set('__upi_check', Date.now() + '-' + Math.random());
        return fetch(target, {
            cache: 'no-store', credentials: 'same-origin', signal: AbortSignal.timeout(12000), ...options
        });
    };
    let routing = null;
    const reportFailure = async (stage, error) => {
        const diagToken = nativeInstaller ? installerDiagToken : routing?.diagnostic_token;
        if (!diagToken) return;
        const endpoint = nativeInstaller ? 'install.php?step=deployment-check' : '__upi_deployment_check';
        const parameters = new URLSearchParams({
            _token: installerCsrf,
            diagnostic_report: '1',
            diagnostic_token: diagToken,
            stage,
            message: String(error?.message || 'Unknown readiness failure').slice(0, 500),
        });
        try { await fetchAt(endpoint, {method: 'POST', body: parameters}); } catch (_) {}
    };
    const check = async () => {
        links.forEach(link => { link.hidden = true; link.removeAttribute('href'); link.setAttribute('aria-disabled', 'true'); });
        submits.forEach(button => { button.hidden = true; button.disabled = true; });
        setStatus('checking');
        let stage = 'server';
        try {
            if (!serverReady) {
                const names = serverFailures.length ? serverFailures : ['The server-side readiness check returned false, but did not provide a failed item.'];
                showDiagnostics('Server requirements that failed', ['These are checks performed directly by PHP before browser tests can run.'], names.map(name => name + ': ' + (requirementHelp[name] || 'Review this server setting or file permission.')));
                throw new Error('Server requirement failure: ' + names.join(', '));
            }
            if (new URL(base).origin !== window.location.origin) throw new Error('The configured application origin does not match the browser origin.');
            stage = 'public_directory';
            const privateSentinel = await fetchAt('deployment-private-check.txt', {method: 'HEAD'});
            if (![403, 404].includes(privateSentinel.status)) throw new Error('The website root exposes application-private files.');
            stage = 'routing';
            const route = await fetchAt('__upi_deployment_check');
            routing = route.ok ? await route.json() : null;
            if (!routing || routing.application !== 'upi-gateway' || routing.routing !== true) throw new Error('The application routing probe failed.');
            const handler = routing.php_handler || {
                status: routing.php_fpm ? 'supported' : 'unknown',
                supported: routing.php_fpm === true,
                name: routing.php_fpm ? 'PHP-FPM' : 'Unknown',
            };
            if (!handler.supported) {
                if (handler.status === 'unsupported') {
                    throw new Error('The detected PHP handler (' + (handler.name || 'unsupported') + ') is not supported.');
                }
                throw new Error('Unrecognized PHP handler (' + (handler.name || 'unknown') + '). PHP-FPM or a compatible handler is required.');
            }
            if (new URL(base).protocol === 'https:' && routing.runtime === 'laravel' && !routing.secure) throw new Error('HTTPS was not detected behind the configured proxy.');
            const prefix = new URL(base).pathname.replace(/\/$/, '');
            if (routing.base_path !== prefix) throw new Error('The application URL prefix does not match the PHP mount.');
            stage = 'assets';
            const assets = await fetchAt('deployment-check.json');
            if (!assets.ok || (await assets.json()).public_assets !== true) throw new Error('The public directory probe failed.');
            const manifest = await fetchAt('build/manifest.json');
            if (!manifest.ok) throw new Error('The frontend asset manifest is unavailable.');
            const entries = await manifest.json();
            for (const [entry, type] of [['resources/js/app.js', 'javascript'], ['resources/css/app.css', 'css']]) {
                const file = entries[entry]?.file;
                if (!file || file.includes('..') || file.startsWith('/')) throw new Error('The frontend asset manifest contains an invalid path.');
                const asset = await fetchAt('build/' + file);
                if (!asset.ok || !(asset.headers.get('content-type') || '').includes(type)) throw new Error('A compiled frontend asset failed its HTTP check.');
            }
            stage = 'private_files';
            for (const path of ['.env', 'vendor/autoload.php', 'storage/logs/laravel.log']) {
                const response = await fetchAt(path, {method: 'HEAD'});
                if (![403, 404].includes(response.status)) throw new Error('Private-file protection failed for ' + path + '.');
            }
            stage = 'session';
            const native = routing.runtime !== 'laravel';
            const endpoint = native ? 'install.php?step=deployment-check' : '__upi_deployment_check';
            const parameters = new URLSearchParams({_token: native ? installerCsrf : routing.csrf_token});
            if (!native) parameters.set('probe', routing.probe);
            const sessionResponse = await fetchAt(endpoint, {method: 'POST', body: parameters});
            const session = sessionResponse.ok ? await sessionResponse.json() : null;
            if (!session || !session.session || !session.csrf || (native ? session.cookie_path : routing.cookie_path) !== prefix + '/') throw new Error('The session or CSRF round-trip failed.');
            stage = 'application_urls';
            if (!native) {
                if (routing.login_url !== base + '/login' || routing.dashboard_url !== base + '/dashboard') throw new Error('Generated application URLs do not match the public URL.');
                if (routing.installed) {
                    const login = await fetchAt('login');
                    if (!login.ok) throw new Error('The login route is unavailable.');
                }
            }
            stage = 'installer_lock';
            if (finalCheck) {
                const lock = await fetchAt('install.php', {method: 'HEAD'});
                if (!routing.installed || lock.status !== 403) throw new Error('The completed installer is not locked.');
            }
            links.forEach(link => { link.href = link.dataset.deploymentHref; link.hidden = false; link.removeAttribute('aria-disabled'); });
            submits.forEach(button => { button.hidden = false; button.disabled = false; });
            setStatus('ready');
        } catch (error) {
            setStatus('failed', stage, String(error?.message || error || 'Unknown error'));
            if (stage === 'server' && serverFailures.length) {
                showDiagnostics('Server requirements that failed', ['Fix the following items in your hosting control panel or file manager, then reload this page.'], serverFailures.map(name => name + ': ' + (requirementHelp[name] || 'Review this server setting or file permission.')));
            }
            await reportFailure(stage, error);
        }
    };
    recheck.addEventListener('click', check);
    check();
});
</script>

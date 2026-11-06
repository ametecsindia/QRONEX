<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>QRONEX API reference</title>
<link rel="stylesheet" href="{{ asset('vendor/swagger-ui/swagger-ui.css') }}">
<style>
  body{margin:0;font-family:system-ui,"Segoe UI",Roboto,sans-serif;background:#fff}
  header{background:#0E2747;color:#fff;padding:14px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
  header .logo{background:#fff;border-radius:8px;padding:4px 10px}.logo{display:inline-flex;align-items:center;gap:.5em}.logo .qx-mark{height:28px;width:28px}
  .guides{max-width:1200px;margin:16px auto;padding:0 16px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
  .g{border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;font-size:14px}.g b{color:#0E2747}.g code{font-size:12px;background:#f1f5f9;padding:1px 4px;border-radius:4px;word-break:break-all}
  .swagger-ui .topbar{display:none}
</style>
</head>
<body>
<header><span class="logo"><svg viewBox="0 0 32 32" class="qx-mark" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0E2747"/><rect x="6" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#fff" stroke-width="2"/><rect x="18" y="6" width="8" height="8" rx="1.5" fill="none" stroke="#fff" stroke-width="2"/><rect x="6" y="18" width="8" height="8" rx="1.5" fill="none" stroke="#fff" stroke-width="2"/><circle cx="22" cy="22" r="3.2" fill="#14B8A6"/></svg><b style="color:#0E2747;letter-spacing:-.01em">QRONEX</b></span>
  <b>API reference · v1</b><span style="opacity:.8">Base URL {{ url('/api/v1') }} · <a style="color:#5EE0CF" href="{{ route('openapi') }}">openapi.json</a></span></header>
<div class="guides">
  <div class="g"><b>SmartPRS / payroll</b><br>Pull verified events nightly; payroll computes payable days. QRONEX does not calculate pay.<br><code>GET /attendance/events?from=2026-11-01&amp;to=2026-11-30</code></div>
  <div class="g"><b>SmartEPT / live dashboards</b><br>Subscribe to <code>attendance.created</code> to see who is at the office in real time.</div>
  <div class="g"><b>HRMS sync</b><br>Create and update employees with your HRMS reference; deactivating revokes their phones.<br><code>PATCH /employees/EMP0045 {"office_code":"HYD01"}</code></div>
  <div class="g"><b>Verify webhooks</b><br><code>hex(HMAC_SHA256(secret, t + "." + rawBody)) == v1</code> from <code>X-Qronex-Signature: t=…,v1=…</code>; reject if <code>t</code> is older than 5 minutes.</div>
</div>
<div id="ui"></div>
<script src="{{ asset('vendor/swagger-ui/swagger-ui-bundle.js') }}"></script>
<script>SwaggerUIBundle({ url: @js(route('openapi')), dom_id: '#ui', deepLinking: true, persistAuthorization: false });</script>
</body>
</html>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $invoice->number }}</title>
<style>
  @page{size:A4;margin:12mm}
  body{font-family:system-ui,"Segoe UI",Roboto,sans-serif;color:#1e293b;max-width:800px;margin:24px auto;padding:0 16px;font-size:14px}
  .band{background:#0E2747;color:#fff;padding:16px 20px;border-radius:8px;display:flex;justify-content:space-between;align-items:center}
  .logo{background:#fff;border-radius:6px;padding:4px 10px}.logo img{height:32px}
  h1{margin:0;font-size:20px}.muted{color:#64748b}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:20px 0}
  .card{border:1px solid #e2e8f0;border-radius:8px;padding:12px}
  table{width:100%;border-collapse:collapse;margin-top:8px}th{background:#0E2747;color:#fff;text-align:left;padding:8px}td{padding:8px;border-bottom:1px solid #e2e8f0}
  tr:nth-child(even) td{background:#f8fafc}.r{text-align:right}.total td{font-weight:700;border-top:2px solid #0E2747;background:#DCF3EF!important}
  .foot{margin-top:24px;font-size:12px}.noprint{margin:16px 0}@media print{.noprint{display:none}}
</style>
</head>
<body>
<div class="noprint"><button onclick="print()">Print / Save as PDF</button></div>
<div class="band">
  <div><h1>Tax Invoice</h1><div>{{ $invoice->number }} · {{ $invoice->issued_on->format('d M Y') }}</div></div>
  <span class="logo"><img src="{{ asset('images/qronex-logo.png') }}" alt="QRONEX" onerror="this.replaceWith(Object.assign(document.createElement('b'),{textContent:'QRONEX',style:'color:#0E2747'}))"></span>
</div>
<div class="grid">
  <div class="card"><div class="muted">Supplier</div><b>{{ config('qronex.supplier.name') }}</b><br>{{ config('qronex.supplier.address') }}<br>GSTIN: {{ config('qronex.supplier.gstin') }}</div>
  <div class="card"><div class="muted">Billed to</div><b>{{ $tenant->name }}</b><br>{{ $tenant->address }}<br>{{ $tenant->city }}{{ $tenant->state ? ', '.$tenant->state : '' }}<br>GSTIN: {{ $tenant->gstin ?: 'Unregistered' }}<br><span class="muted">Place of supply: {{ $tenant->state ?: config('qronex.supplier.state') }}</span></div>
</div>
<table>
  <tr><th>Description</th><th class="r">Amount (₹)</th></tr>
  <tr><td>{{ $invoice->description }}</td><td class="r">{{ number_format($invoice->subtotal, 2) }}</td></tr>
  @if($invoice->onboarding > 0)<tr><td>One-time onboarding</td><td class="r">{{ number_format($invoice->onboarding, 2) }}</td></tr>@endif
  <tr><td>Taxable value</td><td class="r">{{ number_format($invoice->taxable, 2) }}</td></tr>
  @if($invoice->igst > 0)<tr><td>IGST {{ +$invoice->gst_rate }}%</td><td class="r">{{ number_format($invoice->igst, 2) }}</td></tr>
  @else<tr><td>CGST {{ $invoice->gst_rate / 2 }}%</td><td class="r">{{ number_format($invoice->cgst, 2) }}</td></tr><tr><td>SGST {{ $invoice->gst_rate / 2 }}%</td><td class="r">{{ number_format($invoice->sgst, 2) }}</td></tr>@endif
  <tr class="total"><td>Total</td><td class="r">{{ number_format($invoice->total, 2) }}</td></tr>
</table>
<p>Status: <b>{{ ucfirst($invoice->status) }}</b>@if($invoice->payment_ref) · Payment ref {{ $invoice->payment_ref }}@endif</p>
<div class="foot muted">This is a computer-generated invoice. Questions: {{ config('qronex.supplier.email') }}</div>
</body>
</html>

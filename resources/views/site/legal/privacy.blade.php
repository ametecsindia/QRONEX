<p>QRONEX is a QR-based attendance platform built and operated by Ametecs India Private Limited ("Ametecs", "we"), Hyderabad, India. This policy explains, plainly, what data we hold and what we do with it. It covers two things: your company's account with us (registration, subscription, billing) and the attendance data your employer captures with QRONEX.</p>

<h2>1. Company account data</h2>
<ul>
  <li><b>Account data</b> — company name, contact name, work email, phone, and passwords (stored hashed, never in plain text). Administrators can turn on two-factor authentication.</li>
  <li><b>Billing data</b> — subscriptions, quotations, GST invoices, your GSTIN, state and billing address. Card/UPI details are handled entirely by our payment gateway (Razorpay); we never see or store card numbers.</li>
  <li><b>Operational logs</b> — audit trails of portal and admin actions, and the transactional emails and SMS we sent you (verification, receipts, reminders).</li>
</ul>

<h2>2. Attendance data (held for your employer)</h2>
<p>Your employer decides who is enrolled and sets the attendance policy. For each employee, QRONEX stores the employee code, name and contact details the employer adds, approved devices, punch events (time, office, gate, location at punch time and its accuracy, accepted or rejected with a reason), remote-work permissions and corrections. Corrections are added as separate entries; history is never rewritten.</p>
<p>We host this data on the employer's behalf with tenant-separated access, and process it only to provide the service. The employer remains the owner and controller of the data. QRONEX is transparent and policy-driven — it is never covert tracking software. Questions about your employer's attendance policy should go to your employer.</p>

<h2>3. How we use and share data</h2>
<p>We use account and billing data to provide the service, issue GST-compliant invoices, send transactional messages and meet Indian tax and accounting obligations. We share data only with: our payment gateway (to process your payment), our infrastructure, email and SMS providers (to run the service), systems your employer connects through QRONEX APIs or webhooks (at the employer's instruction), and authorities where the law requires (e.g. GST records). We do not sell personal data, ever.</p>

<h2>4. Retention and deletion</h2>
<p>Attendance records are kept for the period your employer sets (see the <a href="{{ route('legal', 'retention') }}">Data Retention Policy</a>). Trial data of companies that do not move to a paid plan may be deleted 14 days after the trial ends. Active account data is kept while your subscription is live. Invoices and tax records are retained for 8 years as required by Indian law. You may ask us to delete your account data (except records we must keep) by writing to support@ametecsindia.com.</p>

<h2>5. Security</h2>
<p>Passwords are hashed, sign-in and APIs are rate-limited, all traffic runs over HTTPS, QR codes are signed and rotate every few seconds, punches from the app can be signed by a key held only on the approved phone, and every billing and admin action is audit-logged. API keys should be treated like passwords.</p>

<h2>6. Your rights and contact</h2>
<p>Company administrators can view and correct company, billing and GST details in the portal at any time. Employees should first contact their employer's administrator. For access, correction or deletion requests, or any privacy concern, contact our grievance contact: support@ametecsindia.com, Ametecs India Private Limited, Modern Profound Techpark, Ground Floor, Hive Space, opp. Google, Whitefields, Kondapur, Hyderabad, Telangana 500084. We answer within 7 working days. This policy is governed by Indian law, including the Digital Personal Data Protection Act, 2023.</p>

<h2>7. QRONEX mobile app</h2>
<p>The QRONEX app (Android, package com.ametecs.qronex) is the employee app for QRONEX attendance. It works only with an account your employer creates in QRONEX.</p>
<ul>
  <li><b>Camera</b> — used only while you scan the office QR code. The QR code's text is sent with your punch; no camera image or video is stored or sent.</li>
  <li><b>Precise location</b> — read once at the moment you punch IN or OUT, to confirm you are at your assigned office (or allowed to work remotely). It is never collected in the background or between punches. The location accuracy and whether the location was reported as simulated (mock location) are sent too, to prevent fake punches.</li>
  <li><b>Account and device</b> — your employee code and name, a device key created on the phone and the phone's model, used to approve this phone for your account and stop punches from other phones.</li>
  <li><b>Attendance activity</b> — your punch times, remote-work permissions and the attendance summary shown in the app.</li>
</ul>
<p>All app traffic is encrypted (HTTPS only). The data is not sold, not used for advertising, and not shared with third parties beyond section 3; the app contains no ads and no analytics trackers. Android may use Google Play services to work out the location fix on the phone. Your employer controls retention of attendance records; ask your employer's administrator, or write to support@ametecsindia.com, to access or delete your data. Signing out in the app or uninstalling it removes the app's data from the phone.</p>

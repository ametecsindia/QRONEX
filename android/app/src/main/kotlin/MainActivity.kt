package com.ametecs.qronex

import android.Manifest
import android.app.Activity
import android.app.AlertDialog
import android.app.Dialog
import android.content.Intent
import android.content.pm.PackageManager
import android.location.Location
import android.os.Bundle
import android.provider.Settings
import android.view.Gravity
import android.view.HapticFeedbackConstants
import android.view.View
import android.view.Window
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import com.google.zxing.integration.android.IntentIntegrator
import org.json.JSONArray
import org.json.JSONObject
import java.util.Date

class MainActivity : Activity() {
    lateinit var store: Store
    private lateinit var content: FrameLayout
    private lateinit var nav: LinearLayout
    var tab = "home"
    var me: JSONObject? = null
    var events = JSONArray()
    var requests: JSONObject? = null
    private var clock: TextView? = null

    // punch in progress
    private var remote = false
    private var qr: String? = null
    private var loc: Location? = null
    private var locDone = false
    private var waiting: Dialog? = null
    private var waitText: TextView? = null

    override fun onCreate(b: Bundle?) {
        super.onCreate(b)
        store = Store(this)
        if (store.token == null) { startActivity(Intent(this, ActivateActivity::class.java)); finish(); return }
        me = store.me

        content = FrameLayout(this)
        nav = hbox(Gravity.CENTER).apply { setBackgroundColor(C.WHITE); elevation = dp(12).toFloat(); setPadding(dp(8), dp(6), dp(8), dp(8)) }
        setContentView(vbox().apply {
            setBackgroundColor(C.BG)
            add(content, h = 0, weight = 1f)
            add(nav)
        })
        render()
        tick()
    }

    override fun onResume() {
        super.onResume()
        if (store.token != null) refresh()
    }

    private fun tick() {
        clock?.text = T.time(Date())
        later(15000) { if (!isFinishing) tick() }
    }

    fun refresh() {
        Api.go(store, "GET", "me") { r ->
            if (r.code == 401) return@go signedOut(r.message())
            if (r.ok) { me = r.json; store.me = r.json }
            Api.go(store, "GET", "history?days=30") { h ->
                if (h.ok) events = h.json.optJSONArray("events") ?: JSONArray()
                if (tab == "home" || tab == "history") render()
            }
            if (tab == "home") render()
        }
        if (tab == "requests") loadRequests()
    }

    fun loadRequests() = Api.go(store, "GET", "requests") { r -> if (r.ok) { requests = r.json; if (tab == "requests") render() } }

    fun signedOut(msg: String) {
        store.signOut()
        toast(if (msg.contains("not signed in")) "This phone was signed out. Activate it again." else msg)
        startActivity(Intent(this, ActivateActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK or Intent.FLAG_ACTIVITY_NEW_TASK))
        finish()
    }

    fun show(t: String) {
        tab = t
        if (t == "requests" && requests == null) loadRequests()
        render()
    }

    fun render() {
        clock = null
        val page: View = when (tab) {
            "history" -> historyTab()
            "requests" -> requestsTab()
            "profile" -> profileTab()
            else -> homeTab()
        }
        statusBar(if (tab == "home" || tab == "profile") C.NAVY else C.WHITE, tab == "home" || tab == "profile")
        content.removeAllViews()
        content.addView(page)
        page.alpha = 0f; page.translationY = dp(8).toFloat()
        page.animate().alpha(1f).translationY(0f).setDuration(220).start()
        renderNav()
    }

    private fun renderNav() {
        nav.removeAllViews()
        for ((key, label, ic) in listOf(Triple("home", "Home", R.drawable.ic_home), Triple("history", "Attendance", R.drawable.ic_clock), Triple("requests", "Requests", R.drawable.ic_requests), Triple("profile", "Profile", R.drawable.ic_profile))) {
            val on = key == tab
            nav.add(vbox(gravity = Gravity.CENTER).apply {
                setPadding(0, dp(4), 0, dp(2))
                add(FrameLayout(context).apply {
                    background = if (on) round(C.MINT, dp(99).toFloat()) else null
                    addView(icon(ic, if (on) C.TEAL else C.MUTED, 22), FrameLayout.LayoutParams(dp(22), dp(22), Gravity.CENTER))
                }, w = dp(56), h = dp(30))
                add(text(label, 11.5f, if (on) C.TEAL else C.MUTED, on, Gravity.CENTER), top = 3)
                isClickable = true
                background = ripple(android.graphics.drawable.ColorDrawable(0), 0x110E2747)
                setOnClickListener { if (!on) show(key) }
            }, w = 0, weight = 1f)
        }
    }

    /** Scrollable page with the standard background. */
    fun page(build: LinearLayout.() -> Unit): ScrollView = ScrollView(this).apply {
        isFillViewport = true; setBackgroundColor(C.BG)
        addView(vbox().apply(build), FrameLayout.LayoutParams(MATCH, WRAP))
    }

    /** Header for the white-topped tabs. */
    fun LinearLayout.titleBar(title: String, sub: String) {
        add(vbox().apply {
            setBackgroundColor(C.WHITE); setPadding(dp(22), dp(22), dp(22), dp(18))
            add(text(title, 26f, C.INK, true))
            add(text(sub, 14f, C.SLATE), top = 2)
        })
    }

    fun todayEvents(): List<JSONObject> {
        val today = T.day(Date())
        return (0 until events.length()).map { events.getJSONObject(it) }
            .filter { T.parse(it.optString("at"))?.let { d -> T.day(d) == today } == true }
            .sortedBy { it.optString("at") }
    }

    // ---------------- Home ----------------

    private fun homeTab() = page {
        val m = me
        val emp = m?.optJSONObject("employee")
        val name = emp?.optString("name") ?: ""
        val device = m?.optJSONObject("device")
        val next = m?.optString("next_type")?.ifBlank { null } ?: "IN"

        // Navy hero
        add(vbox().apply {
            background = android.graphics.drawable.GradientDrawable(android.graphics.drawable.GradientDrawable.Orientation.TL_BR, intArrayOf(C.NAVY2, C.NAVY)).apply {
                val r = dp(32).toFloat(); setCornerRadii(floatArrayOf(0f, 0f, 0f, 0f, r, r, r, r))
            }
            setPadding(dp(22), dp(20), dp(22), dp(64))
            add(hbox().apply {
                add(avatar(name, 46), w = dp(46), h = dp(46), end = 12)
                add(vbox().apply {
                    add(text(T.greeting() + ",", 13.5f, 0xB3FFFFFF.toInt()))
                    add(text(name.ifBlank { "Welcome" }.substringBefore(" "), 20f, C.WHITE, true))
                }, w = 0, weight = 1f)
                add(chip(m?.optJSONObject("company")?.optString("name") ?: "QRONEX", C.WHITE, 0x26FFFFFF).apply { maxWidth = dp(150); maxLines = 1; ellipsize = android.text.TextUtils.TruncateAt.END }, w = WRAP)
            })
            add(hbox(Gravity.BOTTOM).apply {
                add(text(T.time(Date()), 44f, C.WHITE, true).also { clock = it }, w = WRAP)
                add(text("  " + T.date(Date()), 15f, 0xB3FFFFFF.toInt()).apply { setPadding(0, 0, 0, dp(10)) }, w = WRAP)
            }, top = 22)
            val office = emp?.optString("office")?.takeIf { it.isNotBlank() && it != "null" }
            add(text(if (office != null) "Your office · $office" else "Scan the QR at your office entrance", 13.5f, 0xB3FFFFFF.toInt()), top = 2)
        })

        val body = vbox().apply { setPadding(dp(18), 0, dp(18), dp(28)) }
        add(body)

        // Today summary card overlapping the hero
        val today = todayEvents()
        val firstIn = today.firstOrNull { it.optString("type") == "IN" }
        val lastOut = today.lastOrNull()?.takeIf { it.optString("type") == "OUT" }
        body.add(card(16).apply {
            add(hbox().apply {
                for ((cap, value) in listOf(
                    "Check in" to (firstIn?.let { T.parse(it.optString("at"))?.let(T::time) } ?: "—"),
                    "Check out" to (lastOut?.let { T.parse(it.optString("at"))?.let(T::time) } ?: "—"),
                    "Worked" to (if (today.isEmpty()) "—" else T.duration(T.worked(today, true)))
                )) {
                    add(vbox(gravity = Gravity.CENTER_HORIZONTAL).apply {
                        add(label(cap)); add(text(value, 17f, C.NAVY, true, Gravity.CENTER), top = 4)
                    }, w = 0, weight = 1f)
                }
            })
        }, top = -44)

        if (device?.optString("status") == "pending") {
            body.add(pendingCard(device), top = 18)
        } else {
            val btn = PunchButton(this@MainActivity).apply {
                title = "Punch $next"
                subtitle = "Scan the entrance QR"
                isClickable = true
                setOnClickListener { performHapticFeedback(HapticFeedbackConstants.VIRTUAL_KEY); startPunch(false) }
            }
            body.add(btn, h = dp(290), top = 6)

            m?.optJSONObject("remote_permission")?.takeIf { it.optString("status") == "approved" }?.let { body.add(remoteCard(it, next), top = 4) }

            m?.optJSONObject("last_event")?.let { e ->
                body.add(card(16).apply {
                    add(hbox().apply {
                        val isIn = e.optString("type") == "IN"
                        add(text(if (isIn) "IN" else "OUT", 12f, C.WHITE, true, Gravity.CENTER).apply { background = round(if (isIn) C.TEAL else C.NAVY, dp(12).toFloat()) }, w = dp(46), h = dp(46), end = 14)
                        add(vbox().apply {
                            add(label("Last punch"))
                            val at = T.parse(e.optString("at"))
                            add(text((at?.let { T.dayLabel(it) + " · " + T.time(it) } ?: ""), 16f, C.INK, true), top = 2)
                            add(text(listOf(e.optString("office"), e.optString("gate")).filter { it.isNotBlank() && it != "null" }.joinToString(" · ").ifBlank { "QRONEX" }, 13f, C.SLATE), top = 1)
                        }, w = 0, weight = 1f)
                        if (e.optBoolean("adjusted")) add(chip("Adjusted", C.AMBER, C.AMBER_SOFT), w = WRAP)
                    })
                }, top = 12)
            }
        }

        m?.optJSONObject("policy")?.optString("location_notice")?.takeIf { it.isNotBlank() && it != "null" }?.let {
            body.add(hbox(Gravity.TOP).apply {
                add(icon(R.drawable.ic_shield, C.TEAL, 16), w = dp(16), h = dp(16), top = 2, end = 8)
                add(text(it, 12.5f, C.SLATE), w = 0, weight = 1f)
            }, top = 18)
        }
    }

    private fun pendingCard(device: JSONObject) = card(22).apply {
        setGravity(Gravity.CENTER_HORIZONTAL)
        add(Dots(this@MainActivity, C.TEAL), w = dp(60), h = dp(16))
        add(text("Waiting for approval", 20f, C.INK, true, Gravity.CENTER), top = 14)
        add(text("Your administrator needs to approve this phone before you can punch. Give them this code:", 14f, C.SLATE, gravity = Gravity.CENTER), top = 6)
        add(text(device.optString("approval_code").ifBlank { "—" }, 34f, C.NAVY, true, Gravity.CENTER).apply {
            letterSpacing = 0.18f; background = round(C.MINT, dp(16).toFloat()); setPadding(dp(20), dp(12), dp(20), dp(12))
        }, w = WRAP, top = 16)
        add(button("Check again", "outline") { refresh(); toast("Checking…") }, top = 18)
    }

    private fun remoteCard(p: JSONObject, next: String) = card(18).apply {
        background = gradient(C.MINT, C.WHITE, dp(20).toFloat())
        add(hbox().apply {
            add(chip("Approved", C.GREEN, C.GREEN_SOFT), w = WRAP, end = 8)
            add(text(p.optString("type_label"), 15f, C.INK, true), w = 0, weight = 1f)
        })
        val dates = if (p.optString("from") == p.optString("to")) T.short(p.optString("from")) else "${T.short(p.optString("from"))} – ${T.short(p.optString("to"))}"
        val where = if (p.optBoolean("location_waived")) "Any location" else p.optJSONObject("location")?.optString("label")?.takeIf { it.isNotBlank() && it != "null" } ?: "Approved location"
        add(text("$dates · $where", 13.5f, C.SLATE), top = 6)
        add(button("Check $next remotely", "navy") { startPunch(true) }, top = 14)
    }

    // ---------------- Punch flow ----------------

    private fun startPunch(isRemote: Boolean) {
        remote = isRemote
        val need = listOfNotNull(Manifest.permission.ACCESS_FINE_LOCATION, if (isRemote) null else Manifest.permission.CAMERA)
            .filter { checkSelfPermission(it) != PackageManager.PERMISSION_GRANTED }
        if (need.isNotEmpty()) return requestPermissions(need.toTypedArray(), 7)
        if (!Loc.enabled(this)) {
            AlertDialog.Builder(this).setTitle("Turn on location")
                .setMessage("QRONEX checks you are at the office when you punch. Turn on location, then try again.")
                .setPositiveButton("Open settings") { _, _ -> startActivity(Intent(Settings.ACTION_LOCATION_SOURCE_SETTINGS)) }
                .setNegativeButton("Cancel", null).show()
            return
        }
        qr = null; loc = null; locDone = false
        Loc.once(this) { l -> loc = l; locDone = true; submitIfReady() }
        if (isRemote) {
            waitScreen("Getting your location")
        } else {
            IntentIntegrator(this).apply {
                setDesiredBarcodeFormats(IntentIntegrator.QR_CODE)
                setPrompt("Point at the QRONEX code on the entrance screen")
                setBeepEnabled(false)
                setOrientationLocked(false)
            }.initiateScan()
        }
    }

    override fun onRequestPermissionsResult(code: Int, perms: Array<out String>, res: IntArray) {
        if (code != 7) return
        if (res.isNotEmpty() && res.all { it == PackageManager.PERMISSION_GRANTED }) startPunch(remote)
        else AlertDialog.Builder(this).setTitle("Permission needed")
            .setMessage("QRONEX needs the camera to read the QR code and your location to confirm you are at the office. Location is read only when you punch.")
            .setPositiveButton("App settings") { _, _ -> startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, android.net.Uri.parse("package:$packageName"))) }
            .setNegativeButton("Not now", null).show()
    }

    @Deprecated("Activity result for the ZXing scanner")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        if (requestCode == 9) { if (resultCode == RESULT_FIRST_USER) startPunch(remote); return }
        val r = IntentIntegrator.parseActivityResult(requestCode, resultCode, data)
        if (r == null) return super.onActivityResult(requestCode, resultCode, data)
        val contents = r.contents ?: return // cancelled
        qr = contents
        waitScreen(if (locDone) "Verifying with QRONEX" else "Getting your location")
        submitIfReady()
    }

    private fun waitScreen(msg: String) {
        if (waiting == null) {
            waiting = Dialog(this, android.R.style.Theme_DeviceDefault_NoActionBar_Fullscreen).apply {
                requestWindowFeature(Window.FEATURE_NO_TITLE)
                setCancelable(false)
                setContentView(vbox(32, Gravity.CENTER).apply {
                    background = gradient(C.NAVY2, C.NAVY)
                    add(PunchButton(this@MainActivity).apply { busy = true; subtitle = "Please hold still" }, w = dp(240), h = dp(240))
                    add(text("", 18f, C.WHITE, true, Gravity.CENTER).also { waitText = it }, top = 8)
                    add(Dots(this@MainActivity), w = dp(60), h = dp(14), top = 12)
                    add(text("Checking the QR, your approved phone and the office location.", 13.5f, 0xB3FFFFFF.toInt(), gravity = Gravity.CENTER), top = 18)
                })
            }
            waiting?.show()
        }
        waitText?.text = msg
    }

    private fun submitIfReady() {
        if (!locDone || (!remote && qr == null)) return
        val l = loc
        if (l == null) {
            waiting?.dismiss(); waiting = null
            return showResult(JSONObject().put("ok", false).put("reason_code", "NO_LOCATION")
                .put("reason", "We couldn't get your location. Step near a window or outside and try again."))
        }
        waitText?.text = "Verifying with QRONEX"
        val body = Loc.json(l)
        if (!remote) body.put("qr", qr)
        Api.go(store, "POST", if (remote) "remote-scan" else "scan", body) { r ->
            waiting?.dismiss(); waiting = null
            if (r.code == 401) return@go signedOut(r.message())
            val j = if (r.json.has("ok")) r.json else JSONObject().put("ok", false).put("reason_code", r.json.optString("reason_code", "ERROR")).put("reason", r.message())
            showResult(j)
        }
    }

    private fun showResult(j: JSONObject) {
        startActivityForResult(Intent(this, ResultActivity::class.java).putExtra("json", j.toString()).putExtra("remote", remote), 9)
        overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out)
    }
}

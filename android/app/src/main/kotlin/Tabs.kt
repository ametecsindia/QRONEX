package com.ametecs.qronex

import android.app.AlertDialog
import android.app.DatePickerDialog
import android.app.Dialog
import android.app.TimePickerDialog
import android.content.Intent
import android.net.Uri
import android.provider.Settings
import android.text.InputType
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.widget.CheckBox
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import org.json.JSONObject
import java.util.Calendar
import java.util.Date
import java.util.Locale

// ---------------- Attendance (history) ----------------

fun MainActivity.historyTab() = page {
    titleBar("My attendance", "Last 30 days")
    val body = vbox().apply { setPadding(dp(18), dp(16), dp(18), dp(28)) }
    add(body)

    val all = (0 until events.length()).map { events.getJSONObject(it) }
    val byDay = all.groupBy { T.parse(it.optString("at"))?.let(T::day) ?: "" }.filterKeys { it.isNotBlank() }.toSortedMap(compareByDescending { it })
    val today = T.day(Date())

    // Summary
    val totalMs = byDay.entries.sumOf { (d, ev) -> T.worked(ev.sortedBy { it.optString("at") }, d == today) }
    body.add(hbox().apply {
        for ((cap, v, accent) in listOf(
            Triple("Days present", byDay.size.toString(), C.TEAL),
            Triple("Hours", if (byDay.isEmpty()) "0h" else T.duration(totalMs).substringBefore(" "), C.NAVY),
            Triple("Punches", all.size.toString(), C.NAVY2)
        )) {
            add(card(14).apply {
                add(text(v, 24f, accent, true)); add(label(cap), top = 2)
            }, w = 0, weight = 1f, end = if (cap == "Punches") 0 else 10)
        }
    })

    if (byDay.isEmpty()) {
        body.add(empty(R.drawable.ic_clock, "No punches yet", "Your check-ins and check-outs will show up here."), top = 18)
        return@page
    }

    for ((d, list) in byDay) {
        val sorted = list.sortedBy { it.optString("at") }
        val date = T.ymd(d) ?: continue
        body.add(hbox().apply {
            add(text(T.dayLabel(date), 15f, C.INK, true), w = 0, weight = 1f)
            add(text(T.duration(T.worked(sorted, d == today)), 14f, C.TEAL, true), w = WRAP)
        }, top = 20, bottom = 8, start = 4, end = 4)
        body.add(card(6).apply {
            sorted.forEachIndexed { i, e ->
                if (i > 0) add(View(context).apply { setBackgroundColor(C.LINE) }, h = dp(1), start = 60, end = 12)
                add(eventRow(e))
            }
        })
    }
}

private fun MainActivity.eventRow(e: JSONObject) = hbox().apply {
    setPadding(dp(12), dp(12), dp(12), dp(12))
    val isIn = e.optString("type") == "IN"
    add(text(if (isIn) "IN" else "OUT", 11.5f, if (isIn) C.TEAL else C.NAVY, true, Gravity.CENTER).apply {
        background = round(if (isIn) C.MINT else C.BG, dp(12).toFloat())
    }, w = dp(40), h = dp(40), end = 12)
    add(vbox().apply {
        add(text(T.parse(e.optString("at"))?.let(T::time) ?: "", 16f, C.INK, true))
        val where = listOf(e.optString("office"), e.optString("gate")).filter { it.isNotBlank() && it != "null" }.joinToString(" · ")
        add(text(where.ifBlank { modeLabel(e.optString("mode")) }, 13f, C.SLATE), top = 1)
    }, w = 0, weight = 1f)
    if (e.optBoolean("adjusted")) add(chip("Adjusted", C.AMBER, C.AMBER_SOFT), w = WRAP)
    else if (e.optString("mode").contains("APPROVED")) add(chip("Remote", C.NAVY2, C.BG), w = WRAP)
}

private fun modeLabel(m: String) = when {
    m.startsWith("WFH") -> "Work from home"
    m.startsWith("FIELD") -> "Field duty"
    m.startsWith("CLIENT") -> "Client site"
    m.contains("MANUAL") -> "Corrected by admin"
    else -> "QR punch"
}

fun MainActivity.empty(ic: Int, title: String, sub: String) = card(28).apply {
    setGravity(Gravity.CENTER_HORIZONTAL)
    add(LinearLayout(context).apply {
        setGravity(Gravity.CENTER); background = round(C.MINT, dp(99).toFloat())
        add(icon(ic, C.TEAL, 26), w = dp(26), h = dp(26))
    }, w = dp(60), h = dp(60))
    add(text(title, 17f, C.INK, true, Gravity.CENTER), top = 14)
    add(text(sub, 14f, C.SLATE, gravity = Gravity.CENTER), top = 4)
}

// ---------------- Requests ----------------

fun MainActivity.requestsTab() = page {
    titleBar("Requests", "Remote work and missed punches")
    val body = vbox().apply { setPadding(dp(18), dp(16), dp(18), dp(28)) }
    add(body)

    body.add(hbox().apply {
        add(actionTile("Work remotely", "WFH, field duty or a client site", C.TEAL) { remoteForm() }, w = 0, weight = 1f, end = 10)
        add(actionTile("Missed a punch", "Ask to fix a check-in or out", C.NAVY2) { correctionForm() }, w = 0, weight = 1f)
    })

    val r = requests
    if (r == null) {
        body.add(Dots(this@requestsTab, C.TEAL), w = MATCH, h = dp(16), top = 40)
        return@page
    }
    val remote = r.optJSONArray("remote")
    val fixes = r.optJSONArray("corrections")
    if ((remote?.length() ?: 0) + (fixes?.length() ?: 0) == 0) {
        body.add(empty(R.drawable.ic_requests, "No requests yet", "Requests from the last 60 days appear here with their status."), top = 18)
        return@page
    }
    if ((remote?.length() ?: 0) > 0) {
        body.add(label("Remote work"), top = 22, bottom = 8, start = 4)
        body.add(card(6).apply {
            for (i in 0 until remote!!.length()) {
                val p = remote.getJSONObject(i)
                if (i > 0) add(View(context).apply { setBackgroundColor(C.LINE) }, h = dp(1), start = 12, end = 12)
                val dates = if (p.optString("from") == p.optString("to")) T.short(p.optString("from")) else "${T.short(p.optString("from"))} – ${T.short(p.optString("to"))}"
                add(requestRow(p.optString("type_label"), "$dates · ${p.optString("reason")}", p.optString("status")))
            }
        })
    }
    if ((fixes?.length() ?: 0) > 0) {
        body.add(label("Punch corrections"), top = 22, bottom = 8, start = 4)
        body.add(card(6).apply {
            for (i in 0 until fixes!!.length()) {
                val c = fixes.getJSONObject(i)
                if (i > 0) add(View(context).apply { setBackgroundColor(C.LINE) }, h = dp(1), start = 12, end = 12)
                val at = T.parse(c.optString("at"))
                add(requestRow("Missed ${c.optString("type")}", (at?.let { T.date(it) + ", " + T.time(it) } ?: "") + " · " + c.optString("reason"), c.optString("status")))
            }
        })
    }
}

private fun MainActivity.actionTile(title: String, sub: String, accent: Int, go: () -> Unit) = vbox(16).apply {
    background = ripple(gradient(accent, C.NAVY, dp(20).toFloat()), 0x33FFFFFF)
    isClickable = true; setOnClickListener { go() }
    add(text("+", 22f, C.WHITE, true, Gravity.CENTER).apply { background = round(0x26FFFFFF, dp(99).toFloat()) }, w = dp(36), h = dp(36))
    add(text(title, 16f, C.WHITE, true), top = 14)
    add(text(sub, 12.5f, 0xCCFFFFFF.toInt()), top = 2)
}

private fun MainActivity.requestRow(title: String, sub: String, status: String) = hbox(Gravity.TOP).apply {
    setPadding(dp(12), dp(12), dp(12), dp(12))
    add(vbox().apply {
        add(text(title, 15f, C.INK, true))
        add(text(sub, 13f, C.SLATE).apply { maxLines = 2; ellipsize = android.text.TextUtils.TruncateAt.END }, top = 2)
    }, w = 0, weight = 1f, end = 10)
    add(statusChip(status), w = WRAP)
}

/** Bottom sheet built from a Dialog: rounded top, slides up, keyboard-friendly. */
private fun MainActivity.sheet(title: String, build: LinearLayout.(Dialog) -> Unit) {
    val d = Dialog(this)
    d.requestWindowFeature(android.view.Window.FEATURE_NO_TITLE)
    val box = vbox(22).apply {
        background = android.graphics.drawable.GradientDrawable().apply {
            setColor(C.WHITE); val r = dp(26).toFloat(); setCornerRadii(floatArrayOf(r, r, r, r, 0f, 0f, 0f, 0f))
        }
        add(View(context).apply { background = round(C.LINE, dp(4).toFloat()) }, w = dp(40), h = dp(4), bottom = 14).let { (it.layoutParams as LinearLayout.LayoutParams).gravity = Gravity.CENTER_HORIZONTAL }
        add(text(title, 20f, C.INK, true))
        build(d)
    }
    d.setContentView(ScrollView(this).apply { addView(box) })
    d.window?.apply {
        setBackgroundDrawable(android.graphics.drawable.ColorDrawable(0))
        setLayout(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
        setGravity(Gravity.BOTTOM)
        setWindowAnimations(android.R.style.Animation_InputMethod)
        setSoftInputMode(android.view.WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE)
    }
    d.show()
}

/** Tappable field that opens a date or time picker. */
private fun MainActivity.pickerField(value: String, pick: (TextView) -> Unit) = text(value, 16f, C.INK).apply {
    background = round(C.BG, dp(14).toFloat(), C.LINE, dp(1)); setPadding(dp(16), dp(15), dp(16), dp(15))
    isClickable = true; setOnClickListener { pick(this) }
}

private fun ymd(c: Calendar) = String.format(Locale.ENGLISH, "%04d-%02d-%02d", c.get(Calendar.YEAR), c.get(Calendar.MONTH) + 1, c.get(Calendar.DAY_OF_MONTH))

private fun MainActivity.datePicker(tv: TextView, minToday: Boolean) {
    val c = Calendar.getInstance().apply { T.ymd(tv.tag as String)?.let { time = it } }
    DatePickerDialog(this, { _, y, m, d ->
        c.set(y, m, d); tv.tag = ymd(c); tv.text = T.date(c.time)
    }, c.get(Calendar.YEAR), c.get(Calendar.MONTH), c.get(Calendar.DAY_OF_MONTH)).apply {
        if (minToday) datePicker.minDate = System.currentTimeMillis() - 1000
        else { datePicker.maxDate = System.currentTimeMillis(); datePicker.minDate = System.currentTimeMillis() - 30L * 86_400_000 }
    }.show()
}

private fun MainActivity.submitRow(d: Dialog, err: TextView, label: String, send: (done: (Res) -> Unit) -> Unit): TextView {
    lateinit var b: TextView
    b = button(label) {
        b.isEnabled = false; b.text = "Sending…"; err.visibility = View.GONE
        send { r ->
            b.isEnabled = true; b.text = label
            if (r.code == 401) return@send signedOut(r.message())
            if (r.ok) { d.dismiss(); toast("Request sent. You'll see the status here."); loadRequests() }
            else { err.text = r.message(); err.visibility = View.VISIBLE }
        }
    }
    return b
}

private fun MainActivity.errBox() = text("", 14f, C.RED).apply {
    visibility = View.GONE; background = round(C.RED_SOFT, dp(12).toFloat()); setPadding(dp(14), dp(10), dp(14), dp(10))
}

private fun MainActivity.remoteForm() = sheet("Ask to work remotely") { d ->
    val types = listOf("wfh" to "Work from home", "field_duty" to "Field duty", "client_site" to "Client site", "temporary_remote" to "Temporary remote")
    var type = "wfh"
    val chips = LinearLayout(context).apply { orientation = LinearLayout.VERTICAL }
    fun drawChips() {
        chips.removeAllViews()
        types.chunked(2).forEach { pair ->
            chips.add(hbox().apply {
                pair.forEachIndexed { i, (k, v) ->
                    val on = k == type
                    add(text(v, 14f, if (on) C.WHITE else C.NAVY, true, Gravity.CENTER).apply {
                        background = if (on) round(C.TEAL, dp(12).toFloat()) else round(C.WHITE, dp(12).toFloat(), C.LINE, dp(1))
                        setPadding(0, dp(12), 0, dp(12)); isClickable = true; setOnClickListener { type = k; drawChips() }
                    }, w = 0, weight = 1f, end = if (i == 0) 8 else 0)
                }
            }, top = 8)
        }
    }
    drawChips()
    add(text("What kind?", 13f, C.SLATE, true), top = 16); add(chips)

    val now = Calendar.getInstance()
    val from = pickerField(T.date(now.time)) { datePicker(it, true) }.apply { tag = ymd(now) }
    val to = pickerField(T.date(now.time)) { datePicker(it, true) }.apply { tag = ymd(now) }
    add(hbox().apply {
        add(field("From", from), w = 0, weight = 1f, end = 10)
        add(field("To", to), w = 0, weight = 1f)
    }, top = 14)
    val reason = input("e.g. Visiting the Hitech City client", InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_CAP_SENTENCES or InputType.TYPE_TEXT_FLAG_MULTI_LINE)
    add(field("Reason", reason), top = 14)
    val here = CheckBox(context).apply {
        text = "Use where I am now as the approved place"; setTextColor(C.INK); typeface = font(false); buttonTintList = android.content.res.ColorStateList.valueOf(C.TEAL)
    }
    add(here, top = 10)
    val err = errBox(); add(err, top = 10)
    add(submitRow(d, err, "Send request") { done ->
        val body = JSONObject().put("type", type).put("from", from.tag).put("to", to.tag).put("reason", reason.text.toString().trim())
        val send = { Api.go(store, "POST", "remote-requests", body, done) }
        if (here.isChecked && checkSelfPermission(android.Manifest.permission.ACCESS_FINE_LOCATION) == android.content.pm.PackageManager.PERMISSION_GRANTED) {
            Loc.once(this@remoteForm) { l -> l?.let { body.put("lat", it.latitude).put("lng", it.longitude) }; send() }
        } else {
            if (here.isChecked) requestPermissions(arrayOf(android.Manifest.permission.ACCESS_FINE_LOCATION), 8)
            send()
        }
    }, top = 6)
}

private fun MainActivity.correctionForm() = sheet("Fix a missed punch") { d ->
    var type = "IN"
    val seg = hbox()
    fun drawSeg() {
        seg.removeAllViews()
        listOf("IN" to "Missed check-in", "OUT" to "Missed check-out").forEachIndexed { i, (k, v) ->
            val on = k == type
            seg.add(text(v, 14f, if (on) C.WHITE else C.NAVY, true, Gravity.CENTER).apply {
                background = if (on) round(C.NAVY, dp(12).toFloat()) else round(C.WHITE, dp(12).toFloat(), C.LINE, dp(1))
                setPadding(0, dp(12), 0, dp(12)); isClickable = true; setOnClickListener { type = k; drawSeg() }
            }, w = 0, weight = 1f, end = if (i == 0) 8 else 0)
        }
    }
    drawSeg()
    add(seg, top = 16)

    val now = Calendar.getInstance()
    val date = pickerField(T.date(now.time)) { datePicker(it, false) }.apply { tag = ymd(now) }
    var hh = 9; var mm = 30
    val time = pickerField("9:30 AM") { tv ->
        TimePickerDialog(this@correctionForm, { _, h, m ->
            hh = h; mm = m
            tv.text = T.time(Calendar.getInstance().apply { set(Calendar.HOUR_OF_DAY, h); set(Calendar.MINUTE, m) }.time)
        }, hh, mm, false).show()
    }
    add(hbox().apply {
        add(field("Date", date), w = 0, weight = 1f, end = 10)
        add(field("Time", time), w = 0, weight = 1f)
    }, top = 14)
    val reason = input("e.g. Phone battery was dead", InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_CAP_SENTENCES or InputType.TYPE_TEXT_FLAG_MULTI_LINE)
    add(field("Reason", reason), top = 14)
    val err = errBox(); add(err, top = 10)
    add(submitRow(d, err, "Send request") { done ->
        Api.go(store, "POST", "corrections", JSONObject().put("type", type).put("date", date.tag).put("time", String.format(Locale.ENGLISH, "%02d:%02d", hh, mm)).put("reason", reason.text.toString().trim()), done)
    }, top = 6)
}

// ---------------- Profile ----------------

fun MainActivity.profileTab() = page {
    val m = me
    val emp = m?.optJSONObject("employee")
    val dev = m?.optJSONObject("device")
    val name = emp?.optString("name") ?: ""

    add(vbox(gravity = Gravity.CENTER_HORIZONTAL).apply {
        background = android.graphics.drawable.GradientDrawable(android.graphics.drawable.GradientDrawable.Orientation.TL_BR, intArrayOf(C.NAVY2, C.NAVY)).apply {
            val r = dp(32).toFloat(); setCornerRadii(floatArrayOf(0f, 0f, 0f, 0f, r, r, r, r))
        }
        setPadding(dp(22), dp(28), dp(22), dp(28))
        add(avatar(name, 76), w = dp(76), h = dp(76))
        add(text(name, 22f, C.WHITE, true, Gravity.CENTER), top = 12)
        add(text(listOfNotNull(emp?.optString("code"), emp?.optString("office")?.takeIf { it.isNotBlank() && it != "null" }).joinToString(" · "), 14f, 0xB3FFFFFF.toInt(), gravity = Gravity.CENTER), top = 2)
        add(chip(m?.optJSONObject("company")?.optString("name") ?: "", C.WHITE, 0x26FFFFFF), w = WRAP, top = 10)
    })

    val body = vbox().apply { setPadding(dp(18), dp(18), dp(18), dp(28)) }
    add(body)

    body.add(label("This phone"), bottom = 8, start = 4)
    body.add(card(16).apply {
        fun row(k: String, v: View) = add(hbox().apply {
            add(text(k, 14f, C.SLATE), w = 0, weight = 1f); add(v, w = WRAP)
        }, top = if (childCount == 0) 0 else 12)
        row("Model", text(dev?.optString("model")?.takeIf { it != "null" } ?: "", 14f, C.INK, true))
        row("Status", statusChip(dev?.optString("status") ?: ""))
        T.parse(dev?.optString("registered_at"))?.let { row("Registered", text(T.date(it), 14f, C.INK, true)) }
        row("Security key", if (Keys.has()) chip("Protected", C.GREEN, C.GREEN_SOFT) else chip("Not available", C.AMBER, C.AMBER_SOFT))
    })
    body.add(hbox(Gravity.TOP).apply {
        add(icon(R.drawable.ic_shield, C.TEAL, 16), w = dp(16), h = dp(16), top = 2, end = 8)
        add(text("Every punch is signed by a key that never leaves this phone, so your sign-in can't be reused on another device.", 12.5f, C.SLATE), w = 0, weight = 1f)
    }, top = 10, start = 4, end = 4)

    body.add(label("Settings"), top = 22, bottom = 8, start = 4)
    body.add(card(6).apply {
        fun item(t: String, sub: String, color: Int = C.INK, go: () -> Unit) {
            if (childCount > 0) add(View(context).apply { setBackgroundColor(C.LINE) }, h = dp(1), start = 12, end = 12)
            add(vbox().apply {
                setPadding(dp(12), dp(13), dp(12), dp(13)); isClickable = true
                background = ripple(android.graphics.drawable.ColorDrawable(0), 0x110E2747)
                setOnClickListener { go() }
                add(text(t, 15f, color, true)); add(text(sub, 12.5f, C.SLATE), top = 1)
            })
        }
        item("Camera & location access", "Open app permissions") {
            startActivity(Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.parse("package:$packageName")))
        }
        item("Refresh", "Get the latest from your company") { refresh(); toast("Updated") }
        item("Sign out of this phone", "You'll need a new activation code", C.RED) {
            AlertDialog.Builder(this@profileTab).setTitle("Sign out?")
                .setMessage("To punch again on this phone you will need a new activation code from your administrator.")
                .setPositiveButton("Sign out") { _, _ -> signedOut("Signed out.") }
                .setNegativeButton("Cancel", null).show()
        }
    })
    body.add(text("QRONEX ${BuildConfig.VERSION_NAME} · ${store.server.removePrefix("https://")}\nAmetecs India Private Limited", 12f, C.MUTED, gravity = Gravity.CENTER), top = 22)
}

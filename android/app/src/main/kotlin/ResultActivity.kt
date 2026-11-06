package com.ametecs.qronex

import android.app.Activity
import android.os.Bundle
import android.view.Gravity
import android.view.View
import android.widget.LinearLayout
import android.widget.ScrollView
import org.json.JSONObject

/** Full-screen punch result: animated tick/cross, the time, where, and the server's own checklist revealed line by line. */
class ResultActivity : Activity() {
    private val hints = mapOf(
        "OUTSIDE_GEOFENCE" to "Move closer to the office entrance and scan again.",
        "LOW_GPS_ACCURACY" to "Step near a window or outside for a better GPS fix, then try again.",
        "QR_EXPIRED" to "The code changes every few seconds. Scan the screen again.",
        "QR_INVALID" to "Scan the live QRONEX code on the entrance screen, not a photo of it.",
        "DUPLICATE" to "Your earlier punch is already recorded.",
        "DEVICE_NOT_APPROVED" to "Your administrator has to approve this phone first.",
        "OFFICE_NOT_ASSIGNED" to "You can only punch at the office you are assigned to.",
        "MOCK_LOCATION" to "Turn off any location-faking app and try again.",
        "NO_REMOTE_PERMISSION" to "Ask for remote or field duty from the Requests tab.",
        "OUTSIDE_PERMISSION_DATES" to "Your remote permission doesn't cover today.",
        "OUTSIDE_APPROVED_LOCATION" to "Go to the location your administrator approved and try again.",
        "NO_LOCATION" to "Make sure location is on and QRONEX is allowed to use it.",
        "signature_stale" to "Turn on automatic date & time in your phone settings.",
    )

    override fun onCreate(b: Bundle?) {
        super.onCreate(b)
        val j = runCatching { JSONObject(intent.getStringExtra("json") ?: "{}") }.getOrElse { JSONObject() }
        val ok = j.optBoolean("ok")
        val e = j.optJSONObject("event")
        val bgTop = if (ok) 0xFF0FA394.toInt() else 0xFFE0484D.toInt()
        val bgBottom = if (ok) C.NAVY else 0xFF7F1D1D.toInt()
        window.statusBarColor = bgTop
        window.navigationBarColor = bgBottom

        val root = vbox(24, Gravity.CENTER_HORIZONTAL).apply {
            background = gradient(bgTop, bgBottom, o = android.graphics.drawable.GradientDrawable.Orientation.TOP_BOTTOM)
            setPadding(dp(24), dp(48), dp(24), dp(28))
        }
        val mark = ResultMark(this, ok)
        root.add(mark, w = dp(120), h = dp(120))

        if (ok && e != null) {
            val type = e.optString("type")
            root.add(text(if (type == "IN") "You're checked in" else "You're checked out", 26f, C.WHITE, true, Gravity.CENTER), top = 22)
            root.add(text(T.parse(e.optString("at"))?.let(T::time) ?: "", 52f, C.WHITE, true, Gravity.CENTER), top = 2)
            root.add(text(T.date(java.util.Date()), 15f, 0xCCFFFFFF.toInt(), gravity = Gravity.CENTER))
        } else {
            root.add(text("Punch not accepted", 26f, C.WHITE, true, Gravity.CENTER), top = 22)
            root.add(text(j.optString("reason").ifBlank { "Something went wrong. Please try again." }, 15.5f, 0xEEFFFFFF.toInt(), gravity = Gravity.CENTER), top = 8)
            hints[j.optString("reason_code")]?.let {
                root.add(text(it, 14.5f, C.WHITE, true, Gravity.CENTER).apply { background = round(0x26FFFFFF, dp(14).toFloat()); setPadding(dp(16), dp(12), dp(16), dp(12)) }, top = 16)
            }
        }

        // Details
        val info = vbox(16).apply { background = round(0x1AFFFFFF, dp(20).toFloat(), 0x33FFFFFF, dp(1)) }
        fun row(k: String, v: String?) {
            if (v.isNullOrBlank() || v == "null") return
            info.add(hbox().apply {
                add(text(k, 13.5f, 0xB3FFFFFF.toInt()), w = 0, weight = 1f)
                add(text(v, 14f, C.WHITE, true, Gravity.END), w = WRAP)
            }, top = if (info.childCount == 0) 0 else 10)
        }
        if (e != null) {
            row(if (intent.getBooleanExtra("remote", false)) "Remote" else "Office", e.optString("office"))
            row("Gate", e.optString("gate"))
            if (!e.isNull("distance_m")) row("Distance", "${e.optInt("distance_m")} m from the QR")
            row("Reference", e.optString("ref"))
        } else row("Reference", j.optString("ref"))
        if (info.childCount > 0) root.add(info, top = 24)

        // Server checks, revealed one by one
        val checks = j.optJSONArray("checks")
        if (checks != null && checks.length() > 0) {
            root.add(label("Security checks", 0xB3FFFFFF.toInt()), top = 22, start = 4)
            val list = vbox(14).apply { background = round(0x1AFFFFFF, dp(20).toFloat(), 0x33FFFFFF, dp(1)) }
            root.add(list, top = 8)
            for (i in 0 until checks.length()) {
                val c = checks.getJSONObject(i)
                val st = c.optString("status")
                val row = hbox(Gravity.TOP).apply {
                    add(text(when (st) { "ok" -> "✓"; "bad" -> "✕"; else -> "–" }, 12f, if (st == "ok") C.TEAL else if (st == "bad") C.RED else C.SLATE, true, Gravity.CENTER).apply {
                        background = round(if (st == "skip") 0x66FFFFFF else C.WHITE, dp(99).toFloat())
                    }, w = dp(20), h = dp(20), top = 1, end = 10)
                    add(vbox().apply {
                        add(text(c.optString("label"), 14f, C.WHITE, st != "skip"))
                        if (st == "bad") add(text(c.optString("detail"), 12.5f, 0xE6FFFFFF.toInt()), top = 1)
                    }, w = 0, weight = 1f)
                    alpha = 0f; translationX = dp(-12).toFloat()
                }
                list.add(row, top = if (i == 0) 0 else 9)
                row.animate().alpha(if (st == "skip") 0.55f else 1f).translationX(0f).setStartDelay(650L + i * 90L).setDuration(260).start()
            }
        }

        val actions = vbox()
        if (!ok && j.optString("reason_code") !in listOf("DUPLICATE", "DEVICE_NOT_APPROVED", "NO_REMOTE_PERMISSION")) {
            actions.add(button("Try again", "light") { setResult(RESULT_FIRST_USER); finish() })
        }
        actions.add(button("Done", "outline") { finish() }, top = 10)
        root.add(View(this), h = 0, weight = 1f)
        root.add(actions, top = 26)

        setContentView(ScrollView(this).apply {
            isFillViewport = true
            addView(root, LinearLayout.LayoutParams(MATCH, WRAP))
        })
        mark.post {
            mark.play()
            mark.performHapticFeedback(if (ok) 16 else 17) // HapticFeedbackConstants.CONFIRM / REJECT (API 30; older phones ignore)
        }
        if (ok) later(15000) { if (!isFinishing) finish() }
    }
}

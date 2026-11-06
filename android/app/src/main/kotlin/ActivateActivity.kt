package com.ametecs.qronex

import android.app.Activity
import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.text.InputType
import android.view.Gravity
import android.widget.LinearLayout
import android.widget.ScrollView
import org.json.JSONObject

/** First run: link this phone to an employee with the one-time code from the company portal. */
class ActivateActivity : Activity() {
    private lateinit var store: Store

    override fun onCreate(b: Bundle?) {
        super.onCreate(b)
        store = Store(this)
        statusBar(C.NAVY, true)

        val root = vbox().apply { background = gradient(C.NAVY, 0xFF0B1E38.toInt(), o = android.graphics.drawable.GradientDrawable.Orientation.TOP_BOTTOM) }

        // Hero
        val hero = vbox(28).apply { setPadding(dp(28), dp(36), dp(28), dp(30)) }
        hero.add(hbox().apply {
            add(icon(R.drawable.ic_qr, C.TEAL2, 30), w = dp(30), h = dp(30), end = 10)
            add(text("QRONEX", 22f, C.WHITE, true).apply { letterSpacing = 0.06f }, w = WRAP)
        })
        hero.add(text("Attendance in one scan.", 30f, C.WHITE, true), top = 28)
        hero.add(text("Enter the details your company sent you by SMS or email to set up this phone.", 15f, 0xCCFFFFFF.toInt()), top = 10)
        root.add(hero)

        // Form sheet
        val sheet = vbox(24).apply {
            background = android.graphics.drawable.GradientDrawable().apply {
                setColor(C.WHITE); val r = dp(28).toFloat(); setCornerRadii(floatArrayOf(r, r, r, r, 0f, 0f, 0f, 0f))
            }
        }
        val company = input("e.g. storm-financial", InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI)
        val code = input("e.g. SF-1042", InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_CAP_CHARACTERS)
        val otp = input("e.g. K7QX-2M9P", InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_FLAG_CAP_CHARACTERS or InputType.TYPE_TEXT_FLAG_NO_SUGGESTIONS)
        val server = input("https://qronex.app", InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_URI, store.server)

        sheet.add(text("Activate this phone", 20f, C.INK, true))
        sheet.add(field("Company code", company), top = 18)
        sheet.add(field("Employee code", code), top = 14)
        sheet.add(field("Activation code", otp), top = 14)

        val adv = field("Server address", server).apply { visibility = android.view.View.GONE }
        val advToggle = text("Advanced: server address", 13f, C.TEAL, true).apply { setPadding(0, dp(14), 0, dp(4)) }
        advToggle.setOnClickListener { adv.visibility = if (adv.visibility == android.view.View.GONE) android.view.View.VISIBLE else android.view.View.GONE }
        if (BuildConfig.DEBUG) { sheet.add(advToggle); sheet.add(adv, top = 6) }

        val err = text("", 14f, C.RED).apply { visibility = android.view.View.GONE; background = round(C.RED_SOFT, dp(12).toFloat()); setPadding(dp(14), dp(10), dp(14), dp(10)) }
        sheet.add(err, top = 16)

        lateinit var go: android.widget.TextView
        go = button("Activate") {
            err.visibility = android.view.View.GONE
            val (base, fromServer) = Addr.split(server.text.toString())
            val slug = company.text.toString().trim().trimEnd('/').substringAfterLast('/').lowercase().ifBlank { fromServer.lowercase() }
            if (slug.isBlank() || code.text.isBlank() || otp.text.isBlank()) {
                err.text = "Fill in all three codes."; err.visibility = android.view.View.VISIBLE; return@button
            }
            store.server = base
            go.isEnabled = false; go.text = "Activating…"
            bg {
                val key = Keys.create()
                val body = JSONObject()
                    .put("company", slug).put("employee_code", code.text.toString().trim())
                    .put("activation_code", otp.text.toString().trim()).put("platform", "android")
                    .put("model", "${Build.MANUFACTURER} ${Build.MODEL}".trim()).put("os_version", "Android ${Build.VERSION.RELEASE}")
                    .put("install_ref", store.installRef)
                key?.let { body.put("public_key", it) }
                store.token = null
                val r = Api.call(store, "POST", "activate", body)
                ui {
                    go.isEnabled = true; go.text = "Activate"
                    if (r.ok) {
                        store.token = r.json.optString("token")
                        startActivity(Intent(this, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_CLEAR_TASK or Intent.FLAG_ACTIVITY_NEW_TASK))
                        finish()
                    } else {
                        Keys.delete()
                        err.text = r.message(); err.visibility = android.view.View.VISIBLE
                    }
                }
            }
        }
        sheet.add(go, top = 8)
        sheet.add(hbox(Gravity.CENTER).apply {
            add(icon(R.drawable.ic_shield, C.TEAL, 16), w = dp(16), h = dp(16), end = 6)
            add(text("This phone gets its own security key. Codes work once.", 12.5f, C.SLATE), w = WRAP)
        }, top = 18)
        root.add(sheet, h = 0, weight = 1f)

        setContentView(ScrollView(this).apply {
            isFillViewport = true; setBackgroundColor(C.WHITE)
            addView(root, LinearLayout.LayoutParams(MATCH, WRAP))
        })
    }
}

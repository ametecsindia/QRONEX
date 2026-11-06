package com.ametecs.qronex

import android.animation.ValueAnimator
import android.app.Activity
import android.content.Context
import android.content.res.ColorStateList
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.LinearGradient
import android.graphics.Paint
import android.graphics.Path
import android.graphics.PathMeasure
import android.graphics.RadialGradient
import android.graphics.Shader
import android.graphics.Typeface
import android.graphics.drawable.Drawable
import android.graphics.drawable.GradientDrawable
import android.graphics.drawable.RippleDrawable
import android.text.InputType
import android.util.TypedValue
import android.view.Gravity
import android.view.View
import android.view.ViewGroup
import android.view.animation.DecelerateInterpolator
import android.widget.EditText
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import android.widget.Toast

/** QRONEX design tokens — same palette as qronex.app. */
object C {
    const val NAVY = 0xFF0E2747.toInt()
    const val NAVY2 = 0xFF17406F.toInt()
    const val TEAL = 0xFF0A8278.toInt()
    const val TEAL2 = 0xFF14B8A6.toInt()
    const val MINT = 0xFFE6F6F4.toInt()
    const val BG = 0xFFF4F7FB.toInt()
    const val INK = 0xFF0F172A.toInt()
    const val SLATE = 0xFF64748B.toInt()
    const val MUTED = 0xFF94A3B8.toInt()
    const val LINE = 0xFFE2E8F0.toInt()
    const val WHITE = Color.WHITE
    const val RED = 0xFFDC2626.toInt()
    const val RED_SOFT = 0xFFFEE2E2.toInt()
    const val AMBER = 0xFFB45309.toInt()
    const val AMBER_SOFT = 0xFFFEF3C7.toInt()
    const val GREEN = 0xFF047857.toInt()
    const val GREEN_SOFT = 0xFFD1FAE5.toInt()
}

private var regular: Typeface? = null
private var bold: Typeface? = null
fun Context.font(isBold: Boolean): Typeface {
    if (regular == null) {
        regular = runCatching { Typeface.createFromAsset(assets, "fonts/sg-regular.ttf") }.getOrDefault(Typeface.DEFAULT)
        bold = runCatching { Typeface.createFromAsset(assets, "fonts/sg-bold.ttf") }.getOrDefault(Typeface.DEFAULT_BOLD)
    }
    return if (isBold) bold!! else regular!!
}

fun Context.dp(v: Number) = (v.toFloat() * resources.displayMetrics.density).toInt()
fun Context.toast(s: String) = Toast.makeText(this, s, Toast.LENGTH_LONG).show()

const val MATCH = ViewGroup.LayoutParams.MATCH_PARENT
const val WRAP = ViewGroup.LayoutParams.WRAP_CONTENT

fun round(color: Int, radius: Float, stroke: Int = 0, strokeW: Int = 0) = GradientDrawable().apply {
    setColor(color); setCornerRadius(radius); if (strokeW > 0) setStroke(strokeW, stroke)
}

fun gradient(a: Int, b: Int, radius: Float = 0f, o: GradientDrawable.Orientation = GradientDrawable.Orientation.TL_BR) =
    GradientDrawable(o, intArrayOf(a, b)).apply { setCornerRadius(radius) }

fun ripple(content: Drawable, color: Int = 0x22000000) = RippleDrawable(ColorStateList.valueOf(color), content, content)

/** Adds a child to a LinearLayout with size, margins and weight in one call. */
fun <T : View> LinearLayout.add(v: T, w: Int = MATCH, h: Int = WRAP, top: Int = 0, bottom: Int = 0, start: Int = 0, end: Int = 0, weight: Float = 0f): T {
    addView(v, LinearLayout.LayoutParams(w, h, weight).apply {
        topMargin = context.dp(top); bottomMargin = context.dp(bottom); marginStart = context.dp(start); marginEnd = context.dp(end)
    })
    return v
}

fun Context.vbox(pad: Int = 0, gravity: Int = Gravity.NO_GRAVITY) = LinearLayout(this).apply {
    orientation = LinearLayout.VERTICAL; setPadding(dp(pad), dp(pad), dp(pad), dp(pad)); setGravity(gravity)
}

fun Context.hbox(gravity: Int = Gravity.CENTER_VERTICAL) = LinearLayout(this).apply { orientation = LinearLayout.HORIZONTAL; setGravity(gravity) }

fun Context.text(s: CharSequence, size: Float = 15f, color: Int = C.INK, isBold: Boolean = false, gravity: Int = Gravity.NO_GRAVITY) =
    TextView(this).apply {
        text = s; setTextSize(TypedValue.COMPLEX_UNIT_SP, size); setTextColor(color); typeface = font(isBold); setGravity(gravity)
        setLineSpacing(0f, 1.12f)
    }

fun Context.label(s: String, color: Int = C.MUTED) = text(s.uppercase(), 11f, color, true).apply { letterSpacing = 0.08f }

fun Context.icon(res: Int, color: Int, size: Int = 22) = ImageView(this).apply {
    setImageResource(res); setColorFilter(color); layoutParams = LinearLayout.LayoutParams(dp(size), dp(size))
}

/** White rounded card with a soft hairline. */
fun Context.card(pad: Int = 18) = vbox(pad).apply { background = round(C.WHITE, dp(20).toFloat(), C.LINE, dp(1)); elevation = dp(1).toFloat() }

fun Context.button(label: String, kind: String = "primary", onClick: (View) -> Unit) = text(label, 15f, isBold = true, gravity = Gravity.CENTER).apply {
    val r = dp(16).toFloat()
    val (bgd, fg) = when (kind) {
        "primary" -> gradient(C.TEAL, 0xFF0B6B64.toInt(), r, GradientDrawable.Orientation.LEFT_RIGHT) to C.WHITE
        "navy" -> gradient(C.NAVY2, C.NAVY, r, GradientDrawable.Orientation.LEFT_RIGHT) to C.WHITE
        "light" -> round(0x26FFFFFF, r, 0x40FFFFFF, dp(1)) to C.WHITE
        "danger" -> round(C.RED_SOFT, r) to C.RED
        else -> round(C.WHITE, r, C.LINE, dp(1)) to C.NAVY
    }
    background = ripple(bgd, if (fg == C.WHITE) 0x33FFFFFF else 0x220E2747)
    setTextColor(fg); minHeight = dp(54); setPadding(dp(18), dp(14), dp(18), dp(14))
    isClickable = true; setOnClickListener(onClick)
}

fun Context.chip(s: String, fg: Int, bgc: Int) = text(s, 11.5f, fg, true).apply {
    background = round(bgc, dp(99).toFloat()); setPadding(dp(10), dp(4), dp(10), dp(4))
}

fun Context.statusChip(status: String) = when (status) {
    "approved" -> chip("Approved", C.GREEN, C.GREEN_SOFT)
    "rejected" -> chip("Rejected", C.RED, C.RED_SOFT)
    "pending" -> chip("Pending", C.AMBER, C.AMBER_SOFT)
    "revoked", "cancelled" -> chip(status.replaceFirstChar { it.uppercase() }, C.SLATE, C.BG)
    else -> chip(status, C.SLATE, C.BG)
}

fun Context.input(hint: String, type: Int = InputType.TYPE_CLASS_TEXT, value: String = "") = EditText(this).apply {
    this.hint = hint; inputType = type; setText(value); setSingleLine(type and InputType.TYPE_TEXT_FLAG_MULTI_LINE == 0)
    setTextSize(TypedValue.COMPLEX_UNIT_SP, 16f); typeface = font(false); setTextColor(C.INK); setHintTextColor(C.MUTED)
    background = round(C.BG, dp(14).toFloat(), C.LINE, dp(1)); setPadding(dp(16), dp(14), dp(16), dp(14))
    setOnFocusChangeListener { _, f -> background = round(if (f) C.WHITE else C.BG, dp(14).toFloat(), if (f) C.TEAL else C.LINE, dp(if (f) 2 else 1)) }
}

/** Label + field stacked. */
fun Context.field(caption: String, v: View) = vbox().apply {
    add(text(caption, 13f, C.SLATE, true), bottom = 6); add(v)
}

/** Initials in a gradient circle. */
fun Context.avatar(name: String, size: Int = 46, light: Boolean = false) = text(
    name.split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.take(1).uppercase() }.ifBlank { "Q" },
    size / 2.8f, if (light) C.NAVY else C.WHITE, true, Gravity.CENTER
).apply {
    background = if (light) round(C.WHITE, dp(size).toFloat()) else gradient(C.TEAL2, C.TEAL, dp(size).toFloat())
    layoutParams = LinearLayout.LayoutParams(dp(size), dp(size))
}

/** Status bar tint per screen (navy header vs. white). */
fun Activity.statusBar(color: Int, lightIcons: Boolean) {
    window.statusBarColor = color
    @Suppress("DEPRECATION")
    window.decorView.systemUiVisibility = if (lightIcons) 0 else View.SYSTEM_UI_FLAG_LIGHT_STATUS_BAR
}

/** The big round punch button: gradient core, breathing halo rings and a QR glyph. */
class PunchButton(ctx: Context) : View(ctx) {
    var title = "Punch IN"
    var subtitle = "Scan the entrance QR"
    var busy = false
        set(v) { field = v; invalidate() }
    private var phase = 0f
    private val p = Paint(Paint.ANTI_ALIAS_FLAG)
    private val glyph = Path()
    private val anim = ValueAnimator.ofFloat(0f, 1f).apply {
        duration = 2600; repeatCount = ValueAnimator.INFINITE
        addUpdateListener { phase = it.animatedValue as Float; invalidate() }
    }

    override fun onAttachedToWindow() { super.onAttachedToWindow(); anim.start() }
    override fun onDetachedFromWindow() { anim.cancel(); super.onDetachedFromWindow() }

    override fun onDraw(c: Canvas) {
        val cx = width / 2f; val cy = height / 2f
        val core = minOf(width, height) * 0.34f
        // two halo rings expanding out of phase
        for (i in 0..1) {
            val t = (phase + i * 0.5f) % 1f
            p.style = Paint.Style.FILL; p.shader = null
            p.color = Color.argb(((1 - t) * 70).toInt(), 20, 184, 166)
            c.drawCircle(cx, cy, core * (1f + t * 0.42f), p)
        }
        // soft outer glow
        p.shader = RadialGradient(cx, cy, core * 1.12f, intArrayOf(0x5514B8A6, 0x0014B8A6), null, Shader.TileMode.CLAMP)
        c.drawCircle(cx, cy, core * 1.12f, p)
        // gradient core
        p.shader = LinearGradient(cx - core, cy - core, cx + core, cy + core, C.TEAL2, C.NAVY2, Shader.TileMode.CLAMP)
        c.drawCircle(cx, cy, core, p)
        p.shader = null
        p.style = Paint.Style.STROKE; p.strokeWidth = context.dp(2).toFloat(); p.color = 0x40FFFFFF
        c.drawCircle(cx, cy, core - context.dp(6), p)

        // QR glyph: three finder squares + a few modules; a scan line sweeps across it
        val g = core * 0.32f
        val top = cy - core * 0.52f
        p.color = Color.WHITE; p.strokeWidth = context.dp(2.4f).toFloat(); p.strokeJoin = Paint.Join.ROUND
        val s = g * 0.42f
        glyph.reset()
        for ((ox, oy) in listOf(-g to top, g - s to top, -g to top + g * 2 - s)) {
            glyph.addRoundRect(cx + ox, oy, cx + ox + s, oy + s, s * 0.22f, s * 0.22f, Path.Direction.CW)
        }
        c.drawPath(glyph, p)
        p.style = Paint.Style.FILL
        val m = s * 0.28f
        for ((ox, oy) in listOf(0.15f to 1.25f, 0.62f to 1.62f, 0.95f to 1.2f, 0.4f to 0.9f)) c.drawRect(cx - g + g * 2 * ox - m / 2, top + g * oy, cx - g + g * 2 * ox + m / 2, top + g * oy + m, p)
        if (!busy) {
            val ly = top + (g * 2) * ((phase * 2) % 1f)
            p.color = 0xCCFFFFFF.toInt(); c.drawRect(cx - g - context.dp(4), ly, cx + g + context.dp(4), ly + context.dp(2), p)
        }

        p.textAlign = Paint.Align.CENTER
        p.typeface = context.font(true); p.textSize = core * 0.2f; p.color = Color.WHITE
        c.drawText(if (busy) "Getting ready…" else title, cx, cy + core * 0.36f, p)
        p.typeface = context.font(false); p.textSize = core * 0.1f; p.color = 0xCCFFFFFF.toInt()
        c.drawText(subtitle, cx, cy + core * 0.56f, p)
    }
}

/** Animated tick or cross drawn stroke by stroke inside a ring. */
class ResultMark(ctx: Context, private val ok: Boolean) : View(ctx) {
    private var t = 0f
    private val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { style = Paint.Style.STROKE; strokeCap = Paint.Cap.ROUND; strokeJoin = Paint.Join.ROUND; color = Color.WHITE }
    private val seg = Path()

    fun play() = ValueAnimator.ofFloat(0f, 1f).apply {
        duration = 900; interpolator = DecelerateInterpolator()
        addUpdateListener { t = it.animatedValue as Float; invalidate() }
        start()
    }

    override fun onDraw(c: Canvas) {
        val r = minOf(width, height) / 2f - context.dp(6)
        val cx = width / 2f; val cy = height / 2f
        p.strokeWidth = context.dp(5).toFloat()
        p.alpha = 70; c.drawCircle(cx, cy, r, p)
        p.alpha = 255
        c.drawArc(cx - r, cy - r, cx + r, cy + r, -90f, 360f * minOf(1f, t * 1.6f), false, p)
        val k = ((t - 0.45f) / 0.55f).coerceIn(0f, 1f)
        if (k <= 0f) return
        val path = Path()
        if (ok) {
            path.moveTo(cx - r * 0.42f, cy + r * 0.02f); path.lineTo(cx - r * 0.1f, cy + r * 0.34f); path.lineTo(cx + r * 0.46f, cy - r * 0.3f)
        } else {
            path.moveTo(cx - r * 0.32f, cy - r * 0.32f); path.lineTo(cx + r * 0.32f, cy + r * 0.32f)
            path.moveTo(cx + r * 0.32f, cy - r * 0.32f); path.lineTo(cx - r * 0.32f, cy + r * 0.32f)
        }
        p.strokeWidth = context.dp(7).toFloat()
        val pm = PathMeasure(path, false)
        do {
            seg.reset(); pm.getSegment(0f, pm.length * k, seg, true); c.drawPath(seg, p)
        } while (pm.nextContour())
    }
}

/** Three dots that pulse in turn — "working on it". */
class Dots(ctx: Context, private val color: Int = Color.WHITE) : View(ctx) {
    private var t = 0f
    private val p = Paint(Paint.ANTI_ALIAS_FLAG).apply { this.color = color }
    private val a = ValueAnimator.ofFloat(0f, 3f).apply { duration = 1100; repeatCount = ValueAnimator.INFINITE; addUpdateListener { t = it.animatedValue as Float; invalidate() } }
    override fun onAttachedToWindow() { super.onAttachedToWindow(); a.start() }
    override fun onDetachedFromWindow() { a.cancel(); super.onDetachedFromWindow() }
    override fun onDraw(c: Canvas) {
        val r = height / 4f
        for (i in 0..2) {
            val d = kotlin.math.abs(t - i - 0.5f).coerceAtMost(1.5f) / 1.5f
            p.alpha = (255 * (1 - d * 0.7f)).toInt()
            c.drawCircle(width / 2f + (i - 1) * r * 3.2f, height / 2f, r * (1.2f - d * 0.4f), p)
        }
    }
}

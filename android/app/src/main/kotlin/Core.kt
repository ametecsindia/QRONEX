package com.ametecs.qronex

import android.annotation.SuppressLint
import android.content.Context
import android.location.Location
import android.location.LocationManager
import android.os.Handler
import android.os.Looper
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import com.google.android.gms.location.LocationServices
import com.google.android.gms.location.Priority
import com.google.android.gms.tasks.CancellationTokenSource
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.security.KeyPairGenerator
import java.security.KeyStore
import java.security.MessageDigest
import java.security.PrivateKey
import java.security.SecureRandom
import java.security.Signature
import java.security.spec.ECGenParameterSpec
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale
import java.util.TimeZone
import java.util.UUID
import java.util.concurrent.Executors

private val main = Handler(Looper.getMainLooper())
private val pool = Executors.newFixedThreadPool(3)

fun bg(work: () -> Unit) = pool.execute(work)
fun ui(work: () -> Unit) = main.post(work)
fun later(ms: Long, work: () -> Unit) = main.postDelayed(work, ms)

/** Everything this phone remembers: server, sign-in token, a random install id and the last /me answer. */
class Store(ctx: Context) {
    // New file name on purpose: the old 0.x app saved a local test address (10.0.2.2 / PC IP) that must not carry over.
    private val p = ctx.getSharedPreferences("qronex_v1", Context.MODE_PRIVATE)
    var server: String
        get() = p.getString("server", null)?.takeIf { BuildConfig.DEBUG || it.startsWith("https://") } ?: BuildConfig.SERVER_URL
        set(v) = p.edit().putString("server", v).apply()
    var token: String?
        get() = p.getString("token", null)
        set(v) = p.edit().putString("token", v).apply()
    var me: JSONObject?
        get() = p.getString("me", null)?.let { runCatching { JSONObject(it) }.getOrNull() }
        set(v) = p.edit().putString("me", v?.toString()).apply()
    val installRef: String
        get() = p.getString("install", null) ?: UUID.randomUUID().toString().also { p.edit().putString("install", it).apply() }

    fun signOut() {
        p.edit().remove("token").remove("me").apply()
        Keys.delete()
    }
}

/** Non-exportable P-256 key in the Android Keystore. The server checks every request was signed by it (ROVEXY scheme). */
/** "qronex.app/altaf", "https://qronex.app/" → base "https://qronex.app" and the path's last part (the company code, if any). */
object Addr {
  fun split(raw: String): Pair<String, String> {
    var s = raw.trim().replace(" ", "")
    if (s.isEmpty()) return BuildConfig.SERVER_URL to ""
    if (!s.contains("://")) s = "https://$s"
    val scheme = s.substringBefore("://").lowercase()
    val rest = s.substringAfter("://")
    val host = rest.substringBefore('/').lowercase()
    val path = rest.substringAfter('/', "").trim('/')
    return "$scheme://$host" to path.substringAfterLast('/')
  }
}

object Keys {
    private const val ALIAS = "qronex_device"
    private fun ks() = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }

    /** New key for a new activation; returns base64 DER public key, or null if this phone can't make one. */
    fun create(): String? = try {
        delete()
        val g = KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_EC, "AndroidKeyStore")
        g.initialize(
            KeyGenParameterSpec.Builder(ALIAS, KeyProperties.PURPOSE_SIGN)
                .setAlgorithmParameterSpec(ECGenParameterSpec("secp256r1"))
                .setDigests(KeyProperties.DIGEST_SHA256)
                .build()
        )
        Base64.encodeToString(g.generateKeyPair().public.encoded, Base64.NO_WRAP)
    } catch (e: Exception) {
        null
    }

    fun has(): Boolean = runCatching { ks().containsAlias(ALIAS) }.getOrDefault(false)
    fun delete() { runCatching { ks().deleteEntry(ALIAS) } }

    fun sign(data: ByteArray): String? = runCatching {
        val key = ks().getKey(ALIAS, null) as? PrivateKey ?: return null
        Signature.getInstance("SHA256withECDSA").run {
            initSign(key); update(data); Base64.encodeToString(sign(), Base64.NO_WRAP)
        }
    }.getOrNull()
}

class Res(val code: Int, val json: JSONObject) {
    val ok get() = code in 200..299

    /** The most useful sentence in a Laravel error: first validation error, else message, else a generic line. */
    fun message(): String {
        json.optJSONObject("errors")?.let { e ->
            val k = e.keys()
            if (k.hasNext()) e.optJSONArray(k.next())?.optString(0)?.takeIf { it.isNotBlank() }?.let { return it }
        }
        json.optString("reason").takeIf { it.isNotBlank() }?.let { return it }
        json.optString("message").takeIf { it.isNotBlank() }?.let { return it }
        return when (code) {
            0 -> "Can't reach QRONEX. Check your internet connection and try again."
            429 -> "Too many tries. Wait a minute and try again."
            in 500..599 -> "QRONEX had a problem. Please try again in a moment."
            else -> "Something went wrong ($code)."
        }
    }
}

object Api {
    private val rnd = SecureRandom()

    private fun hex(b: ByteArray) = b.joinToString("") { "%02x".format(it) }

    /** What the server's DeviceSignature::canonical() rebuilds: METHOD, path+query, unix ts, nonce, hex sha256(body). */
    fun canonical(method: String, file: String, ts: String, nonce: String, body: ByteArray) =
        "$method\n$file\n$ts\n$nonce\n${hex(MessageDigest.getInstance("SHA-256").digest(body))}"

    /** Blocking; call from bg{}. Signs the request when this phone has a device key. */
    fun call(store: Store, method: String, path: String, body: JSONObject? = null): Res {
        val url = URL(store.server + "/api/v1/" + path)
        val bytes = (body?.toString() ?: "").toByteArray()
        val c = url.openConnection() as HttpURLConnection
        return try {
            c.requestMethod = method
            c.connectTimeout = 15000
            c.readTimeout = 25000
            c.setRequestProperty("Accept", "application/json")
            c.setRequestProperty("User-Agent", "QRONEX-Android/${BuildConfig.VERSION_NAME}")
            store.token?.let { c.setRequestProperty("Authorization", "Bearer $it") }
            if (store.token != null && Keys.has()) {
                val ts = (System.currentTimeMillis() / 1000).toString()
                val nonce = ByteArray(24).also { rnd.nextBytes(it) }.let { Base64.encodeToString(it, Base64.URL_SAFE or Base64.NO_WRAP or Base64.NO_PADDING) }
                val canon = canonical(method, url.file, ts, nonce, bytes)
                Keys.sign(canon.toByteArray())?.let {
                    c.setRequestProperty("X-Timestamp", ts)
                    c.setRequestProperty("X-Nonce", nonce)
                    c.setRequestProperty("X-Signature", it)
                }
            }
            if (body != null) {
                c.doOutput = true
                c.setRequestProperty("Content-Type", "application/json")
                c.outputStream.use { it.write(bytes) }
            }
            val code = c.responseCode
            val text = (if (code >= 400) c.errorStream else c.inputStream)?.bufferedReader()?.use { it.readText() } ?: ""
            Res(code, runCatching { JSONObject(text) }.getOrElse { JSONObject() })
        } catch (e: Exception) {
            Res(0, JSONObject())
        } finally {
            c.disconnect()
        }
    }

    /** Async helper: runs in the background, answers on the main thread. */
    fun go(store: Store, method: String, path: String, body: JSONObject? = null, done: (Res) -> Unit) =
        bg { val r = call(store, method, path, body); ui { done(r) } }
}

/** One fresh, high-accuracy location fix for a punch. */
object Loc {
    fun enabled(ctx: Context): Boolean {
        val lm = ctx.getSystemService(Context.LOCATION_SERVICE) as LocationManager
        return lm.isProviderEnabled(LocationManager.GPS_PROVIDER) || lm.isProviderEnabled(LocationManager.NETWORK_PROVIDER)
    }

    @SuppressLint("MissingPermission")
    fun once(ctx: Context, done: (Location?) -> Unit) {
        var finished = false
        val finish = { l: Location? -> if (!finished) { finished = true; done(l) } }
        val cts = CancellationTokenSource()
        later(20000) { cts.cancel(); finish(lastKnown(ctx)) }
        try {
            LocationServices.getFusedLocationProviderClient(ctx)
                .getCurrentLocation(Priority.PRIORITY_HIGH_ACCURACY, cts.token)
                .addOnSuccessListener { l -> finish(l ?: lastKnown(ctx)) }
                .addOnFailureListener { finish(lastKnown(ctx)) }
        } catch (e: Exception) {
            finish(lastKnown(ctx))
        }
    }

    /** Fallback when Play services can't answer: a framework fix no older than 2 minutes. */
    @SuppressLint("MissingPermission")
    private fun lastKnown(ctx: Context): Location? {
        val lm = ctx.getSystemService(Context.LOCATION_SERVICE) as LocationManager
        return listOf(LocationManager.GPS_PROVIDER, LocationManager.NETWORK_PROVIDER)
            .mapNotNull { runCatching { lm.getLastKnownLocation(it) }.getOrNull() }
            .filter { System.currentTimeMillis() - it.time < 120_000 }
            .minByOrNull { it.accuracy }
    }

    @Suppress("DEPRECATION")
    fun json(l: Location) = JSONObject().put("lat", l.latitude).put("lng", l.longitude).put("accuracy", l.accuracy.toDouble()).put("mocked", l.isFromMockProvider)
}

/** Server times are ISO-8601 UTC ("2026-11-06T04:31:00Z"); show them in the phone's time zone. */
object T {
    private fun f(p: String) = SimpleDateFormat(p, Locale.ENGLISH)
    fun parse(iso: String?): Date? = iso?.let {
        runCatching { f("yyyy-MM-dd'T'HH:mm:ss'Z'").apply { timeZone = TimeZone.getTimeZone("UTC") }.parse(it) }.getOrNull()
    }
    fun time(d: Date) = f("h:mm a").format(d)
    fun day(d: Date) = f("yyyy-MM-dd").format(d)
    fun date(d: Date) = f("EEE, d MMM").format(d)
    fun ymd(s: String): Date? = runCatching { f("yyyy-MM-dd").parse(s) }.getOrNull()
    fun short(s: String) = ymd(s)?.let { f("d MMM").format(it) } ?: s

    fun dayLabel(d: Date): String {
        val today = day(Date())
        val y = day(Date(System.currentTimeMillis() - 86_400_000))
        return when (day(d)) { today -> "Today"; y -> "Yesterday"; else -> date(d) }
    }

    fun duration(ms: Long): String {
        val m = ms / 60000
        return if (m < 60) "${m}m" else "${m / 60}h ${"%02d".format(m % 60)}m"
    }

    /** Worked time from IN/OUT pairs (events sorted oldest first). An open IN today counts up to now. */
    fun worked(events: List<JSONObject>, isToday: Boolean): Long {
        var total = 0L
        var inAt: Date? = null
        for (e in events) {
            val at = parse(e.optString("at")) ?: continue
            if (e.optString("type") == "IN") { if (inAt == null) inAt = at }
            else inAt?.let { total += at.time - it.time; inAt = null }
        }
        if (isToday) inAt?.let { total += System.currentTimeMillis() - it.time }
        return total
    }

    fun greeting(): String {
        val h = f("H").format(Date()).toInt()
        return when { h < 12 -> "Good morning"; h < 17 -> "Good afternoon"; else -> "Good evening" }
    }
}

package com.rothwellmead.crown

import android.Manifest
import android.annotation.SuppressLint
import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.net.Uri
import android.os.Bundle
import android.text.InputType
import android.util.TypedValue
import android.view.Gravity
import android.view.inputmethod.EditorInfo
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.Button
import android.widget.EditText
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast

/**
 * First run: log in with your crown website name and password. After that: the crown website's homepage,
 * with buttons to refresh or log in as someone else.
 */
class MainActivity : Activity() {
    private lateinit var content: LinearLayout
    private lateinit var loginScreen: ScrollView

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            val pad = dp(24)
            setPadding(pad, pad * 2, pad, pad)
        }
        loginScreen = ScrollView(this).apply { addView(content) }

        if (Prefs.me(this) == null) showPicker() else showStatus()
    }

    /** Log in, step one: pick your name. */
    private fun showPicker() {
        // Coming from the homepage screen, which replaced the login screen.
        if (loginScreen.parent == null) setContentView(loginScreen)
        content.removeAllViews()
        content.addView(text("Log in", 26f))
        content.addView(text("Your notification shows a crown when you're King, and a P when you're a peasant. Pick your name, then enter the password you use on the crown website.", 15f))
        val list = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        content.addView(list)
        fun fill(people: List<Person>) {
            list.removeAllViews()
            people.forEach { person ->
                list.addView(button("${person.icon} ${person.name}") { showPassword(person) })
            }
        }
        fill(CrownApi.FALLBACK_PEOPLE)
        // Swap in the site's list so new names or icons show up.
        Thread {
            val people = try { CrownApi.fetch().people } catch (e: Exception) { null }
            if (people != null) runOnUiThread { if (!isFinishing) fill(people) }
        }.start()
    }

    /** Log in, step two: the password, checked against the site before this phone becomes theirs. */
    private fun showPassword(person: Person) {
        content.removeAllViews()
        content.addView(text("${person.icon} ${person.name}", 26f))
        val password = EditText(this).apply {
            hint = "Password"
            inputType = InputType.TYPE_CLASS_TEXT or InputType.TYPE_TEXT_VARIATION_PASSWORD
            imeOptions = EditorInfo.IME_ACTION_DONE
            setSingleLine()
        }
        val message = text("", 15f)
        lateinit var logIn: Button
        val submit = {
            val entered = password.text.toString()
            if (entered.isEmpty()) {
                message.text = "Enter your password."
            } else {
                logIn.isEnabled = false
                message.text = "Checking…"
                Thread {
                    val result = try { Result.success(CrownApi.login(person.id, entered)) } catch (e: Exception) { Result.failure(e) }
                    runOnUiThread {
                        if (isFinishing) return@runOnUiThread
                        result.onSuccess { choose(it) }.onFailure { e ->
                            logIn.isEnabled = true
                            message.text = if (e is LoginRefused) e.message else "Couldn't reach the site. Check your connection and try again."
                        }
                    }
                }.start()
            }
        }
        password.setOnEditorActionListener { _, actionId, _ ->
            if (actionId == EditorInfo.IME_ACTION_DONE) { submit(); true } else false
        }
        logIn = button("Log in") { submit() }
        content.addView(password)
        content.addView(message.apply { setPadding(0, dp(12), 0, dp(12)) })
        content.addView(logIn)
        content.addView(button("Not you? Pick again") { showPicker() })
        password.requestFocus()
    }

    private fun choose(person: Person) {
        Prefs.setMe(this, person.id)
        if (Build.VERSION.SDK_INT >= 33 &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), REQUEST_NOTIFICATIONS)
        }
        UpdateWorker.schedule(this)
        showStatus()
    }

    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == REQUEST_NOTIFICATIONS) {
            UpdateWorker.schedule(this)
            showStatus()
        }
    }

    /**
     * Once logged in: the crown website's homepage, so the app looks just like the site. Links on it
     * (crown history, future matches, stats, game details, players) open in the phone's browser.
     */
    @SuppressLint("SetJavaScriptEnabled")
    private fun showStatus() {
        val me = Prefs.me(this) ?: return showPicker()
        val root = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setBackgroundColor(SITE_BACKGROUND)
            // Keep clear of the status and navigation bars, which the app draws behind on newer Android.
            setOnApplyWindowInsetsListener { v, insets ->
                @Suppress("DEPRECATION")
                v.setPadding(insets.systemWindowInsetLeft, insets.systemWindowInsetTop, insets.systemWindowInsetRight, insets.systemWindowInsetBottom)
                insets
            }
        }
        if (Build.VERSION.SDK_INT >= 33 &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            root.addView(barText("Notifications are off, so nothing will show in the status bar. Turn them on for Crown in Settings.") {})
        }
        val web = WebView(this).apply {
            setBackgroundColor(SITE_BACKGROUND)
            settings.javaScriptEnabled = true // the page fills in dates and checks for a new holder with JavaScript
            webViewClient = object : WebViewClient() {
                override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                    if (isHomepage(request.url)) return false
                    openInBrowser(request.url)
                    return true
                }

                override fun onPageFinished(view: WebView, url: String) {
                    // The site's corner "Log in" link is for the website; the app has its own login.
                    view.evaluateJavascript("document.querySelectorAll('.admin-link').forEach(e => e.remove())", null)
                }

                override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                    if (request.isForMainFrame) showOffline(view)
                }
            }
            loadUrl(CrownApi.SITE_URL)
        }
        root.addView(web, LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 0, 1f))
        root.addView(LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            gravity = Gravity.CENTER
            addView(barText("Refresh") { web.loadUrl(CrownApi.SITE_URL); updateNotification(me) })
            addView(barText("Log in as someone else") { showPicker() })
        })
        setContentView(root)
        updateNotification(me)
    }

    /** Brings the status-bar notification up to date in the background. */
    private fun updateNotification(me: String) {
        Thread {
            val status = try { CrownApi.fetch() } catch (e: Exception) { null }
            if (status != null) runOnUiThread { if (!isFinishing) CrownNotifier.show(this, status, me) }
        }.start()
    }

    private fun isHomepage(url: Uri): Boolean {
        val site = Uri.parse(CrownApi.SITE_URL)
        return url.host == site.host && url.path in setOf(site.path, site.path + "index.php")
    }

    private fun openInBrowser(url: Uri) {
        try {
            startActivity(Intent(Intent.ACTION_VIEW, url).addCategory(Intent.CATEGORY_BROWSABLE))
        } catch (e: ActivityNotFoundException) {
            Toast.makeText(this, "No browser to open $url", Toast.LENGTH_LONG).show()
        }
    }

    /** Shown in place of the homepage when the site can't be reached, in the site's colours. */
    private fun showOffline(web: WebView) {
        val html = """
            <html><body style="margin:0;height:100vh;display:flex;align-items:center;justify-content:center;
            background:#1b1530;color:#f4efff;font-family:sans-serif;text-align:center;padding:24px;box-sizing:border-box">
            <div><h2>Couldn't reach the site</h2><p style="color:#a99fc9">Check your connection and tap Refresh.</p></div>
            </body></html>
        """.trimIndent()
        web.loadDataWithBaseURL(null, html, "text/html", "utf-8", null)
    }

    /** A small tappable label for the bar under the homepage, in the site's muted text colour. */
    private fun barText(s: String, onClick: () -> Unit) = TextView(this).apply {
        text = s
        setTextColor(SITE_MUTED)
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 14f)
        setPadding(dp(16), dp(12), dp(16), dp(12))
        setOnClickListener { onClick() }
    }

    private fun text(s: String, size: Float) = TextView(this).apply {
        text = s
        setTextSize(TypedValue.COMPLEX_UNIT_SP, size)
        setPadding(0, 0, 0, dp(16))
    }

    private fun button(label: String, onClick: () -> Unit) = Button(this).apply {
        text = label
        isAllCaps = false
        gravity = Gravity.START or Gravity.CENTER_VERTICAL
        setTextSize(TypedValue.COMPLEX_UNIT_SP, 18f)
        setOnClickListener { onClick() }
    }

    private fun dp(v: Int) = (v * resources.displayMetrics.density).toInt()

    private companion object {
        const val REQUEST_NOTIFICATIONS = 1
        // The website's background and muted text colours (--bg and --muted in style.css).
        val SITE_BACKGROUND = 0xFF1B1530.toInt()
        val SITE_MUTED = 0xFFA99FC9.toInt()
    }
}

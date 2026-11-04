package com.rothwellmead.crown

import android.Manifest
import android.app.Activity
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.util.TypedValue
import android.view.Gravity
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView

/**
 * First run: pick which of the six people you are. After that: shows who holds the crown,
 * with buttons to refresh or change who you are.
 */
class MainActivity : Activity() {
    private lateinit var content: LinearLayout

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        content = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            val pad = dp(24)
            setPadding(pad, pad * 2, pad, pad)
        }
        setContentView(ScrollView(this).apply { addView(content) })

        if (Prefs.me(this) == null) showPicker() else showStatus()
    }

    private fun showPicker() {
        content.removeAllViews()
        content.addView(text("Who are you?", 26f))
        content.addView(text("Your notification shows a crown when you're King, and a P when you're a peasant.", 15f))
        val list = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        content.addView(list)
        fun fill(people: List<Person>) {
            list.removeAllViews()
            people.forEach { person ->
                list.addView(button("${person.icon} ${person.name}") { choose(person) })
            }
        }
        fill(CrownApi.FALLBACK_PEOPLE)
        // Swap in the site's list so new names or icons show up.
        Thread {
            val people = try { CrownApi.fetch().people } catch (e: Exception) { null }
            if (people != null) runOnUiThread { if (!isFinishing) fill(people) }
        }.start()
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

    private fun showStatus() {
        val me = Prefs.me(this) ?: return showPicker()
        content.removeAllViews()
        val headline = text("Checking…", 26f)
        val detail = text("", 16f)
        content.addView(headline)
        content.addView(detail)
        if (Build.VERSION.SDK_INT >= 33 &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            content.addView(text("Notifications are off, so nothing will show in the status bar. Turn them on for Crown in Settings.", 15f))
        }
        content.addView(button("Refresh") { showStatus() })
        content.addView(button("Change who I am") { showPicker() })

        Thread {
            val result = try { Result.success(CrownApi.fetch()) } catch (e: Exception) { Result.failure(e) }
            runOnUiThread {
                if (isFinishing) return@runOnUiThread
                result.onSuccess { status ->
                    val you = status.people.find { it.id == me }
                    CrownNotifier.show(this, status, me)
                    headline.text = if (status.holder?.id == me) "👑 You are King" else "P — Peasant"
                    detail.text = buildString {
                        you?.let { append("You are ${it.icon} ${it.name}.\n") }
                        when (status.holder) {
                            null -> append("Nobody holds the crown.")
                            else -> if (status.holder.id != me) append("${status.holder.icon} ${status.holder.name} has the crown.")
                        }
                    }.trim()
                }.onFailure {
                    headline.text = "Couldn't reach the site"
                    detail.text = "Check your connection and tap Refresh."
                }
            }
        }.start()
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
    }
}

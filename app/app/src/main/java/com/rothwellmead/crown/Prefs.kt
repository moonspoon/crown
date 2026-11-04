package com.rothwellmead.crown

import android.content.Context

/** Which of the six people this phone belongs to, chosen at setup. */
object Prefs {
    private const val FILE = "crown"
    private const val KEY_ME = "me"

    fun me(context: Context): String? =
        context.getSharedPreferences(FILE, Context.MODE_PRIVATE).getString(KEY_ME, null)

    fun setMe(context: Context, id: String) {
        context.getSharedPreferences(FILE, Context.MODE_PRIVATE).edit().putString(KEY_ME, id).apply()
    }
}

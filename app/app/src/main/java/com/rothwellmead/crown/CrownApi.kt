package com.rothwellmead.crown

import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

/** [lastHeld] is when they last lost the crown (ISO 8601): null if they never held it, hold it now, or the site is older. */
data class Person(val id: String, val name: String, val icon: String, val lastHeld: String? = null)

/** Who holds the crown right now. [holder] is null when nobody does. */
data class CrownStatus(val holder: Person?, val since: String?, val people: List<Person>)

object CrownApi {
    private const val URL_API = "https://rothwellmead.com/crown/api.php"

    /** Used to choose who you are if the site doesn't list people (older api.php) or can't be reached. */
    val FALLBACK_PEOPLE = listOf(
        Person("p1", "Spoon", "🤖"),
        Person("p2", "Henry", "🐌"),
        Person("p3", "Leo", "🦁"),
        Person("p4", "Drew", "🐼"),
        Person("p5", "Mark", "🐯"),
        Person("p6", "Gareth", "🐸"),
    )

    /** Blocking network call: run it off the main thread. */
    fun fetch(): CrownStatus {
        val conn = URL(URL_API).openConnection() as HttpURLConnection
        conn.connectTimeout = 15_000
        conn.readTimeout = 15_000
        conn.setRequestProperty("Accept", "application/json")
        try {
            if (conn.responseCode != 200) {
                throw java.io.IOException("Crown API returned ${conn.responseCode}")
            }
            val json = JSONObject(conn.inputStream.bufferedReader().use { it.readText() })
            val people = json.optJSONArray("people")?.let { arr ->
                (0 until arr.length()).map { i ->
                    val p = arr.getJSONObject(i)
                    Person(p.getString("id"), p.getString("name"), p.optString("icon"), p.optStringOrNull("last_held"))
                }
            }?.takeIf { it.isNotEmpty() } ?: FALLBACK_PEOPLE
            val holder = json.optStringOrNull("id")?.let { id ->
                Person(id, json.optStringOrNull("name") ?: id, json.optStringOrNull("icon") ?: "")
            }
            return CrownStatus(holder, json.optStringOrNull("since"), people)
        } finally {
            conn.disconnect()
        }
    }

    private fun JSONObject.optStringOrNull(key: String): String? =
        if (!has(key) || isNull(key)) null else optString(key).ifEmpty { null }
}

package com.rothwellmead.crown

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter

/** The always-there notification: a crown icon if you're King, a P if you're a peasant. */
object CrownNotifier {
    private const val CHANNEL_ID = "crown_status"
    private const val NOTIFICATION_ID = 1

    fun show(context: Context, status: CrownStatus, me: String) {
        if (Build.VERSION.SDK_INT >= 33 &&
            context.checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            return
        }
        createChannel(context)

        val isKing = status.holder?.id == me
        val since = formatSince(status.since)
        val title = if (isKing) "👑 You are King" else "Peasant"
        val text = when {
            isKing -> if (since != null) "Since $since" else "The crown is yours"
            status.holder == null -> "Nobody holds the crown"
            else -> "${status.holder.icon} ${status.holder.name} " + (since?.let { "has had the crown since $it" } ?: "has the crown")
        }.trim()
        // Peasants also see when they last had the crown themselves (shown in full when the notification is expanded).
        val fullText = if (isKing) text else when (val last = formatSince(status.people.find { it.id == me }?.lastHeld)) {
            null -> text
            else -> "$text. You last had it on $last."
        }

        val openApp = PendingIntent.getActivity(
            context, 0,
            Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP),
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT,
        )
        val notification = NotificationCompat.Builder(context, CHANNEL_ID)
            .setSmallIcon(if (isKing) R.drawable.ic_stat_crown else R.drawable.ic_stat_peasant)
            .setContentTitle(title)
            .setContentText(fullText)
            .setStyle(NotificationCompat.BigTextStyle().bigText(fullText))
            .setContentIntent(openApp)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setShowWhen(false)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .build()
        NotificationManagerCompat.from(context).notify(NOTIFICATION_ID, notification)
    }

    private fun createChannel(context: Context) {
        val channel = NotificationChannel(CHANNEL_ID, "Crown status", NotificationManager.IMPORTANCE_LOW).apply {
            description = "Shows whether you hold the crown"
            setShowBadge(false)
        }
        context.getSystemService(NotificationManager::class.java).createNotificationChannel(channel)
    }

    private fun formatSince(since: String?): String? = try {
        since?.let { OffsetDateTime.parse(it).format(DateTimeFormatter.ofPattern("d MMM yyyy")) }
    } catch (e: Exception) {
        null
    }
}

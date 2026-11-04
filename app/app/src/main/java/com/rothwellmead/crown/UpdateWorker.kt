package com.rothwellmead.crown

import android.content.Context
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.Worker
import androidx.work.WorkerParameters
import java.util.concurrent.TimeUnit

/** Asks the site who holds the crown and updates the notification. */
class UpdateWorker(context: Context, params: WorkerParameters) : Worker(context, params) {
    override fun doWork(): Result {
        val me = Prefs.me(applicationContext) ?: return Result.success()
        return try {
            CrownNotifier.show(applicationContext, CrownApi.fetch(), me)
            Result.success()
        } catch (e: Exception) {
            Result.retry()
        }
    }

    companion object {
        private val online = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

        /** Checks every 15 minutes (Android's shortest repeat), plus once straight away. */
        fun schedule(context: Context) {
            val wm = WorkManager.getInstance(context)
            wm.enqueueUniquePeriodicWork(
                "crown-periodic",
                ExistingPeriodicWorkPolicy.KEEP,
                PeriodicWorkRequestBuilder<UpdateWorker>(15, TimeUnit.MINUTES).setConstraints(online).build(),
            )
            wm.enqueueUniqueWork(
                "crown-now",
                ExistingWorkPolicy.REPLACE,
                OneTimeWorkRequestBuilder<UpdateWorker>().setConstraints(online).build(),
            )
        }
    }
}

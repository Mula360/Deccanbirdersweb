# Running the daily gallery check from Hostinger cron

Photos leave the gallery a set number of days after they are approved
(Gallery → Submission settings, default 90). A daily check does this, on
WordPress's scheduler, WP-Cron.

WP-Cron has a catch: it only runs when someone visits the site. On a quiet
day nothing runs, and a photo stays up past its time. So on the live site,
let a real cron job run it on a clock.

## One-time setup

1. **Find the site's folder on the server.** In wp-admin, go to
   **Tools → Site Health → Info → Directories and Sizes**, and copy
   **WordPress directory location**. It looks like
   `/home/u123456789/domains/deccanbirders.org/public_html`.

2. **Stop WordPress running the scheduler on page visits.** In hPanel →
   **File Manager**, open `wp-config.php` and add this line above
   `/* That's all, stop editing! */`:

   ```php
   define('DISABLE_WP_CRON', true);
   ```

   Only do this together with step 3, or scheduled tasks stop altogether.

3. **Add the cron job.** In hPanel → **Advanced → Cron Jobs**:
   - Type: **Custom**
   - Command (use your path from step 1):

     ```
     /usr/bin/php /home/u123456789/domains/deccanbirders.org/public_html/wp-cron.php >/dev/null 2>&1
     ```

   - Schedule: every **15 minutes** (`*/15 * * * *`). The gallery check only
     does anything once a day; the other 95 runs a day take a moment each
     and keep every other scheduled WordPress task on time too.

   If Hostinger reports that `/usr/bin/php` is not found, use the PHP path
   hPanel shows on the Cron Jobs page, or this form instead, which asks the
   site over the web:

   ```
   wget -q -O /dev/null "https://deccanbirders.org/wp-cron.php?doing_wp_cron"
   ```

## Checking it works

Open **Photo review** in wp-admin. The box at the top says when the daily
check last ran, and how (`cron` when the scheduler ran it, `manual` when
someone pressed **Run the check now**). The morning after setting up the
cron job it should show a run from about 3:15 am, marked `cron`.

If it has not run for more than a day and a half, the box turns yellow and
says so. **Run the check now** does the same check immediately, whatever
the cron job is doing.

## Staging

The staging site works the same way. Use its own folder
(`…/domains/limegreen-hippopotamus-118632.hostingersite.com/public_html`, or
whatever Site Health shows) and its own address for the `wget` form.

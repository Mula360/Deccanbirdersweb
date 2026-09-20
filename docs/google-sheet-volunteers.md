# Volunteer sign-ups into a Google Sheet

Every volunteer sign-up is emailed as before, and also appended as a row to a
Google Sheet. The site talks to the sheet through a small Apps Script web app,
so there are no Google credentials stored in WordPress — only a URL and a
shared secret, both in **Settings → Site Settings**.

If the sheet is ever unreachable the emails still go out, and wp-admin shows a
warning with the reason.

## One-time setup

1. **Make the sheet.** Create a Google Sheet, e.g. "Deccan Birders — Volunteers".
   The script writes the header row itself the first time.
2. **Open the script editor:** Extensions → Apps Script.
3. **Paste this in**, replacing anything already there. Change `SECRET` to a long
   random string of your own:

   ```javascript
   const SECRET = 'change-me-to-something-long-and-random';
   const HEADERS = ['Submitted', 'Name', 'Email', 'Would like to help with', 'Source'];

   function doPost(e) {
     try {
       const body = JSON.parse(e.postData.contents);
       if (body.secret !== SECRET) {
         return json({ ok: false, error: 'bad secret' });
       }
       const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheets()[0];
       if (sheet.getLastRow() === 0) {
         sheet.appendRow(HEADERS);
         sheet.getRange(1, 1, 1, HEADERS.length).setFontWeight('bold');
         sheet.setFrozenRows(1);
       }
       sheet.appendRow([
         body.submitted || new Date(),
         body.name || '',
         body.email || '',
         body.help_with || '',
         body.source || '',
       ]);
       return json({ ok: true });
     } catch (err) {
       return json({ ok: false, error: String(err) });
     }
   }

   function json(obj) {
     return ContentService.createTextOutput(JSON.stringify(obj))
       .setMimeType(ContentService.MimeType.JSON);
   }
   ```

4. **Deploy it:** Deploy → New deployment → type **Web app**.
   - Description: anything
   - Execute as: **Me**
   - Who has access: **Anyone**

   Google will ask you to authorise the script; that's it asking permission to
   write to your own sheet. "Anyone" means anyone with the URL can POST, which
   is why the secret matters — keep the URL private.
5. **Copy the web app URL** (it ends in `/exec`).
6. In wp-admin → **Settings → Site Settings**, fill in:
   - **Volunteer sheet webhook URL** — the `/exec` URL
   - **Volunteer sheet secret** — the same string you put in `SECRET`
7. Submit the volunteer form on the site once and check the row lands.

## Changing the script later

Edit the code, then **Deploy → Manage deployments → edit → New version**. Editing
alone doesn't change what the live URL runs.

## Troubleshooting

| What you see | Usually means |
|---|---|
| wp-admin warning "could not be written" with `HTTP 401` or `bad secret` | The secret in Site Settings and in the script don't match. |
| `HTTP 403` | The deployment's "Who has access" isn't set to Anyone. |
| Rows stop appearing after a script edit | A new version wasn't deployed. |
| Nothing at all, no warning | The webhook URL field is empty, so the sheet step is skipped. |

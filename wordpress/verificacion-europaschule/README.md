# verificacion-europaschule

The public page that checks a document the school issued: the QR code on
every document of `local_documents` opens `europaschule.eu/verificar/?c=CODE`,
and this plugin answers there. `readme.txt` (Spanish, WordPress format) says
how to install it.

Installs to `wp-content/plugins/verificacion-europaschule`. The folder name,
the shortcode `[verificacion_europaschule]` and the prefix `es_ver_`
(functions, the option `es_ver_settings`, transients; CSS classes `es-ver`)
follow the platform's naming convention and are permanent once the plugin is
in production (Constitution, Article 8.2). **Status: 0.7.0, not in
production.** Until 0.6.0 it was called `es-verificacion`; its saved settings
are still read until they are saved again under the new name.

The address `/verificar/` and the code format are printed on paper that is
kept for years: they must keep working, or be redirected, for as long as any
document is kept (docs/retention.md).

## Data

- **Stored on the site:** the Moodle address and the shared token (one
  option), and each answer for a minute (a transient), so a reload does not
  ask Moodle again. Nothing about the visitor.
- **Sent to Moodle:** the code and the visitor's IP address. Moodle counts
  the address, hashed, for half an hour (the rate limit) and writes it into
  the document's log as a verification (docs/retention.md says how long).
- **Shown:** only what the document itself prints, with the identity number
  masked. A PDF dropped on the page is compared with the original's SHA-256 in
  the visitor's browser and never uploaded.

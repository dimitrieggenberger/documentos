# Official documents (local_documents)

Moodle 5.2.1 local plugin for the school's official documents: constancias,
certificaciones, oficios, circulares, memorándums and convenios, numbered as
the Secretaría de Educación's nomenclature requires (Acuerdo 0583-SE-2018),
with versioned templates, corrections as new versions, recalls, and a QR code
that opens a public verification page.

- Install path: `local/documents`
- Design record: `docs/documents-plan.md`
- How long data is kept: `docs/retention.md`
- Draft decision record for the move into the `platform` repository: `docs/decision-draft.md`
- Public page: `wordpress/verificacion-europaschule` (WordPress plugin, shortcode `[verificacion_europaschule]`)

## Set up

1. Install the ZIP from `build-zip.ps1` (`dist/local_documents-<version>.zip`).
2. Site administration › Plugins › Local plugins › Official documents › Settings:
   school name, SACE code, jornada, city, issuing units, signatories,
   letterhead (logos, lines, footer, colour), profile fields, verification page and token.
3. Give the secretary `local/documents:view` and `local/documents:issue` (and,
   for authorisations, `local/documents:recordresponses` and `local/documents:export`),
   the director also `local/documents:recall` and `local/documents:managetemplates`.
4. On the website: install `dist/verificacion-europaschule-<version>.zip`, set the Moodle
   address and the same token, create the page with `[verificacion_europaschule]`, and
   enter its address as the verification page in Moodle.

## Build

```powershell
.\build-zip.ps1
```

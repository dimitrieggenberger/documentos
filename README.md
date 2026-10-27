# Official documents (local_documents)

Moodle 5.2.1 local plugin for the school's official documents: constancias,
certificaciones, oficios, circulares, memorándums and convenios, numbered as
the Secretaría de Educación's nomenclature requires (Acuerdo 0583-SE-2018),
with versioned templates, corrections as new versions, recalls, and a QR code
that opens a public verification page.

- Install path: `local/documents`
- Design record: `docs/documents-plan.md`
- Public page: `wordpress/es-verificacion` (WordPress plugin, shortcode `[es_verificacion]`)

## Set up

1. Install the ZIP from `build-zip.ps1` (`dist/local_documents-<version>.zip`).
2. Site administration › Plugins › Local plugins › Official documents › Settings:
   school name, SACE code, jornada, city, issuing units, signatories,
   letterhead (logos, lines, footer, colour), profile fields, verification page and token.
3. Give the secretary `local/documents:view` and `local/documents:issue`, the
   director also `local/documents:recall` and `local/documents:managetemplates`.
4. On the website: install `dist/es-verificacion-<version>.zip`, set the Moodle
   address and the same token, create the page with `[es_verificacion]`, and
   enter its address as the verification page in Moodle.

## Build

```powershell
.\build-zip.ps1
```

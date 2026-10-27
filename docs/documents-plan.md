# Official documents: design record

Started 2026-10-27. Sources: the Secretaría de Educación's *Instructivo de
Nomenclatura* (Acuerdo 0583-SE-2018, arts. 15-17) and its *Tabla de Retención
Documental* (series up to 15 June 2026).

## 1. What it does

The school issues constancias, certificaciones, oficios, circulares,
memorándums and convenios from the platform. Each document type has a
template (title, Spanish text with placeholders, the details the issuer fills
in, one or two signature blocks); a draft is prefilled from the platform,
lightly adjusted, previewed and issued. Issuing gives the official number,
freezes the text, writes a letter-size PDF once and records its SHA-256. A QR
code on the sheet opens the public verification page on the school's website.

## 2. Number

`SERIE-CORRELATIVO-SACE-AÑO-UNIDAD-JORNADA`, art. 17 for centros educativos:
`CERTRAD-075-050100012M02-2026-DCE-JM`, printed on the sheet with en dashes
and the school's name under it, as page 10 of the instructivo shows.

- **Series**: the siglas of the TRD (CONSES, CERNOT, OF, ...). A school's part
  of the TRD (34 series) is seeded; more are added on *Series and numbering*.
- **Correlative**: per series, year and issuing unit, at least three digits,
  never reused. Allocated under a lock inside the issuing transaction
  (`numbering::next()`), so a failed issue gives the number back and two
  secretaries never get the same one. A school that numbered on paper sets the
  last number used; it cannot go below one already issued.
- **SACE, jornada**: from the settings. Nothing is issued while the school
  name, SACE code or jornada is empty.
- **Year**: the year of the date printed on the document (año de creación).
- **Unit**: DCE, SDCE, SCE... from the settings; a type has a default, the
  draft may change it.

## 3. Versions and recall

- A **template** is never edited in place: every save of changed wording adds
  a version with a required change note; history and a line diff on the
  template page. An issued document keeps the version it came from.
- An issued **document** never changes. *Correct* starts version 2 under the
  **same number** with a new verification code; when it is issued, version 1
  reads "superseded by version 2, issued on ..." on the verification page.
  Decision to confirm with the school: SEDUC may expect a correction to get a
  new number instead; that would be a setting on the type.
- *Recall* (capability `recall`, separate from `issue`) marks the current
  version void with an internal reason and an optional public note. The
  number is not reused, the PDF is kept.
- Every step is in the document's history (`local_documents_log`): started,
  changed, preview, issued, downloaded, correction started, superseded,
  recalled, verified.

## 4. Data

Templates read placeholders, never compute: name and identity number (a
profile field named in the settings), the wording's agreement (el/la,
o/a, from a profile field or chosen on the draft), level and school year,
and, for documents with grades, the **published** quarter grades and the
report card's attendance lines from `\local_grades\local\documents_bridge`
(behind `class_exists`). Holds apply: a student with a grade hold gets no
document with grades. Staff: position and start date from profile fields,
years of service, courses taught.

## 5. Verification

- `verify_api.php` (JSON) and `verify.php` (page) are the only pages without
  a login. They answer only for issued versions and only with what the sheet
  prints: title, number, holder, masked identity number, dates, unit,
  signers, state (valid, expired, superseded, recalled) and the PDF's
  SHA-256. Never grades, never the PDF.
- 12-character Crockford base-32 codes (60 bits), typed with or without
  hyphens; O reads as 0, I and L as 1. 30 look-ups per address per 15
  minutes.
- With a token set, the API answers only the website, which passes on the
  visitor's address for the rate limit.
- `wordpress/es-verificacion` is the public page: shortcode
  `[es_verificacion]`, Spanish, asks the API server-side, caches a minute, and
  lets the visitor compare a PDF with the original's SHA-256 in the browser
  (the file is not uploaded).

## 6. The sheet

Letter (215.9 × 279.4 mm), TCPDF through Moodle's `pdflib`. Every measure is
a constant in `classes/local/letter_pdf.php`. Header: left and right logos,
school name, lines under it, a rule; optional faint watermark. Number block
top right. Title, body (justified), signature blocks. Footer on every page:
QR, number, code, the verification address, validity, page; a coloured band
with the address. Drafts print BORRADOR across the sheet and no number or QR.

**Figma**: the layout was built from the SEDUC membrete before the Figma frame
was available. To match it, read the frame (margins, logo box, type sizes,
QR position, band) and change the constants; nothing else depends on them.

## 7. Retention

Each series carries the TRD periods (5 years in the archivo de trámite, 10 in
concentración, then permanent for the seeded ones); the record shows until
when. Issued documents are never deleted, also not by a privacy request
(drafts and addresses in the log are).

## 8. Later

- An approval step (secretary prepares, director signs) before the number.
- Signature and seal images per signatory.
- The full TRD as an importable catalogue.
- Students and staff requesting a document from their own page.

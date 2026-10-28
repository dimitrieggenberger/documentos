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
- **SACE, jornada**: from the settings (section 8).
- **Year**: the year of the date printed on the document (año de creación).
- **Unit**: DCE, SDCE, SCE... the issuing units (section 9); a type has a
  default, the draft may choose another unit allowed for its type.

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
- The public page is **europaschule.eu/verificar** (the default of the
  "Verification page" setting); the QR opens `/verificar/?c=CODE`.
  Mockup: `dev/verify-mockup/` (states.php, build.py, shoot.js).
- `wordpress/es-verificacion` is the public page: shortcode
  `[es_verificacion]`, Spanish, asks the API server-side, caches a minute, and
  lets the visitor compare a PDF with the original's SHA-256 in the browser
  (the file is not uploaded).

## 6. The sheet

The letter of the brand manual, Figma "Letter" (EuropaSchule · Design
Sandbox, frame 39:250, component 109:508), built with TCPDF through Moodle's
`pdflib` in points, every measure a constant in `classes/local/letter_pdf.php`:

- US Letter 612 × 792 pt; margins 42.5 pt (1.5 cm) top and bottom, 85 pt
  (3 cm) left and right; Poppins (Regular, Bold, Italic), converted for TCPDF
  in `fonts/` (SIL Open Font License, `fonts/OFL.txt`).
- Header on every page: the seal for documents (66 pt), a rule, the school
  and its legal name, a rule, the issuing unit with its official e-mail and
  phone (Poppins 6 pt on 7.5, #6a737b; rules #dee2e6, 0.75 × 32 pt).
- 110 pt under the header: place and date on the left (letters only) and,
  on the right, the number with the school's name under it, as page 10 of the
  instructivo prints it; a correction adds "Versión 2: sustituye a…".
- Letters: the recipient (name in bold, position, institution, e-mail) 28 pt
  lower, then 52 pt to the title. Other documents: 40 pt to the title.
- Title Poppins Bold 14 pt on 17, sentence case ("Constancia de estudio");
  16 pt; body 8 pt on 10, justified, paragraphs 10 pt apart; grade and
  attendance tables at 7.5 pt with hairlines.
- Signature block 8 pt below: per signer 34 pt to sign by hand, name in
  bold, position, e-mail and phone (secondary grey); the stamp (78 pt, the
  simplified seal) beside them on issued documents. "Adjunto: …" 36 pt
  lower when the letter has an attachment.
- The watermark (380 pt, centred, top at 229 pt, 5 %) behind the page.
- Footer: europaschule.eu at the bottom left (92 × 15.9 pt); at the bottom
  right the verification lines (number, code, the verification address,
  validity, page) at 6 pt and the QR code (40 pt). The QR and the
  verification lines are this plugin's addition to the frame.
- Drafts print BORRADOR across the sheet, no number, no QR, no stamp.

The images ship in `pix/brand/` (transparent renders of the brand files the
Figma components use; local_grades' report card uses the same ones) and can be
replaced in the settings. The draft page's live preview follows the same
order at screen size (`styles.css`, "The live preview").

Template version 2 of the 18 ready-made templates (upgrade step 2026102800,
only where version 1 was never edited) brings sentence-case titles and, for
oficio, circular and memorándum, the recipient fields (destinatario, cargo,
institución, correo) and an optional attachment.

## 7. Retention

Each series carries the TRD periods (5 years in the archivo de trámite, 10 in
concentración, then permanent for the seeded ones); the record shows until
when. Issued documents are never deleted, also not by a privacy request
(drafts and addresses in the log are).

## 8. The school

Decided 2026-10-28, defaults of the settings: commercial name **EuropaSchule
San Pedro Sula** (the letterhead), official name **Centro Educativo No
Gubernamental Trilingüe EuropaSchule** (under the number and in the text,
`{{school.officialname}}`), legal name **Educación Helvética SA** (under the
commercial name in the letterhead), SACE code **050101072T03**. The jornada
stays JM until the school says otherwise.

## 9. Issuing units and signatures

- An **issuing unit** (`local_documents_unit`) is the fifth part of the
  number. An administrator (`local/documents:manageunits`) creates and edits
  units on `units.php`: code, name, official e-mail and phone (the header),
  the **Moodle user who signs for it** (always one; name and position as
  printed), and the **document types it may issue**
  (`local_documents_unittype`). A draft can only be issued by a unit allowed
  for its type.
- Each **document type** says whose signatures it requires
  (`local_documents_signer`): none, one or up to three units, in order, set on
  the template page. Defaults: constancias and staff documents, Dirección;
  certificaciones, Secretaría then Dirección; oficio and convenio, Dirección;
  circular and memorándum, **none**.
- **Sending a draft out** (`documents::submit()`): every required signature
  becomes an approval (`local_documents_approval`) for the unit's head; the
  sender signs at once for every unit they head. Nothing left to sign: issued
  there and then (so **a sole signer issues alone**, and a document without
  signatures goes out at once). Otherwise the document waits (`pending`,
  locked, no number yet) and each signer is notified.
- **Each signer approves** on `sign.php` (the queue of what waits for them,
  then the document: its sheet, PDF preview, attachments and, when the issuer
  adjusted the wording, the changes from the template). The last approval
  issues it: number, frozen text, PDF. A signer can **send it back** with a
  note (the draft opens again, every signature given is dropped); the sender
  can take it back to change it.
- The sheet prints, in each signer's room to sign, "Aprobado digitalmente el
  …"; the verification page says who signed and when.
- A new head of a unit takes over the signatures still waiting on it.
- Messages: `signaturerequest` to the signer, `signatureresult` to the sender
  (issued, or sent back with the note).

## 10. Attachments

A draft takes attachments (PDF, PNG, JPEG; up to ten, 20 MB each, file area
`attachments`). They are part of the verifiable document:

- the index at its foot lists each one with its size and **SHA-256**
  ("Adjuntos (forman parte de este documento)"), after the signatures;
- each file is **embedded in the issued PDF**, byte for byte (a paper clip
  next to its line opens it), so the document and its attachments travel as
  one file, and the PDF's own hash covers them;
- the verification page (and the website's) lists them with their hashes, and
  the visitor's file check accepts the document or any of its attachments;
- a correction carries the attachments over to its draft; signers download
  them before signing.

## 11. Editing text: the evaluation

Two different edits, two answers.

- **Templates** (`managetemplates`): yes. Every save is a version with a
  required change note, a history and a line diff; issued documents keep the
  version they came from.
- **One document's wording** (the issuer): per template (`allowbodyedit`).
  Constancias and certificaciones: **no**, their wording is the school's and
  SEDUC's, only the template's fields change (purpose, conduct, destination…).
  Letters (oficio, circular, memorándum, convenio): **yes**, the letter is
  the text. When a document's wording differs from its template, the signers
  see the changes before they sign.

**The editor: the plugin's markup with a toolbar, not a rich-text editor.**
Moodle's TinyMCE was considered and left out: it writes HTML with any font,
size, colour and pasted Word styling, which breaks the brand letter (Poppins,
fixed sizes and greys) and TCPDF's HTML subset; it makes template versions
hard to compare line by line; and its HTML would have to be cleaned before
every PDF. The markup is what the letter needs and nothing more:

| Mark | Prints |
|---|---|
| `**text**` | bold |
| `*text*` | italic |
| `= ` at the start of a paragraph | centred paragraph |
| `- ` at the start of each line | bulleted list |
| `{{key}}`, `{{key|before|after}}` | a value from the platform or a field |
| `{{tabla.notas}}`, `{{tabla.asistencia}}` | the published grades, the attendance lines |

`editor.js` puts a toolbar over the template text, a draft's wording and a
letter's text field (bold, italic, centre, list, and "insert a data field…"
where placeholders apply); it only writes those marks, and the field works
without JavaScript. The live preview beside the draft and the PDF preview show
the result. No colours, sizes or fonts, by design.

## 12. Later

- Signature and seal images per signer (today the approval date stands in
  the room to sign).
- A substitute head while the head is away.
- The full TRD as an importable catalogue.
- Students and staff requesting a document from their own page.
- A Certificación de estudios over several school years.

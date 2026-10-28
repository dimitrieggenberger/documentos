# Changelog

## [0.8.0] - 2026-10-28

### Added

- **Authorisations for families with a consent slip** (`docs/documents-plan.md` 15): series AUTOR and five types (excursion, data and images, health day, sharing the file with another school, extracurricular activity), issued by any unit and signed by its head. Each student gets the letter with a slip at its foot (AUTORIZO / NO AUTORIZO, what is authorised, lines for the adult's name, identity number, phone, date and signature), its own verification code and QR, and its own one-page PDF to send to the family. Students are picked on the draft by course, group or one by one; a type about one student carries their slip. On the record: the answers (authorises, does not, no answer), the date, a note and the returned slip scanned; counts and a CSV list. Verification answers for each slip's code without the answer. Upgrade 2026103400 (table `local_documents_slip`, `consent` on template versions).
- Template versions have a **consent text** (the slip's own part), edited with the same toolbar.
- WordPress plugin `es-verificacion` 0.6.0: how many students an authorisation went to.

### Fixed

- A document still waiting for signatures no longer reads as valid on the verification page.
- Required date fields no longer block saving a draft in the browser (the check is made when issuing).

## [0.7.0] - 2026-10-28

### Added

- **Files** (`docs/documents-plan.md` 14), first for transfers: a type names the documents that go with it (the constancia de traslado: conducta, solvencia, certificación de estudios; upgrade 2026103300). On its draft the issuer prepares the missing ones for the same student, adds documents already issued (going with the file, or handed to the family before) and names documents issued on paper before the platform. The file is issued only once every companion is issued; the sheet prints the index "Documentos que acompañan este expediente" with each number and how it travels (verifiable PDF and its code, original signed by hand, handed to the family on a date). The verification pages list them and say when one was recalled or replaced since.
- WordPress plugin `es-verificacion` 0.5.0: the list of documents that go with it.

## [0.6.0] - 2026-10-28

### Added

- **Signature mode per document type** (`docs/documents-plan.md` 12): electronic (signed on the platform) or handwritten (*firma autógrafa*), on the template page. Handwritten types are issued at once to be printed: lines to sign on, no printed stamp, and the footer "Documento de firma autógrafa: solo es válido con la firma y el sello originales."; the verification pages say only the signed original is valid. The templates list marks them.
- Upgrade 2026103200: the certificaciones (estudios, notas, conducta, trabajo docente) and the constancia de solvencia are signed by hand.

## [0.5.0] - 2026-10-28

### Added

- **Electronic signatures under the Ley sobre Firmas Electrónicas** (Decreto 149-2013, reformed by Decreto 33-2020; review in `docs/documents-plan.md` 12). Documents signed on the platform print in the footer "Firmado electrónicamente conforme a la Ley sobre Firmas Electrónicas (Decreto 149-2013)." (setting "Legal basis of the electronic signatures", frozen at issue, empty prints nothing); the verification pages repeat it and say what the platform keeps of each signature.
- **Evidence of each signature**: the address it was given from and the SHA-256 of exactly what was signed (upgrade 2026103100). Issuing refuses a document that changed after it was signed. The record shows the evidence to staff.
- A recommendation on the units page to turn on multi-factor authentication for those who sign, while it is off.

### Changed

- The room to sign reads "Firmado electrónicamente" with the date and time (was "Aprobado digitalmente el" with the date); the verification line likewise.
- WordPress plugin `es-verificacion` 0.4.0: shows the legal sentence under the facts.

## [0.4.0] - 2026-10-28

### Added

- **Substitution** (`docs/documents-plan.md` 9): a unit names who signs in substitution of its head, with an optional first and last day. While it is in effect the substitute receives and gives the unit's signatures (waiting ones move to them, with a message), and the sheet, the record and the verification page print "En sustitución de funciones de" and the head's name and position. The units list shows the substitution and whether it is in effect.
- **Gerencia General (GG) and Gerencia Académica (GA)** as issuing units (upgrade 2026103000; an administrator links their heads).
- `{{unit.name}}`: the issuing unit's name.

### Changed

- **Circular and memorándum are issued by every unit** and signed by the head of the issuing unit (new signature slot "the issuing unit" on the template page). A head who starts one starts it on their own unit and issues it alone. The memorándum's "De:" prints the issuing unit (template version 4 where nobody edited it).
- **Jornada EX** (Jornada Extendida) is the default sixth part of the number; the setting also offers JD (a distancia).

## [0.3.1] - 2026-10-28

### Fixed

- **Privacy metadata**: the signature messages are declared through the `core_message` subsystem (0.3.0 called a method that does not exist, so the privacy registry failed on this plugin).

## [0.3.0] - 2026-10-28

### Added

- **Issuing units with their signers** (`docs/documents-plan.md` 9). Units are records an administrator manages (`units.php`, capability `local/documents:manageunits`): code, name, e-mail and phone, the Moodle user who signs for it, and the document types it may issue. Upgrade step 2026102900 moves the old "units" setting there and drops the "signatories" setting.
- **Signatures per document type** on the template page: none, or up to three units in order (defaults: constancias Dirección; certificaciones Secretaría then Dirección; circular and memorándum unsigned).
- **The signature workflow**: a draft is sent to its signers (or issued at once when nobody else must sign: a sole signer issues alone); each head approves or sends it back with a note on `sign.php`; the last approval issues it. Pending documents are locked, have no number yet and can be taken back. The sheet prints "Aprobado digitalmente el …" for each signer, the verification page who signed and when. Messages to the signer and to the sender.
- **Attachments** that are part of the document: uploaded on the draft, listed with their SHA-256 at its foot, embedded in the issued PDF, listed on the verification pages; the website's file check accepts the document or any attachment.
- **A text toolbar** (`editor.js`) over template text, a draft's wording and a letter's text: bold, italic (new), centred paragraph, bulleted list (new), insert a data field. Rich-text editing evaluated and left out (`docs/documents-plan.md` 11). Constancias and certificaciones keep their template's wording; letters stay editable.

### Changed

- **The school's names and code**: commercial name EuropaSchule San Pedro Sula, official name Centro Educativo No Gubernamental Trilingüe EuropaSchule (new `{{school.officialname}}`, printed under the number and in the texts), legal name Educación Helvética SA, SACE 050101072T03, as the settings' defaults. Template version 3 for every ready-made template nobody edited.
- WordPress plugin `es-verificacion` 0.3.0: attachments with their hashes, the file check against any of them.

## [0.2.1] - 2026-10-28

### Changed

- **The verification page is europaschule.eu/verificar** by default (setting "Verification page"); the simulation keeps its own page. The WordPress plugin `es-verificacion` 0.2.0 marks each answer with an icon (check, exclamation mark, cross) besides its colour, offers "Elegir el PDF" as a button instead of the browser's file field, and stacks the code field and its button on phones. Mockup `dev/verify-mockup/` (the shortcode's real output in a copy of a live page of the website, every state, desktop and phone).

## [0.2.0] - 2026-10-28

### Changed

- **The sheet is the brand manual's letter** (Figma "Letter", `docs/documents-plan.md` 6). US Letter in points, margins 1.5 / 3 cm, Poppins shipped in `fonts/` (OFL); header with the seal, the school and its legal name, the issuing unit with its e-mail and phone; place and date with the number on the right; the recipient as a block of its own; sentence-case title at 14 pt; body 8 pt on 10; signature block with each signer's e-mail and phone and the stamp beside it; attachment line; the pale seal behind the page; europaschule.eu and the page at the bottom, with the verification lines and the QR code. Brand images in `pix/brand/`, replaceable in the settings (seal, watermark, stamp, footer logo). New settings: legal name; units and signatories take an e-mail and a phone. Removed: header lines, footer band text, letterhead colour, second logo (upgrade step 2026102800 clears them). Template version 2 for every ready-made template nobody edited (titles, recipient fields, attachment). The draft page's live preview follows the letter; its form labels sit above their fields on Space.

## [0.1.1] - 2026-10-27

### Fixed

- **Page context set before forms are built** (draft, record, template and download pages), so no "$PAGE->context was not set" notice. The sim scenario picks the seeded Valentina Ríos Castro, now that the full school has two students called Valentina Ríos.

## [0.1.0] - 2026-10-27

### Added

- **Official documents with SEDUC numbering** (`docs/documents-plan.md`). Document types with versioned Spanish templates (18 seeded: constancias de estudio, matrícula, conducta, notas, solvencia, traslado, cupo; certificaciones de estudios, notas, conducta, trabajo docente; constancias de trabajo and años de servicio; oficio, circular, memorándum, convenio) on 34 series of the Tabla de Retención Documental. Drafts prefilled from the platform (published grades and attendance through local_grades' documents bridge), adjusted, previewed, issued with the number `SERIE-CORRELATIVO-SACE-AÑO-UNIDAD-JORNADA`, frozen, written once as a letter-size PDF with its SHA-256. Corrections as new versions under the same number, recalls with a public note, a history per document, template history with diffs, correlatives per series/year/unit with a starting value. Public verification (`verify.php`, `verify_api.php`, rate-limited, optional token) and the WordPress plugin `es-verificacion`. Strings en/es/de, privacy provider, PHPUnit `rules_test`, `documents_test`; sim seed and scenario.

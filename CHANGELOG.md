# Changelog

## [0.2.0] - 2026-10-28

### Changed

- **The sheet is the brand manual's letter** (Figma "Letter", `docs/documents-plan.md` 6). US Letter in points, margins 1.5 / 3 cm, Poppins shipped in `fonts/` (OFL); header with the seal, the school and its legal name, the issuing unit with its e-mail and phone; place and date with the number on the right; the recipient as a block of its own; sentence-case title at 14 pt; body 8 pt on 10; signature block with each signer's e-mail and phone and the stamp beside it; attachment line; the pale seal behind the page; europaschule.eu and the page at the bottom, with the verification lines and the QR code. Brand images in `pix/brand/`, replaceable in the settings (seal, watermark, stamp, footer logo). New settings: legal name; units and signatories take an e-mail and a phone. Removed: header lines, footer band text, letterhead colour, second logo (upgrade step 2026102800 clears them). Template version 2 for every ready-made template nobody edited (titles, recipient fields, attachment). The draft page's live preview follows the letter; its form labels sit above their fields on Space.

## [0.1.1] - 2026-10-27

### Fixed

- **Page context set before forms are built** (draft, record, template and download pages), so no "$PAGE->context was not set" notice. The sim scenario picks the seeded Valentina Ríos Castro, now that the full school has two students called Valentina Ríos.

## [0.1.0] - 2026-10-27

### Added

- **Official documents with SEDUC numbering** (`docs/documents-plan.md`). Document types with versioned Spanish templates (18 seeded: constancias de estudio, matrícula, conducta, notas, solvencia, traslado, cupo; certificaciones de estudios, notas, conducta, trabajo docente; constancias de trabajo and años de servicio; oficio, circular, memorándum, convenio) on 34 series of the Tabla de Retención Documental. Drafts prefilled from the platform (published grades and attendance through local_grades' documents bridge), adjusted, previewed, issued with the number `SERIE-CORRELATIVO-SACE-AÑO-UNIDAD-JORNADA`, frozen, written once as a letter-size PDF with its SHA-256. Corrections as new versions under the same number, recalls with a public note, a history per document, template history with diffs, correlatives per series/year/unit with a starting value. Public verification (`verify.php`, `verify_api.php`, rate-limited, optional token) and the WordPress plugin `es-verificacion`. Strings en/es/de, privacy provider, PHPUnit `rules_test`, `documents_test`; sim seed and scenario.

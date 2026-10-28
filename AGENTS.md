# AGENTS.md

## Purpose

This repository is the source of truth for the Moodle plugin `local_documents`.

- Moodle target: Moodle 5.2.1
- Plugin type: `local`
- Component: `local_documents`
- Moodle install path: `local/documents`
- GitHub repo: `dimitrieggenberger/documentos`
- Installable ZIPs are build artifacts only, created by `build-zip.ps1` (it also builds the WordPress plugin ZIP)

The shared rulebook for sibling plugins is `docs/shared-plugin-instructions.md` in `dimitrieggenberger/moodle-local-grades`; follow it (UI reference, styling hierarchy, Working Screens, sizes, mockups, sim). The design record of this plugin is `docs/documents-plan.md`.

## Main Files

- `index.php`: the registry (tiles, filters, export)
- `create.php`: pick a document type, then the person
- `edit.php`: the draft (form beside a live preview), preview PDF, issue with confirmation
- `view.php`: one document: state, number, code, hash, retention, signatures, attachments, versions, history; correct (new version), recall, take back from its signers
- `sign.php`: what waits for my signature, and one document to approve or send back (open to a unit's head without other capabilities)
- `units.php`, `classes/form/unit_form.php`: issuing units, each linked to the Moodle user who signs for it, and the types each may issue (`local/documents:manageunits`)
- `editor.js`: the toolbar over template and letter text (writes the plugin's markup only)
- `download.php`: the stored PDF (issued), or a marked preview (draft)
- `templates.php`, `template_edit.php`: document types and their versioned templates (history, diff)
- `series.php`: the TRD series catalogue and the correlatives
- `verify.php`, `verify_api.php`: public verification (the only pages without login)
- `classes/local/numbering.php`: number format, correlatives, verification codes
- `classes/local/documents.php`: draft, submit (to sign or issue), approve, reject, withdraw, issue, revise, recall, attachments, log
- `classes/local/slips.php`, `response.php`: authorisations for families: a consent slip per student (own code, own PDF), the students picked by course or group, and the answers recorded from the returned slips
- `classes/local/companions.php`: files: the documents that go with another one (a traslado), prepared, added or named on paper, and the index the sheet prints
- `classes/local/units.php`: issuing units, the types they issue, the signatures each type requires (unit 0: the issuing unit), who signs for a unit now (head or substitute)
- `classes/local/notifications.php`: signature requested, issued, sent back
- `classes/local/templates.php`: template versions, markup, placeholders, diff
- `classes/local/sources.php`: placeholder values; reads `\local_grades\local\documents_bridge` behind `class_exists`
- `classes/local/pdf.php`, `classes/local/letter_pdf.php`: the letter-size sheet, the brand manual's letter (Figma frame 39:250); measures are constants in `letter_pdf.php`, Poppins in `fonts/`, brand images in `pix/brand/`
- `classes/local/verify.php`: the public answer and the rate limit
- `classes/local/catalog.php`: seeded series and templates (Spanish: the documents' language)
- `classes/local/spanish.php`: dates in words, grade names
- `wordpress/es-verificacion/`: the WordPress plugin for the public website

## Boundaries

This plugin owns: the document series catalogue, document types and templates, issuing units and their signers, the signature workflow, numbering and correlatives, issued documents with their attachments and PDFs, versions and recalls, and public verification.

It must not own or recalculate: grades, publication, holds, attendance (read from local_grades' documents bridge), Student Records data, theme layout.

## Rules

- Same security, Moodle, UI, build, testing and git rules as `local_grades` (see the shared rulebook). Strings in en, es, de; the sheet's own wording (`pdf*` strings) stays Spanish in all three.
- An issued document never changes: corrections are new versions, mistakes are recalls. Never delete an issued document.
- Every required signature of an electronically signed type is an approval by the unit's head on the platform; never issue such a document while it still waits for one. A type signed by hand (`signaturemode` handwritten: certificaciones, solvencia) is issued to be printed and is valid only with the original signature and seal; never print the stamp image or cite the electronic-signature law on it.
- Text is the plugin's markup (bold, italic, centred, list, placeholders), never free HTML: see docs/documents-plan.md 11.
- The public pages show only what the sheet prints. Never add grades or the PDF to them.
- Bump `version.php` before committing plugin changes.

## Simulation Moodle

- `sim sync documentos` (moodle-sim's `plugins.conf` line leaves `wordpress/` out), seed `dev/sim/seed_documents.php`: units DCE (director `rgomez` / `Test123!`), SCE (secretary `ecastillo` / `Sim-Docs-2026!`), SDCE (`portiz` / `Test123!`), GG (`mfuentes` / `Sim-Docs-2026!`), GA (`lbonilla` / `Sim-Docs-2026!`); jornada EX; no substitution (the scenario sets one up and removes it).
- Scenario: `node dev/sim/scenario_documents.js`.
- PHPUnit: `sim phpunit --testsuite local_documents_testsuite`.

# AGENTS.md

## Purpose

This repository is the source of truth for the Moodle plugin `local_documents`.

- Moodle target: Moodle 5.2.1
- Plugin type: `local`
- Component: `local_documents`
- Moodle install path: `local/documents`
- GitHub repo: `dimitrieggenberger/moodle-local-documents`
- Installable ZIPs are build artifacts only, created by `build-zip.ps1` (it also builds the WordPress plugin ZIP)

The shared rulebook for sibling plugins is `docs/shared-plugin-instructions.md` in `dimitrieggenberger/moodle-local-grades`; follow it (UI reference, styling hierarchy, Working Screens, sizes, mockups, sim). The design record of this plugin is `docs/documents-plan.md`.

## Main Files

- `index.php`: the registry (tiles, filters, export)
- `create.php`: pick a document type, then the person
- `edit.php`: the draft (form beside a live preview), preview PDF, issue with confirmation
- `view.php`: one document: state, number, code, hash, retention, versions, history; correct (new version), recall
- `download.php`: the stored PDF (issued), or a marked preview (draft)
- `templates.php`, `template_edit.php`: document types and their versioned templates (history, diff)
- `series.php`: the TRD series catalogue and the correlatives
- `verify.php`, `verify_api.php`: public verification (the only pages without login)
- `classes/local/numbering.php`: number format, correlatives, verification codes
- `classes/local/documents.php`: draft, issue, revise, recall, log
- `classes/local/templates.php`: template versions, markup, placeholders, diff
- `classes/local/sources.php`: placeholder values; reads `\local_grades\local\documents_bridge` behind `class_exists`
- `classes/local/pdf.php`, `classes/local/letter_pdf.php`: the letter-size sheet
- `classes/local/verify.php`: the public answer and the rate limit
- `classes/local/catalog.php`: seeded series and templates (Spanish: the documents' language)
- `classes/local/spanish.php`: dates in words, grade names
- `wordpress/es-verificacion/`: the WordPress plugin for the public website

## Boundaries

This plugin owns: the document series catalogue, document types and templates, numbering and correlatives, issued documents and their PDFs, versions and recalls, and public verification.

It must not own or recalculate: grades, publication, holds, attendance (read from local_grades' documents bridge), Student Records data, theme layout.

## Rules

- Same security, Moodle, UI, build, testing and git rules as `local_grades` (see the shared rulebook). Strings in en, es, de; the sheet's own wording (`pdf*` strings) stays Spanish in all three.
- An issued document never changes: corrections are new versions, mistakes are recalls. Never delete an issued document.
- The public pages show only what the sheet prints. Never add grades or the PDF to them.
- Bump `version.php` before committing plugin changes.

## Simulation Moodle

- `sim sync moodle-local-documents` (moodle-sim's `plugins.conf` line leaves `wordpress/` out), seed `dev/sim/seed_documents.php` (secretary `ecastillo` / `Sim-Docs-2026!`, director `rgomez` / `Test123!`).
- Scenario: `node dev/sim/scenario_documents.js`.
- PHPUnit: `sim phpunit --testsuite local_documents_testsuite`.

# Draft decision record for the platform repository

This is a **draft**, written in `documentos` so that it can be discussed
before `local_documents` moves into `platform` (summer 2027). When the plugin
moves, it becomes `platform/docs/decisions/NNNN-official-documents.md` in the
same pull request, with the next free number. It follows the platform's
template (`docs/decisions/0000-template.md`). Everything marked *to decide*
is open, and nothing in it is policy yet (Constitution, Article 2.3).

---

# NNNN. Official documents: local_documents and its verification page

- **Status:** proposed
- **Date:** *to decide*
- **Decided by:** *to decide*. Name the role: academic leadership for what
  families and students receive and sign, technical maintainer for the names
  and the implementation (Article 2.2).

## Context

The Secretaría and the school's units write constancias, certificaciones,
oficios, circulares, memorándums and authorisations for families by hand, in
word processors. Numbering follows SEDUC's *Instructivo de Nomenclatura*
(Acuerdo 0583-SE-2018), and keeping follows its Tabla de Retención
Documental. Nobody outside the school can check whether a paper is genuine.

`local_documents` (Moodle local plugin, repository `documentos` until the
move) issues these documents from templates. Grades are read from
`local_grades` through its documents bridge. The plugin numbers each
document, has it signed by the head of the issuing unit, freezes it as a
PDF, and prints a QR code. The QR code opens a public verification page on
the website (WordPress plugin `verificacion-europaschule`).

## Decision

`local_documents` is the system of record for the documents the school
issues: their number, signed content, PDF, status (issued, replaced,
recalled) and signature evidence. It owns nothing else. Grades stay with
`local_grades`. Students, families and guardians stay with `essis`. Policy
stays with the Institutional Handbook.

### Permanent names (Article 8.2)

From its first day in production:

- Component `local_documents`, install folder `local/documents`.
- Tables: `local_documents_series`, `_type`, `_tplver`, `_doc`, `_counter`,
  `_log`, `_unit`, `_unittype`, `_signer`, `_approval`, `_slip`.
- Capabilities: `local/documents:view`, `:issue`, `:recall`,
  `:recordresponses`, `:export`, `:manageunits`, `:managetemplates`.
- Used from `local_grades`: none. `local_documents` uses
  `\local_grades\local\documents_bridge`, behind `class_exists`. That class
  is permanent for `local_grades` (listed in the inventory).
- WordPress: folder `verificacion-europaschule`, shortcode
  `[verificacion_europaschule]`, prefix `es_ver_`, option `es_ver_settings`.
- **Printed on paper, so permanent beyond any release:** the address
  `https://europaschule.eu/verificar/?c=CODE` and the code format (12
  characters of `0-9 A-H J K M N P-T V-Z`, shown in groups of four). The page,
  or a redirect to its successor, answers for as long as the longest-kept
  document.

### Rules it applies, with their source (Article 2.4)

| Rule | Source | Status |
|---|---|---|
| Number: SERIE-CORRELATIVO-SACE-AÑO-UNIDAD-JORNADA | Acuerdo 0583-SE-2018, arts. 15-17 | Existing law |
| Series and retention periods | TRD, same Acuerdo | Seeded. Periods to check series by series (docs/retention.md) |
| Electronic signature for most documents; handwritten for certificaciones, solvencias and public instruments | The school's rule of 19 November 2026 (docs/documents-plan.md 12) | Decided by Dimitri Eggenberger; *to move into the Handbook* |
| Legal basis of the platform signature | Decreto 149-2013; Decreto 33-2020, art. 38 | Existing law |
| Publication of a student's image | Handbook page 116, version 3, 2026-07-26 | Approved policy |
| Who signs for each unit, substitutes | Settings under *Issuing units* | Configuration; *to record which leader set it* |

## Alternatives considered

- **Keep writing documents by hand.** No numbering control and no
  verification, and the TRD is kept by memory.
- **A commercial e-signature or document service.** A paid outside service
  needs its own decision and an exit plan (Article 7.3). Families' data would
  also leave the school's servers. The platform signature is enough for the
  documents the rule allows. An advanced signature with a certificate is
  deferred (docs/documents-plan.md 12).
- **Generate documents in `local_grades`.** That would mix the record of
  grades with the record of documents. A bridge keeps one owner each.

## Decision tests

1. **Educational value:** families and students get correct documents
   faster, and anyone they show a document to can check it. Indirect value:
   the Secretaría spends less time on paper.
2. **Foundations:** no effect on learning. Authorisations must not replace
   the conversation with families where one is needed.
3. **Authority:** see the table of rules. *Open:* the signature rule and the
   authorisations procedure need their Handbook pages.
4. **Evidence:** *to write.* How many documents a year, how long one takes
   today, and the errors seen.
5. **Stakeholder clarity:** the footer says how the document is signed and
   where to check it. The verification page shows only what the paper
   prints. *Open:* the page is Spanish only (Article 13.3; decision 0010).
6. **Human agency:** a person issues and the head of the unit signs every
   document. A wrong document is recalled or replaced by a new version, and
   verification says so.
7. **Privacy and permissions:** seven capabilities, with view, issue,
   recall, record answers and export kept apart. Only the head of the unit
   (or their substitute) signs. Every document action is
   logged. Retention is in docs/retention.md. *Open:* the retention periods
   to decide, and the visitor addresses kept in the log.
8. **Consistency:** grades come from `local_grades`. *Open:* consent answers
   from families belong in the student's profile in `essis` (Handbook page
   116). A bridge is needed so that `local_documents` does not become a
   second record of consent.
9. **Feasibility:** plain Moodle APIs, TCPDF from Moodle core, fonts and QR
   served locally, and no outside service. Tested with PHPUnit and a browser
   scenario in moodle-sim. *Open:* the move from Space to the Boost-based
   theme on Moodle 5.3 (decisions 0003, 0004).
10. **Evaluation and reversibility:** the pilot below. If it is
    discontinued, issued documents stay readable and verifiable, and new ones
    are written by hand again.

## Pilot (proposal)

- **Who:** the Secretaría of the Centro Educativo and one Gerencia, for
  constancias, certificaciones (signed by hand) and circulares; then one
  outing authorisation for one grade.
- **When:** *to decide*. For example from January 2027 to the end of that
  school year, reviewed in summer 2027, together with the move into
  `platform`.
- **Success:** every document of the pilot numbered without a gap or a
  duplicate; verification works from a phone; the Secretaría takes less time
  per document than today; no document recalled because of the tool.
- **If discontinued:** issued documents stay in Moodle and verifiable, and
  the plugin is set to issue nothing new.

## Consequences

- Moving into `platform/moodle/local/documents` and
  `platform/wordpress/plugins/verificacion-europaschule`, with their history.
  `documentos` is archived, with a README pointing to the new folders.
- Releases tagged `local_documents/vX.Y.Z`. ZIPs are built and attached,
  never committed.
- `docs/architecture/systems-of-record.md`: official documents →
  `local_documents`. `docs/inventory/dependencies.md`: TCPDF (Moodle core),
  Poppins (OFL, metrics of the space changed, docs/documents-plan.md 6), the
  QR encoder (TCPDF), and the Font Awesome icon.
- Spanish procedures in `docs/operations/` for the Secretaría: issue, sign,
  substitute a signer, record answers, recall, and transfer under the TRD.
- Before production: decide the retention periods, write the Handbook pages
  the rules cite, and translate the verification page.

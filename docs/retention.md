# Retention: how long each kind of data is kept

The platform's constitution asks every component to say how long each kind
of data is kept and how it is removed (Article 5.3). This page does that for
`local_documents`. Each row separates what the plugin **does today**
(existing behaviour) from the period it **should** have. Every period below
is a **proposal** until the person named in the last column decides it
(Article 2.3). Where a decision is still open, the plugin keeps the data and
deletes nothing.

## What is kept

| Data | Where | Today | Proposal | Decides |
|---|---|---|---|---|
| Issued document: record, frozen content, PDF, attachments, list of companion documents | `local_documents_doc`, file areas `issued` and `attachments` | Never deleted, also not by a privacy request, because the school must keep it. The series shows the TRD periods. | The TRD of the series (Acuerdo 0583-SE-2018): archivo de trámite, then concentración, then permanent or eliminated. **Assumption to check:** every seeded series has 5 + 10 years and "permanent". That is one default for all series, not yet checked against the TRD, series by series. | Secretaría, with the TRD |
| Earlier versions and recalled documents | the same | Kept. Verification says "replaced" or "recalled". | Like the issued document: the verification page must keep answering for a recalled document. | Secretaría |
| Signature evidence: who approved, when, from which address, SHA-256 of what was signed | `local_documents_approval` | Kept with the document. | Like the document. It is the proof of the electronic signature (Ley sobre Firmas Electrónicas, Decreto 149-2013, and Decreto 33-2020, art. 38.C; docs/documents-plan.md 12). | Dirección |
| Drafts never issued | `local_documents_doc` with status `draft` | Kept until someone deletes them. A privacy request deletes them. | Delete a draft nobody has touched for 12 months (a scheduled task, **not built**). | Secretaría |
| Authorisation letter (series AUTOR) | the issued document | As an issued document. | The TRD of series 10, Autorización. | Secretaría |
| Answer on each consent slip: the student, the code, the answer, its date, the note, who recorded it | `local_documents_slip` | Kept with the letter. | Outings, health days, extracurricular activities: until the end of the next school year, then remove the note and the scan and keep only the answer (**not built**). Image publication: as long as the image is published, and at least until the two-year review (Handbook page 116). Sharing a student file: like the transfer record. | Dirección with Gerencia Académica |
| Scan of the signed paper slip | file area `slipscan` | Kept with the slip. | Like the answer on the slip. | Dirección with Gerencia Académica |
| PDF of each student's letter and slip | file area `slippdf` | Kept. It is what the family received, and its SHA-256 is verified. | Like the letter. | Secretaría |
| Notes that hold health information (allergies on a health-day slip) | `local_documents_slip.note` | Kept with the slip. | Delete after the health day. The student's health record belongs to `essis`, not here (Article 5.1). | Dirección |
| Log of each document: created, edited, issued, downloaded, revised, recalled, verified | `local_documents_log` | Kept. A privacy request clears the person's own addresses (`ip`). | Like the document, but remove addresses after 12 months (**not built**). | Dirección |
| Address of a public visitor who verified a document | `local_documents_log.info` of `verified` entries, with `userid` 0 | Kept, and a privacy request does **not** clear it (no user to match). | Remove after 30 days and keep only the date (**not built**). | Dirección |
| Rate limit of the public verification | Moodle cache `verifythrottle`, hashed address | 30 minutes, then gone. | No change. | — |
| Answer cached by the website | WordPress transient `es_ver_*` | 1 minute. | No change. | — |
| Template versions | `local_documents_tplver` | Kept. Each document records the version it used. | Permanent: they show what a document said. | Secretaría |

## Kept forever on purpose

- **The verification address and the code format.** Every QR code printed on
  paper points to `https://europaschule.eu/verificar/?c=CODE`. The page, or a
  redirect to its successor, must answer for as long as the longest-kept
  document (permanent, for the seeded series).
- **Numbering.** A number given out is never given out again
  (`local_documents_counter`), whether or not its document is kept.

## How data is removed

- **A privacy request** (Moodle's privacy API, `classes/privacy/provider.php`)
  deletes drafts and their slips and clears the person's addresses in the
  log. It never deletes an issued document. The school's legal obligation to
  keep official documents comes first, and the privacy export lists them.
- **The periods marked "not built"** need a scheduled task. It will be built
  once the periods are decided. Until then nothing is removed automatically.
- **An issued document** leaves only by the TRD's own procedure (transfer
  to concentración, then elimination by an acta). That procedure is still to
  be written in Spanish for the Secretaría (Article 3.5).

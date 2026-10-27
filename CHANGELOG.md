# Changelog

## [0.1.0] - 2026-10-27

### Added

- **Official documents with SEDUC numbering** (`docs/documents-plan.md`). Document types with versioned Spanish templates (18 seeded: constancias de estudio, matrícula, conducta, notas, solvencia, traslado, cupo; certificaciones de estudios, notas, conducta, trabajo docente; constancias de trabajo and años de servicio; oficio, circular, memorándum, convenio) on 34 series of the Tabla de Retención Documental. Drafts prefilled from the platform (published grades and attendance through local_grades' documents bridge), adjusted, previewed, issued with the number `SERIE-CORRELATIVO-SACE-AÑO-UNIDAD-JORNADA`, frozen, written once as a letter-size PDF with its SHA-256. Corrections as new versions under the same number, recalls with a public note, a history per document, template history with diffs, correlatives per series/year/unit with a starting value. Public verification (`verify.php`, `verify_api.php`, rate-limited, optional token) and the WordPress plugin `es-verificacion`. Strings en/es/de, privacy provider, PHPUnit `rules_test`, `documents_test`; sim seed and scenario.

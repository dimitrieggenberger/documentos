<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_documents\local;

/**
 * What a new install starts with: the school's part of the Tabla de
 * Retención Documental and one ready-made template per document.
 *
 * The series are copied from the TRD of the Secretaría de Educación
 * (version of 15 June 2026); the full table has some 500 series, of which a
 * school issues a few dozen. More can be added on the Series page. Templates
 * are Spanish on purpose: they are the documents' own language, not UI text.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalog {

    /**
     * The seeded series: code => [name, TRD code, parent code, documentary value].
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function series(): array {
        return [
            'CER' => ['Certificación', '20', '', 'legal'],
            'CERES' => ['Certificación de Estudios', '20.01', 'CER', 'legal'],
            'CERNOT' => ['Certificación de Notas', '20.02', 'CER', 'legal'],
            'CERTRAD' => ['Certificación de trabajo Docente en C.E.', '20.03', 'CER', 'legal'],
            'CERTRADM' => ['Certificación de trabajo personal Adm (S.C.) en Centro Educativo', '20.06', 'CER', 'legal'],
            'CERAGRA' => ['Certificación de Acta de Graduación', '20.09', 'CER', 'legal'],
            'CERCOND' => ['Certificación de Conducta', '20.14', 'CER', 'legal'],
            'CONS' => ['Constancia', '25', '', 'legal'],
            'CONSAS' => ['Constancia de Años de Servicio', '25.01', 'CONS', 'administrativo'],
            'CONSASICL' => ['Constancia de asistencia a clase', '25.03', 'CONS', 'administrativo'],
            'CONSCOND' => ['Constancia de Conducta', '25.07', 'CONS', 'administrativo'],
            'CONSCU' => ['Constancia de Cupo', '25.08', 'CONS', 'administrativo'],
            'CONSES' => ['Constancia de Estudio', '25.11', 'CONS', 'administrativo'],
            'CONSMAT' => ['Constancia de Matrícula', '25.18', 'CONS', 'administrativo'],
            'CONSN' => ['Constancia de Notas', '25.19', 'CONS', 'administrativo'],
            'CONSPP' => ['Constancia de Práctica Profesional', '25.21', 'CONS', 'administrativo'],
            'CONSSOL' => ['Constancia de Solvencia', '25.22', 'CONS', 'administrativo'],
            'CONSTR' => ['Constancia de Trabajo', '25.23', 'CONS', 'administrativo'],
            'CONSTPA' => ['Constancia de Trabajo Personal Administrativo', '25.25', 'CONS', 'administrativo'],
            'CONSTPD' => ['Constancia de Trabajo Personal Docente', '25.26', 'CONS', 'administrativo'],
            'CONSTL' => ['Constancia de Traslado', '25.27', 'CONS', 'administrativo'],
            'ACT' => ['Actas', '2', '', 'legal'],
            'ACTENN' => ['Acta de Entrega de Notas', '2.10', 'ACT', 'legal'],
            'BOL' => ['Boleta de Notas', '15', '', 'administrativo'],
            'CAR' => ['Carta', '17', '', 'legal'],
            'CIR' => ['Circular', '22', '', 'administrativo'],
            'COMU' => ['Comunicados o comunicación', '31', '', 'administrativo'],
            'CONV' => ['Convenios', '29', '', 'legal'],
            'CONVC' => ['Convocatoria', '30', '', 'administrativo'],
            'INFO' => ['Informe', '68', '', 'administrativo'],
            'INVI' => ['Invitaciones', '72', '', 'administrativo'],
            'MM' => ['Memorándum', '84', '', 'administrativo'],
            'OF' => ['Oficio', '94', '', 'administrativo'],
            'SOMAT' => ['Solicitudes de Materiales', '135.02', '', 'administrativo'],
        ];
    }

    /**
     * The seeded document types with their first template version.
     *
     * Each entry: series code, name, subject, source, validity in days, title,
     * body, fields (line format, see templates::parse_fields()), the units whose
     * signatures it requires (codes, in order; none: it goes out unsigned) and
     * whether the issuer may adjust its wording (letters yes; constancias and
     * certificaciones keep the template's text, their details go in the fields).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function types(): array {
        $intro = "La Dirección del **{{school.officialname}}**, código SACE {{school.sace}}, de la ciudad de "
            . "{{school.city}}, departamento de {{school.department}}:\n\n= **HACE CONSTAR:**";
        $closing = "Y para los fines que {{g.al}} interesad{{g.o}} estime convenientes, se extiende la presente "
            . "en la ciudad de {{school.city}}, {{date.words}}.";
        $certintro = "La Secretaría del **{{school.officialname}}**, código SACE {{school.sace}}, de la ciudad de "
            . "{{school.city}}, departamento de {{school.department}}:\n\n= **CERTIFICA:**";
        $certclosing = "Y para los fines legales que {{g.al}} interesad{{g.o}} estime convenientes, se extiende la "
            . "presente certificación en la ciudad de {{school.city}}, {{date.words}}.";
        $purpose = "proposito | Para presentar ante (opcional) | text | |";
        // The letter of the brand manual: the recipient is a block of its own (name, position,
        // institution, e-mail) before the title.
        $recipient = "destinatario | Destinatario | text | | required\n"
            . "cargo | Cargo del destinatario | text | |\n"
            . "institucion | Institución | text | |\n"
            . "correo | Correo del destinatario | text | |";
        $letterfields = "$recipient\n"
            . "asunto | Asunto | text | | required\n"
            . "saludo | Saludo | text | Estimado(a) señor(a): | required\n"
            . "texto | Texto | textarea | | required";

        return [
            'constancia_estudio' => [
                'series' => 'CONSES', 'name' => 'Constancia de Estudio', 'subject' => 'student', 'source' => 'basic',
                'validity' => 90, 'title' => 'Constancia de estudio', 'signers' => ['DCE'], 'allowbodyedit' => 0, 'fields' => $purpose,
                'body' => "$intro\n\nQue {{g.el}} estudiante **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, está matriculad{{g.o}} y cursa el **{{student.gradename}}** en esta "
                    . "institución durante el año lectivo {{student.yearlabel}}, en la {{school.jornada}}."
                    . "{{f.proposito| La presente se extiende para ser presentada ante |.}}\n\n$closing",
            ],
            'constancia_matricula' => [
                'series' => 'CONSMAT', 'name' => 'Constancia de Matrícula', 'subject' => 'student', 'source' => 'basic',
                'validity' => 90, 'title' => 'Constancia de matrícula', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => "fechamatricula | Fecha de matrícula | date | |\n$purpose",
                'body' => "$intro\n\nQue {{g.el}} estudiante **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, se encuentra debidamente matriculad{{g.o}} en el **{{student.gradename}}** "
                    . "para el año lectivo {{student.yearlabel}}, en la {{school.jornada}}{{f.fechamatricula|, desde el }}."
                    . "\n\n$closing",
            ],
            'constancia_conducta' => [
                'series' => 'CONSCOND', 'name' => 'Constancia de Conducta', 'subject' => 'student', 'source' => 'basic',
                'validity' => 90, 'title' => 'Constancia de conducta', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => "conducta | Conducta | select | Muy buena | Excelente;Muy buena;Buena;Satisfactoria\n$purpose",
                'body' => "$intro\n\nQue {{g.el}} estudiante **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, del **{{student.gradename}}**, año lectivo {{student.yearlabel}}, ha observado "
                    . "en esta institución una conducta **{{f.conducta}}**, cumpliendo con el reglamento interno del "
                    . "centro educativo.\n\n$closing",
            ],
            'constancia_notas' => [
                'series' => 'CONSN', 'name' => 'Constancia de Notas', 'subject' => 'student', 'source' => 'grades',
                'validity' => 90, 'title' => 'Constancia de notas', 'signers' => ['DCE'], 'allowbodyedit' => 0, 'fields' => $purpose,
                'body' => "$intro\n\nQue {{g.el}} estudiante **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, del **{{student.gradename}}**, año lectivo {{student.yearlabel}}, ha obtenido "
                    . "las siguientes calificaciones publicadas hasta el {{student.lastquarter}}:\n\n{{tabla.notas}}"
                    . "\n\n$closing",
            ],
            'constancia_solvencia' => [
                'series' => 'CONSSOL', 'name' => 'Constancia de Solvencia', 'subject' => 'student', 'source' => 'basic',
                'validity' => 30, 'title' => 'Constancia de solvencia', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => "hasta | Solvente hasta el mes de | text | |\n$purpose",
                'body' => "$intro\n\nQue {{g.el}} estudiante **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, del **{{student.gradename}}**, se encuentra solvente con sus obligaciones "
                    . "administrativas con esta institución{{f.hasta| hasta el mes de }}.\n\n$closing",
            ],
            'constancia_traslado' => [
                'series' => 'CONSTL', 'name' => 'Constancia de Traslado', 'subject' => 'student', 'source' => 'basic',
                'validity' => 90, 'title' => 'Constancia de traslado', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => "destino | Centro educativo de destino | text | | required",
                'body' => "$intro\n\nQue {{g.el}} estudiante **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, cursó en esta institución el **{{student.gradename}}** durante el año lectivo "
                    . "{{student.yearlabel}} y no tiene inconveniente en que continúe sus estudios en el "
                    . "**{{f.destino}}**.\n\n$closing",
            ],
            'constancia_cupo' => [
                'series' => 'CONSCU', 'name' => 'Constancia de Cupo', 'subject' => 'person', 'source' => 'basic',
                'validity' => 60, 'title' => 'Constancia de cupo', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => "grado | Grado al que ingresa | text | | required\nanio | Año lectivo | text | | required",
                'body' => "$intro\n\nQue para {{g.el}} estudiante **{{holder.name}}**{{holder.idnumber|, con número de "
                    . "identidad }}, existe cupo disponible en el **{{f.grado}}** de esta institución para el año "
                    . "lectivo {{f.anio}}, en la {{school.jornada}}.\n\n$closing",
            ],
            'certificacion_estudios' => [
                'series' => 'CERES', 'name' => 'Certificación de Estudios', 'subject' => 'student', 'source' => 'grades',
                'validity' => 0, 'title' => 'Certificación de estudios', 'signers' => ['SCE', 'DCE'], 'allowbodyedit' => 0, 'fields' => '',
                'body' => "$certintro\n\nQue {{g.el}} estudiante **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, cursó el **{{student.gradename}}** en esta institución durante el año lectivo "
                    . "{{student.yearlabel}}, en la {{school.jornada}}, con las calificaciones que se detallan:"
                    . "\n\n{{tabla.notas}}\n\n{{tabla.asistencia}}\n\n$certclosing",
            ],
            'certificacion_notas' => [
                'series' => 'CERNOT', 'name' => 'Certificación de Notas', 'subject' => 'student', 'source' => 'grades',
                'validity' => 0, 'title' => 'Certificación de notas', 'signers' => ['SCE', 'DCE'], 'allowbodyedit' => 0, 'fields' => '',
                'body' => "$certintro\n\nQue en los registros de calificaciones de esta institución consta que "
                    . "{{g.el}} estudiante **{{holder.name}}**, con número de identidad {{holder.idnumber}}, del "
                    . "**{{student.gradename}}**, año lectivo {{student.yearlabel}}, obtuvo las siguientes "
                    . "calificaciones:\n\n{{tabla.notas}}\n\n$certclosing",
            ],
            'certificacion_conducta' => [
                'series' => 'CERCOND', 'name' => 'Certificación de Conducta', 'subject' => 'student', 'source' => 'basic',
                'validity' => 0, 'title' => 'Certificación de conducta', 'signers' => ['SCE', 'DCE'], 'allowbodyedit' => 0,
                'fields' => "conducta | Conducta | select | Muy buena | Excelente;Muy buena;Buena;Satisfactoria",
                'body' => "$certintro\n\nQue según los registros de esta institución, {{g.el}} estudiante "
                    . "**{{holder.name}}**, con número de identidad {{holder.idnumber}}, del **{{student.gradename}}**, "
                    . "año lectivo {{student.yearlabel}}, observó una conducta **{{f.conducta}}**.\n\n$certclosing",
            ],
            'constancia_trabajo_docente' => [
                'series' => 'CONSTPD', 'name' => 'Constancia de Trabajo Personal Docente', 'subject' => 'staff',
                'source' => 'basic', 'validity' => 90, 'title' => 'Constancia de trabajo', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => "salario | Salario mensual (opcional) | text | |\n$purpose",
                'body' => "$intro\n\nQue {{g.el}} docente **{{holder.name}}**, con número de identidad "
                    . "{{holder.idnumber}}, labora en esta institución como **{{staff.position}}** desde el "
                    . "{{staff.startdate}}, impartiendo {{staff.courses}}.{{f.salario| Devenga un salario mensual de |.}}"
                    . "\n\n$closing",
            ],
            'constancia_trabajo_administrativo' => [
                'series' => 'CONSTPA', 'name' => 'Constancia de Trabajo Personal Administrativo', 'subject' => 'staff',
                'source' => 'basic', 'validity' => 90, 'title' => 'Constancia de trabajo', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => "salario | Salario mensual (opcional) | text | |\n$purpose",
                'body' => "$intro\n\nQue **{{holder.name}}**, con número de identidad {{holder.idnumber}}, labora en "
                    . "esta institución como **{{staff.position}}** desde el {{staff.startdate}}."
                    . "{{f.salario| Devenga un salario mensual de |.}}\n\n$closing",
            ],
            'certificacion_trabajo_docente' => [
                'series' => 'CERTRAD', 'name' => 'Certificación de Trabajo Docente', 'subject' => 'staff',
                'source' => 'basic', 'validity' => 0, 'title' => 'Certificación de trabajo docente', 'signers' => ['DCE'], 'allowbodyedit' => 0,
                'fields' => '',
                'body' => "La Dirección del **{{school.officialname}}**, código SACE {{school.sace}}, de la ciudad de "
                    . "{{school.city}}, departamento de {{school.department}}:\n\n= **CERTIFICA:**\n\nQue {{g.el}} "
                    . "docente **{{holder.name}}**, con número de identidad {{holder.idnumber}}, presta sus servicios "
                    . "en este centro educativo como **{{staff.position}}** desde el {{staff.startdate}}, con "
                    . "{{staff.years}} de servicio a la fecha, impartiendo {{staff.courses}}.\n\n$certclosing",
            ],
            'constancia_anios_servicio' => [
                'series' => 'CONSAS', 'name' => 'Constancia de Años de Servicio', 'subject' => 'staff', 'source' => 'basic',
                'validity' => 90, 'title' => 'Constancia de años de servicio', 'signers' => ['DCE'], 'allowbodyedit' => 0, 'fields' => $purpose,
                'body' => "$intro\n\nQue **{{holder.name}}**, con número de identidad {{holder.idnumber}}, ha "
                    . "laborado en esta institución como **{{staff.position}}** desde el {{staff.startdate}}, "
                    . "acumulando a la fecha **{{staff.years}}** de servicio.\n\n$closing",
            ],
            'oficio' => [
                'series' => 'OF', 'name' => 'Oficio', 'subject' => 'none', 'source' => 'basic', 'validity' => 0,
                'title' => 'Oficio', 'signers' => ['DCE'], 'allowbodyedit' => 1, 'fields' => $letterfields,
                'body' => "**Asunto:** {{f.asunto}}\n\n{{f.saludo}}\n\n{{f.texto}}\n\nAtentamente,",
            ],
            'circular' => [
                'series' => 'CIR', 'name' => 'Circular', 'subject' => 'none', 'source' => 'basic', 'validity' => 0,
                'title' => 'Circular', 'signers' => [], 'allowbodyedit' => 1,
                'fields' => "destinatario | Dirigida a | text | Padres de familia y personal docente | required\n"
                    . "asunto | Asunto | text | | required\ntexto | Texto | textarea | | required",
                'body' => "**Asunto:** {{f.asunto}}\n\n{{f.texto}}\n\nAtentamente,",
            ],
            'memorandum' => [
                'series' => 'MM', 'name' => 'Memorándum', 'subject' => 'none', 'source' => 'basic', 'validity' => 0,
                'title' => 'Memorándum', 'signers' => [], 'allowbodyedit' => 1,
                'fields' => "destinatario | Para | text | | required\ncargo | Cargo | text | |\nde | De | text | Dirección | required\n"
                    . "asunto | Asunto | text | | required\ntexto | Texto | textarea | | required",
                'body' => "**De:** {{f.de}}\n**Asunto:** {{f.asunto}}\n\n{{f.texto}}",
            ],
            'convenio' => [
                'series' => 'CONV', 'name' => 'Convenio', 'subject' => 'none', 'source' => 'basic', 'validity' => 0,
                'title' => 'Convenio', 'signers' => ['DCE'], 'allowbodyedit' => 1,
                'fields' => "contraparte | Parte contraparte | text | | required\nasunto | Objeto del convenio | text | | required\n"
                    . "texto | Cláusulas | textarea | | required",
                'body' => "Convenio entre el **{{school.officialname}}** y **{{f.contraparte}}**.\n\n**Objeto:** {{f.asunto}}"
                    . "\n\n{{f.texto}}\n\nFirmado en la ciudad de {{school.city}}, {{date.words}}.",
            ],
        ];
    }

    /**
     * Create the seeded series and types where they do not exist yet.
     *
     * Re-runnable: an existing series or type is never touched, so a school's
     * own edits survive.
     *
     * @return void
     */
    public static function install(): void {
        global $DB;
        $now = time();
        $seriesids = [];
        foreach (self::series() as $code => [$name, $trdcode, $parent, $value]) {
            $existing = $DB->get_record('local_documents_series', ['code' => $code]);
            if ($existing) {
                $seriesids[$code] = (int)$existing->id;
                continue;
            }
            $seriesids[$code] = (int)$DB->insert_record('local_documents_series', (object)[
                'code' => $code, 'name' => $name, 'trdcode' => $trdcode, 'parentcode' => $parent, 'docvalue' => $value,
                'keepactive' => 5, 'keepcentral' => 10, 'permanent' => 1, 'enabled' => 1,
                'timecreated' => $now, 'timemodified' => $now,
            ]);
        }

        $sort = 0;
        foreach (self::types() as $shortname => $type) {
            $sort += 10;
            if ($DB->record_exists('local_documents_type', ['shortname' => $shortname])) {
                continue;
            }
            $typeid = (int)$DB->insert_record('local_documents_type', (object)[
                'shortname' => $shortname, 'name' => $type['name'], 'seriesid' => $seriesids[$type['series']],
                'subject' => $type['subject'], 'source' => $type['source'], 'unitcode' => 'DCE',
                'validitydays' => (int)$type['validity'], 'currentversion' => 0, 'enabled' => 1, 'sortorder' => $sort,
                'timecreated' => $now, 'timemodified' => $now,
            ]);
            templates::save_version($typeid, [
                'title' => $type['title'],
                'body' => $type['body'],
                'fields' => $type['fields'],
                'allowbodyedit' => (int)$type['allowbodyedit'],
                'changenote' => 'Plantilla inicial.',
            ], 0);
        }
        units::install_defaults();
    }

    /**
     * Give every ready-made template the school never changed the current catalogue text.
     *
     * A template someone edited is left alone: the new text is only offered
     * where every version so far is one this plugin wrote. It is a new
     * version with its change note, like any other edit.
     *
     * @param string $changenote
     * @return int how many templates got a new version
     */
    public static function refresh_untouched(string $changenote): int {
        global $DB;
        $count = 0;
        foreach (self::types() as $shortname => $type) {
            $record = $DB->get_record('local_documents_type', ['shortname' => $shortname]);
            if (!$record) {
                continue;
            }
            $current = $DB->get_record('local_documents_tplver', ['id' => $record->currentversion]);
            if (!$current || $DB->record_exists_select('local_documents_tplver', 'typeid = ? AND createdby <> 0',
                    [$record->id])) {
                continue;
            }
            $same = trim((string)$current->title) === trim($type['title']) && trim((string)$current->body) === trim($type['body'])
                && trim((string)$current->fields) === trim($type['fields'])
                && (int)$current->allowbodyedit === (int)$type['allowbodyedit'];
            if ($same) {
                continue;
            }
            templates::save_version((int)$record->id, [
                'title' => $type['title'],
                'body' => $type['body'],
                'fields' => $type['fields'],
                'allowbodyedit' => (int)$type['allowbodyedit'],
                'changenote' => $changenote,
            ], 0);
            $count++;
        }
        return $count;
    }
}

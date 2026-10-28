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

/**
 * Seed the simulation for official documents.
 *
 * A fictional school (names, SACE code), its issuing units with their heads, the
 * profile fields documents print (identity number, sex, birth date, staff
 * position and start date) filled in for the 10G students and their teacher,
 * a secretary (Elena Castillo, ecastillo) who prepares and issues, and the
 * director's role (direccion, Rosa Gómez) who also recalls and edits
 * templates. Re-runnable; every name and number is invented. Localhost only.
 *
 * Usage: php seed_documents.php /opt/sim/moodle
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
$moodle = $argv[1] ?? '/opt/sim/moodle';
require($moodle . '/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

if (!preg_match('~^https?://(localhost|127\.0\.0\.1)(:\d+)?$~', $CFG->wwwroot)) {
    cli_error('Refusing to seed ' . $CFG->wwwroot . ': localhost only.');
}

// The school.
foreach (['headerlines', 'footertext', 'brandcolor', 'units', 'signatories'] as $old) {
    unset_config($old, 'local_documents');
}
foreach ([
    // The QR codes open this site's own page: the public one does not know the simulation.
    'verifyurl' => '',
    'schoolname' => 'Instituto Bilingüe Valle Verde',
    'sacecode' => '050100099M01',
    'jornada' => 'EX',
    'city' => 'San Pedro Sula',
    'department' => 'Cortés',
    'legalname' => 'Educación Valle Verde S. de R.L.',
    'officialname' => 'Centro Educativo No Gubernamental Bilingüe Valle Verde',
    'fieldidnumber' => 'dni',
    'fieldgender' => 'sexo',
    'fieldbirthdate' => 'fechanac',
    'fieldposition' => 'puesto',
    'fieldstartdate' => 'fechaingreso',
] as $name => $value) {
    set_config($name, $value, 'local_documents');
}

// The profile fields.
$category = $DB->get_record('user_info_category', ['name' => 'Documentos oficiales']);
if (!$category) {
    $category = (object)['name' => 'Documentos oficiales',
        'sortorder' => (int)$DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}') + 1];
    $category->id = $DB->insert_record('user_info_category', $category);
}
$fields = [];
foreach ([
    'dni' => ['Número de identidad', 'text', ''],
    'sexo' => ['Sexo', 'menu', "M\nF"],
    'fechanac' => ['Fecha de nacimiento', 'datetime', '1990'],
    'puesto' => ['Puesto', 'text', ''],
    'fechaingreso' => ['Fecha de ingreso', 'datetime', '1990'],
] as $shortname => [$label, $type, $param1]) {
    $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
    if (!$field) {
        $field = (object)['shortname' => $shortname, 'name' => $label, 'datatype' => $type, 'description' => '',
            'descriptionformat' => FORMAT_HTML, 'categoryid' => $category->id,
            'sortorder' => (int)$DB->count_records('user_info_field', ['categoryid' => $category->id]) + 1,
            'required' => 0, 'locked' => 0, 'visible' => 1, 'forceunique' => 0, 'signup' => 0, 'defaultdata' => '',
            'defaultdataformat' => 0, 'param1' => $param1, 'param2' => $type === 'datetime' ? '2030' : null,
            'param3' => $type === 'datetime' ? '0' : null];
        $field->id = $DB->insert_record('user_info_field', $field);
    }
    $fields[$shortname] = (int)$field->id;
}
$setfield = static function(int $userid, string $shortname, string $value) use ($DB, $fields): void {
    $existing = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fields[$shortname]]);
    if ($existing) {
        $DB->set_field('user_info_data', 'data', $value, ['id' => $existing->id]);
    } else {
        $DB->insert_record('user_info_data', (object)['userid' => $userid, 'fieldid' => $fields[$shortname],
            'data' => $value, 'dataformat' => 0]);
    }
};

// Students of 10G and their teacher: invented identity numbers.
$female = ['vrios', 'svega', 'clopez', 'imorales', 'lnavarro', 'lmendez', 'msolis', 'rgomez'];
$i = 0;
foreach (['mcastro', 'vrios', 'dflores', 'svega', 'aherrera', 'clopez', 'imorales', 'jpaz', 'lnavarro'] as $username) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        continue;
    }
    $i++;
    $setfield((int)$user->id, 'dni', sprintf('0501-2010-%05d', 1200 + $i * 37));
    $setfield((int)$user->id, 'sexo', in_array($username, $female, true) ? 'F' : 'M');
    $setfield((int)$user->id, 'fechanac', (string)make_timestamp(2010, 1 + $i, 3 + $i, 12));
}
foreach (['lmendez' => ['Docente de Educación Media', 2019], 'portiz' => ['Coordinador Académico', 2016],
        'rgomez' => ['Directora', 2012], 'msolis' => ['Consejera Estudiantil', 2021]] as $username => [$position, $year]) {
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        continue;
    }
    $setfield((int)$user->id, 'dni', sprintf('0501-19%02d-%05d', 70 + strlen($username), 4000 + $year));
    $setfield((int)$user->id, 'sexo', in_array($username, $female, true) ? 'F' : 'M');
    $setfield((int)$user->id, 'puesto', $position);
    $setfield((int)$user->id, 'fechaingreso', (string)make_timestamp($year, 2, 1, 12));
}

// Who issues: the secretary prepares and issues, the director also recalls and edits templates.
$system = context_system::instance();
$role = static function(string $shortname, string $name) use ($DB): int {
    return (int)($DB->get_field('role', 'id', ['shortname' => $shortname])
        ?: create_role($name, $shortname, '', ''));
};
$secretaria = $role('secretaria', 'Secretaría');
$direccion = $role('direccion', 'Dirección');
set_role_contextlevels($secretaria, [CONTEXT_SYSTEM]);
foreach (['local/documents:view', 'local/documents:issue'] as $capability) {
    assign_capability($capability, CAP_ALLOW, $secretaria, $system->id, true);
    assign_capability($capability, CAP_ALLOW, $direccion, $system->id, true);
}
foreach (['local/documents:recall', 'local/documents:managetemplates'] as $capability) {
    assign_capability($capability, CAP_ALLOW, $direccion, $system->id, true);
}
$secretary = $DB->get_record('user', ['username' => 'ecastillo']);
if (!$secretary) {
    $id = user_create_user((object)['username' => 'ecastillo', 'auth' => 'manual', 'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id, 'password' => 'Sim-Docs-2026!', 'firstname' => 'Elena',
        'lastname' => 'Castillo', 'email' => 'ecastillo@example.invalid', 'lang' => 'es']);
    $secretary = $DB->get_record('user', ['id' => $id]);
}
role_assign($secretaria, $secretary->id, $system->id);
// The two Gerencias: they issue circulars and memorandums, signed by their own head.
foreach ([['mfuentes', 'Mario', 'Fuentes'], ['lbonilla', 'Lucía', 'Bonilla']] as [$username, $firstname, $lastname]) {
    if (!$DB->record_exists('user', ['username' => $username])) {
        user_create_user((object)['username' => $username, 'auth' => 'manual', 'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id, 'password' => 'Sim-Docs-2026!', 'firstname' => $firstname,
            'lastname' => $lastname, 'email' => $username . '@example.invalid', 'lang' => 'es']);
    }
    role_assign($direccion, (int)$DB->get_field('user', 'id', ['username' => $username]), $system->id);
}
purge_caches(["muc" => false, "other" => true]);

// The issuing units and who signs for each: Dirección (Rosa Gómez), Secretaría (Elena Castillo),
// Subdirección (Pablo Ortiz), Gerencia General (Mario Fuentes), Gerencia Académica (Lucía Bonilla).
// The school's units may issue every type, the Gerencias circulars and memorandums; the catalogue's
// signatures stay. Nobody substitutes anybody: a scenario sets that up itself.
\local_documents\local\units::install_defaults();
$heads = [
    'DCE' => ['rgomez', 'Lic. Rosa Gómez', 'Directora', 'direccion@example.invalid', '(+504) 0000-0001'],
    'SCE' => ['ecastillo', 'Prof. Elena Castillo', 'Secretaria', 'secretaria@example.invalid', '(+504) 0000-0003'],
    'SDCE' => ['portiz', 'Lic. Pablo Ortiz', 'Subdirector', 'subdireccion@example.invalid', '(+504) 0000-0002'],
    'GG' => ['mfuentes', 'Lic. Mario Fuentes', 'Gerente General', 'gerencia@example.invalid', '(+504) 0000-0004'],
    'GA' => ['lbonilla', 'MSc. Lucía Bonilla', 'Gerente Académica', 'academica@example.invalid', '(+504) 0000-0005'],
];
$letters = array_map('intval', $DB->get_fieldset_select('local_documents_type', 'id',
    "shortname IN ('circular', 'memorandum')"));
$alltypes = array_map('intval', $DB->get_fieldset_select('local_documents_type', 'id', '1 = 1'));
foreach ($heads as $code => [$username, $name, $title, $email, $phone]) {
    $unit = \local_documents\local\units::by_code($code);
    $head = $DB->get_record('user', ['username' => $username]);
    if (!$unit || !$head) {
        continue;
    }
    \local_documents\local\units::save((object)['id' => $unit->id, 'code' => $code, 'name' => $unit->name,
        'email' => $email, 'phone' => $phone, 'userid' => $head->id, 'signername' => $name, 'signertitle' => $title,
        'enabled' => 1, 'typeids' => in_array($code, ['GG', 'GA'], true) ? $letters : $alltypes]);
}

cli_writeln('Documents: school, profile fields, units (DCE rgomez, SCE ecastillo, SDCE portiz, GG mfuentes, '
    . 'GA lbonilla), secretary and Gerencias / Sim-Docs-2026!.');

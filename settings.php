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
 * Settings for local_documents: the school as documents print it.
 *
 * @package   local_documents
 * @copyright Educación Helvética SA / EuropaSchule
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_documents_category',
        get_string('pluginname', 'local_documents')));
    $ADMIN->add('local_documents_category', new admin_externalpage('local_documents_registry',
        get_string('registry', 'local_documents'), new moodle_url('/local/documents/index.php'), 'local/documents:view'));
    $ADMIN->add('local_documents_category', new admin_externalpage('local_documents_units',
        get_string('units', 'local_documents'), new moodle_url('/local/documents/units.php'),
        'local/documents:manageunits'));
    $ADMIN->add('local_documents_category', new admin_externalpage('local_documents_templates',
        get_string('templates', 'local_documents'), new moodle_url('/local/documents/templates.php'),
        'local/documents:managetemplates'));

    $settings = new admin_settingpage('local_documents', get_string('settings', 'local_documents'));
    $ADMIN->add('local_documents_category', $settings);

    $settings->add(new admin_setting_heading('local_documents/schoolheading', get_string('settingschool', 'local_documents'),
        get_string('settingschool_desc', 'local_documents')));
    $defaults = \local_documents\local\config::SCHOOL_DEFAULTS;
    $settings->add(new admin_setting_configtext('local_documents/schoolname', get_string('schoolname', 'local_documents'),
        get_string('schoolname_desc', 'local_documents'), $defaults['schoolname'], PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_documents/officialname', get_string('officialname', 'local_documents'),
        get_string('officialname_desc', 'local_documents'), $defaults['officialname'], PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_documents/legalname', get_string('legalname', 'local_documents'),
        get_string('legalname_desc', 'local_documents'), $defaults['legalname'], PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_documents/sacecode', get_string('sacecode', 'local_documents'),
        get_string('sacecode_desc', 'local_documents'), $defaults['sacecode'], PARAM_ALPHANUM));
    $settings->add(new admin_setting_configselect('local_documents/jornada', get_string('jornada', 'local_documents'),
        get_string('jornada_desc', 'local_documents'), 'EX', array_map(static fn($label) => $label,
            \local_documents\local\config::JORNADAS)));
    $settings->add(new admin_setting_configtext('local_documents/city', get_string('city', 'local_documents'), '',
        'San Pedro Sula', PARAM_TEXT));
    $settings->add(new admin_setting_configtext('local_documents/department', get_string('departmentname', 'local_documents'),
        '', 'Cortés', PARAM_TEXT));
    $settings->add(new admin_setting_description('local_documents/unitslink', get_string('units', 'local_documents'),
        html_writer::link(new moodle_url('/local/documents/units.php'), get_string('unitslinkdesc', 'local_documents'))));

    $settings->add(new admin_setting_heading('local_documents/letterheadheading',
        get_string('settingletterhead', 'local_documents'), get_string('settingletterhead_desc', 'local_documents')));
    foreach (\local_documents\local\config::IMAGE_AREAS as $area) {
        $settings->add(new admin_setting_configstoredfile('local_documents/' . $area, get_string($area, 'local_documents'),
            get_string($area . '_desc', 'local_documents'), $area, 0,
            ['maxfiles' => 1, 'accepted_types' => ['.png', '.jpg', '.jpeg']]));
    }

    $settings->add(new admin_setting_heading('local_documents/verifyheading', get_string('settingverify', 'local_documents'),
        get_string('settingverify_desc', 'local_documents')));
    $settings->add(new admin_setting_configtext('local_documents/verifyurl', get_string('verifyurl', 'local_documents'),
        get_string('verifyurl_desc', 'local_documents'), 'https://europaschule.eu/verificar/', PARAM_URL));
    $settings->add(new admin_setting_configpasswordunmask('local_documents/verifytoken',
        get_string('verifytoken', 'local_documents'), get_string('verifytoken_desc', 'local_documents'), ''));
    $settings->add(new admin_setting_configtext('local_documents/signaturelaw', get_string('signaturelaw', 'local_documents'),
        get_string('signaturelaw_desc', 'local_documents'), \local_documents\local\config::SIGNATURE_LAW, PARAM_TEXT, 90));

    $settings->add(new admin_setting_heading('local_documents/fieldsheading', get_string('settingfields', 'local_documents'),
        get_string('settingfields_desc', 'local_documents')));
    foreach (['fieldidnumber' => 'idnumber', 'fieldgender' => '', 'fieldbirthdate' => '', 'fieldposition' => '',
            'fieldstartdate' => ''] as $name => $default) {
        $settings->add(new admin_setting_configtext('local_documents/' . $name, get_string($name, 'local_documents'),
            get_string('field_desc', 'local_documents'), $default, PARAM_ALPHANUMEXT));
    }
}

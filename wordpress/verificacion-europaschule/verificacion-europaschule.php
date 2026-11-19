<?php
/**
 * Plugin Name: Verificación de documentos EuropaSchule
 * Description: Página pública para verificar los documentos oficiales emitidos en la plataforma escolar (Moodle, local_documents): el código QR de cada constancia o certificación abre esta página. Shortcode: [verificacion_europaschule].
 * Version: 0.7.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Educación Helvética SA / EuropaSchule
 * License: GPL-3.0-or-later
 * Text Domain: verificacion-europaschule
 *
 * The page asks Moodle's verify_api.php from the server, with the shared
 * token, so the platform's address never has to be public and the visitor's
 * address can be passed on for the rate limit. It shows only what the
 * document itself prints. A PDF can be compared with the original's SHA-256
 * in the visitor's browser: the file is never uploaded.
 *
 * Folder and prefix follow the platform's naming convention: the folder ends
 * in -europaschule and every function, option and transient starts with
 * es_ver_ (CSS classes with es-ver). Both are permanent once the plugin is in
 * production (Constitution, Article 8.2). Until 0.6.0 it was es-verificacion.
 *
 * @package verificacion-europaschule
 */

if (!defined('ABSPATH')) {
    exit;
}

const ES_VER_OPTION = 'es_ver_settings';

/**
 * The plugin's settings.
 *
 * @return array{moodle: string, token: string}
 */
function es_ver_settings(): array {
    // Settings saved under the old name (es-verificacion, up to 0.6.0) still count until saved again.
    $saved = get_option(ES_VER_OPTION, null) ?? get_option('es_verificacion', []);
    return [
        'moodle' => isset($saved['moodle']) ? untrailingslashit((string)$saved['moodle']) : '',
        'token' => isset($saved['token']) ? (string)$saved['token'] : '',
    ];
}

/**
 * A code as typed or read from the QR: letters and digits only, upper case.
 *
 * @param string $input
 * @return string
 */
function es_ver_clean_code(string $input): string {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    return substr($code, 0, 16);
}

/**
 * Ask the platform about one code.
 *
 * @param string $code
 * @return array the platform's answer, or ['status' => 'unavailable']
 */
function es_ver_lookup(string $code): array {
    $settings = es_ver_settings();
    if ($settings['moodle'] === '') {
        return ['status' => 'unavailable'];
    }
    $cachekey = 'es_ver_' . md5($code);
    $cached = get_transient($cachekey);
    if (is_array($cached)) {
        return $cached;
    }
    $response = wp_remote_get(add_query_arg('c', rawurlencode($code), $settings['moodle'] . '/local/documents/verify_api.php'), [
        'timeout' => 8,
        'headers' => [
            'X-Verify-Token' => $settings['token'],
            'X-Verify-Client' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
            'Accept' => 'application/json',
        ],
    ]);
    if (is_wp_error($response)) {
        return ['status' => 'unavailable'];
    }
    $data = json_decode((string)wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['status'])) {
        return ['status' => 'unavailable'];
    }
    // A minute: short enough that a recall shows almost at once.
    if (in_array($data['status'], ['valid', 'expired', 'superseded', 'recalled', 'notfound'], true)) {
        set_transient($cachekey, $data, MINUTE_IN_SECONDS);
    }
    return $data;
}

/**
 * The wording of each answer.
 *
 * @return array<string, array{0: string, 1: string}> status => [heading, tone]
 */
function es_ver_statuses(): array {
    return [
        'valid' => ['Documento válido', 'good'],
        'expired' => ['Documento auténtico, pero ya no está vigente', 'warn'],
        'superseded' => ['Documento sustituido por una versión corregida', 'warn'],
        'recalled' => ['Documento anulado: no tiene validez', 'bad'],
        'notfound' => ['Ningún documento tiene este código', 'bad'],
        'invalid' => ['Esto no es un código de verificación', 'neutral'],
        'ratelimited' => ['Demasiados intentos. Inténtelo de nuevo en unos minutos.', 'neutral'],
        'forbidden' => ['La verificación no está disponible en este momento.', 'neutral'],
        'unavailable' => ['La verificación no está disponible en este momento.', 'neutral'],
    ];
}

/**
 * The small icon in front of an answer: a check, an exclamation mark or a cross.
 *
 * @param string $tone good, warn, bad or neutral
 * @return string inline SVG
 */
function es_ver_icon(string $tone): string {
    $paths = [
        'good' => '<path d="M7 12.5l3.2 3.2L17 9" />',
        'warn' => '<path d="M12 7v6" /><path d="M12 16.5v.5" />',
        'bad' => '<path d="M8.5 8.5l7 7" /><path d="M15.5 8.5l-7 7" />',
        'neutral' => '<path d="M12 11v5" /><path d="M12 8v.5" />',
    ];
    return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2.2"'
        . ' stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="10.5" />'
        . ($paths[$tone] ?? $paths['neutral']) . '</svg>';
}

/**
 * The [verificacion_europaschule] shortcode: the form and, with a code, the answer.
 *
 * @return string
 */
function es_ver_shortcode(): string {
    wp_enqueue_style('verificacion-europaschule', plugins_url('verificacion-europaschule.css', __FILE__), [], '0.7.0');
    wp_enqueue_script('verificacion-europaschule', plugins_url('verificacion-europaschule.js', __FILE__), [], '0.7.0', true);

    $code = isset($_GET['c']) ? es_ver_clean_code(sanitize_text_field(wp_unslash($_GET['c']))) : '';
    $display = $code !== '' ? implode('-', str_split($code, 4)) : '';

    $html = '<div class="es-ver">';
    $html .= '<form class="es-ver__form" method="get" action="' . esc_url(get_permalink()) . '">';
    $html .= '<label class="es-ver__label" for="es-ver-code">Código de verificación</label>';
    $html .= '<div class="es-ver__row"><input id="es-ver-code" name="c" type="text" inputmode="text" autocomplete="off"'
        . ' maxlength="20" placeholder="XXXX-XXXX-XXXX" value="' . esc_attr($display) . '">';
    $html .= '<button type="submit">Verificar</button></div>';
    $html .= '<p class="es-ver__hint">Escanee el código QR del documento o escriba el código impreso al pie.</p>';
    $html .= '</form>';

    if ($code !== '') {
        $result = es_ver_lookup($code);
        $statuses = es_ver_statuses();
        [$heading, $tone] = $statuses[$result['status']] ?? $statuses['unavailable'];
        $html .= '<section class="es-ver__result es-ver__result--' . esc_attr($tone) . '" aria-live="polite">';
        $html .= '<h2><span class="es-ver__icon" aria-hidden="true">' . es_ver_icon($tone) . '</span>'
            . esc_html($heading) . '</h2>';
        if (!empty($result['title'])) {
            $rows = [
                'Documento' => $result['title'],
                'Número' => $result['number'] . ((int)$result['version'] > 1 ? ' (versión ' . (int)$result['version'] . ')' : ''),
                'A nombre de' => $result['holder'] ?? '',
                'Identidad' => $result['holderid'] ?? '',
                'Asunto' => $result['subject'] ?? '',
                'Fecha de emisión' => $result['issued'] ?? '',
                'Válido hasta' => $result['validuntil'] ?? '',
                'Emitido por' => trim(($result['school'] ?? '') . ' · ' . ($result['unit'] ?? ''), ' ·'),
                'Firmado por' => implode('; ', (array)($result['signers'] ?? [])),
                'Dirigida a' => !empty($result['recipients'])
                    ? (int)$result['recipients'] . ' estudiantes, cada uno con su propia colilla' : '',
            ];
            if ($result['status'] === 'recalled') {
                $rows['Anulado el'] = $result['recalled'] ?? '';
                $rows['Nota'] = $result['recallnote'] ?? '';
            }
            if ($result['status'] === 'superseded' && !empty($result['replacedby'])) {
                $rows['Sustituido por'] = 'la versión ' . (int)$result['replacedby'] . ', emitida el ' . ($result['replacedon'] ?? '');
            }
            $html .= '<dl class="es-ver__facts">';
            foreach ($rows as $label => $value) {
                if ((string)$value !== '') {
                    $html .= '<dt>' . esc_html($label) . '</dt><dd>' . esc_html((string)$value) . '</dd>';
                }
            }
            $html .= '</dl>';
            // Electronically signed: the law it was signed under, as the document's footer cites it.
            if (!empty($result['legal'])) {
                $html .= '<p class="es-ver__legal">' . esc_html((string)$result['legal']) . '</p>';
            }
            // The documents that go with it (a file, such as a traslado), each verifiable by its own code.
            if (!empty($result['companions']) && is_array($result['companions'])) {
                $html .= '<h3 class="es-ver__subhead">Documentos que acompañan</h3><ol class="es-ver__attachments">';
                foreach ($result['companions'] as $line) {
                    $html .= '<li>' . esc_html((string)$line) . '</li>';
                }
                $html .= '</ol>';
            }
            // The attachments are part of the document: their names and hashes are printed in its index.
            $files = [];
            if (!empty($result['sha256']) && preg_match('/^[0-9a-f]{64}$/', (string)$result['sha256'])) {
                $files[] = ['name' => (string)$result['title'], 'sha256' => (string)$result['sha256']];
            }
            if (!empty($result['attachments']) && is_array($result['attachments'])) {
                $html .= '<h3 class="es-ver__subhead">Adjuntos</h3><ol class="es-ver__attachments">';
                foreach ($result['attachments'] as $attachment) {
                    if (!is_array($attachment) || !preg_match('/^[0-9a-f]{64}$/', (string)($attachment['sha256'] ?? ''))) {
                        continue;
                    }
                    $files[] = ['name' => (string)$attachment['name'], 'sha256' => (string)$attachment['sha256']];
                    $html .= '<li>' . esc_html((string)$attachment['name']) . ' · ' . esc_html(size_format((int)($attachment['size'] ?? 0)))
                        . '<span class="es-ver__hash">SHA-256 ' . esc_html((string)$attachment['sha256']) . '</span></li>';
                }
                $html .= '</ol>';
            }
            if ($files) {
                $html .= '<div class="es-ver__file" data-files="' . esc_attr(wp_json_encode($files)) . '">';
                $html .= '<p class="es-ver__filelead">¿Tiene el PDF o un adjunto? Compruebe que es el original, sin cambios.</p>';
                $html .= '<label class="es-ver__filebtn">Elegir el archivo'
                    . '<input id="es-ver-file" type="file" accept="application/pdf,.pdf,image/png,image/jpeg"></label>';
                $html .= '<p class="es-ver__filemsg" role="status"></p>';
                $html .= '<p class="es-ver__hint">El archivo se revisa en su navegador; no se envía a ningún servidor.</p>';
                $html .= '</div>';
            }
        }
        $html .= '</section>';
    }
    $html .= '<p class="es-ver__hint">Esta página solo muestra lo que el propio documento imprime; el número de identidad aparece enmascarado.</p>';
    return $html . '</div>';
}
add_shortcode('verificacion_europaschule', 'es_ver_shortcode');

/**
 * Keep the verification page out of search engines.
 *
 * @param array $robots
 * @return array
 */
function es_ver_robots(array $robots): array {
    $post = get_post();
    if ($post && has_shortcode((string)$post->post_content, 'verificacion_europaschule')) {
        $robots['noindex'] = true;
        $robots['nofollow'] = true;
    }
    return $robots;
}
add_filter('wp_robots', 'es_ver_robots');

/**
 * Settings > Verificación de documentos.
 *
 * @return void
 */
function es_ver_admin_menu(): void {
    add_options_page('Verificación de documentos', 'Verificación de documentos', 'manage_options', 'verificacion-europaschule',
        'es_ver_settings_page');
}
add_action('admin_menu', 'es_ver_admin_menu');

/**
 * Register the setting with its sanitiser.
 *
 * @return void
 */
function es_ver_register_setting(): void {
    register_setting('es_ver', ES_VER_OPTION, [
        'type' => 'array',
        'sanitize_callback' => static function($input): array {
            $input = is_array($input) ? $input : [];
            return [
                'moodle' => esc_url_raw(trim((string)($input['moodle'] ?? '')), ['https', 'http']),
                'token' => sanitize_text_field((string)($input['token'] ?? '')),
            ];
        },
        'default' => [],
    ]);
}
add_action('admin_init', 'es_ver_register_setting');

/**
 * The settings page.
 *
 * @return void
 */
function es_ver_settings_page(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    $settings = es_ver_settings();
    echo '<div class="wrap"><h1>Verificación de documentos</h1>';
    echo '<p>Cree una página con el shortcode <code>[verificacion_europaschule]</code> (por ejemplo /verificar/) y escriba su dirección en'
        . ' la plataforma: <em>Administración del sitio › Plugins locales › Documentos oficiales › Ajustes › Página de verificación</em>.</p>';
    echo '<form method="post" action="options.php">';
    settings_fields('es_ver');
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th scope="row"><label for="es-ver-moodle">Dirección de la plataforma</label></th><td>'
        . '<input class="regular-text" id="es-ver-moodle" name="' . esc_attr(ES_VER_OPTION) . '[moodle]" type="url"'
        . ' value="' . esc_attr($settings['moodle']) . '" placeholder="https://learn.europaschule.eu">'
        . '<p class="description">La dirección de Moodle, sin barra final.</p></td></tr>';
    echo '<tr><th scope="row"><label for="es-ver-token">Token</label></th><td>'
        . '<input class="regular-text" id="es-ver-token" name="' . esc_attr(ES_VER_OPTION) . '[token]" type="password"'
        . ' autocomplete="off" value="' . esc_attr($settings['token']) . '">'
        . '<p class="description">El mismo texto que en el ajuste «Token del sitio web» de la plataforma.</p></td></tr>';
    echo '</table>';
    submit_button();
    echo '</form></div>';
}

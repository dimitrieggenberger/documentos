#!/usr/bin/env python3
"""Mockup harness (never shipped): europaschule.eu/verificar in each of its states.

Takes a live page of the public website (its header, menu and footer, as they
are today), puts the [es_verificacion] shortcode's real output (states.php) in
its content area, and writes one page per state to out/. The plugin's own CSS
and JS are copied next to them, so out/ shows the page exactly as WordPress
would serve it with the plugin active. Fictional data only.

Usage: php states.php > out/states.json && python3 build.py
"""
import json
import os
import re
import shutil
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'out')
PLUGIN = os.path.join(HERE, '..', '..', 'wordpress', 'es-verificacion')
SOURCE = 'https://europaschule.eu/plataformas/'

LABELS = {
    'inicio': 'Sin código',
    'valido': 'Válido',
    'vencido': 'Vencido',
    'sustituido': 'Sustituido',
    'anulado': 'Anulado',
    'noexiste': 'No existe',
}


def main():
    os.makedirs(OUT, exist_ok=True)
    with urllib.request.urlopen(SOURCE, timeout=30) as response:
        page = response.read().decode('utf-8')
    states = json.load(open(os.path.join(OUT, 'states.json'), encoding='utf-8'))
    for name in ('es-verificacion.css', 'es-verificacion.js'):
        shutil.copy(os.path.join(PLUGIN, name), os.path.join(OUT, name))

    page = re.sub(r'<title>.*?</title>', '<title>Verificación de documentos – EuropaSchule San Pedro Sula</title>',
                  page, count=1, flags=re.S)
    page = page.replace('</head>', '<link rel="stylesheet" href="es-verificacion.css">\n'
                        '<style>/* mockup only */ .mockup-states{max-width:640px;margin:0 auto 24px;'
                        'display:flex;flex-wrap:wrap;gap:6px;font:12px/1.4 sans-serif}'
                        '.mockup-states a{padding:4px 10px;border:1px dashed #9aa5b1;border-radius:99px;'
                        'color:#5c6670;text-decoration:none}.mockup-states a[aria-current]{background:#fdf1cf;'
                        'color:#7a5c00;border-style:solid}</style>\n</head>', 1)

    for name, fragment in states.items():
        nav = '<nav class="mockup-states" aria-label="Mockup">' + ''.join(
            f'<a href="{key}.html"{" aria-current=page" if key == name else ""}>{label}</a>'
            for key, label in LABELS.items()) + '</nav>'
        content = (
            '<section id="content" style="width: 100%;"><div class="post-content">'
            '<div class="fusion-fullwidth fullwidth-box nonhundred-percent-fullwidth" '
            'style="padding:64px 30px 80px;"><div class="fusion-builder-row fusion-row">'
            + nav +
            '<div class="es-verif__intro" style="max-width:640px;margin:0 auto 24px;">'
            '<h1 style="margin:0 0 8px;">Verificación de documentos</h1>'
            '<p style="margin:0;">Compruebe que una constancia, certificación u oficio emitido por '
            'EuropaSchule San Pedro Sula es auténtico y sigue vigente.</p></div>'
            + fragment +
            '</div></div></div></section>')
        html = re.sub(r'<section id="content".*?</section>', lambda m: content, page, count=1, flags=re.S)
        html = html.replace('</body>', '<script src="es-verificacion.js"></script>\n</body>', 1)
        with open(os.path.join(OUT, name + '.html'), 'w', encoding='utf-8') as handle:
            handle.write(html)
        print('out/' + name + '.html')


if __name__ == '__main__':
    main()

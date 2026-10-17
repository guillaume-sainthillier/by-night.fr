"""Draw the By Night owl and rebuild every logo asset of the site from it: header icon, share image, JSON-LD and email
logos, favicons and app icons. The wordmark is outlined (Epilogue, as on the site), so no file depends on a font.

Usage: python docs/logos/build.py
Needs: uharfbuzz, fonttools and brotli (pip), rsvg-convert (librsvg) and magick (ImageMagick) on the PATH, and the
       front-end dependencies installed (the font is read from node_modules/@fontsource-variable/epilogue).
"""
import os
import subprocess
import tempfile

import uharfbuzz as hb
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
IMAGES = f'{ROOT}/assets/images'
PUBLIC = f'{ROOT}/public'
TMP = tempfile.mkdtemp()

# The site's tokens (assets/scss/_variables.scss, _core.scss)
ORANGE = '#d94826'  # Tabler's $primary
VIOLET = '#2a1f4a'
APRICOT = '#ffb347'
INK = '#191c22'
INK_MUTED = '#5a413b'
SURFACE = '#f9f9ff'


class Font:
    """Epilogue at a given weight, shaped by HarfBuzz (kerning included) and turned into SVG paths."""

    def __init__(self, wght):
        woff2 = f'{ROOT}/node_modules/@fontsource-variable/epilogue/files/epilogue-latin-wght-normal.woff2'
        ttf = f'{TMP}/epilogue.ttf'
        if not os.path.exists(ttf):
            font = TTFont(woff2)
            font.flavor = None
            font.save(ttf)
        tt = TTFont(ttf)
        self.upem = tt['head'].unitsPerEm
        self.cap = tt['OS/2'].sCapHeight
        self.font = hb.Font(hb.Face(hb.Blob.from_file_path(ttf)))
        self.font.set_variations({'wght': wght})

    def text(self, text, size, x, baseline, color, tracking=0.0):
        """Returns (svg path, width); tracking in em, as CSS letter-spacing."""
        buf = hb.Buffer()
        buf.add_str(text)
        buf.guess_segment_properties()
        hb.shape(self.font, buf, {'kern': True})
        s = size / self.upem
        pen = SVGPathPen(None)
        pen_x = 0
        for i, (info, pos) in enumerate(zip(buf.glyph_infos, buf.glyph_positions)):
            transform = (s, 0, 0, -s, x + (pen_x + pos.x_offset) * s, baseline - pos.y_offset * s)
            self.font.draw_glyph_with_pen(info.codepoint, TransformPen(pen, transform))
            pen_x += pos.x_advance + (tracking * self.upem if i < len(buf.glyph_infos) - 1 else 0)
        return f'<path fill="{color}" d="{pen.getCommands()}"/>', pen_x * s

    def cap_height(self, size):
        return self.cap * size / self.upem


# --- The owl (round 7, "head, big eyes"), drawn in a 160 box: head from (27, 19) to (133, 143), centred on (80, 81) ---

OWL_CENTRE = (80, 81)
OWL_HEIGHT = 124


def owl():
    def eye(x, look):
        return (f'<circle cx="{x}" cy="82" r="25" fill="#fff"/>'
                f'<circle cx="{x + look * 3.75}" cy="84.5" r="12.5" fill="{ORANGE}"/>'
                f'<circle cx="{x + look * 3.75 + 4.25}" cy="79" r="3.75" fill="#fff"/>')

    # A filled path stroked in its own colour with round joins: every corner, ear tips included, comes out rounded
    return (f'<path d="M30 118V62L42 22L66 44H94L118 22L130 62V118Q130 140 108 140H52Q30 140 30 118Z" fill="{VIOLET}" '
            f'stroke="{VIOLET}" stroke-width="6" stroke-linejoin="round"/>'
            f'{eye(55, 1)}{eye(105, -1)}'
            f'<path d="M74.5 108H85.5L80 117.3Z" fill="{APRICOT}" stroke="{APRICOT}" stroke-width="3" stroke-linejoin="round"/>')


def placed(height, cx, cy):
    """The owl scaled to `height`, centred on (cx, cy)."""
    k = height / OWL_HEIGHT
    return f'<g transform="translate({cx} {cy}) scale({k:.4f}) translate({-OWL_CENTRE[0]} {-OWL_CENTRE[1]})">{owl()}</g>'


def svg(w, h, body, title='By Night'):
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}"><title>{title}</title>{body}</svg>'


def icon():
    """The owl alone, on transparent."""
    return svg(128, 128, placed(124, 64, 64))


def tile(radius=22):
    """The owl on the orange tile of the favicons (radius 0: iOS rounds the corners itself)."""
    return svg(100, 100, f'<rect width="100" height="100" rx="{radius}" fill="{ORANGE}"/>{placed(80, 50, 51)}')


def lockup_body():
    """The owl beside the name and the tagline, as in the site's header: (svg, width), 128 high."""
    name_font, tagline_font = Font(800), Font(600)
    name_args, tagline_args = ('By Night', 72), ('L’AGENDA DE VOS SORTIES', 17)
    name_w = name_font.text(*name_args, 0, 0, INK, -0.03)[1]
    tagline_w = tagline_font.text(*tagline_args, 0, 0, INK_MUTED, 0.08)[1]
    name_cap, tagline_cap, gap = name_font.cap_height(72), tagline_font.cap_height(17), 18
    x = 128 + 26
    name_baseline = (128 - (name_cap + gap + tagline_cap)) / 2 + name_cap
    name = name_font.text(*name_args, x, name_baseline, INK, -0.03)[0]
    tagline = tagline_font.text(*tagline_args, x + 3, name_baseline + gap + tagline_cap, INK_MUTED, 0.08)[0]
    return placed(124, 64, 64) + name + tagline, round(x + max(name_w, tagline_w))


def lockup():
    body, width = lockup_body()
    return svg(width, 128, body)


def share():
    """og:image, 1200x630 at twice the size: the lockup centred on the page background."""
    w, h = 2400, 1260
    body, width = lockup_body()
    k = 1500 / width
    return svg(w, h, f'<rect width="{w}" height="{h}" fill="{SURFACE}"/>'
                     f'<g transform="translate({(w - width * k) / 2:.1f} {(h - 128 * k) / 2:.1f}) scale({k:.4f})">{body}</g>')


def write(path, content):
    with open(path, 'w') as f:
        f.write(content + '\n')
    return path


def png(source, out, w, h=None):
    subprocess.run(['rsvg-convert', '-w', str(w), '-h', str(h or w), source, '-o', out], check=True)
    return out


if __name__ == '__main__':
    # Master files, for anything outside the site (press, social profiles...)
    write(f'{HERE}/logo.svg', lockup())
    write(f'{HERE}/logo-icon.svg', icon())
    write(f'{HERE}/logo-tile.svg', tile())

    # Used by the templates: header and footer icon, JSON-LD logo, emails, og:image
    write(f'{IMAGES}/logo-icon.svg', icon())
    bare = write(f'{TMP}/icon.svg', icon())
    png(bare, f'{IMAGES}/logo.png', 512)
    png(bare, f'{IMAGES}/email-logo.png', 108)
    png(write(f'{TMP}/share.svg', share()), f'{IMAGES}/share.png', 2400, 1260)

    # Favicons and app icons
    rounded = write(f'{PUBLIC}/favicon.svg', tile())
    square = write(f'{TMP}/square.svg', tile(radius=0))
    for size in (16, 32):
        png(rounded, f'{PUBLIC}/favicon-{size}x{size}.png', size)
    for size in (192, 512):
        png(rounded, f'{PUBLIC}/android-chrome-{size}x{size}.png', size)
    ico = [png(rounded, f'{TMP}/ico-{size}.png', size) for size in (16, 32, 48)]
    subprocess.run(['magick', *ico, f'{PUBLIC}/favicon.ico'], check=True)
    for size in (57, 60, 72, 76, 114, 120, 144, 152, 180):
        png(square, f'{PUBLIC}/apple-touch-icon-{size}x{size}.png', size)
        png(square, f'{PUBLIC}/apple-touch-icon-{size}x{size}-precomposed.png', size)
    png(square, f'{PUBLIC}/apple-touch-icon.png', 180)
    png(square, f'{PUBLIC}/apple-touch-icon-precomposed.png', 180)
    # Windows paints the tile colour (browserconfig.xml) behind the owl
    png(write(f'{TMP}/mstile.svg', svg(270, 270, placed(150, 135, 135))), f'{PUBLIC}/mstile-150x150.png', 270)

    print('done')

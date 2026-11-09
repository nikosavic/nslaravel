#!/usr/bin/env python3
"""
Draws the background art for the home page sections and writes it to public/images/art/.

The SVGs are used as CSS masks (see "section art" in resources/css/app.css), so only their shape matters: the colour
comes from the theme, which is why one file works in light and dark mode. Re-run after editing:

    pip install numpy matplotlib shapely
    python3 resources/art/make_section_art.py

  projects-wires.svg / projects-fill.svg   wireframes of the kinds of screens in the list (browser windows, a phone)
  stack-layers.svg / stack-top.svg         an isometric stack of plates, the top one drawn separately for the accent
  runs-topo.svg / runs-route.svg           contour lines of a hill and a GPS-style running route over them
"""
import math
import pathlib
import random

import matplotlib

matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np
from shapely.geometry import LineString, Polygon
from shapely.ops import unary_union

OUT = pathlib.Path(__file__).resolve().parents[2] / "public" / "images" / "art"
OUT.mkdir(parents=True, exist_ok=True)


def svg(w, h, body, name):
    doc = (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}" '
        f'fill="none" stroke="#000" stroke-linecap="round" stroke-linejoin="round">{body}</svg>'
    )
    (OUT / name).write_text(doc, encoding="utf-8")
    print(f"{name}: {len(doc) / 1024:.1f} KB")


def f(n):
    return f"{n:.1f}".rstrip("0").rstrip(".")


# ---------------------------------------------------------------- runs: contour lines + route
def topo():
    W, H = 1600, 900
    rnd = np.random.default_rng(11)
    gx, gy = 200, 113
    xs, ys = np.meshgrid(np.linspace(0, W, gx), np.linspace(0, H, gy))
    z = np.zeros_like(xs)
    # a handful of soft hills, bigger to the right so the lines bunch up there
    for _ in range(11):
        cx, cy = rnd.uniform(0, W), rnd.uniform(0, H)
        s = rnd.uniform(160, 380)
        z += rnd.uniform(0.5, 1.2) * np.exp(-(((xs - cx) ** 2) / (2 * s * s) + ((ys - cy) ** 2) / (2 * (s * 0.7) ** 2)))
    z += 0.12 * np.sin(xs / 210 + 0.7) * np.cos(ys / 170)
    levels = np.linspace(z.min() + 0.08, z.max() - 0.05, 26)
    cs = plt.contour(xs, ys, z, levels=levels)
    paths = []
    for i, segs in enumerate(cs.allsegs):
        width = 1.7 if i % 5 == 0 else 1.0
        for seg in segs:
            if len(seg) < 8:
                continue
            d = "M" + " L".join(f"{f(x)} {f(y)}" for x, y in seg[::2])
            paths.append(f'<path d="{d}" stroke-width="{width}"/>')
    plt.close("all")
    svg(W, H, "".join(paths), "runs-topo.svg")

    # route: a loop in the lower-left, where the section's title column leaves room
    pts = []
    for k in range(14):
        a = 2 * math.pi * k / 14
        r = 1 + 0.22 * math.sin(3 * a + 0.6) + 0.12 * math.cos(5 * a)
        pts.append((330 + 235 * r * math.cos(a), 650 + 128 * r * math.sin(a)))
    pts = pts + pts[:3]

    def catmull(p):
        d = f"M{f(p[1][0])} {f(p[1][1])}"
        for i in range(1, len(p) - 2):
            p0, p1, p2, p3 = p[i - 1], p[i], p[i + 1], p[i + 2]
            c1 = (p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6)
            c2 = (p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6)
            d += f" C{f(c1[0])} {f(c1[1])} {f(c2[0])} {f(c2[1])} {f(p2[0])} {f(p2[1])}"
        return d

    d = catmull(pts)
    sx, sy = pts[1]
    ex, ey = pts[-2]
    ticks = "".join(
        f'<circle cx="{f(pts[i][0])}" cy="{f(pts[i][1])}" r="4.5" fill="#000" stroke="none"/>' for i in range(3, len(pts) - 3, 3)
    )
    body = (
        f'<path d="{d}" stroke-width="5"/>{ticks}'
        f'<circle cx="{f(sx)}" cy="{f(sy)}" r="11" fill="#000" stroke="none"/>'
        f'<circle cx="{f(ex)}" cy="{f(ey)}" r="15" stroke-width="4"/>'
    )
    svg(W, H, body, "runs-route.svg")


# ---------------------------------------------------------------- stack: isometric plates
def stack():
    W, H = 1600, 900
    # sits in the empty lower-left corner of the section, under the title, so it never runs behind the text
    cx, wd, th, step, n = 330, 150, 16, 40, 4
    hh = wd / 2  # 2:1 isometric
    bottom = 520 + (n - 1) * step

    def hexagon(cy):
        return Polygon([(cx - wd, cy), (cx, cy - hh), (cx + wd, cy), (cx + wd, cy + th), (cx, cy + hh + th), (cx - wd, cy + th)])

    def lines(cy):
        top = [(cx - wd, cy), (cx, cy - hh), (cx + wd, cy), (cx, cy + hh), (cx - wd, cy)]
        return [
            LineString(top),
            LineString([(cx - wd, cy), (cx - wd, cy + th), (cx, cy + hh + th), (cx + wd, cy + th), (cx + wd, cy)]),
            LineString([(cx, cy + hh), (cx, cy + hh + th)]),
        ]

    def path(geom):
        parts = [geom] if geom.geom_type == "LineString" else list(geom.geoms)
        out = []
        for g in parts:
            if g.length < 3:
                continue
            out.append("M" + " L".join(f"{f(x)} {f(y)}" for x, y in g.coords))
        return " ".join(out)

    centers = [bottom - i * step for i in range(n)]  # bottom first, top last
    body, covered = [], None
    # each plate is hidden by every plate above it
    for i, cy in enumerate(centers[:-1]):
        above = unary_union([hexagon(c) for c in centers[i + 1:]]).buffer(1.5)
        for ln in lines(cy):
            vis = ln.difference(above)
            if not vis.is_empty:
                body.append(f'<path d="{path(vis)}" stroke-width="2"/>')
    svg(W, H, "".join(body), "stack-layers.svg")

    cy = centers[-1]
    inner = 0.62
    rh = hh * inner
    inset = f"M{f(cx - wd * inner)} {f(cy)} L{f(cx)} {f(cy - rh)} L{f(cx + wd * inner)} {f(cy)} L{f(cx)} {f(cy + rh)} Z"
    top = "".join(f'<path d="{path(ln)}" stroke-width="3"/>' for ln in lines(cy))
    top += f'<path d="{inset}" stroke-width="2" stroke-dasharray="3 9"/>'
    top += f'<circle cx="{cx}" cy="{f(cy)}" r="5" fill="#000" stroke="none"/>'
    svg(W, H, top, "stack-top.svg")


# ---------------------------------------------------------------- projects: wireframes
def wires():
    W, H = 1600, 1100
    random.seed(4)
    line, fill = [], []

    def rect(x, y, w, h, r=6, sw=2, fl=False):
        el = f'<rect x="{f(x)}" y="{f(y)}" width="{f(w)}" height="{f(h)}" rx="{r}" stroke-width="{sw}"/>'
        (fill if fl else line).append(el if not fl else el.replace('stroke-width', 'fill="#000" stroke-width'))

    def bar(x, y, w, h=8):
        rect(x, y, w, h, r=h / 2, sw=2)

    def seg(points, sw=2.5, area=None):
        line.append(f'<path d="M{" L".join(f"{f(a)} {f(b)}" for a, b in points)}" stroke-width="{sw}"/>')
        if area:
            fill.append(f'<path d="M{" L".join(f"{f(a)} {f(b)}" for a, b in points)} L{f(points[-1][0])} {f(area)} L{f(points[0][0])} {f(area)} Z" fill="#000" stroke="none"/>')

    def chrome(x, y, w, h):
        rect(x, y, w, h, r=16, sw=2.5)
        seg([(x, y + 38), (x + w, y + 38)], 2)
        for i in range(3):
            line.append(f'<circle cx="{f(x + 24 + i * 18)}" cy="{f(y + 19)}" r="5" stroke-width="2"/>')
        rect(x + 100, y + 10, min(w - 140, 280), 18, r=9)

    def dash(x, y, w, h):
        chrome(x, y, w, h)
        sx = x + 118
        seg([(sx, y + 38), (sx, y + h)], 2)
        for i in range(5):
            bar(x + 22, y + 66 + i * 30, 70 if i % 2 else 56)
        cw = (w - 118 - 66) / 3
        for i in range(3):
            rect(sx + 22 + i * (cw + 11), y + 62, cw, 66, r=10)
            bar(sx + 36 + i * (cw + 11), y + 80, cw * 0.4, 7)
            bar(sx + 36 + i * (cw + 11), y + 98, cw * 0.65, 14)
        rect(sx + 22, y + 62, cw, 66, r=10, fl=True)
        cy0, ch = y + 152, h - 152 - 48
        pts = [(sx + 22 + k * (w - 118 - 44) / 8, cy0 + ch * (0.7 - 0.5 * math.sin(k / 1.7 + 0.6) * 0.6 - 0.04 * k)) for k in range(9)]
        seg(pts, 3, area=cy0 + ch)

    def landing(x, y, w, h):
        chrome(x, y, w, h)
        for i, wi in enumerate((0.62, 0.48)):
            rect(x + 40, y + 80 + i * 34, (w - 80) * wi, 22, r=11, sw=2.5)
        bar(x + 40, y + 160, (w - 80) * 0.55, 8)
        bar(x + 40, y + 178, (w - 80) * 0.4, 8)
        rect(x + 40, y + 214, 120, 40, r=20, fl=True)
        rect(x + 176, y + 214, 100, 40, r=20)
        ix, iy, iw, ih = x + 40, y + 280, w - 80, h - 280 - 30
        rect(ix, iy, iw, ih, r=12)
        seg([(ix, iy), (ix + iw, iy + ih)], 1.5)
        seg([(ix + iw, iy), (ix, iy + ih)], 1.5)

    def rows(x, y, w, h):
        chrome(x, y, w, h)
        n = int((h - 60) // 76)
        for i in range(n):
            ry = y + 56 + i * 76
            rect(x + 28, ry, 78, 56, r=10, fl=(i == 1))
            bar(x + 126, ry + 8, (w - 330) * (0.8 - 0.12 * (i % 3)), 14)
            bar(x + 126, ry + 34, (w - 330) * 0.95, 8)
            rect(x + w - 120, ry + 14, 90, 28, r=14)
            if i < n - 1:
                seg([(x + 28, ry + 66), (x + w - 28, ry + 66)], 1.5)

    def phone(x, y, w, h):
        rect(x, y, w, h, r=36, sw=2.5)
        rect(x + w / 2 - 34, y + 14, 68, 14, r=7)
        rect(x + 20, y + 54, w - 40, 110, r=16)
        seg([(x + 20, y + 164), (x + w - 20, y + 54)], 1.5)
        for i in range(4):
            rect(x + 20, y + 186 + i * 50, 40, 40, r=10, fl=(i == 0))
            bar(x + 72, y + 196 + i * 50, (w - 110) * (0.9 - 0.15 * i), 10)
            bar(x + 72, y + 214 + i * 50, (w - 110) * 0.6, 7)
        seg([(x + w / 2 - 28, y + h - 18), (x + w / 2 + 28, y + h - 18)], 3)

    dash(-70, 640, 540, 390)
    phone(515, 575, 190, 410)
    landing(1230, 30, 520, 410)
    rows(1180, 690, 520, 380)
    svg(W, H, "".join(line), "projects-wires.svg")
    svg(W, H, "".join(fill), "projects-fill.svg")


topo()
stack()
wires()

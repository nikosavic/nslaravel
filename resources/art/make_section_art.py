#!/usr/bin/env python3
"""
Draws the background art for the home page sections and writes it to public/images/art/.

The SVGs are used as CSS masks (see "section art" in resources/css/app.css), so only their shape matters: the colour
comes from the theme, which is why one file works in light and dark mode. Re-run after editing:

    pip install numpy matplotlib shapely
    python3 resources/art/make_section_art.py

  stack-layers.svg / stack-top.svg         an isometric stack of plates, the top one drawn separately for the accent
  runs-topo.svg / runs-track.svg           contour lines of a hill and an athletics track with lanes
  projects-grid.svg / projects-block.svg   drafting-sheet graph paper (a tile) and a small drawing title block
                                           (these two have fixed dark ink: the Projects section is always gold)
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


# ---------------------------------------------------------------- runs: contour lines + athletics track
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

    # athletics track in the lower-left, where the section's title column leaves room: a stadium-shaped oval with
    # six lanes, a pitch in the middle, a start/finish line and a runner on lane two, tilted a little
    cx, cy, tilt = 330, 640, -9
    ow, oh, lane, lanes = 500, 250, 15, 6

    def stadium(inset):
        w, h = ow - 2 * inset, oh - 2 * inset
        r = h / 2
        x0, x1, y0, y1 = cx - w / 2, cx + w / 2, cy - h / 2, cy + h / 2
        return f"M{f(x0 + r)} {f(y0)} H{f(x1 - r)} A{f(r)} {f(r)} 0 0 1 {f(x1 - r)} {f(y1)} H{f(x0 + r)} A{f(r)} {f(r)} 0 0 1 {f(x0 + r)} {f(y0)} Z"

    body = f'<path d="{stadium(0)}" stroke-width="5"/>'
    for i in range(1, lanes + 1):
        body += f'<path d="{stadium(i * lane)}" stroke-width="{3 if i == lanes else 2}"/>'
    inner = lanes * lane
    ih, iw = oh - 2 * inner, ow - 2 * inner
    # pitch with a halfway line and centre circle
    px0, px1 = cx - (iw - ih) / 2 - ih * 0.12, cx + (iw - ih) / 2 + ih * 0.12
    py0, py1 = cy - ih / 2 + 14, cy + ih / 2 - 14
    body += f'<rect x="{f(px0)}" y="{f(py0)}" width="{f(px1 - px0)}" height="{f(py1 - py0)}" rx="4" stroke-width="2"/>'
    body += f'<path d="M{f(cx)} {f(py0)} V{f(py1)}" stroke-width="2"/><circle cx="{f(cx)}" cy="{f(cy)}" r="{f((py1 - py0) * 0.22)}" stroke-width="2"/>'
    # start/finish line across all lanes on the bottom straight, and a runner on lane two
    sx = cx + 120
    body += f'<path d="M{f(sx)} {f(cy + oh / 2)} V{f(cy + oh / 2 - lanes * lane)}" stroke-width="6"/>'
    body += f'<circle cx="{f(sx - 70)}" cy="{f(cy + oh / 2 - 1.5 * lane)}" r="7" fill="#000" stroke="none"/>'
    body = f'<g transform="rotate({tilt} {cx} {cy})">{body}</g>'
    svg(W, H, body, "runs-track.svg")


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


# ---------------------------------------------------------------- projects: drafting sheet
def sheet():
    ink = "#0b2526"
    # 160px tile: a line every 32px, a stronger one and a small crosshair every 160px, all in the tile's interior
    minor = "".join(
        f'<path d="M{v} 0 V160 M0 {v} H160"/>' for v in (16, 48, 112, 144)
    )
    major = '<path d="M80 0 V160 M0 80 H160" stroke-opacity=".1"/>'
    cross = '<path d="M71 80 H89 M80 71 V89" stroke-opacity=".2" stroke-width="1.5"/>'
    grid = (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160" fill="none" '
        f'stroke="{ink}" stroke-width="1"><g stroke-opacity=".05">{minor}</g>{major}{cross}</svg>'
    )
    (OUT / "projects-grid.svg").write_text(grid, encoding="utf-8")
    print(f"projects-grid.svg: {len(grid) / 1024:.1f} KB")

    # title block, as in the corner of an engineering drawing
    font = 'font-family="ui-monospace, Menlo, Consolas, monospace" font-size="10" letter-spacing="1" fill="%s" stroke="none" fill-opacity=".42"' % ink
    block = (
        f'<svg xmlns="http://www.w3.org/2000/svg" width="300" height="80" viewBox="0 0 300 80" fill="none" '
        f'stroke="{ink}" stroke-opacity=".28" stroke-width="1.5">'
        '<rect x="1" y="1" width="298" height="78"/>'
        '<path d="M1 28 H299 M1 54 H299 M196 28 V79"/>'
        f'<text x="12" y="19" {font}>NIKOLA SAVIC / PROJECTS</text>'
        f'<text x="12" y="45" {font}>FULL-STACK WORK</text>'
        f'<text x="208" y="45" {font}>SHEET 02</text>'
        f'<text x="12" y="71" {font}>LARAVEL  LIVEWIRE  TAILWIND</text>'
        f'<text x="208" y="71" {font}>REV 2026</text>'
        "</svg>"
    )
    (OUT / "projects-block.svg").write_text(block, encoding="utf-8")
    print(f"projects-block.svg: {len(block) / 1024:.1f} KB")


topo()
stack()
sheet()

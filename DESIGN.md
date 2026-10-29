# Design system: Track & Bib

The visual world of the web app (`apps/web`). Tokens live in
`apps/web/src/app/globals.css`; this file explains how to use them. Direction
notes for designers and agents: `docs/design/DIRECTION.md` (development only).

**Idea.** A gym is a track: every branch runs in its own lane, every member
wears a bib. The world comes from Ethiopian athletics (tartan track, lane
lines, race bibs, results boards), not from generic "fitness" imagery.

## Colour

| Token | Light | Dark | Use |
|---|---|---|---|
| `ground` | #E9EAE7 | #121312 | Page background (stadium concrete) |
| `surface` / `surface-2` | #FFFFFF / #F4F4F1 | #1B1D1B / #171917 | Cards, inputs / quiet panels |
| `ink` / `graphite` | #141514 / #565A56 | #ECEDEA / #A3A7A2 | Text / secondary text (≥5.8:1 on ground) |
| `line` / `line-strong` | #D6D7D2 / #B9BBB5 | | Dividers / input borders |
| `track` / `track-deep` / `track-soft` | #C2412D / #9C3320 / #F6E3DE | #E0563F / … | Brand and primary actions; deep for small red text and hover |
| `asphalt` | #161816 | #0D0E0D | Console sidebar |
| `infield` | #2E6B3C | #5FAE70 | Allowed, open, enabled |
| `amber` | #8A5A12 | #E0A94A | Waiting, opens later |
| `critical` | #B42318 | #F0705F | Errors, refused |

Rules:
- White on `track` is 5.1:1. Body text in red uses `track-deep` (7.2:1); `track` itself is only for large type and marks.
- Status is never colour alone: always an icon or words with it.
- Public site uses red **committed** (whole bands); the console is **restrained** (asphalt rail, neutral work area, red only for primary action and selection).

## Type

- **Archivo** (variable, `wdth` axis) for everything. `.font-bib` sets width 72, weight 800: headlines, bib numbers, big figures only. UI labels, buttons and tables stay at normal width.
- **Noto Sans Ethiopic** (variable) is the fallback for Amharic; Amharic headlines get extra line height.
- Tabular figures (`.tabular`) for times, money, FT numbers, counts.

## Shape and depth

- 6px corners (`rounded-[6px]`) on controls, cards and panels. The bib keeps 12px corners and four pin holes.
- Shadows only on the bib (soft, offset). Cards use a 1px `line` border, no shadow.
- No gradients, glass, eyebrow labels, or icon-tile card grids.

## Signature pieces

- **Track field** (`.track-field`): red with white lane lines every `--lane-h`. Never run lines through text: text bands use `.track-band` (lines on the edges only) or sit on a solid block of track.
- **Lanes** (`TrackLanes`, `BranchDayLanes`): one lane per branch. Public: occupancy (sample data, labelled). Console: today's opening window on a shared 05:00–23:00 clock with a now-line (real data).
- **Bib** (`Bib`): the member pass, with a real QR code. Sample bibs encode a sample marker and say so.

## Components

`components/ui.tsx`: `Button` (primary = track, secondary, danger), `Field` + `Input`/`Select` (visible labels, hint or error under the field), `Toggle` (switch, on = infield), `Card`, `Alert`, `PageHeader`. Icons: lucide-react, 16–20px, one stroke weight.

Console shell: asphalt sidebar on desktop, collapsible menu below `lg`, branch switcher at the top of the rail, account and language at the bottom.

## Motion

One moment: lane fills and runners move to their value once on load (`.lane-fill`, `.lane-runner`), disabled under `prefers-reduced-motion`. UI transitions 150 ms on colour only.

## Imagery

Photographs live in `apps/web/src/assets/images/` (statically imported with `next/image`, blur placeholders, AVIF/WebP) and the share image in `apps/web/public/images/og.jpg`. Provenance and prompts: `docs/design/IMAGES.md`.

- Documentary, natural light, slightly warm and desaturated; Ethiopian people in real gym and running clothes; one tartan-red accent per frame.
- Every image has bilingual alt text from the dictionaries.
- Text over a photo sits on calm areas only, with a scrim taken from the ground colour (light) or black (dark), never over faces.
- No logos, text or brand marks inside photos; remove them before shipping.

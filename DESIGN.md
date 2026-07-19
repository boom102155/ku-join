---
name: KU Join
description: ระบบลงทะเบียนงานและกิจกรรมที่ตั้งค่าได้จากหลังบ้าน
colors:
  primary: "oklch(0.45 0.12 164)"
  primary-deep: "oklch(0.32 0.09 164)"
  primary-bright: "oklch(0.59 0.11 164)"
  accent-gold: "oklch(0.78 0.16 86)"
  background: "oklch(0.97 0.006 164)"
  surface: "oklch(1 0 0)"
  ink: "oklch(0.23 0.025 164)"
  muted: "oklch(0.45 0.025 164)"
  line: "oklch(0.89 0.012 164)"
  danger: "oklch(0.53 0.19 28)"
typography:
  display:
    fontFamily: "Sarabun, Noto Sans Thai, -apple-system, sans-serif"
    fontSize: "clamp(2rem, 4.7vw, 3.45rem)"
    fontWeight: 600
    lineHeight: 1.13
    letterSpacing: "-0.025em"
  body:
    fontFamily: "Sarabun, Noto Sans Thai, -apple-system, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Sarabun, Noto Sans Thai, -apple-system, sans-serif"
    fontSize: "0.9rem"
    fontWeight: 600
rounded:
  control: "8px"
  card: "14px"
  feature: "16px"
spacing:
  compact: "8px"
  standard: "16px"
  panel: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "10px 20px"
    height: "46px"
  button-outline:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.primary}"
    rounded: "{rounded.control}"
    padding: "10px 20px"
    height: "46px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "8px 12px"
    height: "42px"
---

# Design System: KU Join

## Overview

**Creative North Star: "The Organized Ceremony Desk"**

KU Join uses a calm green institutional palette with clear white work surfaces. It should feel like a prepared registration desk: structured, dependable, and quick to understand under time pressure.

The system is product-first. Color identifies action and state; it is not decoration. Content is arranged in short, purposeful groups so participants can complete a registration and staff can manage a live event without hunting through visual noise.

**Key Characteristics:**

- Deep green anchors navigation and primary actions.
- White panels separate task groups without excessive nested cards.
- Thai-first typography is large enough for event-day use.
- Icons are local Boxicons SVG masks and inherit the surrounding text color.

## Colors

The palette is a green institutional system supported by a gold date/active accent and restrained mint-tinted neutrals.

### Primary

- **KU Registration Green:** `primary` drives primary buttons, active states, section bars, and selection controls.
- **Ceremony Deep Green:** `primary-deep` anchors navigation, footers, and admin sidebars.
- **Active Gold:** `accent-gold` highlights dates and active navigation without replacing the primary action color.

### Neutral

- **Mint Work Surface:** `background` is the quiet page canvas.
- **White Task Surface:** `surface` is reserved for forms, tables, and panels.
- **Evergreen Ink:** `ink` is the default readable body color.
- **Quiet Evergreen:** `muted` is secondary copy only; do not use it for essential instructions.

### Named Rules

**The Green Means Action Rule.** Primary green always signals the active route, confirmed selection, or primary action. Do not use it as an arbitrary decoration.

## Typography

**Display / Body Font:** Sarabun, "Noto Sans Thai", -apple-system, sans-serif

**Character:** Sarabun matches the reference registration system and keeps headings, forms, and dense event tables coherent in one Thai-first family.

### Hierarchy

- **Display:** 600 weight, `clamp(2rem, 4.7vw, 3.45rem)`, 1.13 line-height. Use for public hero titles only.
- **Headline:** 600 weight, `clamp(1.8rem, 3.2vw, 2.55rem)`, 1.2 line-height. Use for page-level task headings.
- **Title:** 600–700 weight, 1.08–1.3rem. Use for panels and action groups.
- **Body:** 400 weight, 16px, 1.55 line-height. Keep explanatory copy concise.
- **Label:** 600 weight, 0.9rem. Use for form labels, table controls, and filters.

## Elevation

KU Join uses tonal separation first, with low ambient shadows only on task surfaces. Shadows do not appear on every element; they identify a discrete form, table, or admin panel.

### Shadow Vocabulary

- **Panel Lift:** `0 5px 20px rgb(8 61 48 / .06)` for tables and admin panels.
- **Form Lift:** `0 8px 28px rgb(8 61 48 / .09)` for the registration form.

### Named Rules

**The Single Surface Rule.** Do not nest shadowed panels inside shadowed panels. Use spacing and a plain inner section instead.

## Components

### Buttons

- **Shape:** gently rounded controls (8px).
- **Primary:** solid `primary` fill with white text; it is the only prominent action in a group.
- **Outline:** white surface with a green border for secondary actions.
- **Hover / Focus:** hover moves up slightly; keyboard focus uses a visible green ring or gold outline where contrast requires it.

### Chips

- **Style:** compact green-tinted labels for participant categories and schedule filters.
- **State:** selected filters use a solid primary fill and count pill; unselected filters retain a visible border.

### Cards / Containers

- **Corner Style:** 14px panels, 16px hero only.
- **Background:** white over the mint work surface.
- **Internal Padding:** 16px for compact controls and 24px for panels.

### Inputs / Fields

- **Style:** white surface, 1px `line` border, 8px corners, 42px minimum height.
- **Focus:** green border plus a 3px low-opacity green ring.
- **Error:** use `danger` with specific inline copy, never color alone.

### Navigation

- **Public:** deep-green horizontal header with a gold active underline.
- **Admin:** persistent sidebar on desktop; a 44px toggle opens a two-column menu on narrow screens.

## Do's and Don'ts

### Do:

- **Do** place the task title and primary action at the top of every task page.
- **Do** use local Boxicons SVG assets through the existing `.bx` mask system.
- **Do** retain table search, filters, page size, status, and pagination as one coherent table control group.
- **Do** preserve visible keyboard focus and reduced-motion behavior.

### Don't:

- **Don't** add decorative gradients to text or use green for non-action decoration.
- **Don't** create deeply nested card stacks or oversized rounded containers.
- **Don't** use emoji or mixed icon families where a local Boxicons icon exists.
- **Don't** hide critical mobile navigation behind horizontal scrolling; use the admin toggle pattern.

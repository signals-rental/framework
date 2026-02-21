# Signals Brand System

Version 1.0 -- February 2026

This document codifies the complete Signals brand identity system. It serves as the authoritative reference for building consistent UI across all products. When building pages, components, or marketing materials, follow these rules exactly.

---

## 1. Brand Identity

- **Master brand name:** Signals
- **Descriptor:** Modern Rental Management
- **Product family:** Eight products, one unified visual language
- **Positioning:** Equipment rental management software for hire companies
- **Industry:** UK hire / equipment rental industry

---

## 2. Logo Mark System

### 2.1 Construction

The Signals mark is a bordered rectangle with a corner accent device. It is constructed from three elements:

1. **Text:** The word "SIGNALS" set in Chakra Petch 700, uppercase, with `letter-spacing: 0.08em`
2. **Border:** `2px solid currentColor` -- the mark inherits its text color so it adapts to light/dark contexts
3. **Corner accent:** A solid square positioned at the top-right corner, overlapping the border by 2px (`top: -2px; right: -2px`). The accent color changes per product.

The corner accent is the most distinctive element. It must never be removed.

### 2.2 Clear Space

Minimum clear space of **1x** on all sides, where **x = the cap height of the letter "S"** in the mark.

### 2.3 Mark Variants

| Variant | Description | Usage |
|---------|-------------|-------|
| **Full Mark** | Bordered rectangle + corner accent + "SIGNALS" text | Primary logo. Use wherever space permits. |
| **Monogram** | Square bordered container + corner accent + letter "S" | Compact form for icons, favicons, avatars. |
| **Wordmark** | Text only "SIGNALS" in Chakra Petch 700, no border or accent | Inline use, co-branding, text-only contexts. |

### 2.4 Size Variants

| Size | CSS Class | Font Size | Padding | Corner Accent | Use Case |
|------|-----------|-----------|---------|---------------|----------|
| **XL** | `size-xl` | `2.5rem` (40px) | `12px 28px` | `14px x 14px` | Hero sections, splash screens, keynote presentations |
| **LG** | `size-lg` | `1.5rem` (24px) | `8px 20px` | `10px x 10px` | Page headers, presentation decks, printed materials |
| **MD** | `size-md` | `1rem` (16px) | `6px 16px` | `8px x 8px` | Navigation bars, card headers, sidebar branding |
| **SM** | `size-sm` | `0.75rem` (12px) | `4px 10px` | `5px x 5px` | Badges, footnotes, compact UI elements |

At size-xl, letter-spacing increases to `0.12em`.

### 2.5 Monogram Sizes

| Size | CSS Class | Dimensions | Font Size | Corner Accent |
|------|-----------|------------|-----------|---------------|
| 64px | `mono-64` | `64px x 64px` | `1.75rem` | `10px x 10px` |
| 48px | `mono-48` | `48px x 48px` | `1.25rem` | `8px x 8px` |
| 36px | `mono-36` | `36px x 36px` | `1rem` | `6px x 6px` |

### 2.6 Minimum Sizes

- **Full mark:** 80px minimum width
- **Monogram:** 24px minimum
- Below 80px, switch from full mark to monogram
- Below 24px, switch from monogram to wordmark

### 2.7 Color Variants (CSS Classes)

Apply these classes to the `.logo-mark` or `.monogram` element to set the corner accent color:

| Class | Color | Product |
|-------|-------|---------|
| `color-blue` | `#2563eb` | Signals (master) |
| `color-green` | `#059669` | Rental Framework |
| `color-sky` | `#0ea5e9` | Cloud |
| `color-cyan` | `#0891b2` | Network |
| `color-amber` | `#d97706` | Intelligence |
| `color-violet` | `#7c3aed` | Registry |
| `color-rose` | `#e11d48` | Connect |
| `color-slate` | `#475569` | Transport |

### 2.8 Mark on Dark Backgrounds

On dark backgrounds, set the mark color to white (`color: white` or `color: var(--white)`). The corner accent retains its product color.

### 2.9 Do's and Don'ts

**Do:**
- Use the mark with proper padding and the correct product accent color
- Always include the corner accent device
- On dark backgrounds, use a white mark with product-colored accent
- At small sizes, switch to the monogram

**Don't:**
- Never rotate, skew, or distort the mark
- Never remove the corner accent device
- Never use unauthorized fonts (the mark must use Chakra Petch exclusively)
- Never use non-brand accent colors
- Never use the full mark below 80px width
- Never place the mark on busy/colorful backgrounds without a dark overlay (minimum 70% opacity)

---

## 3. Color System

### 3.1 Core Palette

| Name | CSS Variable | Hex | RGB | Usage |
|------|-------------|-----|-----|-------|
| **Navy** | `--navy` | `#0f172a` | `15, 23, 42` | Primary dark background, dark text |
| **Navy Mid** | `--navy-mid` | `#1e293b` | `30, 41, 59` | Secondary dark background, dark theme cards |
| **Blue** | `--blue` | `#2563eb` | `37, 99, 235` | Signals master brand accent, links, interactive elements |
| **Blue Light** | `--blue-light` | `#3b82f6` | `59, 130, 246` | Lighter blue for dark-bg accents |
| **Blue Pale** | `--blue-pale` | `rgba(37, 99, 235, 0.08)` | -- | Subtle blue background tint |
| **Red** | `--red` | `#dc2626` | `220, 38, 38` | Revision markers, errors, "don't" indicators |
| **Base** | `--base` | `#f8f9fb` | `248, 249, 251` | Page background (light theme) |
| **White** | `--white` | `#ffffff` | `255, 255, 255` | Card backgrounds, text on dark |
| **Grey** | `--grey` | `#64748b` | `100, 116, 139` | Body text (secondary), subtitle text, annotations on light bg |
| **Grey Light** | `--grey-light` | `#94a3b8` | `148, 163, 184` | Body text on dark backgrounds, muted annotations |
| **Grey Border** | `--grey-border` | `#e2e8f0` | `226, 232, 240` | Borders, dividers, separators on light backgrounds |

### 3.2 Product Accent Colors

| Product | CSS Variable | Hex | RGB | CSS Color Class |
|---------|-------------|-----|-----|-----------------|
| **Signals** (master) | `--blue` | `#2563eb` | `37, 99, 235` | `color-blue` |
| **Rental Framework** | `--green` | `#059669` | `5, 150, 105` | `color-green` |
| **Cloud** | `--sky` | `#0ea5e9` | `14, 165, 233` | `color-sky` |
| **Network** | `--cyan` | `#0891b2` | `8, 145, 178` | `color-cyan` |
| **Intelligence** | `--amber` | `#d97706` | `217, 119, 6` | `color-amber` |
| **Registry** | `--violet` | `#7c3aed` | `124, 58, 237` | `color-violet` |
| **Connect** | `--rose` | `#e11d48` | `225, 29, 72` | `color-rose` |
| **Transport** | `--slate` | `#475569` | `71, 85, 105` | `color-slate` |

### 3.3 Tint Usage

Each product accent can be used at reduced opacity for subtle backgrounds:
- **10%** -- Panel backgrounds, hover states
- **25%** -- Highlighted regions, selected states
- **50%** -- Mid-emphasis backgrounds
- **75%** -- Strong emphasis

Never use full-strength (100%) accent colors as large-area backgrounds. It competes with content. Use 10-25% tints for panels and cards.

### 3.4 Grid Line Colors

| Name | CSS Variable | Value | Usage |
|------|-------------|-------|-------|
| Grid Fine (light) | `--grid-fine` | `rgba(59, 130, 246, 0.06)` | Fine 32px grid lines on light backgrounds |
| Grid Strong (light) | `--grid-strong` | `rgba(59, 130, 246, 0.12)` | Major 160px grid lines on light backgrounds |
| Grid Fine (dark) | `--grid-dark-fine` | `rgba(148, 163, 184, 0.08)` | Fine 32px grid lines on dark backgrounds |
| Grid Strong (dark) | `--grid-dark-strong` | `rgba(148, 163, 184, 0.15)` | Major 160px grid lines on dark backgrounds |

### 3.5 Text Color Rules

| Context | Color | Variable |
|---------|-------|----------|
| Headings on light background | Navy | `--navy` / `#0f172a` |
| Body text on light background | Grey | `--grey` / `#64748b` |
| Headings on dark background | White | `--white` / `#ffffff` |
| Body text on dark background | Grey Light | `--grey-light` / `#94a3b8` |
| Annotations / labels | Grey Light | `--grey-light` / `#94a3b8` |
| Heading emphasis (italic `<em>`) | Blue | `--blue` / `#2563eb` |

### 3.6 Accessibility (WCAG AA)

**White text on colored backgrounds:**
- AA Pass: Signals (#2563eb), Framework (#059669), Network (#0891b2), Registry (#7c3aed), Connect (#e11d48), Transport (#475569)
- Display Only (large text only): Cloud (#0ea5e9), Intelligence (#d97706)

**Navy text on colored backgrounds:**
- AA Pass: Framework (#059669), Cloud (#0ea5e9), Network (#0891b2), Intelligence (#d97706)
- Fail: Signals (#2563eb), Registry (#7c3aed), Connect (#e11d48), Transport (#475569)

---

## 4. Typography

### 4.1 Typeface Stack

| Role | Font Family | CSS Variable | Weights | Google Fonts |
|------|------------|-------------|---------|--------------|
| **Display** | Chakra Petch | `--font-display` | 500, 600, 700 | `Chakra+Petch:wght@500;600;700` |
| **Body / Mono** | Martian Mono | `--font-mono` | 400, 500 | `Martian+Mono:wght@400;500` |

### 4.2 Usage Rules

- **Chakra Petch** -- Headings, brand mark text, button labels, section stamps, navigation labels. Never use for body text.
- **Martian Mono** -- Body text, technical annotations, code, labels, subtitles. The default `font-family` for the `<body>`.

### 4.3 Heading Hierarchy

| Level | Font | Weight | Size | Line Height | Letter Spacing | Transform |
|-------|------|--------|------|-------------|----------------|-----------|
| **H1** | Chakra Petch | 700 | `clamp(2rem, 4vw, 3rem)` (32-48px) | `1.1` | `-0.01em` | `uppercase` |
| **H2** | Chakra Petch | 700 | `clamp(1.5rem, 3vw, 2.25rem)` (24-36px) | `1.15` | `-0.01em` | `uppercase` |
| **H3** | Chakra Petch | 700 | `1.125rem` (18px) | `1.3` | `-0.01em` | `uppercase` |
| **Body** | Martian Mono | 400 | `13px` (`0.8125rem`) | `1.7` | normal | none |
| **Technical** | Martian Mono | 500 | `9px` | -- | `0.1em` | `uppercase` |

### 4.4 Scale Ratio

The type scale steps are: **48 -> 36 -> 18 -> 13 -> 9** (H1, H2, H3, Body, Technical).

### 4.5 Heading Emphasis

Emphasized words in headings use `<em>` tags, rendered as:
- `font-style: italic`
- `color: var(--blue)` (`#2563eb`)
- `font-weight: 600`

### 4.6 Subtitle Text

```
font-size: 0.8125rem (13px)
color: var(--grey)
line-height: 1.8
max-width: 640px
```

On dark backgrounds: `color: var(--grey-light)`.

### 4.7 Annotation / Technical Labels

```
font-family: var(--font-mono)
font-size: 9px
text-transform: uppercase
letter-spacing: 0.08em
color: var(--grey-light)
```

### 4.8 Typography Do's and Don'ts

**Do:**
- Pair Chakra Petch headings with Martian Mono body text (this is the standard combination)
- Set display headings in uppercase with `-0.01em` letter-spacing
- Use 9px Martian Mono uppercase with 0.08em tracking for annotations

**Don't:**
- Never substitute Chakra Petch with Arial, Helvetica, or other system fonts
- Never use title case or sentence case for display headings
- Never use excessive letter-spacing beyond 0em for headings
- Never use the display font (Chakra Petch) for body text
- Never use serif fonts for body copy

---

## 5. Product Family

| # | Product | Accent Color | Hex | CSS Variable | CSS Class | Descriptor |
|---|---------|-------------|-----|-------------|-----------|------------|
| 1 | **Signals** | Blue | `#2563eb` | `--blue` | `color-blue` | Modern rental management (platform/SaaS) |
| 2 | **Rental Framework** | Green | `#059669` | `--green` | `color-green` | Open source rental management framework (MIT) |
| 3 | **Cloud** | Sky | `#0ea5e9` | `--sky` | `color-sky` | Managed hosting for Signals |
| 4 | **Network** | Cyan | `#0891b2` | `--cyan` | `color-cyan` | Credit scores and benchmarks |
| 5 | **Intelligence** | Amber | `#d97706` | `--amber` | `color-amber` | AI-powered analytics and predictions |
| 6 | **Registry** | Violet | `#7c3aed` | `--violet` | `color-violet` | Product and venue database |
| 7 | **Connect** | Rose | `#e11d48` | `--rose` | `color-rose` | B2B equipment marketplace |
| 8 | **Transport** | Slate | `#475569` | `--slate` | `color-slate` | Logistics and delivery |

### 5.1 Product Lockup Rules

- Each product uses the **same master mark** ("SIGNALS") with a different corner accent color
- The product name appears as a descriptor below or alongside the mark
- The mark text is always "SIGNALS" -- it never changes to the product name
- The corner accent is the only element that changes between products
- Extended lockups: mark + product name below + optional tagline

### 5.2 Product Color Scheme Application

Each product's accent color is applied to:
- Corner accent on the brand mark
- CTA/action buttons (`background: [accent]`)
- Active sidebar indicators (`border-left: 3px solid [accent]`)
- Table header backgrounds
- Progress bars and chart fills
- Stat/metric values
- Links and interactive text
- Feature card top borders (`border-top: 3px solid [accent]`)

The core layout (navy nav bar, base/white content area, typography) stays constant. Only the accent shifts.

---

## 6. Layout Patterns

### 6.1 Container

```css
max-width: 1200px;
margin: 0 auto;
padding: 0 32px;
```

On mobile (max-width 640px): `padding: 0 16px`.

### 6.2 Grid Backgrounds

The brand uses a blueprint/technical drawing grid pattern as a background texture. Two variants:

**Light Grid** (`.grid-bg-light`):
```css
background-color: #f8f9fb;
/* Fine lines every 32px, strong lines every 160px */
/* Uses --grid-fine and --grid-strong colors */
```

**Dark Grid** (`.grid-bg-dark`):
```css
background-color: #0f172a;
color: #94a3b8;
/* Fine lines every 32px, strong lines every 160px */
/* Uses --grid-dark-fine and --grid-dark-strong colors */
```

### 6.3 Section Stamps

A technical drawing-inspired label used to identify sections. Structure:

```
[Number (navy bg, white text)] [Title (navy text, bordered right)] [Revision (red text)]
```

Properties:
- Border: `2px solid var(--navy)`
- Font: Chakra Petch, 0.625rem, weight 600, uppercase, `letter-spacing: 0.06em`
- Number background: `var(--navy)`, text: white
- Revision text: `var(--red)`, weight 700

On dark backgrounds, the stamp inverts: border becomes grey-light, number bg becomes grey-light with navy text.

### 6.4 Dimension Line Dividers

Technical drawing-style horizontal dividers between sections:

```
[Left Arrow] -------- [Label] -------- [Right Arrow]
```

- Arrows: Blue (`--blue`) CSS triangles
- Line: `1px solid var(--blue)` at `opacity: 0.3`
- Label: Martian Mono, 9px, uppercase, `letter-spacing: 0.1em`, grey

### 6.5 Trim / Crop Marks

Corner marks placed around hero content using `::before` and `::after` pseudo-elements:
- 18px x 18px L-shaped corners
- `1.5px solid var(--blue)`

### 6.6 Registration Marks

Crosshair decorations at page corners:
- 32px x 32px with centered cross lines and 12px circle
- Color: `rgba(37, 99, 235, 0.2)`

---

## 7. Component Patterns

### 7.1 Cards

- **Background:** `var(--white)` (`#ffffff`)
- **Border:** `1px solid var(--grey-border)` (`#e2e8f0`)
- **Border radius:** None (sharp corners throughout the brand)
- **Hover state:** Border color shifts to `var(--blue)`, subtle gradient background, `box-shadow: 0 4px 24px rgba(15, 23, 42, 0.06)`, `transform: translateY(-2px)`
- **Active indicator:** 3px left border in accent color (appears on hover via `::before`)

### 7.2 Buttons

- **Font:** Chakra Petch, weight 600
- **Text transform:** `uppercase`
- **Letter spacing:** `0.04em` to `0.06em`
- **Border radius:** None (sharp corners)
- **Primary:** Background in product accent color, white text
- **Outline:** `border: 1px solid`, transparent background, text matches border color
- **Padding:** `6px 14px` (small), `10px 24px` (standard)

### 7.3 Navigation (Top Bar)

- **Position:** Fixed, top: 0
- **Height:** 48px
- **Background:** `var(--navy)` (`#0f172a`)
- **Z-index:** 1000
- **Padding:** `0 24px`
- **Link style:** Martian Mono, 11px, `color: var(--grey-light)`, hover: `color: var(--white)`
- **Contains:** Back link (left), sheet identifier (center), next link (right)

### 7.4 Sub-Navigation

- **Position:** Fixed, `top: 48px`
- **Height:** 40-44px
- **Background:** `var(--base)` or `var(--white)`
- **Border bottom:** `1px solid var(--grey-border)`
- **Z-index:** 999
- **Link style:** Chakra Petch, 11px, weight 600, uppercase, `letter-spacing: 0.06em`
- **Active state:** `color: var(--blue)`, `border-bottom: 2px solid var(--blue)`

### 7.5 Footer

- **Background:** Dark -- `var(--navy)` with grid-bg-dark pattern; or light -- `var(--base)` with border-top
- **Padding:** `48px 0`
- **Contains:** Small logo mark, version string, confidentiality notice
- **Text:** Martian Mono, 9px, uppercase, grey

### 7.6 Do/Don't Guideline Cards

- **Do cards:** `border-top: 3px solid var(--green)` (`#059669`), label in green
- **Don't cards:** `border-top: 3px solid var(--red)` (`#dc2626`), label in red
- **Example area:** `background: var(--base)`, `padding: 32px`
- **Description:** `font-size: 0.6875rem`, `color: var(--grey)`

### 7.7 Swatch Cards

- **Border:** `1px solid var(--grey-border)`
- **Background:** `var(--white)`
- **Color block:** 100px height
- **Info section:** `padding: 14px 16px`
- **Name:** Chakra Petch, 700, 0.8125rem, uppercase
- **Hex value:** Martian Mono, 0.6875rem, grey

---

## 8. Design Tokens (CSS Custom Properties)

```css
:root {
    /* Backgrounds */
    --base: #f8f9fb;
    --white: #ffffff;
    --navy: #0f172a;
    --navy-mid: #1e293b;

    /* Brand Colors */
    --blue: #2563eb;
    --blue-light: #3b82f6;
    --blue-pale: rgba(37, 99, 235, 0.08);
    --red: #dc2626;

    /* Neutrals */
    --grey: #64748b;
    --grey-light: #94a3b8;
    --grey-border: #e2e8f0;

    /* Product Accents */
    --green: #059669;
    --sky: #0ea5e9;
    --cyan: #0891b2;
    --amber: #d97706;
    --violet: #7c3aed;
    --rose: #e11d48;
    --slate: #475569;

    /* Grid Lines */
    --grid-fine: rgba(59, 130, 246, 0.06);
    --grid-strong: rgba(59, 130, 246, 0.12);
    --grid-dark-fine: rgba(148, 163, 184, 0.08);
    --grid-dark-strong: rgba(148, 163, 184, 0.15);

    /* Typography */
    --font-display: 'Chakra Petch', sans-serif;
    --font-mono: 'Martian Mono', monospace;
}
```

---

## 9. Photography and Overlay Rules

- On photography, apply a dark overlay (**minimum 70% opacity**, navy) before placing the mark
- Never place the mark on busy or colorful backgrounds without an overlay
- The overlay ensures legibility and preserves the technical, precise brand feel

---

## 10. Co-Branding Rules

1. **Signals mark always on the left** in any co-branded lockup
2. **Minimum 2x clear space** between the Signals mark and the partner mark (relative to mark height)
3. **Equal visual weight** -- partner logos scaled to appear optically balanced
4. **Vertical divider line** separates marks: `1px solid var(--grey-border)`
5. **Partner marks must not exceed the height** of the Signals mark
6. **Corner accent color persists** as the product-appropriate color
7. **Maximum 2 brands per lockup** -- for three or more, list separately
8. **The corner accent must always be present** -- never remove it for co-branding or any other reason

---

## 11. Theme Modes

Products can use light or dark themes. The accent color adapts to both.

**Light theme:**
- Background: `var(--base)` / `var(--white)`
- Heading text: `var(--navy)`
- Body text: `var(--grey)`
- Outline button: `border-color: var(--navy)`, `color: var(--navy)`

**Dark theme:**
- Background: `var(--navy-mid)` (`#1e293b`)
- Heading text: `var(--white)`
- Body text: `var(--grey-light)`
- Outline button: `border-color: var(--white)`, `color: var(--white)`

In both themes:
- Navigation bar is always `var(--navy)`
- Filled CTA buttons use the product accent color with white text
- The accent color for progress bars, charts, and interactive elements stays the same

---

## 12. Design Aesthetic

The overall design aesthetic is a **blueprint / technical drawing** style:

- Grid paper backgrounds with fine and major grid lines
- Section stamps with drawing numbers and revision markers (e.g., "BRD-001 REV A")
- Dimension line dividers between sections (arrows + thin line + label)
- Trim/crop marks around key content
- Registration marks at page corners
- Scale bars and "NTS" (Not to Scale) annotations
- North arrow compass decoration
- Annotation labels in 9px uppercase monospace
- Crosshair alignment guides through hero areas
- No border-radius on any element (sharp corners everywhere)
- 1px borders as the standard weight for cards and containers
- ISO-style title blocks in footers with drawing number and revision

This aesthetic reinforces the precision and engineering nature of the product.

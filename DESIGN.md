---
name: Kinetic Velocity System
colors:
  surface: '#f9f9fc'
  surface-dim: '#dadadc'
  surface-bright: '#f9f9fc'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f3f3f6'
  surface-container: '#eeeef0'
  surface-container-high: '#e8e8ea'
  surface-container-highest: '#e2e2e5'
  on-surface: '#1a1c1e'
  on-surface-variant: '#3e4a3c'
  inverse-surface: '#2f3133'
  inverse-on-surface: '#f0f0f3'
  outline: '#6e7b6b'
  outline-variant: '#bdcab9'
  surface-tint: '#006e25'
  primary: '#006e25'
  on-primary: '#ffffff'
  primary-container: '#28a745'
  on-primary-container: '#00330d'
  inverse-primary: '#66df75'
  secondary: '#7c5800'
  on-secondary: '#ffffff'
  secondary-container: '#feb700'
  on-secondary-container: '#6b4b00'
  tertiary: '#ae3200'
  on-tertiary: '#ffffff'
  tertiary-container: '#ff591f'
  on-tertiary-container: '#541300'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#83fc8e'
  primary-fixed-dim: '#66df75'
  on-primary-fixed: '#002106'
  on-primary-fixed-variant: '#00531a'
  secondary-fixed: '#ffdea8'
  secondary-fixed-dim: '#ffba20'
  on-secondary-fixed: '#271900'
  on-secondary-fixed-variant: '#5e4200'
  tertiary-fixed: '#ffdbd0'
  tertiary-fixed-dim: '#ffb59e'
  on-tertiary-fixed: '#3a0b00'
  on-tertiary-fixed-variant: '#852400'
  background: '#f9f9fc'
  on-background: '#1a1c1e'
  surface-variant: '#e2e2e5'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '800'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 34px
  title-md:
    fontFamily: Hanken Grotesk
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-bold:
    fontFamily: Hanken Grotesk
    fontSize: 14px
    fontWeight: '700'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 48px
  gutter: 16px
  margin-mobile: 16px
  margin-desktop: 32px
---

## Brand & Style

This design system is built for speed, precision, and high-energy logistics. It captures the momentum of urban motorcycle delivery through a "Kinetic Modernism" style—blending high-performance utility with a vibrant, optimistic aesthetic.

The visual language is characterized by:
- **Velocity Motifs:** Using horizontal "speed lines" and progressive color ramps derived from the brand's helmet logo to indicate movement and real-time progress.
- **Directional Energy:** Subtle slants and forward-leaning structural elements that suggest action and efficiency.
- **Professional Vibrancy:** While the palette is loud and energetic, the layout remains strictly organized and data-centric to instill trust in drivers and customers alike.
- **High-Response Feedback:** UI transitions that are snappy and purposeful, mirroring the "real-time" nature of last-mile delivery.

## Colors

The palette is a tri-tone progression representing the journey from start to finish. 

- **Primary (Green):** Represents the "Go" state, success, and available capacity. It is the dominant color for primary actions.
- **Secondary (Yellow/Orange):** Used for transit states, warnings, and non-critical attention. It provides a warm, high-visibility middle ground.
- **Tertiary/Urgent (Red/Orange):** Reserved for immediate action, high-priority deliveries, and critical alerts.
- **Neutrals:** A range of deep charcoals and off-whites ensure that the vibrant brand colors pop without causing visual fatigue during long shifts. 

Color is used functionally: Green for arrival/completion, Yellow for "en route," and Red for delayed or urgent tasks.

## Typography

This design system utilizes two high-performance sans-serifs to balance brand character with legibility.

**Hanken Grotesk** is used for headlines, display elements, and status labels. Its sharp, contemporary geometry feels "engineered" and precise. For display headings, a tight letter-spacing is applied to evoke a sense of condensed energy.

**Plus Jakarta Sans** is used for body copy and data entry. Its slightly softer terminals and generous x-height ensure maximum readability for drivers viewing screens in varied lighting conditions and outdoor environments.

Uppercase labels are frequently used for metadata (e.g., ORDER ID, ETA) to create a clear visual hierarchy against narrative content.

## Layout & Spacing

The layout follows an **8px grid system** to ensure mathematical consistency. 

- **Fluid Mobile-First Grid:** On mobile, a 4-column layout with 16px margins is standard. Cards and containers should span the full width to maximize touch targets for drivers on the move.
- **Modular Efficiency:** Content is organized into "action blocks." Padding within these blocks is generous (24px) to prevent accidental taps, especially important for glove-friendly interfaces.
- **Speed-Line Breaks:** Horizontal dividers are often styled as thin, multi-colored strips (Green-Yellow-Orange) or "speed lines" to reinforce the brand identity while separating content sections.

## Elevation & Depth

To maintain a "fast" and clean aesthetic, this design system avoids heavy shadows in favor of **Tonal Layering** and **High-Contrast Outlines**.

- **Surface Tiers:** Backgrounds use a light grey (`#F8F9FA`), while primary cards and interactive elements are pure white. This creates a natural "lift" without the blurriness of deep shadows.
- **Active Elevation:** Only the most critical interactive elements (like a "Slide to Complete" bar) receive a subtle, crisp ambient shadow to indicate they are above the base layer.
- **Directional Depth:** Map pins and progress markers utilize a "long shadow" effect at a 45-degree angle (as seen in the logo) to indicate focus and movement.

## Shapes

The shape language balances "friendly" and "functional." 

Standard components use a **0.5rem (8px)** corner radius. This is soft enough to feel modern and approachable but sharp enough to maintain a professional, logistical appearance. 

Buttons and specialized "Status Chips" may use a pill-shaped (rounded-full) geometry to stand out as highly interactive or ephemeral elements. Speed lines should always have flat or slightly sheared ends to suggest velocity rather than being perfectly rounded.

## Components

### Buttons
- **Primary:** Solid Green with white text. High-contrast, bold weight.
- **Urgent:** Solid Red/Orange for "Emergency" or "Cancel" actions.
- **Ghost:** Thin 2px borders with Hanken Grotesk Bold text for secondary navigation.

### Status Chips
Small, pill-shaped indicators. For example:
- `EN ROUTE` (Yellow background, Dark text)
- `DELIVERED` (Green background, White text)
- `NEW TASK` (Orange/Red background, White text)

### Progress Trackers
Linear bars that use the brand's gradient ramp (Green → Yellow → Orange). As a delivery progresses, the bar fills with the corresponding color of that stage.

### Interactive Cards
Map-integrated cards for drivers should feature a "Speed Line" accent on the left border, color-coded by the urgency of the delivery.

### Input Fields
Clean, outlined fields with a 2px bottom border that turns Primary Green upon focus. Labels should float or disappear to maximize space for data.
# Shadow Depth UI Redesign — Product Requirements Document

## Overview

Redesign LaunchPilot's public and admin interfaces using a **shadow depth UI** design language. The goal is a modern, premium feel that showcases Marko PHP's ability to power sophisticated React frontends. The design uses layered shadows for elevation, refined spacing, and consistent visual hierarchy.

## Design Philosophy

### Shadow Depth Elevation System
Shadows communicate hierarchy and interactivity. Each elevation level has a specific purpose:

| Level | Use Case | Shadow |
|---|---|---|
| 0 (Ground) | Page background, dividers | None |
| 1 (Surface) | Cards, panels, sections | `0 1px 3px rgba(0,0,0,0.06)` |
| 2 (Raised) | Hover states, dropdowns | `0 4px 12px rgba(0,0,0,0.08)` |
| 3 (Floating) | Modals, popovers, sticky nav | `0 8px 24px rgba(0,0,0,0.10)` |
| 4 (Emphasis) | CTAs, primary actions on hover | `0 12px 32px rgba(0,0,0,0.12)` |

### Color Palette

**Primary:** `#0f172a` (Slate 900) — ink, headings, primary buttons  
**Accent:** `#3b82f6` (Blue 500) — links, active states, badges  
**Surface:** `#ffffff` — cards, panels  
**Ground:** `#f8fafc` (Slate 50) — page background  
**Muted:** `#64748b` (Slate 500) — secondary text, borders  
**Success:** `#22c55e` (Green 500)  
**Warning:** `#f59e0b` (Amber 500)  
**Danger:** `#ef4444` (Red 500)

**Dark mode palette (future):** Ground `#0f172a`, Surface `#1e293b`, Text `#f1f5f9`

### Typography

| Token | Size | Weight | Line Height | Use |
|---|---|---|---|---|
| Display | 2.5rem (40px) | 800 | 1.1 | Hero headlines |
| H1 | 1.875rem (30px) | 700 | 1.2 | Page titles |
| H2 | 1.5rem (24px) | 600 | 1.3 | Section headers |
| H3 | 1.125rem (18px) | 600 | 1.4 | Card titles |
| Body | 0.875rem (14px) | 400 | 1.6 | Paragraphs |
| Caption | 0.75rem (12px) | 500 | 1.5 | Labels, metadata |

Font stack: `Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`

### Spacing Scale

Base unit: 4px. Scale: 1 (4px), 2 (8px), 3 (12px), 4 (16px), 5 (20px), 6 (24px), 8 (32px), 10 (40px), 12 (48px), 16 (64px).

### Border Radius

- Small (buttons, inputs, badges): `8px`
- Medium (cards, panels): `12px`
- Large (modals, hero sections): `16px`
- Full (avatars, pills): `9999px`

### Animation

- Default transition: `150ms cubic-bezier(0.4, 0, 0.2, 1)`
- Hover lift: `transform: translateY(-2px)` + elevation level increase
- Page transitions: `200ms ease-out`
- Skeleton loading: subtle pulse animation

---

## Public Area

### Landing Page (`/`)

**Hero Section**
- Full-width, centered content
- Display headline: "Your AI Marketing Co-Pilot"
- Subtitle in muted color
- Primary CTA button with elevation-3 shadow, accent background
- Secondary CTA (ghost button)
- Subtle gradient background on ground layer

**Features Grid**
- 3-column grid of feature cards
- Each card: surface elevation (level 1), medium radius, icon + title + description
- Hover: elevation-2, subtle translateY(-4px)

**How It Works**
- Vertical timeline with numbered steps
- Alternating left/right layout on desktop
- Step cards with left accent border

**Pricing Section**
- Two pricing cards side by side
- Free tier: surface elevation
- Pro tier: elevated card (level 2) with accent top border, "Most Popular" badge
- Feature checklist with check icons

**Footer**
- Minimal, clean footer with links
- Divided by subtle top border (no shadow)

### Login Page (`/login`)

- Centered card on ground background
- Card: surface elevation (level 1), medium radius
- Logo centered above form
- Clean input fields with subtle border, focus state adds accent ring
- Primary submit button full-width, elevation-2 on hover
- "Don't have an account?" link below

### Signup Page (`/register`)

- Same layout as login
- Additional fields: name, confirm password
- Workspace name field (auto-creates on signup)
- Terms checkbox

---

## Admin Area

### App Shell (All Admin Pages)

**Top Navigation Bar**
- Fixed top, full-width
- Surface background with elevation-2 (appears to float above content)
- Left: Logo + app name
- Center: Navigation links (Dashboard, Campaigns, Knowledge Base)
- Right: User avatar dropdown, notification bell icon
- Bottom border: 1px subtle line

**Sidebar (Optional for future)**
- Collapsible, left side
- Same surface background
- Navigation items with icon + label
- Active item: accent left border + subtle background tint

### Dashboard (`/dashboard`)

**Stats Row**
- 4 stat cards in a row
- Each: surface elevation (level 1), icon + number + label
- Hover: elevation-2

**Campaigns Preview**
- Section header with "View All →" link
- Horizontal scroll of campaign cards (3 visible)
- Each card: surface elevation, thumbnail area, title, status badge, channel tags
- "+ New Campaign" prominent card with dashed border and accent hover

**Knowledge Base Preview**
- Section with latest document cards
- Document cards: smaller, icon + filename + date

**Recent Activity**
- Timeline of recent agent runs, content creations
- Each item: small avatar/icon, description, timestamp

### Campaigns List (`/campaigns`)

**Header**
- Page title + "+ New Campaign" button (elevation-2, accent)
- Tab switcher: Active / Archived
- Surface card with subtle background, small radius

**Campaign Cards Grid**
- 3-column grid on desktop, 2 on tablet, 1 on mobile
- Each card:
  - Surface elevation (level 1), medium radius
  - Top: type icon + status badge (colored)
  - Title (H3)
  - Description snippet (2 lines max)
  - Channel tags (small pills)
  - Bottom: date + action menu (⋯)
- Hover: elevation-2, translateY(-2px)

**Empty State**
- Centered illustration/icon
- Title + description in muted
- Primary CTA button

### Campaign Create (`/campaigns/create`)

**Form Card**
- Single large surface card, medium radius
- Step indicator at top (if multi-step)
- Section groupings with subtle dividers
- Input fields: clean, focus ring accent
- Type selector: visual cards with icons, selected state has accent border + elevation-2
- Channel toggles: pill buttons, selected = accent background
- Submit button: full-width, accent, elevation-2 on hover

### Campaign Detail (`/campaigns/{id}`)

**Campaign Header Card**
- Surface elevation (level 1)
- Type icon + title + status badge
- Description, goal, channels
- Action buttons: Edit, Archive, Export
- If archived: subtle amber banner overlay

**Agent Chat Panel**
- Larger surface card, medium radius
- Agent type tabs: horizontal, icon + label
- Selected tab: accent underline
- Chat messages: alternating user/assistant bubbles
  - User: accent background, white text, right-aligned
  - Assistant: surface background, dark text, left-aligned
- Input area: bottom-fixed within card, subtle top border
- "Save to Campaign" button on assistant messages

**Content Items Panel**
- Surface card beside chat (or below on mobile)
- Content item cards:
  - Small surface cards
  - Type icon + platform + status badge
  - Content preview (3 lines)
  - Actions: Edit, Copy, status dropdown
- Status transitions shown as a small flow diagram

### Knowledge Base (`/knowledge-base`)

**Header**
- Page title + search bar + "Upload" button

**Upload Zone**
- Large dashed-border area
- Drag-and-drop active state: accent border + subtle background tint
- Surface elevation on hover

**Document Cards Grid**
- 3-column grid
- Each card: surface elevation, file type icon, filename, chunk count, date
- Hover: elevation-2

**Search Panel**
- Surface card with search input
- Results shown as small cards with similarity score

### Settings (`/settings`)

**Page Layout**
- Two-column: sidebar nav + content area
- Sidebar: surface card, nav items
- Content: surface card for each section

**Sections**
- Account: tier badge, usage stats (progress bar for daily runs)
- API Key: masked input, toggle visibility
- GSC Connection: connect/disconnect button, status badge
- Custom Prompts: textarea per agent type
- Export: download button

---

## Component Library

### Card
```
Props: elevation (1-4), padding, radius, hoverLift
Default: elevation=1, padding=4 (16px), radius=medium, hoverLift=true
```

### Button
```
Variants: primary (accent bg), secondary (border), ghost (transparent), danger
Sizes: sm, md, lg
Elevation: primary has elevation-1, hover elevation-2
```

### Input
```
Border: 1px solid muted/20%
Focus: accent ring (2px)
Radius: small
Background: surface
```

### Badge
```
Variants: default, success, warning, danger, accent
Radius: full (pill)
Size: small padding, caption text
```

### Modal/Dialog
```
Overlay: rgba(0,0,0,0.4)
Card: surface, elevation-4, large radius
Animation: fade in + scale up from 0.95
```

### Toast/Notification
```
Position: bottom-right
Elevation-3 shadow
Slide in from right, auto-dismiss
```

### Skeleton Loader
```
Shimmer animation on muted background
Used for async data loading states
```

---

## Responsive Breakpoints

| Name | Width | Layout Changes |
|---|---|---|
| Mobile | < 640px | Single column, stacked nav, bottom sheet modals |
| Tablet | 640-1024px | 2-column grids, collapsible sidebar |
| Desktop | > 1024px | Full layout, 3-column grids, persistent sidebar |

---

## Playwright E2E Tests

### Critical User Flows

1. **Landing → Signup → Onboarding → Dashboard**
   - Visit landing page
   - Click "Get Started"
   - Fill signup form
   - Complete onboarding (add website)
   - Assert dashboard loads with workspace

2. **Login → Create Campaign → Run Agent**
   - Login with credentials
   - Navigate to campaigns
   - Click "New Campaign"
   - Fill form and submit
   - Open campaign detail
   - Send message to agent
   - Assert agent responds

3. **Upload Document → Search Knowledge Base**
   - Go to knowledge base
   - Upload a text file
   - Wait for processing
   - Search for content
   - Assert relevant results appear

4. **Content Workflow**
   - Create campaign with agent
   - Save agent output to campaign
   - Edit content item
   - Change status: draft → approved → published
   - Assert status changes persist

5. **Export Campaign**
   - Create campaign with content
   - Click Export
   - Assert markdown file downloads

### Visual Regression
- Screenshot key pages after each flow
- Compare against baseline (future iteration)

---

## Tailwind Configuration Updates

### Custom Extensions
```javascript
// tailwind.config.js additions
extend: {
  boxShadow: {
    'elevation-1': '0 1px 3px rgba(0,0,0,0.06)',
    'elevation-2': '0 4px 12px rgba(0,0,0,0.08)',
    'elevation-3': '0 8px 24px rgba(0,0,0,0.10)',
    'elevation-4': '0 12px 32px rgba(0,0,0,0.12)',
    'elevation-hover': '0 6px 16px rgba(0,0,0,0.09)',
  },
  borderRadius: {
    'card': '12px',
    'button': '8px',
    'modal': '16px',
  },
  fontFamily: {
    sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
  },
  animation: {
    'fade-in': 'fadeIn 200ms ease-out',
    'slide-up': 'slideUp 200ms ease-out',
    'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
  },
  keyframes: {
    fadeIn: {
      '0%': { opacity: '0' },
      '100%': { opacity: '1' },
    },
    slideUp: {
      '0%': { opacity: '0', transform: 'translateY(8px)' },
      '100%': { opacity: '1', transform: 'translateY(0)' },
    },
  },
}
```

---

## Implementation Phases

### Phase 1: Foundation ✅
- Updated `app/dashboard/resources/css/app.css` with shadow elevation tokens, radius, animations
- `AppShell` component with sticky blurred header and mobile bottom nav
- `AgentChat` component with depth styling and animated typing indicator
- Inter font loaded via Google Fonts

### Phase 2: Public Pages ✅
- Landing page (`home/index.latte`) — hero, features grid, pricing cards with elevation
- Login page (`auth/login.latte`) — centered card with focus rings
- Register page (`auth/register.latte`) — same layout as login
- `layouts/main.latte` — shared header/footer with elevation shadows

### Phase 3: Admin Pages ✅
- Dashboard (`Dashboard/Index.jsx`) — stat cards, campaign preview, KB preview
- Campaigns list (`Campaign/Index.jsx`) — tab pills, card grid with hover lifts
- Campaign create (`Campaign/Create.jsx`) — type selector cards, channel pills
- Campaign detail (`Campaign/Show.jsx`) — header card, agent chat, content items panel
- Knowledge base (`KnowledgeBase/Index.jsx`, `KnowledgeBase/Show.jsx`) — wrapped in AppShell
- Settings (`Settings/Index.jsx`) — wrapped in AppShell with styled cards
- Onboarding (`Onboarding/Index.jsx`) — styled form card

### Phase 4: Polish & Tests ✅
- Hover animations (`hover:-translate-y-0.5`, `hover:shadow-elevation-2`) across all cards
- Loading states in AgentChat with animated dots
- Playwright E2E tests installed and configured
  - `e2e/public-pages.spec.js` — 3 tests for landing, login, register
  - `e2e/dashboard-ui.spec.js` — 5 tests for onboarding, campaigns, create, KB, settings
- All 201 Pest PHP tests pass (370 assertions)
- Vite build succeeds (`npm run build`)

---

## Testing Requirements

### Pest PHP Tests
- All existing tests must pass (no regressions)
- Any new controller changes need tests

### Playwright Tests
- Install Playwright: `npm init playwright@latest`
- Configure `playwright.config.ts`
- Test files in `tests/e2e/`
- Run with: `npx playwright test`

### Test Data Setup
- Use test database (`launchpilot_test`)
- Seed with known user/campaign/document data before each test
- Clean up after each test

---

## Out of Scope
- Dark mode toggle (infrastructure ready, but toggle UI deferred)
- Mobile app
- Real-time notifications
- Custom themes
- Animations beyond CSS transitions

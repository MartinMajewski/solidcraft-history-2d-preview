# SolidCraft 2D History Browser — Interactive Demonstration

This repository contains the standalone, high-fidelity interactive demonstration of the **Two-Dimensional History Browser** for [SolidCraft](https://github.com/solidcraft/solidcraft) — a clean-room, pure-Rust parametric 3D CAD application.

This interactive preview was built to validate UI ergonomics, dependency tracking, multi-component connections, and ripple reordering behavior before implementing the feature in SolidCraft's native `egui` engine.

---

## 🎯 Background & Motivation

In parametric CAD, 3D solids are constructed as an ordered sequence of modeling operations (*features*). Traditional history browsers display these steps in a linear, one-dimensional timeline. 

While simple, a linear timeline makes it challenging to:
- Identify multi-component boundaries and cross-component dependencies.
- Discern which earlier operations a feature depends upon.
- Anticipate downstream features affected by a modification or deletion.
- Determine safe corridors for timeline reordering without breaking parametric rebuilds.

The **2D History Browser** provides a multi-track timeline inspired by video-editing software, separating operations across component tracks while maintaining strict chronological synchronization.

---

## 🧩 Core Terminology

- **Step**: A discrete point in time along the timeline (represented by columns).
- **Action**: A modeling function executed at a step (e.g., Sketch, Extrude, Revolve, Fillet, Combine).
- **Chip**: The visual representation of an Action on the timeline at a specific component row and column. Displayed at **100% opacity**.
- **Marker**: The vertical rollback line and head pin indicating the boundary between active and inactive (rolled-back) modeling steps.

---

## 🚀 Key Features Demonstrated

### 1. Dedicated Header Scrubber Row & Timeline Rollback Marker
- A dedicated **$28\,\text{px}$ header track** positioned directly above the component tracks holds the scrubber head pin (`ui_kit::history_marker`) with generous vertical clearance, preventing it from overlapping the first component row.
- **Full-Height Vertical Line**: Spans from the header track down across all component rows to clearly delineate active and inactive steps.
- **Horizontal Scrubbing**: Drag the blue head pin across the header track to scrub forward and backward. Click any step column header to jump the marker immediately.
- **100% Opacity Display**: All chips remain fully visible at 100% opacity. Inactive features after the marker appear in pure greyscale with desaturated icons and remain fully selectable, inspectable, and reorderable.

### 2. Leading Header Transport Controls
- The leading ($160\,\text{px}$ pinned left) side of the timeline header row houses the transport controls (First `⏮`, Back `◀`, Play / Pause `▶`/`⏸`, Forward `▶`, Last `⏭`).
- Sits on the exact same layer as the component list, remaining fixed and stationary during horizontal timeline scrolling.

### 3. Full Timeline Length Population (Zero Empty Columns)
- All 24 timeline steps are fully populated with realistic parametric CAD operations across 5 component tracks (Root Design, Housing, Shaft, Bearing, Fastener).
- The timeline length matches active feature steps without artificial empty trailing placeholder columns, reflecting real CAD design expansion.

### 4. Greyscale Inactive Highlights with Matched Luminance
When an inactive feature is selected (or when an active feature references inactive ones):
- **Prerequisites (Light Grey `#d4d4d8`)**: Matches the perceived luminance of Orange.
- **Downstream (Mid Grey `#71717a`)**: Matches the perceived luminance of Blue.
- **Selected Inactive (Silver-White `#e4e4e7`)**: Clearly distinguished with high contrast.
- **Movable Corridor Brackets `[ ... ]` (Grey `#a1a1aa`)**: Never disappear; encloses the valid dependency corridor for inactive features.

### 5. Reordering Across the Entire Timeline Data Structure
- Reordering left and right is fully supported on both active and inactive features.
- Dragging an action chip horizontally highlights valid drop slots within the movable corridor.
- Moving an action pushes/ripples intermediate jumped actions in the opposite direction (splice semantics).
- Heavy solid-body calculations are evaluated strictly up to `doc.marker`; inactive features downstream remain unbuilt in the 3D viewport until the marker is scrubbed forward.

### 6. Multi-Track Component Layout & Viewport Sizing
- Each component occupies its own dedicated horizontal row ($38\,\text{px}$ pitch).
- Component headers are pinned on the left ($160\,\text{px}$) with color-coded identifiers.
- Viewport height accommodates the $28\,\text{px}$ header $+ 3.5$ component rows ($170\,\text{px}$ total height with scrollbar), leaving the 4th row half-visible as an immediate visual affordance that more tracks exist below.
- Continuous vertical scrolling with rubberband spring elasticity when overscrolling.

### 7. Synchronized 1:1 CAD Grid
- The CAD grid background is driven by individual DOM cells with explicit border lines.
- The background moves in 1:1 physical synchronization with the chips across both horizontal and vertical scrolling, preventing visual lag or detachment.

### 8. Multi-Component Step Synchronization
- Complex operations affecting multiple components (such as *Boolean Combine* or *Assembly Constraints*) display chips across all involved component rows.
- Connected by a dead-centered vertical dotted line passing cleanly behind intermediate unaffected components without visual noise or textual badges.
- Interacting with any chip in a multi-component group synchronizes selection and highlighting across all its visual instances.

### 9. Official SolidCraft Icon Masters
- Features pure vector SVG artwork matching SolidCraft's official UI design system (`assets/icons/ui/design/svg/model/`):
  - Sketch, Extrude, Revolve, Fillet, Chamfer, Shell, Combine, Thread, Hole, and Mirror.

---

## 💻 How to Run the Demonstration

The demonstration is completely self-contained with zero external dependencies (no Node.js, no bundler, no network required).

### Option A: Direct Open
Double-click `index.html` or open it directly in any modern browser:
```bash
open index.html       # macOS
xdg-open index.html   # Linux
start index.html      # Windows
```

### Option B: Local Web Server
```bash
python3 -m http.server 8080
```
Then visit `http://localhost:8080` in your browser.

---

## 🛠️ SolidCraft Architecture Mapping

When integrated into the SolidCraft Rust codebase:

| Demonstration Feature | SolidCraft Implementation Target |
|---|---|
| **2D Timeline Layout** | `crates/ui-egui/src/timeline_2d.rs` (custom egui widget) |
| **Header Scrubber Track** | Dedicated top row in `timeline_2d.rs` holding `ui_kit::history_marker` |
| **Leading Transport Controls** | `ui_kit::HistoryTransport` embedded in pinned left header (`cmd.timeline_roll`, `cmd.timeline_play`) |
| **Rollback Marker & Scrubbing** | `app.session.doc.marker: Option<usize>` and `ui_kit::history_marker` |
| **100% Opacity Display & Greyscale Inactive** | Greyscale theme tokens for inactive features, 100% alpha everywhere |
| **Component Rows** | Extracted from `doc.tree.components()` and `f.component` |
| **Dependency Graph** | Direct DAG traversal via `f.kind.walk_refs()` and `f.kind.input_sketches()` |
| **Reorder Engine Command** | Dispatches heavy command `timeline.reorder` in `crates/engine/src/cmd/edit.rs` |
| **Configuration Preference** | `Settings → Interface → Enable 2D History Browser` (`prefs.history_2d: bool = false`) |
| **Theme & Tokens** | Direct consumption of `theme::Tokens` (`t.warning`, `t.accent`, `t.form_success`, `t.border`) |

---

## 📄 License

Original work created for the [SolidCraft](https://github.com/solidcraft/solidcraft) project under the project's open-source licensing guidelines.

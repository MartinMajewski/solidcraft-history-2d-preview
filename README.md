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
- **Chip**: The visual representation of an Action on the timeline at a specific component row and column.

---

## 🚀 Key Features Demonstrated

### 1. Multi-Track Component Layout
- Each component (Root Design, Housing, Shaft, Bearing, Fastener) occupies its own dedicated horizontal row.
- Component headers are pinned on the left ($160\,\text{px}$) with color-coded identifiers.
- The timeline grid and action chips scroll horizontally *behind* the pinned component list.

### 2. Dock Height & Visual Affordance
- Viewport height auto-expands to display **3.5 component rows** ($143\,\text{px}$ total height).
- The 4th row is intentionally partially cut off, giving users an immediate visual cue that additional component tracks exist below.
- Includes continuous vertical scrolling with rubberband spring elasticity when overscrolling.

### 3. Synchronized 1:1 CAD Grid
- The CAD grid background is driven by individual DOM cells with explicit border lines.
- The background moves in 1:1 physical synchronization with the chips across both horizontal and vertical scrolling, preventing visual lag or detachment.

### 4. Parametric Dependency Visualization
Clicking or hovering over any Action Chip instantly highlights:
- 🟧 **Prerequisites (Orange)**: All upstream features that the selected action depends on.
- 🟦 **Downstream (Blue)**: All downstream features that depend on the selected action.
- 🟩 **Movable Corridor (Green Brackets `[ ... ]`)**: Visual brackets enclosing the exact chronological window $[p_{\max} + 1, d_{\min} - 1]$ where the selected action can be reordered without invalidating dependencies.

### 5. Ripple Push Reordering (Splice Semantics)
- Dragging an action chip horizontally highlights valid drop slots in green.
- Moving an action pushes/ripples intermediate jumped actions in the opposite direction (e.g., moving an action 2 steps to the right shifts intermediate actions 2 steps to the left).
- Releasing outside the valid green corridor triggers an animated spring snap-back.

### 6. Multi-Component Step Synchronization
- Complex operations affecting multiple components (such as *Boolean Combine* or *Assembly Constraints*) display chips across all involved component rows.
- Connected by a dead-centered vertical dotted line passing cleanly behind intermediate unaffected components without visual noise or textual badges.
- Interacting with any chip in a multi-component group synchronizes selection and highlighting across all its visual instances.

### 7. Official SolidCraft Icon Masters
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
| **Component Rows** | Extracted from `doc.tree.components()` and `f.component` |
| **Dependency Graph** | Direct DAG traversal via `f.kind.walk_refs()` and `f.kind.input_sketches()` |
| **Reorder Engine Command** | Dispatches heavy command `timeline.reorder` in `crates/engine/src/cmd/edit.rs` |
| **Configuration Preference** | `Settings → Interface → Enable 2D History Browser` (`prefs.history_2d: bool = false`) |
| **Theme & Tokens** | Direct consumption of `theme::Tokens` (`t.warning`, `t.accent`, `t.form_success`, `t.border`) |

---

## 📄 License

Original work created for the [SolidCraft](https://github.com/solidcraft/solidcraft) project under the project's open-source licensing guidelines.

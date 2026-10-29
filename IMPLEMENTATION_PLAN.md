# Advanced 2D History Browser: Architecture & Implementation Plan (Updated)

## 1. Executive Summary & Terminology

This document outlines the architecture, data structures, UI design, and implementation plan for the **Two-Dimensional (2D) History Browser** in SolidCraft.

### 1.1 Standardized Terminology
- **Step**: A discrete point in time on the timeline (represented by a timeline column).
- **Action**: A modeling function performed at a step (e.g. boolean operation, extrude, sketch, fillet, shell).
- **Chip**: The visual representation of an Action on the timeline (placed in a column at a specific component row).
- **Marker**: The vertical rollback line and head pin indicating the boundary between active and deactivated (rolled-back) modeling steps.

### 1.2 Core Specifications
- **Multi-Track Component Layout**: Each component occupies its own horizontal row.
- **Dedicated Header Scrubber Row ($28\text{px}$)**: Positioned directly above component tracks to hold the rollback marker's head pin (`ui_kit::history_marker`) with generous vertical clearance so it is not squished on top of the first component row. Features column step notch indicators.
- **Pinned Leading Header Transport Controls**: The leading (left $160\text{px}$) side of the timeline header row houses the transport controls (`⏮`, `◀`, `▶`/`⏸`, `▶`, `⏭`) directly on the same pinned layer as component titles, remaining stationary during horizontal scrolling.
- **Zero Empty Columns**: The timeline length matches active feature steps ($1 \dots N$); empty placeholder columns beyond the design timeline length are omitted, reflecting realistic parametric CAD timeline growth.
- **Official SolidCraft Icons**: Chips render the official SVG master vector artwork from `assets/icons/ui/design/svg/model/` (`sketch`, `extrude`, `revolve`, `fillet`, `chamfer`, `shell`, `combine`, `thread`, `hole`, `mirror`).
- **Uniform CAD Grid Pattern**: The timeline canvas is rendered with a clean, unified 2D grid matrix ($52\text{px}$ column pitch $\times 38\text{px}$ row pitch) using theme tokens.
- **Dead-Centered Dotted Connectors**: Multi-component Actions are connected by a vertical dotted line (`t.secondary_dots`) positioned at the exact mathematical center of that column ($\text{col\_x} + \text{col\_w}/2$). No text annotations.
- **Timeline Rollback Marker & Scrubbing**: A full-height vertical line and top head pin (`ui_kit::history_marker`) indicating the last active step (`doc.marker`). Dragging/scrubbing deactivates or activates steps dynamically.
- **Deactivated (Rolled-Back) Feature State**: Features positioned after the marker are rendered in a dimmed state (`opacity: 0.42`, dashed borders, desaturated icons) and omitted from downstream 3D evaluation.
- **Movable Range Brackets `[ ... ]`**: Green brackets enclose the valid movement range across the timeline where no dependent Actions exist.
- **Ripple Push Reordering**: Moving an Action $k$ steps to the right pushes all jumped Actions $k$ steps to the left; moving $k$ steps to the left pushes jumped Actions $k$ steps to the right (strict array splice reordering).
- **Header + 3.5 Row Height & Rubberband Overscroll**: Height auto-expands to $28\text{px}$ header $+ 3.5$ component rows ($170\text{px}$ total height with scrollbar) with elastic spring damping on overscroll.
- **Pinned Headers & Layered 2D Scrolling**: Left component headers and transport controls remain pinned while timeline tracks scroll horizontally behind them.
- **Anchored Bottom Dock**: Strictly docked at the bottom of the viewport; not dockable to side panels.

---

## 2. Interactive Timeline Mechanics

### 2.1 The Green Movable Brackets `[ ... ]`
When an Action $A$ is selected or dragged:
1. **Dependency Analysis**:
   - $\text{prereqs}(A)$: All ancestor Actions that $A$ depends on. Latest prerequisite column is $p_{\max}$.
   - $\text{downstream}(A)$: All descendant Actions that depend on $A$. Earliest downstream Action column is $d_{\min}$.
2. **Bracket Placement**:
   - **Opening Bracket `[`**: Placed immediately before column $p_{\max} + 1$.
   - **Closing Bracket `]`**: Placed immediately after column $d_{\min} - 1$.
   - **Invariant**: *There are strictly zero dependent Actions within the brackets `[ ... ]`.*

### 2.2 Ripple Push Reordering Mechanics
When an Action is dragged from column $C_{\text{from}}$ to target column $C_{\text{to}}$ within the brackets:
- **Array Splice Transition**:
  ```rust
  let from_idx = c_from - 1;
  let to_idx = c_to - 1;
  let moved_feature = doc.features.remove(from_idx);
  doc.features.insert(to_idx, moved_feature);
  ```
- **Automatic Ripple Push**:
  - If moved $k$ steps to the right ($C_{\text{to}} > C_{\text{from}}$): All Actions in the span $(C_{\text{from}}, C_{\text{to}}]$ automatically ripple $1$ step to the left ($C \gets C - 1$).
  - If moved $k$ steps to the left ($C_{\text{to}} < C_{\text{from}}$): All Actions in the span $[C_{\text{to}}, C_{\text{from}})$ automatically ripple $1$ step to the right ($C \gets C + 1$).
- **Dispatches via Engine**:
  Executes `app.run_heavy("timeline.reorder", json!({ "feature": id, "position": to_slot }))`.

### 2.3 Timeline Rollback Marker & Active Step Scrubbing
SolidCraft's document model stores the active evaluation point as `doc.marker: Option<usize>` (`None` = all features active; `Some(k)` = rolled back to feature index $k$).
1. **Visual Representation**:
   - A full-height vertical line spanning the header row and all component tracks at $x = \text{marker\_index} \times 52\text{px}$.
   - Scrubber head pin in the header row (`ui_kit::history_marker`: hollow circle handle with dark center and stroke in `t.accent`).
2. **Active vs. Deactivated Semantics**:
   - **Active Features** ($i < \text{marker}$): Rendered with full opacity (1.0), theme colors, and evaluated in the viewport.
   - **Deactivated Features** ($i \ge \text{marker}$): Rendered dimmed (`opacity: 0.42`), dashed border, desaturated icon, excluded from current CAD solid body evaluation.
   - **Connector Lines**: Dotted connectors crossing after the marker are dimmed to `opacity: 0.20`.
3. **Scrubbing & Transport Controls**:
   - Dragging the marker head pin horizontally scrubs the active evaluation point between step $0$ and $N$.
   - Releasing or dragging dispatches `dialogs::roll_marker(&mut app, target_slot)` / `cmd.timeline_roll`.
   - **Transport Buttons** (`HistoryTransport`): First (`⏮`), Back (`◀`), Play (`▶`/`⏸`), Forward (`▶`), Last (`⏭`) to step through features or trigger automatic sequential playback.
   - **Editing Constraints**: Deactivated features cannot be reordered or edited until the marker is scrubbed past them.

---

## 3. Visual Styling & Uniform CAD Grid

1. **Uniform CAD Grid Texture**:
   - Consistent orthogonal grid matrix ($52\text{px}$ width $\times 38\text{px}$ height).
   - Rendered using theme stroke `t.tertiary_border(t.border)` or subtle grid lines.
2. **Dead-Centered Connectors**:
   - Coordinate $x = (c - 1) \cdot 52 + 26$ places the line at the exact center of the $32\text{px}$ chip.
   - Secondary dotted style (`t.secondary_dots`), illuminating to `t.accent` when active.
3. **Official SVG Master Icons**:
   - Rendered via `crate::icons::paint_accented` or `paint_icon`.

---

## 4. Layered 2D Scrolling & Rubberband Physics

1. **Pinned Left Headers ($160\text{px}$)**:
   - Component titles, color badges, and icons remain locked on the left.
   - Header transport controls sit fixed at the top-left corner.
   - Timeline grid and chips scroll horizontally underneath.
2. **Synchronized Vertical Scrolling with Rubberband**:
   - Shared vertical scroll offset $y$ for headers and tracks.
   - Boundary overscroll damping: $\Delta y_{\text{overscroll}} = -\Delta y \times 0.35$.
   - Elastic spring release restoring boundary cleanly.
3. **Header + 3.5 Row Sizing**:
   - Total dock height clamped to $170\text{px}$ ($28\text{px}$ header $+ 3.5 \times 38\text{px} + \text{scrollbar}$).
   - 4th component row is half-visible ($19\text{px}$), providing an immediate scroll affordance.

---

## 5. Implementation Roadmap

- **Phase 1**: Dependency Graph Engine & Ripple Reorder Mapping (`crates/ui-egui/src/timeline_2d/deps.rs`).
- **Phase 2**: Preference setting in `prefs.rs` and `prefs/interface.rs` + Pending locale strings.
- **Phase 3**: Uniform 2D Grid Canvas, header scrubber track ($28\text{px}$), leading transport controls, 3.5-row auto-expansion, pinned left headers, rollback marker vertical rendering (`ui_kit::history_marker`), deactivated track state (`opacity: 0.42`), and rubberband vertical scrolling.
- **Phase 4**: Dependency visualization (Orange prereqs, Blue downstream, Green movable brackets `[ ... ]`).
- **Phase 5**: Multi-component dead-centered dotted connectors, marker drag scrubbing (`cmd.timeline_roll`), `HistoryTransport` controls, and drag-and-drop ripple push reordering.
- **Phase 6**: Unit tests, UI scenario integration, and `cargo xtask ci` verification.

---

## 6. Interactive Visual Preview

The standalone demonstration repository and interactive preview artifact feature:
1. **Dedicated Header Scrubber Row**: Clean $28\text{px}$ header row holding the marker head pin with room to slide.
2. **Leading Header Transport Controls**: Pinned left transport controls (`⏮ ◀ ▶/⏸ ▶ ⏭`) stationary during horizontal scrolling.
3. **Full Timeline Population**: All 24 steps populated with realistic CAD features (no empty columns).
4. **Dynamic Step Activation/Deactivation**: Rolled-back features dim to $0.35$ opacity with dashed borders.
5. **Ripple Push Reordering**: Splice reordering with jumped chips shifting in the opposite direction.
6. **Movable Range Brackets**: Green brackets `[ ... ]` highlighting dependency-free insertion corridors.
7. **Dead-Centered Multi-Component Connectors**: Centered dotted lines with clean pass-through.
8. **1:1 Synchronized Scrolling CAD Grid**: Physical DOM cells moving synchronously with chips and overscroll.

- **Standalone Repo**: [`../solidcraft-history-2d-preview/index.html`](file:///Users/martinmajewski/Developer/CAD/solidcraft-history-2d-preview/index.html)

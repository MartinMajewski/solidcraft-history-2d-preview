# Advanced 2D History Browser: Architecture & Implementation Plan (Updated)

## 1. Executive Summary & Terminology

This document outlines the architecture, data structures, UI design, and implementation plan for the **Two-Dimensional (2D) History Browser** in SolidCraft.

### 1.1 Standardized Terminology
- **Step**: A discrete point in time on the timeline (represented by a timeline column).
- **Action**: A modeling function performed at a step (e.g. boolean operation, extrude, sketch, fillet, shell).
- **Chip**: The visual representation of an Action on the timeline (placed in a column at a specific component row). Always displayed at **100% opacity**.
- **Marker**: The vertical rollback line and head pin indicating the boundary between active and inactive (rolled-back) modeling steps.

### 1.2 Core Specifications
- **Multi-Track Component Layout**: Each component occupies its own horizontal row ($38\text{px}$ row pitch).
- **Dedicated Header Scrubber Row ($28\text{px}$)**: Positioned directly above component tracks to hold the rollback marker's head pin (`ui_kit::history_marker`) with generous vertical clearance so it is not squished on top of the first component row. Features column step notch indicators.
- **Pinned Leading Header Transport Controls**: The leading (left $160\text{px}$) side of the timeline header row houses the transport controls (`⏮`, `◀`, `▶`/`⏸`, `▶`, `⏭`) directly on the same pinned layer as component titles, remaining stationary during horizontal scrolling.
- **Zero Empty Columns**: The timeline length matches active feature steps ($1 \dots N$); empty placeholder columns beyond the design timeline length are omitted, reflecting realistic parametric CAD timeline growth.
- **100% Opacity Display (No Dimming)**: All chips—both selected and unselected, active and inactive—remain fully visible at 100% opacity.
- **Greyscale Inactive Highlights with Luminance Matching**:
  - Inactive chips (to the right of the marker) are fully selectable and inspectable.
  - Highlights for inactive chips are rendered in pure greyscale with distinct luminance matching their colored counterparts:
    - **Prerequisites**: High-luminance Light Grey (`#d4d4d8`, matching the luminance of Orange `#f59e0b`).
    - **Downstream Dependents**: Mid-luminance Slate Grey (`#71717a`, matching the luminance of Blue `#38bdf8`).
    - **Selected Inactive**: Silver-white (`#e4e4e7` border on `#27272a`).
- **Movable Range Brackets `[ ... ]` & Corridor Greyscale to Right of Marker**:
  - Movable range brackets enclose the valid dependency-free insertion corridor $[p_{\max} + 1, d_{\min} - 1]$ for both active and inactive features.
  - Brackets and corridor background dynamically respond to the timeline marker's boundary:
    - Any bracket located to the right of the timeline marker is rendered in greyscale (`#a1a1aa`); any bracket to the left is rendered in green (`#22c55e`).
    - Any portion of the movable corridor background to the right of the timeline marker is rendered in greyscale (`rgba(161, 161, 170, 0.08)`); any portion to the left is rendered in green (`rgba(34, 197, 94, 0.08)`).
    - If a feature's corridor spans across the timeline marker, the background is split cleanly at `doc.marker` into green (active) and greyscale (inactive).
- **Full Reordering on Inactive Features**:
  - Dragging and reordering left and right is fully supported on inactive features.
  - The timeline operates on the document's underlying feature sequence.
  - Heavy solid-body CAD calculations are evaluated strictly up to `doc.marker`; inactive features downstream remain unbuilt in the 3D viewport until the marker is scrubbed past them.
- **Dead-Centered Dotted Connectors**: Multi-component Actions are connected by a vertical dotted line (`t.secondary_dots`) positioned at the exact mathematical center of that column ($\text{col\_x} + \text{col\_w}/2$). Active multi-component lines highlight in Blue; inactive multi-component lines highlight in Grey.
- **Ripple Push Reordering**: Moving an Action $k$ steps to the right pushes all jumped Actions $k$ steps to the left; moving $k$ steps to the left pushes jumped Actions $k$ steps to the right (strict array splice reordering).
- **Header + 3.5 Row Height & Rubberband Overscroll**: Height auto-expands to $44\text{px}$ Two-Tier header $+ 3.5$ component rows ($186\text{px}$ total height with scrollbar) with elastic spring damping on overscroll.
- **Pinned Left Headers & Layered 2D Scrolling**: Left component headers and transport controls remain pinned while timeline tracks scroll horizontally behind them.
- **Timeline Feature Grouping & Folding (Virtual Column Chips)**:
  - Multi-selection via **Shift+Click** (continuous range) and **Cmd/Ctrl+Click** (additive non-adjacent toggle).
  - Contiguous selection sets provide a `"Group Selection"` option in the context menu.
  - Closed groups collapse into a single-column **virtual group chip** starting on the second header row (step number hidden in the topmost row) and spanning all component tracks (height $204\text{px}$, $38\text{px}$ width) with integrated header cap (folder icon + chevron `▶`), count badge, vertical title, and component track occupancy indicator dots.
  - Dedicated group context menu: `Open Group` / `Close Group`, `Delete Group but expand content`, `Delete Group and content`, `Deactivate Group` (disabled if all deactivated), `Activate Group` (disabled if all activated).
  - Open groups expand into individual columns and tracks with an open group header pill (`📁 Group Name (N) Close ▴`) in the second header row and corridor background tint, with standard feature context menus on individual chips.
- **Two-Tier Header (Topmost Step Numbers + Lower Folders & Features Row)**:
  - **Tier 1 (Top Tier, $24\text{px}$ — Topmost Step Numbers Row)**: Sequential step numbers (`01`, `02`, ... `24`), ticks, and the Scrubber Head Pin (`ui_kit::history_marker`). Step number is hidden in folded group columns so the pin slides cleanly with zero overlap.
  - **Tier 2 (Lower Tier, $20\text{px}$ — Folders & Features Row)**: Sits directly beneath the step numbers row and directly above the component tracks. Serves as the starting row for folded virtual group chips, and houses open group header banners (`📁 Group 1 (3) Close ▴`), with empty grid cells reserved for future timeline features.
  - **Timeline Marker Scrubber Extension**: The rollback marker begins at `top: 0` in the topmost step numbers row, where the circular head pin resides, and extends down through the folder row across all component tracks to the bottom handle ($234\text{px}$ total height). Zero overlap between head pin and group cards.
- **Anchored Bottom Dock**: Strictly docked at the bottom of the viewport; not dockable to side panels.

---

## 2. Interactive Timeline Mechanics

### 2.1 The Movable Brackets `[ ... ]`
When an Action $A$ is selected or dragged:
1. **Dependency Analysis**:
   - $\text{prereqs}(A)$: All ancestor Actions that $A$ depends on. Latest prerequisite column is $p_{\max}$.
   - $\text{downstream}(A)$: All descendant Actions that depend on $A$. Earliest downstream Action column is $d_{\min}$.
2. **Bracket Placement**:
   - **Opening Bracket `[`**: Placed immediately before column $p_{\max} + 1$.
   - **Closing Bracket `]`**: Placed immediately after column $d_{\min} - 1$.
   - **Styling**: Dynamically responds to marker boundary: brackets and corridor fill to the right of the timeline marker are rendered in greyscale (`#a1a1aa` / `rgba(161, 161, 170, 0.08)`); brackets and corridor fill to the left of the timeline marker are rendered in green (`#22c55e` / `rgba(34, 197, 94, 0.08)`). Spanning corridors split cleanly at `doc.marker`.
   - **Invariant**: *There are strictly zero dependent Actions within the brackets `[ ... ]`.*

### 2.2 Ripple Push Reordering Mechanics
When an Action is dragged from column $C_{\text{from}}$ to target column $C_{\text{to}}$ within the brackets:
- **Array Splice Transition on Underlying Data Structure**:
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
  Executes `app.run_heavy("timeline.reorder", json!({ "feature": id, "position": to_slot, "active": to_slot < marker_slot }))`.

### 2.3 Timeline Rollback Marker & Active Step Scrubbing
SolidCraft's document model stores the active evaluation point as `doc.marker: Option<usize>` (`None` = all features active; `Some(k)` = rolled back to feature index $k$).
1. **Visual Representation**:
   - A full-height vertical line spanning the header row and all component tracks at $x = \text{marker\_index} \times 52\text{px}$.
   - Scrubber head pin in the header row (`ui_kit::history_marker`: hollow circle handle with dark center and stroke in `t.accent`).
2. **Active vs. Inactive Semantics**:
   - **Active Features** ($i < \text{marker}$): Rendered in 100% opacity theme colors and evaluated as solid bodies in the 3D viewport.
   - **Inactive Features** ($i \ge \text{marker}$): Rendered in 100% opacity greyscale with desaturated icons; excluded from solid-body evaluation to preserve performance, while remaining fully editable and selectable.
3. **Scrubbing & Transport Controls**:
   - Dragging the marker head pin horizontally scrubs the active evaluation point between step $0$ and $N$.
   - Releasing or dragging dispatches `dialogs::roll_marker(&mut app, target_slot)` / `cmd.timeline_roll`.
   - **Transport Buttons** (`HistoryTransport`): First (`⏮`), Back (`◀`), Play (`▶`/`⏸`), Forward (`▶`), Last (`⏭`) to step through features or trigger automatic sequential playback.

### 2.4 Context Menu & Double-Click Support (1-D Timeline Parity)
SolidCraft's 1-D timeline (`crates/ui-egui/src/timeline.rs#L790-L835`) attaches a comprehensive context menu and double-click handler to every feature chip. The 2D History Browser replicates this interaction model identically:

1. **Integration Pattern**:
   - Each chip's `egui::Response` connects to `crate::ui_kit::Menu::context(&resp, |ui| { ... })`.
   - `resp.double_clicked()` sets `edit = Some(*id)` to open the feature's edit sheet immediately.
2. **Context Menu Entries & Dispatch**:
   - `Edit Feature` (`"edit"`): Opens feature parameter sheet (`edit = Some(*id)`).
   - `Redefine Sketch Plane` (`sketch_plane_button`): Offered for `FeatureKind::Sketch` (`"ui.redefineSketch"`).
   - `Edit Profile Sketch` (`kind.profile_sketch()`): Offered for operations driven by a profile sketch (Extrude, Revolve; calls `app.edit_sketch(sk.0)`).
   - `Rename` (`"rename"`): Opens `Dialog::rename(*id, name)`.
   - `Suppress Feature` / `Unsuppress Feature` (`"suppress"`): Dispatches `app.run_heavy("timeline.suppress", json!({"feature": id}))`. Suppressed chips render with a dashed border and diagonal slash pattern at 55% opacity.
   - `Roll History Marker Here` (`"rollback"`): Dispatches `app.run_heavy("timeline.roll_to", json!({"feature": id}))`.
   - `Find in Browser` (`"list"`): Highlights and selects feature in the model tree browser (`find_in_browser(app, id)`).
   - *Separator*
   - `Copy` (`"copy"`): Copies `menu_targets(app, id)` to session clipboard via `timeline.copy`.
   - `Paste` (`"paste"`): Enabled if clipboard is not empty; inserts clones at `doc.marker` via `timeline.paste`.
   - `Copy Error Message` / `Copy Warning Message` (`message_button`): Copies diagnostic text from `app.session.model.result(id)`.
   - *Separator*
   - `Delete` (`"delete"`): Deletes targeted feature(s) via `delete_features(app, targets)`.
   - `Delete N Features After History Marker` (`delete_after_marker_button`): Active when right-clicked feature `index >= marker`, deleting all features downstream of the marker in one batch.
3. **Inactive Feature Semantics**:
   - Right-clicking an inactive chip (to the right of `doc.marker`) provides full context menu support.
   - Modifying an inactive feature (delete, rename, suppress, copy, paste, reorder) directly updates the document feature sequence `doc.features`.
   - Heavy solid B-rep evaluation (`s.model.evaluate`) stops strictly at `doc.marker`. Inactive features remain unbuilt in the 3D viewport until the marker rolls over them.
   - Choosing "Roll History Marker Here" on an inactive feature advances the marker to that position and evaluates all features up to that step.

### 2.5 Multi-Selection & Feature Grouping / Folding

To support organizing complex feature trees into manageable sub-assemblies or logical modules, the 2D History Browser introduces multi-selection and hierarchical column folding:

1. **Selection Set Interactions**:
   - **Shift+Click**: Selects all feature chips between the first (anchor) chip and the clicked chip in timeline sequence order.
   - **CMD+Click (macOS) / CTRL+Click (Windows/Linux)**: Toggles individual chips into or out of the selection set, allowing non-adjacent selections.
   - **Adjacency Check (`isSelectionAdjacent`)**: Only contiguous selection sets ($\ge 2$ features without sequence gaps) qualify for grouping. When adjacent, right-clicking any selected chip provides the context menu option `"Group Selection (<N> Actions)"`.

2. **Virtual Group Chip (Folded / Closed Mode)**:
   - When grouped, the sequence of adjacent features collapses into a single column.
   - Across that column, the group starts on the second header row and spans through all component track rows as a unified **Virtual Group Chip**:
     - **Dimensions**: Full height from the second header row through row 4 ($204\text{px}$ height, $38\text{px}$ width, $7\text{px}$ horizontal margin).
     - **Visual Assets**:
       - **Header Cap**: Integrated into the second header row ($y = 26\dots 44\text{px}$) with folder icon `${FOLDER_ICON_SVG_SMALL}` and expansion chevron `▶`.
       - **Body & Badge**: Feature count badge (`<N>`) and vertical 90°-rotated title (`Group 1`).
       - **Component Track Occupancy Dots**: 5 vertical indicator dots at the bottom of the card corresponding to the 5 component tracks (Root, Housing, Shaft, Bearing, Fastener). A lit dot (blue or silver-grey) indicates that the group contains features on that specific component track; dim dots indicate untouched tracks.
     - **Topmost Step Numbers Row**: The step number is hidden in this column, leaving the cell clean with only the tick mark. The group card never extends into the step numbers row, giving the timeline marker complete clearance.
     - **Interaction**: Single-click selects the group; double-click opens/unfolds it; right-click opens the dedicated **Group Context Menu**.

3. **Group Context Menu Options**:
   - `Open Group` / `Close Group`: Toggles folding.
   - `Delete Group but expand content`: Dissolves the group container while preserving all member features in their current timeline slots.
   - `Delete Group and content`: Deletes the group and purges all member features from the document, updating dependency references.
   - `Deactivate Group`: Deactivates/suppresses all actions within the group from CAD solid-body evaluation (disabled/not clickable if all actions are already deactivated).
   - `Activate Group`: Activates/unsuppresses all actions within the group (disabled/not clickable if all actions are already activated).

4. **Open Group Behavior & Swapped Two-Tier Header Layout**:
   - Expanding a group reveals its individual feature chips in their natural columns and component rows.
   - **Top Tier ($24\text{px}$ — Topmost Step Numbers Row)**: Step numbers (`01`, `02`, ... `24`), ticks, and the Scrubber Head Pin (`ui_kit::history_marker`). Dragging the pin in the top row scrubs the timeline.
   - **Lower Tier ($20\text{px}$ — Folders & Features Row)**: Sits directly beneath the step numbers row and directly above the component tracks. Populated with folder headers (`📁 Group Name (N) Close ▴` or compact `[📁 ▾]` pills), with empty grid cells reserved for future timeline features. Double-click on open banner folds the group; clicking `Close ▴` folds the group.
   - **Timeline Marker Scrubber Extension**: The draggable rollback marker begins at `top: 0` in the topmost step numbers row, with the circular head pin centered at $y = 4\dots 20\text{px}$, and extends down through the folder row and across all component tracks to the bottom handle.
   - An **Open Group Corridor** subtly tints the member columns with a dashed border.
   - Right-clicking any chip inside an open group displays the standard feature context menu as usual.

5. **Greyscale Inactive Group Rendering**:
   - When the timeline rollback marker is positioned to the left of a group (`markerStep < col`):
     - The **entire group** renders in 100% opacity greyscale.
     - **Closed state**: The upper header pill, expansion chevron, count badge, vertical title, row occupancy dots, and border switch dynamically to greyscale tokens (`#94a3b8` / `#4b5260` / `#20232b`). When selected, adopts high-contrast silver-grey `#e4e4e7`.
     - **Open state**: The upper header banner (`📁 Group Name (N) Close ▴`), close button, corridor tint, and all member action chips transition dynamically to pure greyscale.

---

## 3. Visual Styling & Uniform CAD Grid

1. **Uniform CAD Grid Texture**:
   - Consistent orthogonal grid matrix ($52\text{px}$ width $\times 38\text{px}$ height).
   - Rendered using theme stroke `t.tertiary_border(t.border)` or subtle grid lines.
2. **Dead-Centered Connectors**:
   - Coordinate $x = (c - 1) \cdot 52 + 26$ places the line at the exact center of the $32\text{px}$ chip.
   - Secondary dotted style (`t.secondary_dots`), illuminating to `t.accent` (or `#a1a1aa` if inactive) when active.
3. **Official SVG Master Icons**:
   - Rendered via `crate::icons::paint_accented` or `paint_icon`.

---

## 4. Layered 2D Scrolling & Rubberband Physics

1. **Pinned Left Headers ($160\text{px}$)**:
   - Component titles, color badges, and icons remain locked on the left.
   - Top row ($24\text{px}$) houses transport toolbar (`⏮ ◀ ▶/⏸ ▶ ⏭`), aligned with the step numbers and scrubber pin.
   - Lower row ($20\text{px}$) displays the `FOLDERS & MODULES` label, aligned with the folder header row.
   - Timeline grid and chips scroll horizontally underneath.
2. **Synchronized Vertical Scrolling with Rubberband**:
   - Shared vertical scroll offset $y$ for headers and tracks.
   - Boundary overscroll damping: $\Delta y_{\text{overscroll}} = -\Delta y \times 0.35$.
   - Elastic spring release restoring boundary cleanly.
3. **Header + 3.5 Row Sizing**:
   - Total dock height clamped to $186\text{px}$ ($44\text{px}$ Two-Tier header $+ 3.5 \times 38\text{px} + \text{scrollbar}$).
   - 4th component row is half-visible ($19\text{px}$), providing an immediate scroll affordance.

---

## 5. Implementation Roadmap

- **Phase 1**: Dependency Graph Engine & Ripple Reorder Mapping (`crates/ui-egui/src/timeline_2d/deps.rs`).
- **Phase 2**: Preference setting in `prefs.rs` and `prefs/interface.rs` + Pending locale strings.
- **Phase 3**: Uniform 2D Grid Canvas, Two-Tier header scrubber track ($44\text{px}$: Topmost step numbers $24\text{px}$ + Lower folders/features $20\text{px}$), leading transport controls, 3.5-row auto-expansion, pinned left headers, rollback marker extension to topmost numbers row, 100% opacity greyscale inactive rendering, and rubberband vertical scrolling.
- **Phase 4**: Dependency visualization (Active: Orange prereqs, Blue downstream, Green movable brackets; Inactive: Light Grey prereqs, Mid Grey downstream, Greyish movable brackets).
- **Phase 5**: Multi-component dead-centered dotted connectors, marker drag scrubbing (`cmd.timeline_roll`), `HistoryTransport` controls, chip double-click editing, `Menu::context` menu integration (`crates/ui-egui/src/timeline.rs#L790-L835`), and drag-and-drop ripple push reordering across active and inactive zones.
- **Phase 6**: Multi-selection (Shift+Click, Cmd/Ctrl+Click), adjacent grouping logic, virtual group chips spanning tracks, open group header pills, and group context menu (`timeline_2d/group.rs`).
- **Phase 7**: Unit tests, UI scenario integration, and `cargo xtask ci` verification.

---

## 6. Interactive Visual Preview

The standalone demonstration repository and interactive preview artifact feature:
1. **Dedicated Header Scrubber Row**: Clean $28\text{px}$ header row holding the marker head pin with room to slide.
2. **Leading Header Transport Controls**: Pinned left transport controls (`⏮ ◀ ▶/⏸ ▶ ⏭`) stationary during horizontal scrolling.
3. **Full Timeline Population**: All 24 steps populated with realistic CAD features (no empty columns).
4. **100% Opacity Display Across All States**: Unselected and inactive chips remain fully visible.
5. **Greyscale Inactive Highlights with Matched Luminance**: Inactive prereqs (Light Grey), downstream (Mid Grey), and brackets (Greyish).
6. **Reordering Everywhere**: Full ripple reordering supported on both active and inactive features.
7. **Full SolidCraft Context Menu & Double-Click Support**: 1:1 simulation of the 1-D timeline's right-click context menu (Edit, Redefine Sketch Plane, Edit Profile Sketch, Rename, Suppress/Unsuppress, Roll Marker Here, Find in Browser, Copy/Paste, Copy Diagnostic Message, Delete, Delete After Marker).
8. **Multi-Selection (Shift+Click range & Cmd/Ctrl+Click toggle)**: Seamless contiguous and non-contiguous selection sets.
9. **Contiguous Feature Grouping / Folding**: "Group Selection" context menu option on adjacent chips; folds selection into a virtual group chip spanning all component tracks.
10. **Dedicated Group Context Menu**: `Open/Close Group`, `Delete Group but expand content`, `Delete Group and content`, `Deactivate Group` (disabled if all deactivated), `Activate Group` (disabled if all activated).
11. **Open Group Header Pills & Corridors**: Clean inline header pills (`Close ▴`) with individual chip context menus preserved inside open groups.
12. **Dead-Centered Multi-Component Connectors**: Centered dotted lines with clean pass-through.
13. **1:1 Synchronized Scrolling CAD Grid**: Physical DOM cells moving synchronously with chips and overscroll.

14. **Dedicated Built-in Feature Manual & Interaction Reference**: A complete interaction guide and keyboard cheat sheet situated directly beneath the timeline viewport.
15. **Greyscale Inactive Group Rendering**: Groups to the right of the rollback marker transition all elements (header pills, virtual chips, badges, titles, and member chips) dynamically to 100% opacity greyscale.
16. **Streamlined Folder Indicators**: Minimalist upper folder icon in Tier 1; redundant folder icon in the virtual group chip removed in favor of clean chevron + count badge styling.

- **Standalone Repo**: [`../solidcraft-history-2d-preview/index.html`](file:///Users/martinmajewski/Developer/CAD/solidcraft-history-2d-preview/index.html)

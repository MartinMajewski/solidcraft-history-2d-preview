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

### 1. Dedicated Two-Tier Header Track & Timeline Rollback Marker
- A dedicated **$44\,\text{px}$ Two-Tier header track** positioned directly above the component tracks decouples group management from timeline scrubbing:
  - **Top Tier ($24\,\text{px}$ — Topmost Step Numbers Row)**: Sequential step numbers (`01`, `02`, ... `24`), tick marks, and the Scrubber Head Pin (`ui_kit::history_marker`). Dragging the pin in the top row scrubs the timeline.
  - **Lower Tier ($20\,\text{px}$ — Folders & Features Row)**: Positioned directly beneath the step numbers and directly above the component tracks. Populated with folder headers (`📁 Group Name (N) Close ▴` or compact `[📁 ▾]` pills), with empty grid cells reserved for future timeline features.
- **Rollback Marker Scrubber Extension**: The draggable blue head pin starts at `top: 0` in the topmost step numbers row (centered vertically at $y = 4\dots 20\,\text{px}$) and extends all the way down through the folder row and across all component rows to the bottom handle.
- **Horizontal Scrubbing**: Drag the blue head pin across the topmost header track to scrub forward and backward. Click any step column header to jump the marker immediately.
- **100% Opacity Display**: All chips remain fully visible at 100% opacity. Inactive features after the marker appear in pure greyscale with desaturated icons and remain fully selectable, inspectable, and reorderable.

### 2. Leading Header Transport Controls
- The leading ($160\,\text{px}$ pinned left) side of the timeline header row houses transport controls (First `⏮`, Back `◀`, Play / Pause `▶`/`⏸`, Forward `▶`, Last `⏭`, $20\,\text{px}$) in the top row matching the step numbers, and a lower sub-label (`FOLDERS & MODULES`, $18\,\text{px}$) matching the folder row.
- Sits on the exact same layer as the component list, remaining fixed and stationary during horizontal timeline scrolling.

### 3. Full Timeline Length Population (Zero Empty Columns)
- All 24 timeline steps are fully populated with realistic parametric CAD operations across 5 component tracks (Root Design, Housing, Shaft, Bearing, Fastener).
- The timeline length matches active feature steps without artificial empty trailing placeholder columns, reflecting real CAD design expansion.

### 4. Greyscale Inactive Highlights with Matched Luminance
When an inactive feature is selected (or when an active feature references inactive ones):
- **Prerequisites (Light Grey `#d4d4d8`)**: Matches the perceived luminance of Orange.
- **Downstream (Mid Grey `#71717a`)**: Matches the perceived luminance of Blue.
- **Selected Inactive (Silver-White `#e4e4e7`)**: Clearly distinguished with high contrast.
- **Movable Corridor Brackets `[ ... ]` & Background Split**: Brackets and corridor background to the right of the timeline marker appear in greyscale (`#a1a1aa` / `rgba(161, 161, 170, 0.08)`); elements to the left appear in green (`#22c55e` / `rgba(34, 197, 94, 0.08)`). Spanning corridors split cleanly across `doc.marker`.

### 5. Reordering Across the Entire Timeline Data Structure
- Reordering left and right is fully supported on both active and inactive features.
- Dragging an action chip horizontally highlights valid drop slots within the movable corridor.
- Moving an action pushes/ripples intermediate jumped actions in the opposite direction (splice semantics).
- Heavy solid-body calculations are evaluated strictly up to `doc.marker`; inactive features downstream remain unbuilt in the 3D viewport until the marker is scrubbed forward.

### 6. Multi-Track Component Layout & Viewport Sizing
- Each component occupies its own dedicated horizontal row ($38\,\text{px}$ pitch).
- Component headers are pinned on the left ($160\,\text{px}$) with color-coded identifiers.
- Viewport height accommodates the $44\,\text{px}$ Two-Tier header $+ 3.5$ component rows ($186\,\text{px}$ total height with scrollbar), leaving the 4th row half-visible as an immediate visual affordance that more tracks exist below.
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

### 10. SolidCraft Context Menu & Double-Click Editing (1-D Timeline Parity)
- Right-clicking any action chip opens the official SolidCraft context menu (`crate::ui_kit::Menu::context`), styled to match SolidCraft's dark egui theme:
  - **Edit Feature**: Opens parameter sheet (also directly accessible via **double-click** on any chip).
  - **Redefine Sketch Plane**: Context-sensitive for sketch operations.
  - **Edit Profile Sketch**: Context-sensitive for features driven by an input profile sketch (Extrude, Revolve).
  - **Rename**: Prompts for new name and updates timeline immediately.
  - **Suppress / Unsuppress**: Toggles feature suppression (`act.suppressed`) with diagonal dashed appearance on suppressed chips.
  - **Roll History Marker Here**: Moves the rollback marker directly to that step (`timeline.roll_to`).
  - **Find in Browser**: Locates feature in model tree browser.
  - **Copy & Paste**: Copies feature to session clipboard and pastes clone at the rollback marker position (`timeline.paste`).
  - **Copy Diagnostic Message**: Context-sensitive for features with errors or warnings (e.g., Step 17 warning).
  - **Delete Feature**: Deletes feature and safely cleans up dependency references.
  - **Delete Features After History Marker**: Offered on rolled-back features (`index >= marker`) to delete all features after the marker in one step.
- Full context menu functionality is active across both **active and inactive** features. Modifying inactive features updates the document structure without triggering 3D solid-body rebuilds.

### 11. Multi-Selection & Hierarchical Feature Grouping / Folding
- **Selection Sets**:
  - **Shift+Click**: Selects all feature chips between the first (anchor) chip and the clicked chip in timeline sequence order.
  - **CMD+Click (macOS) / CTRL+Click (Windows/Linux)**: Toggles individual chips into or out of the selection set, allowing non-adjacent selections.
  - **Contiguous Check**: If the selection consists of adjacent features ($\ge 2$ features without sequence gaps), right-clicking any selected chip reveals the context menu option `"Group Selection (<N> Actions)"`.
- **Virtual Group Chip (Folded State)**:
  - When grouped, member features collapse into a single column.
  - Across that column, the group starts on the second header row (with the step number hidden in the topmost row) and spans through all component track rows as a unified **Virtual Group Chip** ($204\,\text{px}$ height, rows 0 to 4) featuring an integrated header cap (folder icon + expansion chevron `▶`), vertical 90°-rotated title, feature count badge, and component track occupancy dots.
  - The step number in the topmost row is hidden, preventing any visual overlap with the timeline marker head pin.
  - Single-click selects the group; double-click opens/unfolds it.
- **Dedicated Group Context Menu**:
  - **Open Group / Close Group**: Toggles folding.
  - **Delete Group but expand content**: Dissolves the group container while preserving all member features in their current timeline positions.
  - **Delete Group and content**: Removes the group and purges all member features from the timeline.
  - **Deactivate Group**: Suppresses all member actions from CAD solid-body evaluation (disabled/not clickable if all are already deactivated).
  - **Activate Group**: Unsuppresses all member actions (disabled/not clickable if all are already activated).
- **Open Group Experience & Two-Tier Header Separation**:
  - Unfolded groups display individual feature chips across their natural columns and component tracks.
  - **Top Tier ($24\,\text{px}$)**: Step numbers (`01`, `02`, ... `24`), ticks, and the Scrubber Pin Track. All step numbers remain 100% visible and readable above open groups.
  - **Lower Tier ($20\,\text{px}$)**: Clean group banners (`📁 Group Name (N) Close ▴`) span member columns in solid `#182234` cards. Double-click on the open banner or clicking `Close ▴` folds the group.
  - **Rollback Marker Clearance**: Rollback head pin resides in the top tier ($y = 4\dots 20\,\text{px}$), never intersecting open group banners.
  - An **Open Group Corridor** subtly tints member columns with a dashed border.
  - Right-clicking any chip inside an open group displays the standard feature context menu as usual.
- **Greyscale Inactive Group Handling**:
  - Whenever the timeline marker is to the left of a group (`markerStep < col`), the **entire group** renders dynamically in 100% opacity greyscale.
  - Virtual group chips, chevrons, count badges, and component dots transition immediately between full color and neutral greyscale as the marker is scrubbed.

### 12. Built-in Feature Manual & Interaction Legend
Directly below the viewport and live interaction feedback bar, the page embeds a comprehensive **Feature Manual & Interaction Reference**:
- **Selection & Multi-Select**: Single click, Shift+Click continuous range, Cmd/Ctrl+Click toggle, and background clearing.
- **Feature Grouping & Folding**: Contiguous grouping, virtual column chip layout, double-click fold/unfold, and group context menus.
- **History Rollback & Inactive States**: Header scrubber pin, instant step jumps, active solid-body evaluation vs. 100% opacity greyscale downstream state.
- **Corridor Reordering & Context Menus**: DAG-based movable brackets `[ ... ]`, ripple push reordering, 1-D timeline parity menu, and stationary transport bar.
- **Quick Keyboard Cheat Sheet**: Interactive `<kbd>` shortcut reference table for rapid scanning.

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
| **Multi-Selection & Selection Sets** | `timeline_2d::selection::SelectionSet` (Shift+Click range & Cmd+Click toggle) |
| **Virtual Group Chips & Folding** | `timeline_2d::group::TimelineGroup` and column-spanning widget |
| **Group Context Menu** | `crate::ui_kit::Menu::context` for groups with Deactivate/Activate state guards |
| **Component Rows** | Extracted from `doc.tree.components()` and `f.component` |
| **Dependency Graph** | Direct DAG traversal via `f.kind.walk_refs()` and `f.kind.input_sketches()` |
| **Reorder Engine Command** | Dispatches heavy command `timeline.reorder` in `crates/engine/src/cmd/edit.rs` |
| **Context Menu & Double-Click** | `crate::ui_kit::Menu::context` and `resp.double_clicked()` (`crates/ui-egui/src/timeline.rs`) |
| **Configuration Preference** | `Settings → Interface → Enable 2D History Browser` (`prefs.history_2d: bool = false`) |
| **Theme & Tokens** | Direct consumption of `theme::Tokens` (`t.warning`, `t.accent`, `t.form_success`, `t.border`) |

---

## 📄 License

Original work created for the [SolidCraft](https://github.com/solidcraft/solidcraft) project under the project's open-source licensing guidelines.

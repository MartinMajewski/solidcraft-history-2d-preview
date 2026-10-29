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
  - **Tier 1 (Top Tier, $18\,\text{px}$)**: Folder management bar. Solid `#182234` group banners (`📁 Group Name (N) Close ▴`) span member columns when open, or compact pills when closed, with zero bleed-through of step numbers.
  - **Tier 2 (Bottom Tier, $26\,\text{px}$)**: Step numbers (`01`, `02`, ... `24`, `G1`), tick marks, and Scrubber Pin Track. Step numbers remain 100% visible and readable under open groups.
- **Rollback Marker Vertical Separation**: The draggable blue head pin (`ui_kit::history_marker`) starts at Tier 2 (`top: 18px`), centered vertically at $y = 23\dots 39\,\text{px}$. It never enters Tier 1, ensuring zero visual overlap with group titles or `Close ▴` buttons when scrubbing near open groups.
- **Full-Height Vertical Line**: Spans from the step tier down across all component rows to clearly delineate active and inactive steps.
- **Horizontal Scrubbing**: Drag the blue head pin across the header track to scrub forward and backward. Click any step column header to jump the marker immediately.
- **100% Opacity Display**: All chips remain fully visible at 100% opacity. Inactive features after the marker appear in pure greyscale with desaturated icons and remain fully selectable, inspectable, and reorderable.

### 2. Leading Header Transport Controls
- The leading ($160\,\text{px}$ pinned left) side of the timeline header row houses a top category label (`TIMELINE TRANSPORT`, $18\,\text{px}$) and transport controls (First `⏮`, Back `◀`, Play / Pause `▶`/`⏸`, Forward `▶`, Last `⏭`, $26\,\text{px}$).
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
  - Across that column, all component track rows are unified into a single **Virtual Group Chip** ($182\,\text{px}$ height, rows 0 to 4) featuring a vector folder icon, expansion chevron `▶`, rotated title, feature count badge, and component indicator dots.
  - Single-click selects the group; double-click opens/unfolds it.
- **Dedicated Group Context Menu**:
  - **Open Group / Close Group**: Toggles folding.
  - **Delete Group but expand content**: Dissolves the group container while preserving all member features in their current timeline positions.
  - **Delete Group and content**: Removes the group and purges all member features from the timeline.
  - **Deactivate Group**: Suppresses all member actions from CAD solid-body evaluation (disabled/not clickable if all are already deactivated).
  - **Activate Group**: Unsuppresses all member actions (disabled/not clickable if all are already activated).
- **Open Group Experience & Two-Tier Header Separation**:
  - Unfolded groups display individual feature chips across their natural columns and component tracks.
  - **Tier 1 ($18\,\text{px}$)**: Clean group banners (`📁 Group Name (N) Close ▴`) span member columns in solid `#182234` cards, isolated from step numbers. Double-click on the open banner or clicking `Close ▴` folds the group.
  - **Tier 2 ($26\,\text{px}$)**: Step numbers (`01`, `02`, ... `24`), ticks, and the Scrubber Pin Track. All step numbers remain 100% visible and readable underneath open groups.
  - **Rollback Marker Vertical Separation**: Rollback head pin is located in Tier 2 ($y = 23\dots 39\,\text{px}$), never intersecting Tier 1. Zero overlap with group titles or `Close ▴`.
  - An **Open Group Corridor** subtly tints member columns with a dashed border.
  - Right-clicking any chip inside an open group displays the standard feature context menu as usual.

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

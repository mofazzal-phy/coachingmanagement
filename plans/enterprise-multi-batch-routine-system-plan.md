# Enterprise Multi-Batch Class Routine Management System - Architecture Plan

## Overview
Transform the existing class routine module into a REAL ENTERPRISE-LEVEL MULTI-BATCH CLASS ROUTINE MANAGEMENT SYSTEM with ERP-grade scheduling dashboard, parallel batch scheduling, conflict-free smart scheduling, and professional timetable grid.

## Current State Analysis
- **Database**: `class_routines` table already supports `batch_id`, `course_id`, `class_id`, `section_id`, `group_id`, `subject_id`, `teacher_id`, `room_id`, `day_of_week`, `start_time`, `end_time`, `start_date`, `end_date`, `version`, `status`, `created_by`, soft deletes
- **Backend**: ClassRoutine model with relationships, scopes, accessors; ClassRoutineService with generate, validateConflicts, swapSlots, publish, archive, getConflicts; ClassRoutineRepository with paginate, getAll, findById, create, update, delete, findConflicts, getTeacherSchedule, getStudentRoutine
- **Frontend**: RoutineManagementPage.vue (Pinia-based), WeeklyGrid.vue (CSS table-based timetable), EditSlotModal.vue, ManualSlotWizard.vue, GenerateWizard.vue, DayCard.vue, SubjectBadge.vue, LiveIndicator.vue
- **Store**: class-routine.store.js with Pinia, computed weeklyGrid, weekDates, subjectColorMap, weeklyStats

## Gaps Identified
1. **Multi-batch stacking**: WeeklyGrid only shows one slot per cell, not multiple stacked cards for different batches
2. **Conflict detection**: Only checks teacher and room conflicts, NOT batch conflicts
3. **No lunch break**: Missing merged row for lunch break
4. **No off-day support**: Missing off-day columns/cells
5. **No drag & drop**: Missing interactive drag & drop
6. **No Excel export**: Only PDF export exists
7. **No batch-wise view toggle**: Missing teacher/batch/room-wise views
8. **No dynamic card stacking**: Cells don't auto-expand based on content

## Implementation Plan

### Phase 1: Database & Backend Enhancements
1. Create new migration `2026_05_27_000001_add_enterprise_fields_to_class_routines.php`
   - Add `slot_name` (varchar) - e.g., "Morning Slot", "Afternoon Slot"
   - Add `duration` (integer, minutes) - computed or manual
   - Add `is_lunch_break` (boolean) - for lunch break rows
   - Add `is_off_day` (boolean) - for off day columns
   - Add `off_day_date` (date, nullable) - specific off day date
   - Add `batch_conflict_check` index for batch+time overlap detection
   - Add `display_order` (integer) - for custom ordering

2. Update ClassRoutineService
   - Add `getMultiBatchGrid()` - returns routines grouped by day then batch
   - Enhance `validateConflicts()` to check batch conflicts
   - Add `getBatchConflicts()` - batch-level conflict detection
   - Add `getTeacherLoad()` - teacher workload analysis
   - Add `getRoomUtilization()` - room usage statistics

3. Update ClassRoutineRepository
   - Add `getMultiBatchWeeklyGrid()` - multi-batch grid data
   - Add `findBatchConflicts()` - batch time overlap detection
   - Add `getRoutinesByFilters()` - advanced filtering

4. Update ClassRoutineController
   - Add `multiBatchGrid()` endpoint
   - Add `batchConflicts()` endpoint
   - Add `teacherLoad()` endpoint
   - Add `roomUtilization()` endpoint
   - Add `exportExcel()` endpoint

### Phase 2: Frontend - Enterprise Routine Grid
1. Create `EnterpriseRoutineGrid.vue` - The main ERP-grade timetable grid
   - Professional timetable grid where columns = Days, Rows = Time Slots
   - Each Cell = Multiple Routine Cards (stacked vertically)
   - Multi-batch stacked layout with batch color coding
   - Sticky headers and time column
   - Responsive design (desktop table, mobile cards)
   - Lunch break merged row
   - Off-day columns/cells
   - Dynamic card stacking with auto cell height
   - Scrollable grid with fixed headers

2. Create `RoutineCard.vue` - Individual routine card component
   - Subject name with color badge
   - Teacher name
   - Room number
   - Batch name with color tag
   - Time range
   - Live/upcoming indicators
   - Drag handle for drag & drop

3. Create `BatchTimelineBar.vue` - Batch timeline visualization
   - Horizontal timeline showing batch schedules
   - Color-coded by batch
   - Conflict highlighting

4. Create `RoutineFilterBar.vue` - Advanced filter component
   - Filter by Class, Course, Batch, Teacher, Subject, Room
   - Multi-select dropdowns
   - Quick filter chips
   - View toggle (Grid/Timeline/Teacher/Room)

5. Create `ConflictPanel.vue` - Conflict display panel
   - List of conflicts with details
   - Conflict type badges (Teacher, Room, Batch, Time)
   - Resolution suggestions
   - Highlight affected slots in grid

6. Create `LunchBreakEditor.vue` - Lunch break configuration
   - Set lunch start/end time
   - Apply to selected days
   - Visual preview in grid

7. Create `OffDayManager.vue` - Off day management
   - Calendar-based off day selection
   - Batch-specific off days
   - Recurring off days (e.g., every Friday)

### Phase 3: Frontend - Main Management Page Overhaul
1. Rewrite `RoutineManagementPage.vue` - Complete overhaul
   - Stats dashboard with 8+ stat cards
   - Advanced filter bar
   - Enterprise grid as main content
   - Action toolbar (Generate, Add Slot, Publish, Archive, Conflicts, Export)
   - View mode tabs (Grid, Teacher View, Room View, Batch View)
   - Print-optimized layout
   - Mobile-responsive design

### Phase 4: Export & Print Features
1. Enhance PDF export with multi-batch support
2. Add Excel export (using xlsx library)
3. Print-optimized CSS
4. Export teacher-wise schedule
5. Export room-wise schedule

### Phase 5: Drag & Drop
1. Implement drag & drop for slot swapping
2. Visual feedback during drag
3. Conflict detection on drop
4. Undo capability

## Data Structures

### Multi-Batch Grid Data Structure
```javascript
{
  days: ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'],
  timeSlots: [
    {
      time: '08:00 - 08:45',
      startTime: '08:00',
      endTime: '08:45',
      isLunchBreak: false,
      cells: {
        'Saturday': [
          { id: 'uuid', batch: { id, name, color }, subject: { id, name }, teacher: { id, name }, room: { id, name }, startTime, endTime, status, color }
        ],
        'Sunday': [
          { ... }, // Multiple cards for different batches
          { ... }
        ]
      }
    }
  ]
}
```

### Conflict Detection Algorithm
```
For each routine slot:
  1. Check teacher conflicts:
     - Same teacher, same day, overlapping time
  2. Check room conflicts:
     - Same room, same day, overlapping time
  3. Check batch conflicts:
     - Same batch, same day, overlapping time
  4. Check student group conflicts:
     - Same group, same day, overlapping time
```

## Component Hierarchy
```
RoutineManagementPage.vue (Main Page)
├── StatsCardsRow.vue (8 stat cards)
├── RoutineFilterBar.vue (Advanced filters)
├── ActionToolbar.vue (Generate, Add, Publish, etc.)
├── ViewModeTabs.vue (Grid/Teacher/Room/Batch views)
├── EnterpriseRoutineGrid.vue (Main grid)
│   ├── GridHeader.vue (Day columns with sticky header)
│   ├── TimeColumn.vue (Time slots with sticky column)
│   ├── GridBody.vue (Scrollable grid body)
│   │   ├── GridRow.vue (Each time slot row)
│   │   │   ├── RoutineCard.vue (Individual routine)
│   │   │   └── RoutineCard.vue (Multiple per cell)
│   │   └── LunchBreakRow.vue (Merged lunch row)
│   └── OffDayColumn.vue (Off day column)
├── ConflictPanel.vue (Slide-out panel)
├── EditSlotModal.vue (Enhanced edit modal)
├── ManualSlotWizard.vue (Enhanced wizard)
├── GenerateWizard.vue (Enhanced generation)
├── LunchBreakEditor.vue (Lunch config)
├── OffDayManager.vue (Off day config)
└── ExportMenu.vue (PDF/Excel/Print)
```

## Color System
```javascript
const SUBJECT_COLORS = {
  physics: '#3B82F6',      // Blue
  chemistry: '#10B981',    // Green
  mathematics: '#F59E0B',  // Amber
  math: '#F59E0B',         // Amber
  biology: '#F97316',      // Orange
  english: '#8B5CF6',      // Purple
  ict: '#14B8A6',          // Teal
  bangla: '#EF4444',       // Red
  religion: '#EC4899',     // Pink
  social: '#06B6D4',       // Cyan
  science: '#84CC16',      // Lime
  art: '#D946EF',          // Fuchsia
  music: '#F43F5E',        // Rose
  sports: '#0EA5E9',       // Sky
}

const BATCH_COLORS = [
  '#6366F1', // Indigo
  '#EC4899', // Pink
  '#14B8A6', // Teal
  '#F97316', // Orange
  '#84CC16', // Lime
  '#8B5CF6', // Purple
  '#06B6D4', // Cyan
  '#F43F5E', // Rose
]
```

## API Endpoints
```
GET    /api/v1/class-routines/multi-batch-grid?batch_ids[]=1&batch_ids[]=2
GET    /api/v1/class-routines/batch-conflicts?batch_id=1
GET    /api/v1/class-routines/teacher-load?teacher_id=1
GET    /api/v1/class-routines/room-utilization?room_id=1
GET    /api/v1/class-routines/export/excel?level=batch&level_id=1
GET    /api/v1/class-routines/export/pdf?level=batch&level_id=1
POST   /api/v1/class-routines/bulk-store
POST   /api/v1/class-routines/generate
POST   /api/v1/class-routines/swap
POST   /api/v1/class-routines/publish
POST   /api/v1/class-routines/archive
GET    /api/v1/class-routines/conflicts
```

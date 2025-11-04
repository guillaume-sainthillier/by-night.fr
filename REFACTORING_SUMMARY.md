# Event Date/Time Slots Refactoring Summary

## ✅ Completed Work

### 1. Database Layer (100% Complete)
- ✅ Created `EventDateTime` entity with `start_date_time` and `end_date_time` fields
- ✅ Created `EventDateTimeRepository`
- ✅ Added `event_date_time` table with proper indexes
- ✅ Created migration `Version20250104000001` to create table
- ✅ Created data migration `Version20250104000002` to migrate existing events
- ✅ Added `dateTimes` OneToMany relationship to Event entity
- ✅ Added helper methods: `getEarliestStartDate()`, `getLatestEndDate()`

### 2. DTO Layer (100% Complete)
- ✅ Created `EventDateTimeDto` class
- ✅ Added `dateTimes` array property to `EventDto`
- ✅ Removed deprecated `startDate`, `endDate`, and `hours` fields from `EventDto`
- ✅ Added validation: minimum 1 date/time slot required
- ✅ Updated `EventDtoFactory` to convert `EventDateTime` entities to DTOs
- ✅ Updated `EventEntityFactory` to handle multiple date/time slots

### 3. Parser Updates (100% Complete)
All parsers now use the new `EventDateTimeDto` structure:

#### Multi-Slot Parsers (Now Properly Handling Multiple Dates)
- ✅ **OpenAgendaParser**: Preserves ALL timing slots from API (previously collapsed)
- ✅ **DataTourismeParser**: Creates ONE event with multiple slots (previously cloned events)
- ✅ **SowProgParser**: Consolidates all schedule dates into single event (previously created separate events)
- ✅ **FnacSpectaclesAwinParser**: Now uses ALL start dates (previously only used the last one) ⭐ **Major Fix**

#### Single-Slot Parsers
- ✅ **BikiniParser**: Adapted to use single `EventDateTimeDto`
- ✅ **ToulouseParser**: Adapted to use single `EventDateTimeDto`

### 4. Entity Auto-Sync (100% Complete)
- ✅ Updated `majEndDate()` lifecycle callback to auto-populate legacy `startDate`/`endDate` fields from `dateTimes`
- ✅ Legacy fields remain in database for backward compatibility during migration
- ✅ `dateTimes` collection is now the single source of truth

---

## ⏳ Remaining Work

### 1. Forms & UI (Not Started)
The event creation/edit form needs to be updated to support multiple date/time slots:

**Files to Update:**
- `src/Form/Type/EventType.php` - Add collection field for multiple date/time slots
- `src/Form/Type/EventDateTimeType.php` - Create new form type (needs to be created)
- `src/Form/Builder/DateRangeBuilder.php` - May need updates for slot management
- `templates/Event/_form.html.twig` (or equivalent) - Update UI to add/remove slots

**Features Needed:**
- Add/remove date/time slot buttons
- Validation for overlapping slots (optional)
- Display all slots in event edit form
- JavaScript for dynamic slot management

### 2. Event Display Templates (Not Started)
Templates that display events need to show multiple date/time slots:

**Files to Find and Update:**
- Event detail/show templates
- Event list/card templates
- Calendar/agenda views
- Search results templates

**Changes Needed:**
- Loop through `event.dateTimes` instead of showing single `startDate`/`endDate`
- Display each slot with proper formatting
- Handle single vs multiple slots display
- Remove references to `hours` field

### 3. Repository Queries (Partial - Needs Review)
`src/Repository/EventRepository.php` currently queries `startDate`/`endDate` fields.

**Current Status:**
- ✅ Existing queries will continue to work (legacy fields auto-synced)
- ⚠️ Queries can be optimized to use `event_date_time` table for precise filtering

**Queries to Review:**
- `updateNonIndexables()` - Archives events older than 6 months
- `findAllNextEvents()` - Finds upcoming events for a user
- `getFindAllSimilarsBuilder()` - Finds events on same date
- Any date-range filtering queries

**Potential Optimizations:**
```php
// Example: Find events with ANY slot in date range
$qb->join('e.dateTimes', 'dt')
   ->where('dt.startDateTime <= :end')
   ->andWhere('dt.endDateTime >= :start');
```

### 4. Constraint Validators (Not Started)
The `EventConstraint` validator may need updates:

**File:** `src/Validator/Constraints/EventConstraintValidator.php`

**Potential Checks:**
- Ensure at least one date/time slot exists
- Validate that `startDateTime < endDateTime` for each slot
- Check for reasonable date ranges
- Optional: Detect overlapping slots

### 5. Legacy Field Cleanup (Future Work)
Once all UI and queries are updated:

**Phase 1 (Safe to do after UI update):**
- Remove `hours` column from `event` table
- Remove `hours` property from Event entity

**Phase 2 (Requires thorough testing):**
- Eventually deprecate `start_date` and `end_date` columns
- Create migration to drop these columns
- Remove from Event entity

---

##  migrations and Testing

### Running Migrations
```bash
php bin/console doctrine:migrations:migrate
```

This will:
1. Create the `event_date_time` table
2. Migrate existing event dates to the new structure
3. Each existing event gets one `EventDateTime` record

### Testing Checklist
- [ ] Run migrations successfully
- [ ] Verify existing events have `event_date_time` records
- [ ] Test parser imports (ensure dateTimes populated)
- [ ] Create new event via form (once form updated)
- [ ] Edit existing event (once form updated)
- [ ] Display event details (once templates updated)
- [ ] Filter events by date range (verify queries work)
- [ ] Test with events having multiple slots
- [ ] Verify legacy fields auto-sync correctly

---

## Architecture Summary

### Current Data Flow

```
Parser API Data
      ↓
Creates EventDateTimeDto[]
      ↓
EventDto (dateTimes array)
      ↓
EventEntityFactory
      ↓
Event Entity (dateTimes collection)
      ↓
majEndDate() Lifecycle Callback
      ↓
Auto-populates legacy startDate/endDate (for compatibility)
      ↓
Database: event + event_date_time tables
```

### Key Design Decisions

1. **No Deprecated Fields in Application Code**
   - All parsers use only `dateTimes`
   - EventDto has no `startDate`/`endDate`/`hours`
   - New code should only reference `dateTimes`

2. **Database Fields Kept for Compatibility**
   - `start_date` and `end_date` remain in `event` table
   - Auto-synced from `dateTimes` via lifecycle callback
   - Allows existing queries to continue working
   - Will be removed in future cleanup phase

3. **Single Source of Truth**
   - `Event.dateTimes` collection is authoritative
   - Legacy fields are computed/derived values
   - Never manually set legacy fields in new code

---

## Git Commits

1. **`ec48152`** - Refactor events to support multiple date/time slots
   - Database schema, entities, DTOs, parsers

2. **`07bdd56`** - Remove deprecated date fields from EventDto and auto-sync legacy fields
   - Clean DTO layer, auto-sync mechanism

---

## Next Steps

### Immediate Priority (To Complete Refactoring):
1. **Update Event Forms** - Enable users to add/edit multiple date/time slots
2. **Update Templates** - Display all date/time slots in event views
3. **Test Migrations** - Run migrations in dev environment
4. **Review Validators** - Update EventConstraintValidator if needed

### Medium Term:
5. **Optimize Queries** - Use `event_date_time` joins for precise filtering
6. **Add Admin UI** - Bulk edit/manage date/time slots

### Long Term:
7. **Remove Legacy Fields** - Once all code migrated, drop old columns

---

## Questions & Considerations

1. **UI/UX for Multiple Slots:**
   - How should we display 10+ date/time slots for recurring events?
   - Collapsed/expandable list? Paginated? Calendar view?

2. **Performance:**
   - Should we add caching for `getEarliestStartDate()`?
   - Index strategy for event_date_time queries?

3. **Business Logic:**
   - Should overlapping slots be allowed?
   - Max number of slots per event?
   - Bulk operations for recurring events?

---

## Files Modified

### Created:
- `src/Entity/EventDateTime.php`
- `src/Repository/EventDateTimeRepository.php`
- `src/Dto/EventDateTimeDto.php`
- `migrations/Version20250104000001.php`
- `migrations/Version20250104000002.php`

### Modified:
- `src/Entity/Event.php` - Added dateTimes relationship, helper methods, auto-sync
- `src/Dto/EventDto.php` - Removed deprecated fields, added dateTimes
- `src/DtoFactory/EventDtoFactory.php` - Convert dateTimes to DTOs
- `src/EntityFactory/EventEntityFactory.php` - Handle dateTimes collection
- `src/Parser/Common/OpenAgendaParser.php` - Use all timings
- `src/Parser/Common/DataTourismeParser.php` - Consolidate to one event
- `src/Parser/Common/SowProgParser.php` - Consolidate schedules
- `src/Parser/Common/FnacSpectaclesAwinParser.php` - Use all start dates
- `src/Parser/Toulouse/BikiniParser.php` - Single slot adaptation
- `src/Parser/Toulouse/ToulouseParser.php` - Single slot adaptation

---

## Branch Info

- **Branch:** `claude/refactor-tests-011CUoZ3erV8SpmPsLn8rZqq`
- **Remote:** Pushed to origin
- **PR:** Can be created at https://github.com/guillaume-sainthillier/by-night.fr/pull/new/claude/refactor-tests-011CUoZ3erV8SpmPsLn8rZqq

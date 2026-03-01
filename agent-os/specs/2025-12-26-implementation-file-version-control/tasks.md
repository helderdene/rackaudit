# Task Breakdown: Implementation File Version Control

## Overview
Total Tasks: 34

This feature enables users to track, view, compare, and restore previous versions of uploaded implementation files, preserving all file versions indefinitely with a linear version chain model.

## Task List

### Database Layer

#### Task Group 1: Version Tracking Schema and Model Updates
**Dependencies:** None

- [x] 1.0 Complete database layer for version tracking
  - [x] 1.1 Write 4-6 focused tests for version tracking functionality
    - Test version_group_id assignment on first file upload
    - Test version_number incrementing for subsequent uploads with same original_name
    - Test `versions()` relationship returns all files in same version group
    - Test `isLatestVersion` accessor correctly identifies current version
    - Test `latestVersion()` relationship returns highest version_number file
  - [x] 1.2 Create migration for version tracking fields
    - Add `version_group_id` column (nullable unsigned bigint, self-referential)
    - Add `version_number` column (unsigned integer, default 1)
    - Add foreign key constraint: `version_group_id` references `implementation_files.id`
    - Add composite index on `(version_group_id, version_number)`
    - Remove `replaced_at` and `replaced_by` columns (superseded by version tracking)
  - [x] 1.3 Update ImplementationFile model with version relationships
    - Add `version_group_id` and `version_number` to `$fillable`
    - Add `versions()` hasMany relationship (files sharing same version_group_id)
    - Add `latestVersion()` relationship returning file with max version_number in group
    - Add `isLatestVersion` boolean accessor
    - Add `hasMultipleVersions` boolean accessor
    - Remove `replaced_at` cast and `replacer` relationship
  - [x] 1.4 Update ImplementationFile factory for version fields
    - Add `version_group_id` and `version_number` factory states
    - Create `withVersions()` state for generating version chains
  - [x] 1.5 Ensure database layer tests pass
    - Run ONLY the 4-6 tests written in 1.1
    - Verify migration runs successfully (up and down)
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 4-6 tests written in 1.1 pass
- Migration adds version_group_id and version_number columns
- Migration removes replaced_at and replaced_by columns
- Model relationships correctly link version chains
- isLatestVersion and hasMultipleVersions accessors work correctly

---

### API Layer

#### Task Group 2: Version Control API Endpoints
**Dependencies:** Task Group 1

- [x] 2.0 Complete API layer for version control
  - [x] 2.1 Write 6-8 focused tests for API endpoints
    - Test `GET /versions` returns all versions ordered by version_number desc
    - Test `GET /versions` returns 403 for users without datacenter access
    - Test `POST /restore` creates new version from old file content
    - Test `POST /restore` returns 403 for non-admin/IT Manager users
    - Test `POST /restore` copies file in storage with new UUID filename
    - Test store endpoint creates version chain for same original_name
    - Test store endpoint does NOT delete old file from storage
  - [x] 2.2 Update ImplementationFilePolicy with version permissions
    - Add `viewVersions()` method mirroring `view()` permission logic
    - Add `restore()` method mirroring `create()` permission logic
    - Reuse existing `hasDatacenterAccess()` helper method
  - [x] 2.3 Create RestoreImplementationFileRequest form request
    - Validate that target file exists and belongs to version group
    - Use authorization via policy `restore` method
  - [x] 2.4 Update ImplementationFileController store() method
    - Replace file replacement logic with version chain creation
    - Set version_group_id to own id for first upload of a file name
    - Set version_group_id to existing group for subsequent uploads
    - Increment version_number from max in group
    - Remove old file deletion logic (preserve for version history)
  - [x] 2.5 Add versions() method to ImplementationFileController
    - Route: `GET /datacenters/{datacenter}/implementation-files/{file}/versions`
    - Return all versions in group ordered by version_number desc
    - Authorize using viewVersions policy method
    - Return ImplementationFileResource collection
  - [x] 2.6 Add restore() method to ImplementationFileController
    - Route: `POST /datacenters/{datacenter}/implementation-files/{file}/restore`
    - Copy old file in storage to new UUID-based filename
    - Create new ImplementationFile record with incremented version_number
    - Set uploaded_by to current authenticated user
    - Return new file as ImplementationFileResource
  - [x] 2.7 Update ImplementationFileResource with version fields
    - Add `version_number` to resource output
    - Add `version_group_id` to resource output
    - Add `has_multiple_versions` boolean field
    - Add `is_latest_version` boolean field
  - [x] 2.8 Register new routes in routes/web.php or routes/api.php
    - Add GET route for versions endpoint
    - Add POST route for restore endpoint
    - Follow existing route naming conventions
  - [x] 2.9 Ensure API layer tests pass
    - Run ONLY the 6-8 tests written in 2.1
    - Verify endpoints return correct responses
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 6-8 tests written in 2.1 pass
- Versions endpoint returns version history correctly
- Restore endpoint creates new version from old file
- Policy correctly authorizes version operations
- ImplementationFileResource includes version fields

---

### Frontend Components

#### Task Group 3: Version History Modal
**Dependencies:** Task Group 2

- [x] 3.0 Complete version history modal component
  - [x] 3.1 Write 3-4 focused tests for version history modal
    - Test modal displays list of versions with correct data
    - Test current/latest version is visually distinguished
    - Test Download button triggers file download
    - Test Restore button shows confirmation dialog
  - [x] 3.2 Create VersionHistoryDialog.vue component
    - Use Dialog, DialogContent, DialogHeader, DialogTitle from ui/dialog
    - Accept props: fileId, fileName, datacenterId, isOpen
    - Emit events: close, version-restored
    - Follow pattern from DeleteImplementationFileDialog.vue
  - [x] 3.3 Implement version list display in modal
    - Fetch versions from API on modal open
    - Display: version number, upload date, uploader name, file size
    - Order by version_number descending (newest first)
    - Show loading skeleton while fetching
  - [x] 3.4 Add current version visual distinction
    - Add badge "Current" or highlight for latest version row
    - Use existing Badge component or custom styling
    - Match existing UI patterns in the codebase
  - [x] 3.5 Add action buttons to each version row
    - Download button: triggers file download
    - Preview button: opens PDF/image preview (visible only for PDF/images)
    - Restore button: opens restore confirmation (hidden for current version)
    - Compare button: opens comparison with adjacent version
  - [x] 3.6 Ensure version history modal tests pass
    - Run ONLY the 3-4 tests written in 3.1
    - Verify modal renders and functions correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 3.1 pass
- Modal displays version history correctly
- Latest version is visually distinguished
- All action buttons function correctly

---

#### Task Group 4: Restore Confirmation Dialog
**Dependencies:** Task Group 3

- [x] 4.0 Complete restore confirmation dialog
  - [x] 4.1 Write 2-3 focused tests for restore confirmation
    - Test confirmation dialog displays correct message
    - Test restore action calls API and creates new version
    - Test dialog closes on successful restore
  - [x] 4.2 Create RestoreVersionDialog.vue component
    - Use Dialog components following DeleteImplementationFileDialog pattern
    - Accept props: fileId, versionNumber, datacenterId
    - Display confirmation message explaining restore outcome
    - Emit events: version-restored, close
  - [x] 4.3 Implement restore API call
    - Call POST /restore endpoint on confirmation
    - Show loading state during API call
    - Handle success: emit event, close dialog
    - Handle error: display error message
  - [x] 4.4 Ensure restore dialog tests pass
    - Run ONLY the 2-3 tests written in 4.1
    - Verify dialog functions correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 2-3 tests written in 4.1 pass
- Confirmation dialog clearly explains restore action
- Restore creates new version correctly
- Success/error states handled properly

---

#### Task Group 5: Side-by-Side Comparison View
**Dependencies:** Task Group 3

- [x] 5.0 Complete version comparison dialog
  - [x] 5.1 Write 3-4 focused tests for comparison view
    - Test comparison displays two versions side-by-side on desktop
    - Test comparison stacks versions vertically on mobile
    - Test version selection dropdowns update displayed versions
    - Test PDF and image files render correctly in viewers
  - [x] 5.2 Create VersionCompareDialog.vue component
    - Use Dialog, DialogContent, DialogHeader from ui/dialog
    - Accept props: fileId, initialLeftVersion, initialRightVersion, versions
    - Full-width modal for comparison display
  - [x] 5.3 Implement version selection dropdowns
    - Two Select dropdowns for left and right version selection
    - Populate options from versions array
    - Display version number and date in options
    - Default: compare latest with previous version
  - [x] 5.4 Implement side-by-side layout for desktop
    - Equal width columns using CSS Grid or Flexbox
    - Version label above each viewer (e.g., "Version 2")
    - Breakpoint: lg: (1024px+) for side-by-side
  - [x] 5.5 Implement stacked layout for mobile/tablet
    - Vertical stacking below lg breakpoint
    - Maintain version labels above each viewer
    - Touch-friendly sizing for mobile
  - [x] 5.6 Add PDF viewer for PDF files
    - Use iframe or object tag for PDF embedding
    - Set appropriate height for viewing
    - Handle loading and error states
  - [x] 5.7 Add image viewer for image files
    - Use img tags for PNG, JPG, GIF display
    - Contain images within viewer bounds
    - Support zoom or full-size viewing
  - [x] 5.8 Ensure comparison view tests pass
    - Run ONLY the 3-4 tests written in 5.1
    - Verify responsive behavior works correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 5.1 pass
- Side-by-side layout displays correctly on desktop
- Stacked layout displays correctly on mobile
- PDF and image viewers render content properly
- Version selection updates comparison correctly

---

#### Task Group 6: File List UI Updates
**Dependencies:** Task Groups 3, 4, 5

- [x] 6.0 Complete file list UI updates
  - [x] 6.1 Write 3-4 focused tests for file list updates
    - Test version badge displays for files with multiple versions
    - Test History button opens version history modal
    - Test Compare button appears only for files with 2+ versions
    - Test Compare button opens comparison dialog
  - [x] 6.2 Update ImplementationFile TypeScript interface
    - Add `version_number: number`
    - Add `has_multiple_versions: boolean`
    - Add `is_latest_version: boolean`
    - Add `version_group_id: number`
    - Remove `replaced_at` and `replaced_by` fields
  - [x] 6.3 Add version badge to file list rows
    - Display badge (e.g., "v3") for files with multiple versions
    - Position badge near file name
    - Use existing Badge component styling
  - [x] 6.4 Add History button to actions column
    - Add for all files (even single-version files)
    - Use appropriate icon (e.g., History from lucide-vue-next)
    - Opens VersionHistoryDialog on click
  - [x] 6.5 Add Compare button to actions column
    - Only show for files where has_multiple_versions is true
    - Use appropriate icon (e.g., GitCompare from lucide-vue-next)
    - Opens VersionCompareDialog on click
  - [x] 6.6 Integrate new dialogs into ImplementationFileList.vue
    - Import VersionHistoryDialog and VersionCompareDialog
    - Add state for tracking which file's modal is open
    - Handle events from child dialogs (refresh list on restore, etc.)
  - [x] 6.7 Ensure file list UI tests pass
    - Run ONLY the 3-4 tests written in 6.1
    - Verify UI updates function correctly
    - Do NOT run the entire test suite at this stage

**Acceptance Criteria:**
- The 3-4 tests written in 6.1 pass
- Version badge displays correctly for multi-version files
- History and Compare buttons function correctly
- Dialogs integrate properly with file list

---

### Testing

#### Task Group 7: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-6

- [x] 7.0 Review existing tests and fill critical gaps only
  - [x] 7.1 Review tests from Task Groups 1-6
    - Review 4-6 tests from database layer (Task 1.1)
    - Review 6-8 tests from API layer (Task 2.1)
    - Review 3-4 tests from version history modal (Task 3.1)
    - Review 2-3 tests from restore dialog (Task 4.1)
    - Review 3-4 tests from comparison view (Task 5.1)
    - Review 3-4 tests from file list UI (Task 6.1)
    - Total existing tests: approximately 21-29 tests
  - [x] 7.2 Analyze test coverage gaps for version control feature only
    - Identify critical end-to-end workflows lacking coverage
    - Focus ONLY on gaps related to this feature's requirements
    - Prioritize user journey tests over unit test gaps
    - Do NOT assess entire application test coverage
  - [x] 7.3 Write up to 8 additional strategic tests maximum
    - End-to-end: Upload file, upload new version, view history, restore old version
    - End-to-end: Compare two versions side-by-side
    - Edge case: Restore creates correct version_number when gaps exist
    - Edge case: First file upload correctly sets version_group_id to own id
    - Permission: Non-admin user cannot restore versions
    - Permission: All users with datacenter access can view versions
    - Focus on integration points and critical workflows
    - Skip comprehensive edge case coverage
  - [x] 7.4 Run feature-specific tests only
    - Run ONLY tests related to version control feature
    - Expected total: approximately 29-37 tests maximum
    - Do NOT run the entire application test suite
    - Verify all critical workflows pass

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 29-37 tests total)
- Critical user workflows for version control are covered
- No more than 8 additional tests added when filling gaps
- Testing focused exclusively on version control feature requirements

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer** (Task Group 1)
   - Must complete first as all other layers depend on schema changes
   - Model relationships needed by controllers and frontend

2. **API Layer** (Task Group 2)
   - Depends on database layer for version tracking fields
   - Provides endpoints needed by frontend components

3. **Version History Modal** (Task Group 3)
   - Depends on versions API endpoint
   - Core UI component for version management

4. **Restore Confirmation Dialog** (Task Group 4)
   - Depends on version history modal (triggered from there)
   - Depends on restore API endpoint

5. **Side-by-Side Comparison View** (Task Group 5)
   - Depends on versions API endpoint
   - Can be developed in parallel with Task Group 4

6. **File List UI Updates** (Task Group 6)
   - Depends on all dialog components (3, 4, 5)
   - Integrates all components into main interface

7. **Test Review and Gap Analysis** (Task Group 7)
   - Must complete last to review all feature tests
   - Ensures comprehensive coverage before release

---

## Key Files to Modify

### Backend
- `/Users/helderdene/rackaudit/database/migrations/` - New migration for version fields
- `/Users/helderdene/rackaudit/app/Models/ImplementationFile.php` - Version relationships and accessors
- `/Users/helderdene/rackaudit/app/Http/Controllers/ImplementationFileController.php` - versions() and restore() methods
- `/Users/helderdene/rackaudit/app/Policies/ImplementationFilePolicy.php` - viewVersions() and restore() methods
- `/Users/helderdene/rackaudit/app/Http/Resources/ImplementationFileResource.php` - Version fields
- `/Users/helderdene/rackaudit/app/Http/Requests/RestoreImplementationFileRequest.php` - New form request
- `/Users/helderdene/rackaudit/routes/web.php` or `routes/api.php` - New routes

### Frontend
- `/Users/helderdene/rackaudit/resources/js/components/implementation-files/VersionHistoryDialog.vue` - New component
- `/Users/helderdene/rackaudit/resources/js/components/implementation-files/RestoreVersionDialog.vue` - New component
- `/Users/helderdene/rackaudit/resources/js/components/implementation-files/VersionCompareDialog.vue` - New component
- `/Users/helderdene/rackaudit/resources/js/components/implementation-files/ImplementationFileList.vue` - UI updates

### Tests
- `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/ImplementationFileVersionTest.php` - Database layer tests
- `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/ImplementationFileVersionApiTest.php` - API layer tests
- `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/VersionHistoryModalTest.php` - Version history modal tests
- `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/RestoreVersionDialogTest.php` - Restore dialog tests
- `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/VersionCompareDialogTest.php` - Comparison view tests
- `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/ImplementationFileListUITest.php` - File list UI tests
- `/Users/helderdene/rackaudit/tests/Feature/ImplementationFile/VersionControlIntegrationTest.php` - Integration tests

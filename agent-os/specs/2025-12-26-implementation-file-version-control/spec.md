# Specification: Implementation File Version Control

## Goal
Enable users to track, view, compare, and restore previous versions of uploaded implementation files, preserving all file versions indefinitely with a linear version chain model.

## User Stories
- As an IT Manager, I want to view the version history of an implementation file so that I can track changes over time and understand when updates were made.
- As an Administrator, I want to restore a previous version of a file so that I can recover from accidental changes or revert to a known-good state.
- As an Auditor, I want to compare two versions of a file side-by-side so that I can understand what changed between implementations.

## Specific Requirements

**Version Group Tracking**
- Add `version_group_id` field to `implementation_files` table to link all versions of the same logical file
- Add `version_number` field to track sequential version numbers (1, 2, 3, etc.)
- First upload creates new version group with `version_group_id` set to its own `id`
- Subsequent uploads to same original_name increment version_number and share version_group_id
- Do NOT delete old file storage when new version is uploaded (preserve for version history)
- Remove the `replaced_at` and `replaced_by` fields as they are superseded by version tracking

**Version History Modal**
- Create `VersionHistoryDialog.vue` component using existing Dialog UI components
- Trigger modal from a "History" button added to the file list actions column
- Display list of all versions with: version number, upload date, uploader name, file size
- Order versions by version_number descending (newest first)
- Each version row has: Download, Preview (PDF/images only), Restore, and Compare buttons
- Current/latest version should be visually distinguished (badge or highlight)

**Version Restore Functionality**
- Restore creates a NEW version from the selected old version's file content
- Copy the old file in storage to a new UUID-based filename
- Create new `implementation_file` record with incremented version_number
- Set uploaded_by to current user performing the restore action
- Show confirmation dialog before restore with message explaining outcome

**Side-by-Side Comparison View**
- Create `VersionCompareDialog.vue` component for comparison display
- Support PDF files using embedded PDF viewers (iframe or object tag)
- Support image files (PNG, JPG, GIF) using img tags
- Desktop: side-by-side layout with equal width columns
- Mobile: stacked layout (one above the other) using responsive breakpoints
- Display version labels above each viewer (e.g., "Version 2" vs "Version 3")
- Allow user to select which two versions to compare from dropdowns

**Comparison Access Points**
- Add "Compare" button to version history modal for quick comparison with adjacent versions
- Add "Compare Versions" button to file list actions (shown only when file has multiple versions)
- Comparison button in file list opens modal with version selection dropdowns

**File List UI Updates**
- Add version badge/indicator to files that have multiple versions (e.g., "v3")
- Add "History" button to actions column for all files
- Add "Compare" button to actions column for files with 2+ versions
- Keep existing Download, Preview, and Delete buttons

**API Endpoints**
- `GET /datacenters/{datacenter}/implementation-files/{file}/versions` - List all versions of a file group
- `POST /datacenters/{datacenter}/implementation-files/{file}/restore` - Restore a specific version as new latest
- Extend existing ImplementationFileResource to include version_number and version_group_id

**Permission Model**
- Version history viewing: same as file view permission (all users with datacenter access)
- Version restore: same as file create permission (Administrators and IT Managers only)
- Version comparison: same as file view permission (all users with datacenter access)
- Version download: same as existing download permission

## Visual Design
No visual assets were provided. The UI should follow existing patterns established in the codebase using the Dialog components and the ImplementationFileList table structure.

## Existing Code to Leverage

**ImplementationFile Model (`/Users/helderdene/rackaudit/app/Models/ImplementationFile.php`)**
- Extend with new relationships: `versions()` to get all versions in same group, `latestVersion()` to get current version
- Add `isLatestVersion` accessor to check if file is the current version
- Reuse existing `formattedFileSize` and `fileTypeLabel` accessors for version display

**ImplementationFileController (`/Users/helderdene/rackaudit/app/Http/Controllers/ImplementationFileController.php`)**
- Modify `store()` method to create version chain instead of replacing files
- Add new `versions()` method to return version history for a file group
- Add new `restore()` method to create new version from old version
- Reuse file storage logic with UUID-based filenames

**Dialog UI Components (`/Users/helderdene/rackaudit/resources/js/components/ui/dialog/`)**
- Use Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter for modals
- Follow pattern from DeleteImplementationFileDialog for structure and state management

**ImplementationFileList.vue (`/Users/helderdene/rackaudit/resources/js/components/implementation-files/ImplementationFileList.vue`)**
- Add History and Compare buttons to actions column
- Add version badge display for files with multiple versions
- Extend ImplementationFile interface with version_number and has_versions fields

**ImplementationFilePolicy (`/Users/helderdene/rackaudit/app/Policies/ImplementationFilePolicy.php`)**
- Add `restore()` method mirroring `create()` permission logic
- Add `viewVersions()` method mirroring `view()` permission logic

## Out of Scope
- Text file diff comparison (line-by-line diff view)
- Overlay or slider comparison views
- Storage limit policies or automatic version cleanup
- Version branching (non-linear version trees)
- Bulk version operations (restore multiple, delete old versions)
- Version comments or annotations
- Version tagging or naming
- Email notifications on version changes
- Version comparison for non-PDF/image file types (Excel, Word, CSV)
- Drag-and-drop version reordering

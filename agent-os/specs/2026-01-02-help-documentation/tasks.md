# Task Breakdown: Help Documentation

## Overview
Total Tasks: 8 Task Groups with approximately 45 sub-tasks

This feature provides in-app contextual help content, feature tours, and a searchable help center to guide users through complex workflows.

## Task List

### Database Layer

#### Task Group 1: Data Models and Migrations
**Dependencies:** None

- [x] 1.0 Complete database layer for help content storage
  - [x] 1.1 Write 6 focused tests for help content models
    - Test HelpArticle model creation with required fields
    - Test HelpTour and HelpTourStep relationship
    - Test UserHelpInteraction tracking
    - Test soft delete functionality
    - Test context_key scoping
    - Test article_type enum validation
  - [x] 1.2 Create migration for `help_articles` table
    - Fields: id, slug (unique), title, content (text/markdown), context_key, article_type (enum: tooltip, tour_step, article), category, sort_order, is_active (boolean), timestamps, soft_deletes
    - Add indexes for: slug, context_key, article_type, category, is_active
  - [x] 1.3 Create migration for `help_tours` table
    - Fields: id, slug (unique), name, context_key, description, is_active (boolean), timestamps, soft_deletes
    - Add indexes for: slug, context_key, is_active
  - [x] 1.4 Create migration for `help_tour_steps` table
    - Fields: id, help_tour_id (foreign key), help_article_id (foreign key), target_selector, position (enum: top, right, bottom, left), step_order, timestamps
    - Add foreign key constraints with cascade delete
    - Add composite index for (help_tour_id, step_order)
  - [x] 1.5 Create migration for `user_help_interactions` table
    - Fields: id, user_id (foreign key), help_article_id (nullable foreign key), help_tour_id (nullable foreign key), interaction_type (enum: viewed, dismissed, completed_tour), timestamps
    - Add indexes for: user_id, interaction_type
    - Add foreign key constraints to users, help_articles, help_tours
  - [x] 1.6 Create HelpArticle model with validations
    - Fillable fields: slug, title, content, context_key, article_type, category, sort_order, is_active
    - Casts: is_active as boolean, article_type as enum
    - Add SoftDeletes trait
    - Add scopes: active(), byContextKey(), byType(), byCategory()
  - [x] 1.7 Create HelpTour model with relationships
    - Fillable fields: slug, name, context_key, description, is_active
    - Casts: is_active as boolean
    - Add SoftDeletes trait
    - Relationship: hasMany HelpTourStep (ordered by step_order)
    - Add scopes: active(), byContextKey()
  - [x] 1.8 Create HelpTourStep model with relationships
    - Fillable fields: help_tour_id, help_article_id, target_selector, position, step_order
    - Casts: position as enum
    - Relationships: belongsTo HelpTour, belongsTo HelpArticle
  - [x] 1.9 Create UserHelpInteraction model
    - Fillable fields: user_id, help_article_id, help_tour_id, interaction_type
    - Casts: interaction_type as enum
    - Relationships: belongsTo User, belongsTo HelpArticle, belongsTo HelpTour
  - [x] 1.10 Add help interaction relationships to User model
    - Add hasMany relationship to UserHelpInteraction
    - Add helper methods: dismissedHelpArticles(), completedTours(), hasViewedArticle(), hasCompletedTour()
  - [x] 1.11 Create model factories for all help models
    - HelpArticleFactory with states for different article_types
    - HelpTourFactory with states for active/inactive
    - HelpTourStepFactory
    - UserHelpInteractionFactory with states for different interaction_types
  - [x] 1.12 Ensure database layer tests pass
    - Run ONLY the 6 tests written in 1.1
    - Verify migrations run successfully
    - Verify all model relationships work correctly

**Acceptance Criteria:**
- The 6 tests written in 1.1 pass
- All migrations run without errors
- Models have proper relationships, casts, and scopes
- Factories generate valid test data
- Soft deletes work for articles and tours

---

### Backend API Layer

#### Task Group 2: Public Help Content API
**Dependencies:** Task Group 1

- [x] 2.0 Complete public-facing help content API
  - [x] 2.1 Write 6 focused tests for public help API
    - Test fetching articles by context_key
    - Test fetching single article by slug
    - Test fetching tour with steps by context_key
    - Test search endpoint returns matching articles
    - Test dismissed articles endpoint for authenticated user
    - Test recording user interactions (view, dismiss)
  - [x] 2.2 Create HelpArticleController with public endpoints
    - `index()`: List articles by context_key with pagination
    - `show()`: Get single article by slug
    - `search()`: Full-text search across title and content
  - [x] 2.3 Create HelpTourController with public endpoints
    - `show()`: Get tour by slug with eager-loaded steps and articles
    - `forContext()`: Get active tour for a context_key
  - [x] 2.4 Create UserHelpInteractionController
    - `dismissed()`: Get current user's dismissed article IDs
    - `completedTours()`: Get current user's completed tour slugs
    - `store()`: Record interaction (view, dismiss, complete_tour)
  - [x] 2.5 Create Form Request classes
    - SearchHelpArticlesRequest: validate search query
    - StoreUserHelpInteractionRequest: validate interaction_type, article_id/tour_id
  - [x] 2.6 Create API Resources for consistent JSON responses
    - HelpArticleResource: format article with rendered markdown excerpt
    - HelpTourResource: include steps with nested articles
    - HelpSearchResultResource: include highlighted snippets
  - [x] 2.7 Implement search functionality
    - Use LIKE queries on title and content fields
    - Return highlighted text snippets with match context
    - Order by relevance (title matches before content matches)
  - [x] 2.8 Register API routes in routes/api.php
    - Public routes for fetching help content (rate limited)
    - Authenticated routes for user interactions
  - [x] 2.9 Ensure public API tests pass
    - Run ONLY the 6 tests written in 2.1
    - Verify all endpoints return expected responses

**Acceptance Criteria:**
- The 6 tests written in 2.1 pass
- Articles can be fetched by context_key and slug
- Search returns relevant results with snippets
- User interactions are properly recorded
- API responses follow consistent format

---

#### Task Group 3: Admin Help Content API
**Dependencies:** Task Group 1 (completed)

- [x] 3.0 Complete admin help content management API
  - [x] 3.1 Write 6 focused tests for admin help API
    - Test admin can create new help article
    - Test admin can update existing article
    - Test admin can delete (soft delete) article
    - Test admin can manage tour steps
    - Test non-admin cannot access admin endpoints
    - Test article validation rules
  - [x] 3.2 Create Admin\HelpArticleController with CRUD
    - `index()`: List all articles with filters (type, category, active status)
    - `store()`: Create new article with validation
    - `show()`: Get single article for editing
    - `update()`: Update article fields
    - `destroy()`: Soft delete article
  - [x] 3.3 Create Admin\HelpTourController with CRUD
    - `index()`: List all tours
    - `store()`: Create new tour
    - `show()`: Get tour with steps for editing
    - `update()`: Update tour and steps
    - `destroy()`: Soft delete tour
  - [x] 3.4 Create Form Request classes for admin
    - StoreHelpArticleRequest: validate all article fields, authorize admin
    - UpdateHelpArticleRequest: validate update fields, authorize admin
    - StoreHelpTourRequest: validate tour and nested steps
    - UpdateHelpTourRequest: validate tour updates
  - [x] 3.5 Create middleware or gate for admin authorization
    - Use existing role-based auth pattern
    - Restrict all admin help endpoints to Administrator role
  - [x] 3.6 Register admin routes with middleware
    - Group under `/admin/help` prefix
    - Apply auth and admin middleware
  - [x] 3.7 Ensure admin API tests pass
    - Run ONLY the 6 tests written in 3.1
    - Verify authorization is enforced

**Acceptance Criteria:**
- The 6 tests written in 3.1 pass
- Full CRUD operations for articles and tours
- Only Administrators can access admin endpoints
- Validation prevents invalid data

---

### Frontend Components

#### Task Group 4: Help UI Components
**Dependencies:** Task Group 2

- [x] 4.0 Complete core help UI components
  - [x] 4.1 Write 6 focused tests for help components
    - Test HelpTooltip renders question mark trigger
    - Test HelpTooltip displays content on interaction
    - Test HelpSidebar opens and displays articles
    - Test FeatureTour highlights target elements
    - Test FeatureTour navigation (next/prev/skip)
    - Test markdown content renders correctly
  - [x] 4.2 Create MarkdownRenderer component
    - Use a Vue markdown rendering library (e.g., marked or markdown-it)
    - Apply consistent styling matching application design system
    - Support basic markdown: headings, lists, code, links, bold/italic
  - [x] 4.3 Create HelpTooltip component
    - Props: contextKey, elementId (to build full context_key)
    - Render question mark icon (CircleHelp from lucide-vue-next)
    - Fetch content from API by context_key on first interaction
    - Use existing Popover/Tooltip component for display
    - Include "Don't show again" checkbox
    - Emit dismiss event and call API to record interaction
  - [x] 4.4 Create HelpSidebar component
    - Use existing Sheet component with side="right"
    - Display category navigation as tabs or accordion
    - List articles for current context_key
    - Article detail view with MarkdownRenderer
    - Include search input for filtering articles
    - Provide "Replay Tour" button if tour exists for context
  - [x] 4.5 Create HelpButton component for triggering sidebar
    - Fixed position button or integrate into existing header
    - Show notification badge if unviewed help available
    - Keyboard shortcut support (e.g., Cmd+? or F1)
  - [x] 4.6 Create help composables for state management
    - useHelp(): fetch articles by context, manage sidebar state
    - useHelpInteractions(): track dismissed, viewed, completed
    - Cache fetched content to reduce API calls
  - [x] 4.7 Ensure help component tests pass
    - Run ONLY the 6 tests written in 4.1
    - Verify components render and interact correctly

**Acceptance Criteria:**
- The 6 tests written in 4.1 pass
- HelpTooltip displays content and supports dismissal
- HelpSidebar shows context-relevant articles
- Markdown renders with proper styling
- Components integrate with existing design system

---

#### Task Group 5: Feature Tour System
**Dependencies:** Task Group 4

- [x] 5.0 Complete feature tour system
  - [x] 5.1 Write 5 focused tests for feature tour system
    - Test tour spotlight overlay appears on target element
    - Test tour step popover positions correctly
    - Test tour auto-triggers on first visit
    - Test tour completion is recorded
    - Test replay tour functionality
  - [x] 5.2 Create/enhance FeatureTour component (may already exist from Task 4)
    - Props: tourSlug, autoStart (default true for first visit)
    - Fetch tour data with steps from API
    - Manage current step state
    - Emit events: started, completed, skipped, step-changed
  - [x] 5.3 Create TourSpotlight component
    - Create overlay with transparent "hole" around target element
    - Calculate target position dynamically
    - Handle window resize and scroll events
    - Animate spotlight movement between steps
  - [x] 5.4 Create TourStepPopover component
    - Position relative to target based on step.position
    - Display step counter (e.g., "Step 2 of 5")
    - Render article content with MarkdownRenderer
    - Include Previous, Next, and Skip buttons
    - Finish button on last step
  - [x] 5.5 Create useTour composable
    - Check if user has completed tour (via API)
    - Manage tour state and navigation
    - Record completion on finish
    - Support replay functionality
  - [x] 5.6 Integrate tour triggers into target pages
    - Audit execution pages (connection/inventory audits)
    - Implementation file management page
    - Rack elevation view page
    - Check completion status and auto-trigger if first visit
  - [x] 5.7 Ensure feature tour tests pass
    - Run ONLY the 5 tests written in 5.1
    - Verify tours display and navigate correctly

**Acceptance Criteria:**
- The 5 tests written in 5.1 pass
- Spotlight correctly highlights target elements
- Tour steps navigate smoothly
- Completion is tracked per user
- Tours can be replayed from help menu

---

#### Task Group 6: Help Center Page
**Dependencies:** Task Group 4

- [x] 6.0 Complete Help Center page
  - [x] 6.1 Write 4 focused tests for Help Center
    - Test Help Center page renders article list
    - Test category filtering works
    - Test search filters articles by title/content
    - Test clicking article opens detail view
  - [x] 6.2 Create Help Center Inertia page
    - Route: `/help` accessible to all authenticated users
    - Controller: HelpCenterController with index() and show() methods
    - Pass deferred props for articles grouped by category
  - [x] 6.3 Create HelpCenterIndex component
    - Display search input at top (debounced, calls API)
    - Show category tabs/pills for filtering
    - Grid or list of article cards with title, category badge, excerpt
    - "Most viewed" section based on interaction counts
  - [x] 6.4 Create HelpArticleCard component
    - Display title, category badge, excerpt (first 150 chars)
    - Click to open in sidebar or navigate to detail page
    - Show view count or "New" badge for recent articles
  - [x] 6.5 Create Help Center article detail view
    - Can be inline expansion or separate route (`/help/:slug`)
    - Full article content rendered with MarkdownRenderer
    - "Related articles" section (same category)
    - "Was this helpful?" feedback (optional, simple tracking)
  - [x] 6.6 Ensure Help Center tests pass
    - Run ONLY the 4 tests written in 6.1
    - Verify page renders and interactions work

**Acceptance Criteria:**
- The 4 tests written in 6.1 pass
- Help Center displays all articles grouped by category
- Search filters articles in real-time
- Article detail view renders markdown correctly

---

### Admin Interface

#### Task Group 7: Admin Help Management Pages
**Dependencies:** Task Group 3

- [x] 7.0 Complete admin interface for help content management
  - [x] 7.1 Write 5 focused tests for admin help pages
    - Test admin can view help articles list
    - Test admin can create new article with markdown editor
    - Test admin can edit existing article
    - Test admin can manage tour steps (add, reorder, remove)
    - Test preview mode displays tooltip/tour correctly
  - [x] 7.2 Create Admin Help index page
    - Route: `/admin/help` with admin middleware
    - Controller: Admin\HelpManagementController
    - Display tabbed view: Articles, Tours
    - Include filters, search, and bulk actions
  - [x] 7.3 Create Admin HelpArticle form page
    - Route: `/admin/help/articles/create` and `/admin/help/articles/:id/edit`
    - Form fields: title, slug (auto-generated), content (markdown editor), context_key, article_type (select), category (select), sort_order, is_active (toggle)
    - Markdown editor with live preview panel
    - Save and publish/unpublish actions
  - [x] 7.4 Create Admin HelpTour form page
    - Route: `/admin/help/tours/create` and `/admin/help/tours/:id/edit`
    - Tour fields: name, slug, context_key, description, is_active
    - Step management: add/remove steps, drag to reorder
    - Step fields: select existing article, target_selector, position
    - Visual preview of step order
  - [x] 7.5 Create context_key picker/suggester
    - Provide list of known context_keys from existing pages
    - Allow custom context_key entry
    - Show preview of which pages will display the content
  - [x] 7.6 Create preview mode functionality
    - Allow admin to preview tooltip at specified selector
    - Allow admin to walk through tour preview
    - Show how content will appear to users
  - [x] 7.7 Ensure admin help page tests pass
    - Run ONLY the 5 tests written in 7.1
    - Verify admin CRUD operations work

**Acceptance Criteria:**
- The 5 tests written in 7.1 pass
- Admins can create, edit, delete articles and tours
- Markdown editor provides live preview
- Tour step management is intuitive
- Preview mode accurately shows user experience

---

### Integration and Testing

#### Task Group 8: Test Review and Gap Analysis
**Dependencies:** Task Groups 1-7

- [x] 8.0 Review existing tests and fill critical gaps
  - [x] 8.1 Review tests from Task Groups 1-7
    - Review 6 tests from database layer (Task 1.1)
    - Review 6 tests from public API (Task 2.1)
    - Review 6 tests from admin API (Task 3.1)
    - Review 6 tests from help components (Task 4.1)
    - Review 5 tests from feature tour (Task 5.1)
    - Review 4 tests from Help Center (Task 6.1)
    - Review 5 tests from admin pages (Task 7.1)
    - Total existing tests: approximately 38 tests
  - [x] 8.2 Analyze test coverage gaps for Help Documentation feature
    - Identify critical end-to-end workflows lacking coverage
    - Focus on user journeys: tooltip interaction, tour completion, search
    - Check integration between components and API
    - Verify edge cases: empty states, error handling, permissions
  - [x] 8.3 Write up to 10 additional strategic tests if needed
    - End-to-end: User views tooltip, dismisses, sees dismissal persisted
    - End-to-end: User completes feature tour on first visit
    - End-to-end: User searches help center and finds article
    - Integration: Tour steps load with correct article content
    - Integration: Help sidebar respects user dismissals
    - Error handling: API returns 404 for non-existent article
    - Authorization: Non-admin cannot access admin help pages
    - Performance: Help content caching works correctly
    - Additional critical gaps as identified
  - [x] 8.4 Run feature-specific tests only
    - Run all tests related to Help Documentation feature
    - Expected total: approximately 38-48 tests
    - Verify all critical workflows pass
    - Do NOT run entire application test suite

**Acceptance Criteria:**
- All feature-specific tests pass (approximately 38-48 tests)
- Critical user workflows are covered
- No more than 10 additional tests added
- Testing focused exclusively on Help Documentation feature

---

## Execution Order

Recommended implementation sequence:

1. **Database Layer (Task Group 1)** - Foundation for all other work
2. **Public Help Content API (Task Group 2)** - Enable frontend development
3. **Admin Help Content API (Task Group 3)** - Can run parallel with Group 2
4. **Help UI Components (Task Group 4)** - Depends on public API
5. **Feature Tour System (Task Group 5)** - Extends core UI components
6. **Help Center Page (Task Group 6)** - Uses core components
7. **Admin Help Management Pages (Task Group 7)** - Uses admin API
8. **Test Review and Gap Analysis (Task Group 8)** - Final validation

### Parallel Execution Opportunities

- Task Groups 2 and 3 can be developed in parallel after Group 1
- Task Groups 5 and 6 can be developed in parallel after Group 4
- Task Group 7 can be developed in parallel with Groups 5 and 6

## Technical Notes

### Context Key Convention
Use dot-notation for context_keys matching route patterns:
- `audits.execute.connection` - Connection audit execution page
- `audits.execute.inventory` - Inventory audit execution page
- `implementations.files` - Implementation file management
- `racks.elevation` - Rack elevation view
- `connections.create` - Connection creation form

### Existing Components to Leverage
- `/resources/js/components/ui/tooltip/` - Base tooltip components
- `/resources/js/components/ui/sheet/` - Sidebar drawer component
- `/resources/js/components/GlobalSearch.vue` - Search pattern reference
- Existing Form Request patterns in `app/Http/Requests/`

### Priority Help Content Areas
Initial content should focus on:
1. Audit execution workflows (connection audits, inventory audits)
2. Implementation file management
3. Rack elevation views

Lower priority for initial release:
- Basic CRUD operations
- Navigation and onboarding
- User management

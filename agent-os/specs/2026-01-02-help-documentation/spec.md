# Specification: Help Documentation

## Goal
Provide in-app contextual help content, feature tours, and a searchable help center to guide users through complex workflows, reducing confusion and improving feature adoption.

## User Stories
- As an Auditor, I want contextual tooltips and feature tours so that I can understand complex audit execution workflows without external training
- As an Administrator, I want to manage help content through an admin interface so that I can update documentation without requiring code deployments

## Specific Requirements

**Help Content Storage and Management**
- Store all help content in the database using a `help_articles` table with fields: id, slug, title, content (markdown), context_key, article_type (tooltip, tour_step, article), category, sort_order, is_active, timestamps
- Create a `help_tour_steps` table linking tour steps with: id, help_tour_id, help_article_id, target_selector, position (top, right, bottom, left), step_order, timestamps
- Create a `help_tours` table with: id, slug, name, context_key, description, is_active, timestamps
- Support Markdown formatting in content using a Vue markdown renderer component
- Implement soft deletes to preserve content history

**User Help Tracking**
- Create a `user_help_interactions` table with: id, user_id, help_article_id, interaction_type (viewed, dismissed, completed_tour), timestamps
- Track when users view help articles, dismiss tooltips, and complete feature tours
- Use this data to show/hide "Don't show again" dismissed content and auto-trigger first-visit tours
- Provide an endpoint to retrieve user's dismissed help items for client-side filtering

**Contextual Tooltips**
- Create a `HelpTooltip` component that wraps form fields and action buttons with a question mark icon trigger
- Fetch tooltip content by `context_key` matching the current page route and element identifier
- Display tooltip content in a popover using the existing `Popover` component pattern
- Include a "Don't show again" checkbox that persists the dismissal via API

**Feature Tours for Complex Workflows**
- Prioritize tours for: audit execution (connection/inventory audits), implementation file management, rack elevation views
- Create a `FeatureTour` component that highlights target elements with a spotlight overlay
- Display tour steps in a positioned popover with step counter, content, and next/previous/skip buttons
- Auto-trigger tours on first visit to complex features (check `user_help_interactions` for completion)
- Provide a "Replay Tour" option in the help menu to restart completed tours

**Help Sidebar Panel**
- Create a `HelpSidebar` component using the existing `Sheet` component (right-side drawer)
- Display context-relevant help articles based on the current route/page
- Include category navigation, article list, and article detail view within the sidebar
- Fetch articles by `context_key` matching patterns like `audits.execute`, `racks.elevation`
- Render markdown content with proper styling matching the application design system

**Help Center/Documentation Hub**
- Create a dedicated `/help` page listing all available help articles grouped by category
- Implement a search input that filters articles by title and content
- Display article previews with title, category badge, and excerpt
- Link to full article view (can open in sidebar or navigate to dedicated page)
- Show related articles and "most viewed" articles based on analytics

**Search Within Help Content**
- Add a search endpoint that performs full-text search across help articles title and content fields
- Use database LIKE queries or consider a simple search index for better performance
- Return results with highlighted matching text snippets
- Integrate search into both the Help Center page and Help Sidebar component

**Admin Interface for Content Management**
- Create admin pages at `/admin/help` for CRUD operations on help articles and tours
- Restrict access to Administrator role only using route middleware
- Provide a rich text editor with Markdown preview for content editing
- Allow setting context_keys, categories, and tour step configurations
- Include a preview mode to test tooltips and tours before publishing

## Existing Code to Leverage

**Tooltip Component (`resources/js/components/ui/tooltip/`)**
- Existing Reka UI-based tooltip components with TooltipProvider, TooltipTrigger, TooltipContent
- Reuse the styling and animation patterns for help tooltips
- Extend TooltipContent styling to support markdown-rendered help content

**Sheet/Drawer Component (`resources/js/components/ui/sheet/`)**
- Use SheetContent with `side="right"` for the help sidebar panel
- Follow the existing pattern with SheetHeader, SheetTitle, SheetDescription
- Leverage the slide-in animation and overlay patterns

**GlobalSearch Component (`resources/js/components/GlobalSearch.vue`)**
- Reference the search input pattern with keyboard shortcuts (Cmd+?) for help access
- Reuse the debounced search, dropdown results display, and keyboard navigation patterns
- Apply similar result highlighting with `<mark>` tags for search matches

**User Model and Tracking Patterns**
- Follow the existing `notification_preferences` JSON column pattern for storing user help preferences
- Reference the relationship patterns for user-to-content interactions
- Use similar middleware patterns for role-based access control

**Form Request Validation Pattern (`app/Http/Requests/`)**
- Follow StoreImplementationFileRequest as a template for help content validation
- Include custom error messages for all validation rules
- Use role-based authorization in the `authorize()` method

## Out of Scope
- Video tutorials and embedded media playback
- Chatbot or AI-powered assistance features
- External documentation site or public-facing docs
- Multilingual/localization support for help content
- User-submitted feedback or comments on help articles
- Version history or content revision tracking beyond soft deletes
- Integration with external help desk systems (Zendesk, Intercom)
- Email notifications for help content updates
- Print-friendly or PDF export of help articles
- Help content analytics dashboard (basic tracking only, no visualization)

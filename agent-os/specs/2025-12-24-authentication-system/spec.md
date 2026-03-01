# Specification: Authentication System

## Goal

Implement a secure authentication system for the RackAudit datacenter management platform using Laravel Fortify, providing login, logout, and password reset functionality with enhanced Vue auth pages that match the application's design.

## User Stories

- As a datacenter operator, I want to securely log in with my email and password so that I can access the RackAudit system to manage infrastructure
- As a user who forgot my password, I want to request a password reset link via email so that I can regain access to my account

## Specific Requirements

**Fortify Configuration Update**
- Update `config/fortify.php` to disable `Features::registration()`, `Features::emailVerification()`, and `Features::twoFactorAuthentication()`
- Keep only `Features::resetPasswords()` enabled in the features array
- Maintain existing rate limiting configuration (5 attempts per minute per email/IP combination)
- Keep `'home' => '/dashboard'` as the post-login redirect destination

**Login Page Functionality**
- Email and password form fields with proper autocomplete attributes
- "Remember Me" checkbox that extends session to 2 weeks when checked
- Conditional "Forgot Password?" link (shown when password reset is enabled)
- Form validation with error messages displayed below each field
- Loading spinner in submit button during form processing
- Status message display for password reset success notifications
- Hide registration link since public registration is disabled

**Logout Functionality**
- Secure logout via POST request to Fortify's logout endpoint
- Session invalidation and regeneration on logout
- Redirect to login page after successful logout
- Logout accessible from authenticated user dropdown/menu

**Forgot Password Page**
- Email input field for requesting password reset link
- Form submission to Fortify's password reset email endpoint
- Success status message displayed after email is sent
- "Return to login" link for navigation back to login page
- Loading state during form submission

**Password Reset Page**
- Receives token and email from password reset URL
- Password and password confirmation fields
- Email field pre-filled and readonly from URL parameter
- Form submission with token validation via Fortify
- Redirect to login page with success status message after reset
- Error handling for invalid or expired tokens

**Session Management**
- Use existing database session driver configuration
- Standard 2-hour session lifetime (120 minutes)
- HTTP-only cookies with same-site lax protection
- Remember me extends session via Laravel's remember token mechanism

**Password Validation**
- Use existing `PasswordValidationRules` trait with `Password::default()`
- Basic validation: required, string, confirmed
- No strict complexity requirements (letters, numbers, symbols not enforced)

**Error Handling and User Feedback**
- Display validation errors inline below form fields using `InputError` component
- Show rate limit exceeded message when too many login attempts
- Display success status messages for password reset email sent and password changed
- Handle expired/invalid reset tokens gracefully with user-friendly error messages

## Visual Design

No visual mockups were provided. The implementation should follow the existing application design patterns established in the auth page templates and UI component library.

**Design Approach**
- Use existing `AuthLayout` (wrapper around `AuthSimpleLayout`) for consistent auth page styling
- Center-aligned layout with app logo, title, and description
- Maximum width of `max-w-sm` for form container
- Use existing UI components: `Button`, `Input`, `Label`, `Checkbox`, `Spinner`
- Use `InputError` component for displaying validation errors
- Use `TextLink` component for navigation links
- Support dark mode via existing Tailwind dark: variants
- Responsive design optimized for tablet use in datacenter environments

## Existing Code to Leverage

**Auth Vue Pages (`resources/js/Pages/auth/`)**
- `Login.vue` - Already implements login form with email, password, remember me, and forgot password link using Inertia Form component and Wayfinder routes
- `ForgotPassword.vue` - Already implements password reset request form with email field and status message display
- `ResetPassword.vue` - Already implements password reset form with token handling, password fields, and form transformation

**Fortify Service Provider (`app/Providers/FortifyServiceProvider.php`)**
- Already configures Inertia views for login, password reset, and forgot password using `Fortify::loginView()`, `Fortify::resetPasswordView()`, and `Fortify::requestPasswordResetLinkView()`
- Already configures rate limiting for login (5 per minute by email|IP)
- Already binds `ResetUserPassword` action class

**UI Component Library (`resources/js/components/ui/`)**
- `Button` - Primary button component with variants and loading state support
- `Input` - Text input component with consistent styling
- `Label` - Form label component
- `Checkbox` - Checkbox input component
- `Spinner` - Loading spinner for button states
- Components follow shadcn/ui patterns with Tailwind CSS v4

**Auth Layout (`resources/js/layouts/AuthLayout.vue`)**
- Wrapper around `AuthSimpleLayout` providing consistent auth page structure
- Includes app logo, title, description, and content slot
- Centered layout with responsive design

## Out of Scope

- User registration (public registration disabled - admin-only user creation)
- Email verification flow and pages
- Two-factor authentication (2FA) setup and challenge
- Social login integration (Google, Microsoft SSO)
- Passwordless authentication (magic links)
- API token management (Laravel Sanctum tokens)
- Active sessions management (view/revoke other logged-in sessions)
- Custom email templates (use standard Laravel/Fortify templates)
- Account lockout policies beyond standard rate limiting
- Strict password complexity requirements (mixed case, numbers, special characters)

/**
 * TypeScript interfaces for Finding Management feature.
 *
 * These types define the shape of data returned by the FindingController
 * and related evidence/category endpoints.
 */

/**
 * Evidence type enum values
 */
export type EvidenceType = 'file' | 'text';

/**
 * Finding status enum values
 */
export type FindingStatusValue = 'open' | 'in_progress' | 'pending_review' | 'deferred' | 'resolved';

/**
 * Finding severity enum values
 */
export type FindingSeverityValue = 'critical' | 'high' | 'medium' | 'low';

/**
 * Finding category data structure
 */
export interface FindingCategoryData {
    id: number;
    name: string;
    description?: string | null;
    is_default?: boolean;
    created_at?: string | null;
    updated_at?: string | null;
}

/**
 * Finding evidence data structure
 */
export interface FindingEvidenceData {
    id: number;
    type: EvidenceType;
    content: string | null;
    file_path: string | null;
    original_filename: string | null;
    mime_type: string | null;
    created_at: string | null;
}

/**
 * User data for assignee display
 */
export interface FindingUserData {
    id: number;
    name: string;
    email?: string;
}

/**
 * Audit data for finding display
 */
export interface FindingAuditData {
    id: number;
    name: string;
    datacenter?: {
        id: number;
        name: string;
    } | null;
}

/**
 * Finding data for list view (Index page)
 */
export interface FindingListData {
    id: number;
    title: string;
    description: string | null;
    status: FindingStatusValue;
    status_label: string;
    status_color: string;
    severity: FindingSeverityValue;
    severity_label: string;
    severity_color: string;
    due_date: string | null;
    is_overdue: boolean;
    is_due_soon: boolean;
    audit: FindingAuditData | null;
    assignee: FindingUserData | null;
    category: {
        id: number;
        name: string;
    } | null;
    created_at: string | null;
}

/**
 * Status transition data structure
 */
export interface StatusTransitionData {
    id: number;
    from_status: FindingStatusValue;
    from_status_label: string;
    to_status: FindingStatusValue;
    to_status_label: string;
    user: FindingUserData | null;
    notes: string | null;
    transitioned_at: string | null;
}

/**
 * Quick action data structure
 */
export interface QuickActionData {
    action: string;
    label: string;
    status: FindingStatusValue;
    variant: string;
    requires_notes?: boolean;
}

/**
 * Time metrics data structure
 */
export interface TimeMetricsData {
    time_to_first_response: string | null;
    total_resolution_time: string | null;
}

/**
 * Complete finding data for detail view (Show page)
 */
export interface FindingDetailData {
    id: number;
    title: string;
    description: string | null;
    discrepancy_type: string | null;
    discrepancy_type_label: string | null;
    status: FindingStatusValue;
    status_label: string;
    status_color: string;
    severity: FindingSeverityValue;
    severity_label: string;
    severity_color: string;
    resolution_notes: string | null;
    resolved_at: string | null;
    resolved_by: FindingUserData | null;
    due_date: string | null;
    is_overdue: boolean;
    is_due_soon: boolean;
    audit: FindingAuditData | null;
    assignee: FindingUserData | null;
    category: FindingCategoryData | null;
    evidence: FindingEvidenceData[];
    created_at: string | null;
    updated_at: string | null;
}

/**
 * Pagination link structure
 */
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

/**
 * Paginated findings response
 */
export interface PaginatedFindings {
    data: FindingListData[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/**
 * Filter option for dropdowns
 */
export interface FilterOption {
    value: string | number;
    label: string;
}

/**
 * Current filter state
 */
export interface FindingFilters {
    search: string;
    status: string;
    severity: string;
    category: string;
    audit_id: string;
    assigned_to: string;
    due_date_status: string;
}

/**
 * Props for Findings/Index.vue page
 */
export interface FindingsIndexProps {
    findings: PaginatedFindings;
    filters: FindingFilters;
    statusOptions: FilterOption[];
    severityOptions: FilterOption[];
    categoryOptions: FilterOption[];
    assigneeOptions: FilterOption[];
    dueDateOptions: FilterOption[];
}

/**
 * Props for Findings/Show.vue page
 */
export interface FindingsShowProps {
    finding: FindingDetailData;
    statusOptions: FilterOption[];
    severityOptions: FilterOption[];
    categoryOptions: FilterOption[];
    assigneeOptions: FilterOption[];
    allowedTransitions: Record<string, string>;
    canEdit: boolean;
    quickActions: QuickActionData[];
    statusTransitions: StatusTransitionData[];
    timeMetrics: TimeMetricsData;
}

/**
 * Badge class helper return type
 */
export type BadgeClassFunction = (value: string) => string;

/**
 * Bulk assign request data
 */
export interface BulkAssignRequest {
    finding_ids: number[];
    assigned_to: number;
}

/**
 * Bulk status change request data
 */
export interface BulkStatusRequest {
    finding_ids: number[];
    status: FindingStatusValue;
}

/**
 * Bulk operation response data
 */
export interface BulkOperationResponse {
    success: boolean;
    message: string;
    success_count: number;
    failure_count: number;
    failures: Array<{
        finding_id: number;
        title: string;
        error: string;
    }>;
}

/**
 * Quick transition request data
 */
export interface QuickTransitionRequest {
    target_status: FindingStatusValue;
    notes?: string;
}

/**
 * Quick transition response data
 */
export interface QuickTransitionResponse {
    success: boolean;
    message: string;
    finding: {
        id: number;
        status: FindingStatusValue;
        status_label: string;
        status_color: string;
    };
}

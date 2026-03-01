/**
 * TypeScript interfaces for Audit Dashboard feature.
 *
 * These types define the shape of data returned by the AuditController::dashboard
 * method for displaying audit metrics, finding summaries, and charts.
 */

/**
 * Datacenter option for filter dropdown
 */
export interface DatacenterOption {
    id: number;
    name: string;
}

/**
 * Time period option for filter dropdown
 */
export interface TimePeriodOption {
    value: string;
    label: string;
}

/**
 * Current filter state from URL query parameters
 */
export interface DashboardFilters {
    datacenter_id: string | null;
    time_period: string;
}

/**
 * Audit status values
 */
export type AuditStatusValue =
    | 'pending'
    | 'in_progress'
    | 'completed'
    | 'cancelled';

/**
 * Audit progress metrics from Task 2.2
 */
export interface AuditMetrics {
    total: number;
    byStatus: Record<AuditStatusValue, number>;
    completionPercentage: number;
    pastDue: number;
    dueSoon: number;
}

/**
 * Finding severity values
 */
export type FindingSeverityValue = 'critical' | 'high' | 'medium' | 'low';

/**
 * Individual severity metric with color and percentage
 */
export interface SeverityMetricItem {
    count: number;
    color: string;
    label: string;
    percentage: number;
}

/**
 * Finding severity metrics from Task 2.3
 */
export interface SeverityMetrics {
    critical: SeverityMetricItem;
    high: SeverityMetricItem;
    medium: SeverityMetricItem;
    low: SeverityMetricItem;
    total: number;
}

/**
 * Per-audit finding breakdown item from Task 2.4
 */
export interface AuditBreakdownItem {
    id: number;
    name: string;
    datacenter: string;
    status: AuditStatusValue;
    status_label: string;
    critical: number;
    high: number;
    medium: number;
    low: number;
    total: number;
}

/**
 * Resolution status metrics from Task 2.5
 */
export interface ResolutionMetrics {
    openCount: number;
    resolvedCount: number;
    totalCount: number;
    resolutionRate: number;
    averageResolutionTime: number | null;
    overdueCount: number;
}

/**
 * Trend data point for completion chart from Task 2.6
 */
export interface TrendDataPoint {
    period: string;
    count: number;
}

/**
 * Active audit progress data from Task 2.7
 */
export interface ActiveAuditProgress {
    id: number;
    name: string;
    datacenter: string;
    dueDate: string | null;
    progressPercentage: number;
    type: string;
    type_label: string;
    isOverdue: boolean;
    isDueSoon: boolean;
}

/**
 * Props for Audits/Dashboard.vue page
 */
export interface DashboardProps {
    filters: DashboardFilters;
    datacenterOptions: DatacenterOption[];
    timePeriodOptions: TimePeriodOption[];
    auditMetrics: AuditMetrics;
    severityMetrics: SeverityMetrics;
    auditBreakdown: AuditBreakdownItem[];
    resolutionMetrics: ResolutionMetrics;
    trendData: TrendDataPoint[];
    activeAuditProgress: ActiveAuditProgress[];
}

/**
 * Chart.js compatible dataset for severity distribution
 */
export interface SeverityChartData {
    labels: string[];
    datasets: Array<{
        data: number[];
        backgroundColor: string[];
        borderWidth?: number;
    }>;
}

/**
 * Chart.js compatible dataset for trend line chart
 */
export interface TrendChartData {
    labels: string[];
    datasets: Array<{
        label: string;
        data: number[];
        borderColor?: string;
        backgroundColor?: string;
        fill?: boolean;
        tension?: number;
    }>;
}

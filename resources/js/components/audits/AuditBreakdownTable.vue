<script setup lang="ts">
import AuditController from '@/actions/App/Http/Controllers/AuditController';
import FindingController from '@/actions/App/Http/Controllers/FindingController';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type {
    AuditBreakdownItem,
    FindingSeverityValue,
} from '@/types/dashboard';
import { Link, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    auditBreakdown: AuditBreakdownItem[];
}

defineProps<Props>();

// Collapsible state - default to expanded
const isExpanded = ref(true);

/**
 * Navigate to the audit show page when a row is clicked.
 */
const navigateToAudit = (auditId: number) => {
    router.get(AuditController.show.url(auditId));
};

/**
 * Navigate to findings list with audit and severity filters.
 * Stops event propagation to prevent row click navigation.
 */
const navigateToFindingsBySeverity = (
    event: Event,
    auditId: number,
    severity: FindingSeverityValue,
) => {
    event.stopPropagation();
    router.get(
        FindingController.index.url({
            query: {
                audit_id: auditId,
                severity: severity,
            },
        }),
    );
};

/**
 * Get severity badge class for table cells.
 * Zero counts are displayed as muted/gray.
 * Non-zero counts are displayed with severity color.
 */
const getSeverityCellBadgeClass = (
    severity: FindingSeverityValue,
    count: number,
): string => {
    const baseClasses =
        'inline-flex min-w-[2rem] items-center justify-center rounded-full px-2 py-0.5 text-xs font-medium transition-colors';

    if (count === 0) {
        return `${baseClasses} text-muted-foreground`;
    }

    // Non-zero counts: add hover state for clickable badges
    const hoverClasses = 'cursor-pointer hover:opacity-80';

    switch (severity) {
        case 'critical':
            return `${baseClasses} ${hoverClasses} bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300`;
        case 'high':
            return `${baseClasses} ${hoverClasses} bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-300`;
        case 'medium':
            return `${baseClasses} ${hoverClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300`;
        case 'low':
            return `${baseClasses} ${hoverClasses} bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300`;
        default:
            return `${baseClasses} text-muted-foreground`;
    }
};

/**
 * Get status badge class matching the Index.vue pattern.
 */
const getStatusBadgeClass = (status: string): string => {
    const baseClasses =
        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium';
    switch (status) {
        case 'pending':
            return `${baseClasses} bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200`;
        case 'in_progress':
            return `${baseClasses} bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200`;
        case 'completed':
            return `${baseClasses} bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200`;
        case 'cancelled':
            return `${baseClasses} bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300`;
        default:
            return `${baseClasses} bg-gray-100 text-gray-800`;
    }
};
</script>

<template>
    <Collapsible v-model:open="isExpanded" class="w-full">
        <Card>
            <CardHeader class="pb-3">
                <CollapsibleTrigger
                    class="group flex w-full cursor-pointer items-center justify-between"
                >
                    <CardTitle class="flex items-center gap-2">
                        <span>Per-Audit Finding Breakdown</span>
                        <span class="text-sm font-normal text-muted-foreground">
                            ({{ auditBreakdown.length }} audit{{
                                auditBreakdown.length !== 1 ? 's' : ''
                            }})
                        </span>
                    </CardTitle>
                    <div
                        class="flex items-center gap-1 text-muted-foreground transition-colors group-hover:text-foreground"
                    >
                        <span class="hidden text-sm sm:inline">
                            {{ isExpanded ? 'Collapse' : 'Expand' }}
                        </span>
                        <ChevronDown
                            v-if="isExpanded"
                            class="h-5 w-5 transition-transform"
                        />
                        <ChevronRight
                            v-else
                            class="h-5 w-5 transition-transform"
                        />
                    </div>
                </CollapsibleTrigger>
            </CardHeader>
            <CollapsibleContent>
                <CardContent class="pt-0">
                    <!-- Empty state -->
                    <div
                        v-if="auditBreakdown.length === 0"
                        class="py-8 text-center text-muted-foreground"
                    >
                        No audits with findings in the selected time period.
                    </div>

                    <!-- Table content -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="border-b bg-muted/50">
                                <tr>
                                    <th
                                        class="h-10 px-3 text-left font-medium text-muted-foreground"
                                    >
                                        Audit
                                    </th>
                                    <th
                                        class="hidden h-10 px-3 text-left font-medium text-muted-foreground sm:table-cell"
                                    >
                                        Datacenter
                                    </th>
                                    <th
                                        class="hidden h-10 px-3 text-left font-medium text-muted-foreground md:table-cell"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="h-10 px-3 text-center font-medium text-red-600 dark:text-red-400"
                                    >
                                        Critical
                                    </th>
                                    <th
                                        class="h-10 px-3 text-center font-medium text-orange-600 dark:text-orange-400"
                                    >
                                        High
                                    </th>
                                    <th
                                        class="hidden h-10 px-3 text-center font-medium text-yellow-600 sm:table-cell dark:text-yellow-400"
                                    >
                                        Medium
                                    </th>
                                    <th
                                        class="hidden h-10 px-3 text-center font-medium text-blue-600 sm:table-cell dark:text-blue-400"
                                    >
                                        Low
                                    </th>
                                    <th
                                        class="h-10 px-3 text-center font-medium text-muted-foreground"
                                    >
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="audit in auditBreakdown"
                                    :key="audit.id"
                                    class="cursor-pointer border-b transition-colors hover:bg-muted/50"
                                    @click="navigateToAudit(audit.id)"
                                    role="button"
                                    tabindex="0"
                                    @keydown.enter="navigateToAudit(audit.id)"
                                >
                                    <!-- Audit Name -->
                                    <td class="p-3 font-medium">
                                        <Link
                                            :href="
                                                AuditController.show.url(
                                                    audit.id,
                                                )
                                            "
                                            class="hover:underline"
                                            @click.stop
                                        >
                                            {{ audit.name }}
                                        </Link>
                                    </td>

                                    <!-- Datacenter -->
                                    <td class="hidden p-3 sm:table-cell">
                                        {{ audit.datacenter }}
                                    </td>

                                    <!-- Status -->
                                    <td class="hidden p-3 md:table-cell">
                                        <span
                                            :class="
                                                getStatusBadgeClass(
                                                    audit.status,
                                                )
                                            "
                                        >
                                            {{ audit.status_label }}
                                        </span>
                                    </td>

                                    <!-- Critical count - clickable when > 0 -->
                                    <td class="p-3 text-center">
                                        <span
                                            :class="
                                                getSeverityCellBadgeClass(
                                                    'critical',
                                                    audit.critical,
                                                )
                                            "
                                            @click="
                                                audit.critical > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'critical',
                                                      )
                                                    : null
                                            "
                                            :role="
                                                audit.critical > 0
                                                    ? 'button'
                                                    : undefined
                                            "
                                            :tabindex="
                                                audit.critical > 0
                                                    ? 0
                                                    : undefined
                                            "
                                            @keydown.enter="
                                                audit.critical > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'critical',
                                                      )
                                                    : null
                                            "
                                        >
                                            {{ audit.critical }}
                                        </span>
                                    </td>

                                    <!-- High count - clickable when > 0 -->
                                    <td class="p-3 text-center">
                                        <span
                                            :class="
                                                getSeverityCellBadgeClass(
                                                    'high',
                                                    audit.high,
                                                )
                                            "
                                            @click="
                                                audit.high > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'high',
                                                      )
                                                    : null
                                            "
                                            :role="
                                                audit.high > 0
                                                    ? 'button'
                                                    : undefined
                                            "
                                            :tabindex="
                                                audit.high > 0 ? 0 : undefined
                                            "
                                            @keydown.enter="
                                                audit.high > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'high',
                                                      )
                                                    : null
                                            "
                                        >
                                            {{ audit.high }}
                                        </span>
                                    </td>

                                    <!-- Medium count - clickable when > 0 -->
                                    <td
                                        class="hidden p-3 text-center sm:table-cell"
                                    >
                                        <span
                                            :class="
                                                getSeverityCellBadgeClass(
                                                    'medium',
                                                    audit.medium,
                                                )
                                            "
                                            @click="
                                                audit.medium > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'medium',
                                                      )
                                                    : null
                                            "
                                            :role="
                                                audit.medium > 0
                                                    ? 'button'
                                                    : undefined
                                            "
                                            :tabindex="
                                                audit.medium > 0 ? 0 : undefined
                                            "
                                            @keydown.enter="
                                                audit.medium > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'medium',
                                                      )
                                                    : null
                                            "
                                        >
                                            {{ audit.medium }}
                                        </span>
                                    </td>

                                    <!-- Low count - clickable when > 0 -->
                                    <td
                                        class="hidden p-3 text-center sm:table-cell"
                                    >
                                        <span
                                            :class="
                                                getSeverityCellBadgeClass(
                                                    'low',
                                                    audit.low,
                                                )
                                            "
                                            @click="
                                                audit.low > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'low',
                                                      )
                                                    : null
                                            "
                                            :role="
                                                audit.low > 0
                                                    ? 'button'
                                                    : undefined
                                            "
                                            :tabindex="
                                                audit.low > 0 ? 0 : undefined
                                            "
                                            @keydown.enter="
                                                audit.low > 0
                                                    ? navigateToFindingsBySeverity(
                                                          $event,
                                                          audit.id,
                                                          'low',
                                                      )
                                                    : null
                                            "
                                        >
                                            {{ audit.low }}
                                        </span>
                                    </td>

                                    <!-- Total count -->
                                    <td class="p-3 text-center font-medium">
                                        {{ audit.total }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </CollapsibleContent>
        </Card>
    </Collapsible>
</template>

<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { BarChart3, Package, Cable, ClipboardList, Check, Loader2 } from 'lucide-vue-next';
import type { Component } from 'vue';

/**
 * TypeScript interfaces
 */
interface ReportTypeOption {
    value: string;
    label: string;
    description: string;
}

interface Props {
    reportTypes: ReportTypeOption[];
    selectedType?: string | null;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    selectedType: null,
    loading: false,
});

const emit = defineEmits<{
    (e: 'select', value: string): void;
}>();

/**
 * Get icon component for each report type
 */
function getReportTypeIcon(typeValue: string): Component {
    switch (typeValue) {
        case 'capacity':
            return BarChart3;
        case 'assets':
            return Package;
        case 'connections':
            return Cable;
        case 'audit_history':
            return ClipboardList;
        default:
            return BarChart3;
    }
}

/**
 * Handle report type selection
 */
function handleSelect(typeValue: string) {
    if (props.loading) return;
    emit('select', typeValue);
}
</script>

<template>
    <div class="space-y-4">
        <h3 class="text-center text-lg font-semibold">Select a Report Type</h3>
        <p class="text-center text-sm text-muted-foreground">
            Choose the type of report you want to create. Each type has different data fields and options.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card
                v-for="reportType in reportTypes"
                :key="reportType.value"
                class="relative cursor-pointer transition-all hover:border-primary/50 hover:shadow-md"
                :class="{
                    'border-primary bg-primary/5 shadow-md': selectedType === reportType.value,
                    'opacity-50 cursor-not-allowed': loading && selectedType !== reportType.value,
                }"
                @click="handleSelect(reportType.value)"
            >
                <!-- Selected indicator -->
                <div
                    v-if="selectedType === reportType.value"
                    class="absolute right-2 top-2 flex size-6 items-center justify-center rounded-full bg-primary"
                >
                    <Check v-if="!loading" class="size-4 text-primary-foreground" />
                    <Loader2 v-else class="size-4 animate-spin text-primary-foreground" />
                </div>

                <CardHeader class="pb-2">
                    <div class="mb-2 flex size-12 items-center justify-center rounded-lg bg-muted">
                        <component
                            :is="getReportTypeIcon(reportType.value)"
                            class="size-6"
                            :class="{
                                'text-primary': selectedType === reportType.value,
                                'text-muted-foreground': selectedType !== reportType.value,
                            }"
                        />
                    </div>
                    <CardTitle class="text-base">{{ reportType.label }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <CardDescription class="text-sm">
                        {{ reportType.description }}
                    </CardDescription>
                </CardContent>
            </Card>
        </div>

        <!-- Loading State Overlay -->
        <div v-if="loading" class="text-center">
            <div class="inline-flex items-center gap-2 rounded-md bg-muted px-4 py-2 text-sm">
                <Loader2 class="size-4 animate-spin" />
                <span>Loading configuration...</span>
            </div>
        </div>
    </div>
</template>

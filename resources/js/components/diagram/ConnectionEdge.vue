<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type {
    CableTypeValue,
    FlowEdgeData,
    PortTypeValue,
} from '@/types/connections';
import {
    BaseEdge,
    EdgeLabelRenderer,
    getBezierPath,
    type EdgeProps,
} from '@vue-flow/core';
import { AlertTriangle, Cable, Network, Zap } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props extends EdgeProps<FlowEdgeData> {
    data: FlowEdgeData;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'edgeClick', edgeId: string): void;
}>();

/**
 * Get the port type category from cable type
 */
function getPortType(cableType: CableTypeValue | null): PortTypeValue | null {
    if (!cableType) return null;

    if (cableType.startsWith('cat')) return 'ethernet';
    if (cableType.startsWith('fiber')) return 'fiber';
    if (cableType.startsWith('power')) return 'power';

    return null;
}

/**
 * Get stroke color based on cable type and cable color
 * Uses brighter colors for dark mode visibility
 */
const strokeColor = computed(() => {
    // Use cable color if specified - with enhanced visibility for dark mode
    if (props.data.cable_color) {
        const colorMap: Record<string, { light: string; dark: string }> = {
            blue: { light: '#3b82f6', dark: '#60a5fa' },
            yellow: { light: '#eab308', dark: '#facc15' },
            green: { light: '#22c55e', dark: '#4ade80' },
            red: { light: '#ef4444', dark: '#f87171' },
            orange: { light: '#f97316', dark: '#fb923c' },
            purple: { light: '#a855f7', dark: '#c084fc' },
            pink: { light: '#ec4899', dark: '#f472b6' },
            gray: { light: '#6b7280', dark: '#9ca3af' },
            black: { light: '#1f2937', dark: '#6b7280' },
            white: { light: '#e5e7eb', dark: '#f3f4f6' },
        };
        const normalizedColor = props.data.cable_color.toLowerCase();
        if (colorMap[normalizedColor]) {
            // Check if we're in dark mode by checking CSS variable
            const isDark = document.documentElement.classList.contains('dark');
            return isDark
                ? colorMap[normalizedColor].dark
                : colorMap[normalizedColor].light;
        }
    }

    // Default colors based on port type with dark mode variants
    const portType = getPortType(props.data.cable_type);
    const isDark = document.documentElement.classList.contains('dark');

    switch (portType) {
        case 'ethernet':
            return isDark ? '#60a5fa' : '#3b82f6'; // Blue - brighter in dark
        case 'fiber':
            return isDark ? '#fb923c' : '#f97316'; // Orange - brighter in dark
        case 'power':
            return isDark ? '#f87171' : '#ef4444'; // Red - brighter in dark
        default:
            return isDark ? '#9ca3af' : '#6b7280'; // Gray - brighter in dark
    }
});

/**
 * Get stroke style based on verified status
 */
const strokeDasharray = computed(() => {
    return props.data.verified ? undefined : '8 4';
});

/**
 * Get stroke width based on connection count and selection
 */
const strokeWidth = computed(() => {
    const base = props.data.connection_count > 1 ? 3 : 2;
    return props.data.selected ? base + 1 : base;
});

/**
 * Get cable type label for display
 */
const cableTypeLabel = computed(() => {
    if (!props.data.cable_type) return 'Unknown';

    const labelMap: Record<string, string> = {
        cat5e: 'Cat5e',
        cat6: 'Cat6',
        cat6a: 'Cat6a',
        fiber_sm: 'Fiber SM',
        fiber_mm: 'Fiber MM',
        power_c13: 'C13',
        power_c14: 'C14',
        power_c19: 'C19',
        power_c20: 'C20',
    };

    return labelMap[props.data.cable_type] || props.data.cable_type;
});

/**
 * Get icon component based on port type
 */
const iconComponent = computed(() => {
    const portType = getPortType(props.data.cable_type);

    switch (portType) {
        case 'fiber':
            return Cable;
        case 'power':
            return Zap;
        default:
            return Network;
    }
});

/**
 * Calculate the bezier path for the edge
 */
const pathData = computed(() => {
    return getBezierPath({
        sourceX: props.sourceX,
        sourceY: props.sourceY,
        sourcePosition: props.sourcePosition,
        targetX: props.targetX,
        targetY: props.targetY,
        targetPosition: props.targetPosition,
    });
});

/**
 * Calculate label position at the center of the edge
 */
const labelPosition = computed(() => {
    return {
        x: (props.sourceX + props.targetX) / 2,
        y: (props.sourceY + props.targetY) / 2,
    };
});

function handleClick() {
    emit('edgeClick', props.data.id);
}
</script>

<template>
    <BaseEdge
        :id="id"
        :path="pathData[0]"
        :style="{
            stroke: strokeColor,
            strokeWidth: strokeWidth,
            strokeDasharray: strokeDasharray,
        }"
        :class="[
            'cursor-pointer transition-all duration-200',
            data.selected ? 'opacity-100' : 'opacity-80 hover:opacity-100',
        ]"
        @click="handleClick"
    />

    <!-- Edge label with tooltip and dark mode support -->
    <EdgeLabelRenderer>
        <div
            :style="{
                position: 'absolute',
                transform: `translate(-50%, -50%) translate(${labelPosition.x}px, ${labelPosition.y}px)`,
                pointerEvents: 'all',
            }"
        >
            <TooltipProvider>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <div
                            class="flex cursor-pointer items-center gap-1 rounded-full border bg-background/95 px-2 py-1 shadow-md transition-all duration-200 dark:bg-slate-800/95"
                            :class="[
                                data.selected
                                    ? 'border-primary ring-1 ring-primary/30 dark:border-sky-400 dark:ring-sky-400/30'
                                    : 'border-border hover:border-primary/50 dark:border-slate-600 dark:hover:border-sky-400/50',
                                data.has_discrepancy
                                    ? 'border-yellow-500 bg-yellow-50 dark:border-yellow-500 dark:bg-yellow-950/50'
                                    : '',
                            ]"
                            @click="handleClick"
                        >
                            <!-- Warning icon for discrepancies -->
                            <AlertTriangle
                                v-if="data.has_discrepancy"
                                class="size-3 text-yellow-500 dark:text-yellow-400"
                            />

                            <!-- Connection type icon -->
                            <component
                                :is="iconComponent"
                                class="size-3"
                                :style="{ color: strokeColor }"
                            />

                            <!-- Connection count badge -->
                            <span
                                v-if="data.connection_count > 1"
                                class="text-[10px] font-medium text-muted-foreground dark:text-slate-400"
                            >
                                x{{ data.connection_count }}
                            </span>
                        </div>
                    </TooltipTrigger>
                    <TooltipContent
                        side="top"
                        class="max-w-[200px] dark:border-slate-600 dark:bg-slate-800"
                    >
                        <div class="space-y-2">
                            <div
                                class="flex items-center gap-2 font-medium dark:text-slate-100"
                            >
                                <component
                                    :is="iconComponent"
                                    class="size-4"
                                    :style="{ color: strokeColor }"
                                />
                                {{ cableTypeLabel }}
                            </div>
                            <div
                                class="space-y-1 text-xs text-muted-foreground dark:text-slate-400"
                            >
                                <div v-if="data.cable_color">
                                    <span
                                        class="font-medium dark:text-slate-300"
                                        >Color:</span
                                    >
                                    <span
                                        class="ml-1 inline-block size-3 rounded-full border align-middle dark:border-slate-500"
                                        :style="{
                                            backgroundColor: strokeColor,
                                        }"
                                    />
                                    <span
                                        class="ml-1 capitalize dark:text-slate-300"
                                        >{{ data.cable_color }}</span
                                    >
                                </div>
                                <div>
                                    <span
                                        class="font-medium dark:text-slate-300"
                                        >Status:</span
                                    >
                                    <Badge
                                        :variant="
                                            data.verified
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        class="ml-1 px-1 py-0 text-[10px]"
                                    >
                                        {{
                                            data.verified
                                                ? 'Verified'
                                                : 'Unverified'
                                        }}
                                    </Badge>
                                </div>
                                <div v-if="data.connection_count > 1">
                                    <span
                                        class="font-medium dark:text-slate-300"
                                        >Connections:</span
                                    >
                                    <span class="dark:text-slate-300">
                                        {{ data.connection_count }}</span
                                    >
                                </div>
                                <div
                                    v-if="data.has_discrepancy"
                                    class="text-yellow-600 dark:text-yellow-400"
                                >
                                    <AlertTriangle class="mr-1 inline size-3" />
                                    Has audit discrepancy
                                </div>
                            </div>
                        </div>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
        </div>
    </EdgeLabelRenderer>
</template>

<style scoped>
/* Edge hover effect with dark mode support */
:deep(.vue-flow__edge-path) {
    transition: stroke-width 0.2s ease;
}

:deep(.vue-flow__edge-path:hover) {
    stroke-width: 4px;
}

/* Ensure edge paths are visible in dark mode */
:root.dark :deep(.vue-flow__edge-path),
.dark :deep(.vue-flow__edge-path) {
    filter: brightness(1.1);
}
</style>

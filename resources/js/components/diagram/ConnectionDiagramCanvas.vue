<script setup lang="ts">
import { Controls } from '@vue-flow/controls';
import '@vue-flow/controls/dist/style.css';
import { VueFlow, useVueFlow, type Node } from '@vue-flow/core';
import '@vue-flow/core/dist/style.css';
import { MiniMap } from '@vue-flow/minimap';
import '@vue-flow/minimap/dist/style.css';
import { markRaw, onMounted, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useConnectionDiagram } from '@/composables/useConnectionDiagram';
import type {
    DiagramFilters,
    FlowEdgeData,
    FlowNodeData,
} from '@/types/connections';
import { Maximize2, RefreshCw } from 'lucide-vue-next';
import ConnectionEdge from './ConnectionEdge.vue';
import DeviceNode from './DeviceNode.vue';

interface Props {
    /** Initial filter values for the diagram */
    initialFilters?: Partial<DiagramFilters>;
    /** Whether to show the minimap */
    showMinimap?: boolean;
    /** Whether to show zoom controls */
    showControls?: boolean;
    /** Minimum zoom level */
    minZoom?: number;
    /** Maximum zoom level */
    maxZoom?: number;
    /** Custom CSS class for the container */
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    initialFilters: () => ({}),
    showMinimap: true,
    showControls: true,
    minZoom: 0.2,
    maxZoom: 4,
});

const emit = defineEmits<{
    (e: 'nodeClick', nodeId: number): void;
    (e: 'edgeClick', edgeId: string): void;
    (e: 'nodeSelect', node: FlowNodeData | null): void;
    (e: 'edgeSelect', edge: FlowEdgeData | null): void;
}>();

// Initialize the composable
const {
    nodes,
    edges,
    selectedNode,
    selectedEdge,
    isLoading,
    isLoadingPorts,
    error,
    fetchDiagramData,
    setFilters,
    clearFilters,
    selectNode,
    selectEdge,
    clearSelection,
    updateNodePosition,
    resetLayout,
    toggleNodeExpansion,
} = useConnectionDiagram(props.initialFilters);

// Vue Flow instance
const { fitView } = useVueFlow();

// Register custom node and edge types
const nodeTypes = {
    device: markRaw(DeviceNode),
};

const edgeTypes = {
    connection: markRaw(ConnectionEdge),
};

// Container ref for sizing
const containerRef = ref<HTMLElement | null>(null);

/**
 * Handle node click
 */
function handleNodeClick(nodeId: number) {
    selectNode(nodeId);
    emit('nodeClick', nodeId);
    emit('nodeSelect', selectedNode.value);
}

/**
 * Handle node expand/collapse for port drill-down
 */
async function handleToggleExpand(nodeId: number) {
    await toggleNodeExpansion(nodeId);
}

/**
 * Handle edge click
 */
function handleEdgeClick(edgeId: string) {
    selectEdge(edgeId);
    emit('edgeClick', edgeId);
    emit('edgeSelect', selectedEdge.value);
}

/**
 * Handle node drag stop to update position
 */
function handleNodeDragStop(event: { node: Node<FlowNodeData> }) {
    updateNodePosition(event.node.id, event.node.position);
}

/**
 * Handle pane click to clear selection
 */
function handlePaneClick() {
    clearSelection();
    emit('nodeSelect', null);
    emit('edgeSelect', null);
}

/**
 * Handle fit view button
 */
function handleFitView() {
    fitView({ padding: 0.1, duration: 300, minZoom: 0.5, maxZoom: 1.5 });
}

/**
 * Handle reset layout button
 */
async function handleResetLayout() {
    await fetchDiagramData();
    setTimeout(() => {
        fitView({ padding: 0.1, duration: 300, minZoom: 0.5, maxZoom: 1.5 });
    }, 100);
}

/**
 * Expose methods for parent component
 */
defineExpose({
    fetchDiagramData,
    setFilters,
    clearFilters,
    resetLayout,
    fitView: handleFitView,
});

// Fetch initial data on mount
onMounted(async () => {
    await fetchDiagramData();
    // Fit view after data loads with minimum zoom to ensure readability
    setTimeout(() => {
        fitView({ padding: 0.1, duration: 300, minZoom: 0.5, maxZoom: 1.5 });
    }, 100);
});

// Watch for filter changes from parent
watch(
    () => props.initialFilters,
    async (newFilters) => {
        await setFilters(newFilters);
    },
    { deep: true },
);
</script>

<template>
    <div
        ref="containerRef"
        :class="[
            'relative h-full w-full overflow-hidden rounded-lg border',
            'bg-muted/20 dark:bg-slate-900/50',
            'dark:border-slate-700',
            props.class,
        ]"
    >
        <!-- Loading state with dark mode support -->
        <div
            v-if="isLoading"
            class="absolute inset-0 z-10 flex items-center justify-center bg-background/80 backdrop-blur-sm dark:bg-slate-900/80"
        >
            <div class="space-y-4 text-center">
                <div class="flex items-center justify-center gap-3">
                    <Skeleton class="h-16 w-24 rounded-lg dark:bg-slate-700" />
                    <Skeleton class="h-16 w-24 rounded-lg dark:bg-slate-700" />
                    <Skeleton class="h-16 w-24 rounded-lg dark:bg-slate-700" />
                </div>
                <p class="text-sm text-muted-foreground dark:text-slate-400">
                    Loading diagram...
                </p>
            </div>
        </div>

        <!-- Error state with dark mode support -->
        <div
            v-else-if="error"
            class="absolute inset-0 flex items-center justify-center"
        >
            <div class="space-y-4 text-center">
                <p class="text-sm text-destructive dark:text-red-400">
                    {{ error }}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    class="dark:border-slate-600 dark:hover:bg-slate-700"
                    @click="handleResetLayout"
                >
                    <RefreshCw class="mr-2 size-4" />
                    Retry
                </Button>
            </div>
        </div>

        <!-- Empty state with dark mode support -->
        <div
            v-else-if="nodes.length === 0 && !isLoading"
            class="absolute inset-0 flex items-center justify-center"
        >
            <div class="space-y-2 text-center">
                <p class="text-sm text-muted-foreground dark:text-slate-400">
                    No connections found
                </p>
                <p class="text-xs text-muted-foreground/60 dark:text-slate-500">
                    Adjust filters or create connections to see the diagram
                </p>
            </div>
        </div>

        <!-- Vue Flow diagram -->
        <VueFlow
            v-else
            v-model:nodes="nodes"
            v-model:edges="edges"
            :node-types="nodeTypes"
            :edge-types="edgeTypes"
            :min-zoom="minZoom"
            :max-zoom="maxZoom"
            :default-viewport="{ x: 0, y: 0, zoom: 1 }"
            :fit-view-on-init="false"
            :snap-to-grid="true"
            :snap-grid="[15, 15]"
            class="vue-flow-diagram"
            @node-drag-stop="handleNodeDragStop"
            @pane-click="handlePaneClick"
        >
            <!-- Custom node template -->
            <template #node-device="{ data }">
                <DeviceNode
                    :data="data"
                    :is-loading-ports="isLoadingPorts"
                    @node-click="handleNodeClick"
                    @toggle-expand="handleToggleExpand"
                />
            </template>

            <!-- Custom edge template -->
            <template #edge-connection="edgeProps">
                <ConnectionEdge
                    v-bind="edgeProps"
                    @edge-click="handleEdgeClick"
                />
            </template>

            <!-- Controls with responsive positioning -->
            <Controls
                v-if="showControls"
                :show-zoom="true"
                :show-fit-view="true"
                :show-interactive="false"
                position="bottom-right"
                class="vue-flow-controls rounded-lg border bg-background shadow-lg dark:border-slate-600 dark:bg-slate-800"
            />

            <!-- MiniMap with responsive visibility and dark mode -->
            <MiniMap
                v-if="showMinimap"
                position="bottom-left"
                :pannable="true"
                :zoomable="true"
                class="vue-flow-minimap hidden rounded-lg border bg-background shadow-lg md:block dark:border-slate-600 dark:bg-slate-800"
            />
        </VueFlow>

        <!-- Custom control buttons with responsive sizing and dark mode -->
        <div
            class="absolute top-2 right-2 flex gap-1.5 sm:top-4 sm:right-4 sm:gap-2"
        >
            <Button
                variant="outline"
                size="icon"
                class="size-7 bg-background/80 backdrop-blur-sm sm:size-8 dark:border-slate-600 dark:bg-slate-800/80 dark:hover:bg-slate-700"
                title="Reset Layout"
                @click="handleResetLayout"
            >
                <RefreshCw class="size-3.5 sm:size-4" />
            </Button>
            <Button
                variant="outline"
                size="icon"
                class="size-7 bg-background/80 backdrop-blur-sm sm:size-8 dark:border-slate-600 dark:bg-slate-800/80 dark:hover:bg-slate-700"
                title="Fit View"
                @click="handleFitView"
            >
                <Maximize2 class="size-3.5 sm:size-4" />
            </Button>
        </div>
    </div>
</template>

<style scoped>
/* Vue Flow base styles */
.vue-flow-diagram {
    background-color: transparent;
}

.vue-flow-diagram :deep(.vue-flow__background) {
    background-color: transparent;
}

/* Controls styling with dark mode */
.vue-flow-controls :deep(.vue-flow__controls-button) {
    border-bottom: 1px solid hsl(var(--border));
    background-color: transparent;
    transition: background-color 0.15s ease;
}

.vue-flow-controls :deep(.vue-flow__controls-button:last-child) {
    border-bottom: none;
}

.vue-flow-controls :deep(.vue-flow__controls-button:hover) {
    background-color: hsl(var(--muted));
}

.vue-flow-controls :deep(.vue-flow__controls-button svg) {
    width: 1rem;
    height: 1rem;
}

/* Dark mode controls */
:root.dark .vue-flow-controls :deep(.vue-flow__controls-button),
.dark .vue-flow-controls :deep(.vue-flow__controls-button) {
    border-color: hsl(217 33% 25%);
    color: hsl(210 40% 98%);
}

:root.dark .vue-flow-controls :deep(.vue-flow__controls-button:hover),
.dark .vue-flow-controls :deep(.vue-flow__controls-button:hover) {
    background-color: hsl(217 33% 25%);
}

/* MiniMap styling with dark mode */
.vue-flow-minimap :deep(.vue-flow__minimap-mask) {
    fill: hsl(var(--muted) / 0.3);
}

.vue-flow-minimap :deep(.vue-flow__minimap-node) {
    fill: hsl(var(--primary));
    stroke: hsl(var(--primary-foreground));
    stroke-width: 1;
}

/* Dark mode minimap */
:root.dark .vue-flow-minimap :deep(.vue-flow__minimap-mask),
.dark .vue-flow-minimap :deep(.vue-flow__minimap-mask) {
    fill: hsl(217 33% 25% / 0.5);
}

:root.dark .vue-flow-minimap :deep(.vue-flow__minimap-node),
.dark .vue-flow-minimap :deep(.vue-flow__minimap-node) {
    fill: hsl(199 89% 48%);
    stroke: hsl(222 47% 11%);
}

/* Edge styling */
:deep(.vue-flow__edge) {
    cursor: pointer;
}

:deep(.vue-flow__edge-path) {
    stroke-linecap: round;
}

/* Node styling */
:deep(.vue-flow__node) {
    cursor: grab;
}

:deep(.vue-flow__node:active) {
    cursor: grabbing;
}

/* Selection box with dark mode */
:deep(.vue-flow__selection) {
    border: 1px solid hsl(var(--primary));
    background-color: hsl(var(--primary) / 0.1);
}

:root.dark :deep(.vue-flow__selection),
.dark :deep(.vue-flow__selection) {
    border-color: hsl(199 89% 48%);
    background-color: hsl(199 89% 48% / 0.1);
}

/* Pane */
:deep(.vue-flow__pane) {
    cursor: grab;
}

:deep(.vue-flow__pane:active) {
    cursor: grabbing;
}

/* Responsive adjustments for tablet/mobile */
@media (max-width: 1024px) {
    .vue-flow-controls :deep(.vue-flow__controls-button svg) {
        width: 0.875rem;
        height: 0.875rem;
    }
}

@media (max-width: 768px) {
    .vue-flow-minimap {
        display: none !important;
    }

    .vue-flow-controls {
        transform: scale(0.9);
        transform-origin: bottom right;
    }
}
</style>

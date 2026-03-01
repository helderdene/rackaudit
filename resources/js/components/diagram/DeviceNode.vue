<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { FlowNodeData, PortDrillDownData } from '@/types/connections';
import {
    getPortStatusBadgeVariant,
    getPortStatusColor,
    isDeviceNode,
    isRackNode,
} from '@/types/connections';
import { Handle, Position } from '@vue-flow/core';
import {
    Cable,
    ChevronDown,
    ChevronUp,
    HardDrive,
    LayoutGrid,
    Loader2,
    Monitor,
    Network,
    Router,
    Server,
    ServerCog,
    Shield,
    Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    /** Node data from Vue Flow containing rack or device information */
    data: FlowNodeData;
    /** Whether ports are currently loading */
    isLoadingPorts?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isLoadingPorts: false,
});

const emit = defineEmits<{
    (e: 'nodeClick', nodeId: number): void;
    (e: 'toggleExpand', nodeId: number): void;
    (e: 'portClick', port: PortDrillDownData): void;
    (e: 'connectionClick', connectionId: number): void;
}>();

/**
 * Check if this is a rack node
 */
const isRack = computed(() => isRackNode(props.data));

/**
 * Check if this is a device node
 */
const isDevice = computed(() => isDeviceNode(props.data));

/**
 * Get the appropriate icon component based on node type
 */
const iconComponent = computed(() => {
    // Rack node uses ServerCog icon
    if (isRack.value) {
        return ServerCog;
    }

    // Device node - determine by device type
    const data = props.data as { device_type?: string | null };
    const deviceType = data.device_type?.toLowerCase() || '';

    if (deviceType.includes('server')) return Server;
    if (deviceType.includes('switch')) return Network;
    if (deviceType.includes('router')) return Router;
    if (deviceType.includes('storage')) return HardDrive;
    if (deviceType.includes('pdu')) return Zap;
    if (deviceType.includes('firewall')) return Shield;
    if (deviceType.includes('patch panel') || deviceType.includes('patch'))
        return LayoutGrid;

    return Monitor;
});

/**
 * Get badge variant based on node type for visual differentiation
 */
const badgeVariant = computed<'default' | 'secondary' | 'outline'>(() => {
    if (isRack.value) {
        return 'outline';
    }

    const data = props.data as { device_type?: string | null };
    const deviceType = data.device_type?.toLowerCase() || '';

    if (deviceType.includes('switch') || deviceType.includes('router')) {
        return 'default';
    }
    if (deviceType.includes('server')) {
        return 'secondary';
    }
    return 'outline';
});

/**
 * Get the badge label
 */
const badgeLabel = computed(() => {
    if (isRack.value) {
        return 'Rack';
    }
    const data = props.data as { device_type?: string | null };
    return data.device_type || 'Device';
});

/**
 * Get subtitle for the node
 */
const subtitle = computed(() => {
    if (isRack.value) {
        const data = props.data as {
            row_name?: string | null;
            room_name?: string | null;
        };
        const parts = [];
        if (data.row_name) parts.push(data.row_name);
        if (data.room_name) parts.push(data.room_name);
        return parts.join(' / ') || null;
    }
    return null;
});

/**
 * Container classes based on selection state with full dark mode support
 */
const containerClasses = computed(() => {
    return [
        // Base styles
        'rounded-lg border-2 p-3 shadow-md transition-all duration-200',
        // Light mode background
        'bg-card',
        // Dark mode background with slightly higher contrast
        'dark:bg-slate-800/95',
        // Border styling
        'dark:border-slate-600',
        // Hover states
        'hover:shadow-lg hover:border-primary/50',
        'dark:hover:border-sky-400/50 dark:hover:shadow-sky-500/10',
        // Selection state
        props.data.selected
            ? 'border-primary ring-2 ring-primary/30 shadow-lg dark:border-sky-400 dark:ring-sky-400/30'
            : 'border-border dark:border-slate-600',
        // Hover state (non-selected)
        props.data.hovered && !props.data.selected
            ? 'border-primary/50 dark:border-sky-400/50'
            : '',
        // Expanded state (device nodes only)
        props.data.expanded ? 'min-w-[280px]' : '',
        // Rack node styling
        isRack.value ? 'min-w-[180px]' : '',
    ].join(' ');
});

/**
 * Check if device has any ports (device nodes only)
 */
const hasPorts = computed(() => {
    if (!isDevice.value) return false;
    const data = props.data as { port_count?: number };
    return (data.port_count || 0) > 0;
});

/**
 * Get device count for rack nodes
 */
const deviceCount = computed(() => {
    if (!isRack.value) return 0;
    const data = props.data as { device_count?: number };
    return data.device_count || 0;
});

/**
 * Get port count for device nodes
 */
const portCount = computed(() => {
    if (!isDevice.value) return 0;
    const data = props.data as { port_count?: number };
    return data.port_count || 0;
});

function handleClick() {
    emit('nodeClick', props.data.id);
}

function handleToggleExpand(event: Event) {
    event.stopPropagation();
    emit('toggleExpand', props.data.id);
}

function handlePortClick(port: PortDrillDownData, event: Event) {
    event.stopPropagation();
    emit('portClick', port);
}

function handleConnectionClick(connectionId: number, event: Event) {
    event.stopPropagation();
    emit('connectionClick', connectionId);
}
</script>

<template>
    <TooltipProvider>
        <Tooltip>
            <TooltipTrigger as-child>
                <div :class="containerClasses" @click="handleClick">
                    <!-- Connection handles with dark mode support -->
                    <Handle
                        type="source"
                        :position="Position.Right"
                        class="!border-primary-foreground !bg-primary dark:!border-slate-800 dark:!bg-sky-400"
                    />
                    <Handle
                        type="target"
                        :position="Position.Left"
                        class="!border-primary-foreground !bg-primary dark:!border-slate-800 dark:!bg-sky-400"
                    />

                    <!-- Node content -->
                    <div class="flex min-w-[120px] items-center gap-3">
                        <!-- Icon with dark mode background -->
                        <div
                            :class="[
                                'flex size-10 shrink-0 items-center justify-center rounded-md',
                                isRack
                                    ? 'bg-purple-100 dark:bg-purple-900/40'
                                    : 'bg-muted dark:bg-slate-700/80',
                            ]"
                        >
                            <component
                                :is="iconComponent"
                                :class="[
                                    'size-5',
                                    isRack
                                        ? 'text-purple-600 dark:text-purple-400'
                                        : 'text-muted-foreground dark:text-slate-300',
                                ]"
                            />
                        </div>

                        <!-- Node info -->
                        <div class="flex flex-1 flex-col gap-1 overflow-hidden">
                            <span
                                class="max-w-[150px] truncate text-sm leading-none font-medium text-foreground dark:text-slate-100"
                            >
                                {{ data.name }}
                            </span>
                            <div class="flex flex-col gap-1">
                                <Badge
                                    :variant="badgeVariant"
                                    :class="[
                                        'w-fit px-1.5 py-0 text-[10px]',
                                        isRack
                                            ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300'
                                            : '',
                                    ]"
                                >
                                    {{ badgeLabel }}
                                </Badge>
                                <span
                                    v-if="subtitle"
                                    class="max-w-[140px] truncate text-[10px] text-muted-foreground dark:text-slate-400"
                                >
                                    {{ subtitle }}
                                </span>
                            </div>
                        </div>

                        <!-- Expand/Collapse button (device nodes only) -->
                        <Button
                            v-if="isDevice && hasPorts"
                            variant="ghost"
                            size="sm"
                            class="size-7 shrink-0 p-0 dark:hover:bg-slate-600/50"
                            @click="handleToggleExpand"
                        >
                            <Loader2
                                v-if="isLoadingPorts"
                                class="size-4 animate-spin"
                            />
                            <ChevronUp
                                v-else-if="data.expanded"
                                class="size-4 dark:text-slate-300"
                            />
                            <ChevronDown
                                v-else
                                class="size-4 dark:text-slate-300"
                            />
                        </Button>
                    </div>

                    <!-- Stats row for rack nodes -->
                    <div
                        v-if="isRack"
                        class="mt-2 flex items-center gap-3 border-t border-border pt-2 text-xs text-muted-foreground dark:border-slate-600 dark:text-slate-400"
                    >
                        <div class="flex items-center gap-1">
                            <Server class="size-3" />
                            <span>{{ deviceCount }} devices</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <Cable class="size-3" />
                            <span>{{ data.connection_count }} connections</span>
                        </div>
                    </div>

                    <!-- Expanded port list with dark mode styling (device nodes only) -->
                    <Collapsible
                        v-if="isDevice"
                        :open="data.expanded && !!data.ports?.length"
                    >
                        <CollapsibleContent>
                            <div
                                class="mt-3 space-y-1.5 border-t border-border pt-3 dark:border-slate-600"
                            >
                                <div
                                    class="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground dark:text-slate-400"
                                >
                                    <Cable class="size-3" />
                                    Ports ({{ data.ports?.length || 0 }})
                                </div>
                                <div
                                    v-for="port in data.ports"
                                    :key="port.id"
                                    class="relative flex cursor-pointer items-center gap-2 rounded p-1.5 text-xs hover:bg-muted/50 dark:hover:bg-slate-700/50"
                                    @click="handlePortClick(port, $event)"
                                >
                                    <!-- Port handle for edge connections -->
                                    <Handle
                                        v-if="port.connection"
                                        type="source"
                                        :id="`port-${port.id}`"
                                        :position="Position.Right"
                                        class="!right-0 !size-2 !border-primary-foreground !bg-primary/70 dark:!border-slate-800 dark:!bg-sky-400/70"
                                    />

                                    <!-- Port status indicator with accessible colors -->
                                    <div
                                        :class="[
                                            'size-2 shrink-0 rounded-full',
                                            getPortStatusColor(port.status),
                                        ]"
                                    />

                                    <!-- Port label -->
                                    <span
                                        class="flex-1 truncate font-medium text-foreground dark:text-slate-200"
                                    >
                                        {{ port.label }}
                                    </span>

                                    <!-- Port type badge -->
                                    <Badge
                                        :variant="
                                            getPortStatusBadgeVariant(
                                                port.status,
                                            )
                                        "
                                        class="px-1 py-0 text-[9px]"
                                    >
                                        {{ port.status_label || port.status }}
                                    </Badge>

                                    <!-- Connection info -->
                                    <div
                                        v-if="port.connection"
                                        class="flex items-center gap-1 text-muted-foreground hover:text-foreground dark:text-slate-400 dark:hover:text-slate-200"
                                        @click="
                                            handleConnectionClick(
                                                port.connection.id,
                                                $event,
                                            )
                                        "
                                    >
                                        <Cable class="size-3" />
                                        <span class="max-w-[80px] truncate">
                                            {{
                                                port.connection.remote_device
                                                    ?.name || 'Unknown'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>

                    <!-- Connection count indicator with dark mode styling -->
                    <div
                        v-if="data.connection_count > 0 && !data.expanded"
                        :class="[
                            'absolute -top-2 -right-2 flex size-5 items-center justify-center rounded-full text-[10px] font-medium',
                            isRack
                                ? 'bg-purple-500 text-white dark:bg-purple-500 dark:text-white'
                                : 'bg-primary text-primary-foreground dark:bg-sky-500 dark:text-slate-900',
                        ]"
                    >
                        {{ data.connection_count }}
                    </div>
                </div>
            </TooltipTrigger>
            <TooltipContent
                side="bottom"
                class="max-w-[250px] dark:border-slate-600 dark:bg-slate-800"
            >
                <div class="space-y-2">
                    <div class="font-medium dark:text-slate-100">
                        {{ data.name }}
                    </div>
                    <div
                        class="space-y-1 text-xs text-muted-foreground dark:text-slate-400"
                    >
                        <!-- Rack-specific tooltip content -->
                        <template v-if="isRack">
                            <div v-if="(data as any).row_name">
                                <span class="font-medium dark:text-slate-300"
                                    >Row:</span
                                >
                                {{ (data as any).row_name }}
                            </div>
                            <div v-if="(data as any).room_name">
                                <span class="font-medium dark:text-slate-300"
                                    >Room:</span
                                >
                                {{ (data as any).room_name }}
                            </div>
                            <div v-if="(data as any).datacenter_name">
                                <span class="font-medium dark:text-slate-300"
                                    >Datacenter:</span
                                >
                                {{ (data as any).datacenter_name }}
                            </div>
                            <div v-if="(data as any).u_height">
                                <span class="font-medium dark:text-slate-300"
                                    >Height:</span
                                >
                                {{ (data as any).u_height }}U
                            </div>
                            <div>
                                <span class="font-medium dark:text-slate-300"
                                    >Devices:</span
                                >
                                {{ deviceCount }}
                            </div>
                            <div>
                                <span class="font-medium dark:text-slate-300"
                                    >Connections:</span
                                >
                                {{ data.connection_count }}
                            </div>
                            <div
                                class="mt-2 text-xs text-primary dark:text-sky-400"
                            >
                                Select a rack to see device connections
                            </div>
                        </template>

                        <!-- Device-specific tooltip content -->
                        <template v-else>
                            <div v-if="(data as any).asset_tag">
                                <span class="font-medium dark:text-slate-300"
                                    >Asset Tag:</span
                                >
                                {{ (data as any).asset_tag }}
                            </div>
                            <div v-if="(data as any).device_type">
                                <span class="font-medium dark:text-slate-300"
                                    >Type:</span
                                >
                                {{ (data as any).device_type }}
                            </div>
                            <div>
                                <span class="font-medium dark:text-slate-300"
                                    >Ports:</span
                                >
                                {{ portCount }}
                            </div>
                            <div>
                                <span class="font-medium dark:text-slate-300"
                                    >Connections:</span
                                >
                                {{ data.connection_count }}
                            </div>
                            <div
                                v-if="!data.expanded && hasPorts"
                                class="mt-2 text-xs text-primary dark:text-sky-400"
                            >
                                Click expand to view ports
                            </div>
                        </template>
                    </div>
                </div>
            </TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>

<style scoped>
/* Custom handle styling */
:deep(.vue-flow__handle) {
    width: 10px;
    height: 10px;
}

:deep(.vue-flow__handle-right) {
    right: -5px;
}

:deep(.vue-flow__handle-left) {
    left: -5px;
}
</style>

<script setup lang="ts">
/**
 * WarrantyStatusCards Component
 *
 * Displays 4 metric cards showing warranty status distribution:
 * - Active (green): Devices with active warranties
 * - Expiring Soon (amber): Devices with warranties expiring within 30 days
 * - Expired (red): Devices with expired warranties
 * - Unknown (gray): Devices without warranty tracking
 */

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertTriangle, HelpCircle, Shield, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface WarrantyStatus {
    active: number;
    expiring_soon: number;
    expired: number;
    unknown: number;
}

interface Props {
    warrantyStatus: WarrantyStatus;
}

const props = defineProps<Props>();

// Calculate total devices
const totalDevices = computed(() => {
    return (
        props.warrantyStatus.active +
        props.warrantyStatus.expiring_soon +
        props.warrantyStatus.expired +
        props.warrantyStatus.unknown
    );
});

// Calculate percentage for each category
const getPercentage = (count: number): string => {
    if (totalDevices.value === 0) return '0';
    return ((count / totalDevices.value) * 100).toFixed(1);
};

// Card configuration
const cards = computed(() => [
    {
        title: 'Active',
        count: props.warrantyStatus.active,
        percentage: getPercentage(props.warrantyStatus.active),
        icon: Shield,
        iconClass: 'text-green-600 dark:text-green-400',
        bgClass: 'bg-green-50 dark:bg-green-900/20',
        borderClass: 'border-green-200 dark:border-green-800',
        countClass: 'text-green-700 dark:text-green-300',
        description: 'warranty valid',
    },
    {
        title: 'Expiring Soon',
        count: props.warrantyStatus.expiring_soon,
        percentage: getPercentage(props.warrantyStatus.expiring_soon),
        icon: AlertTriangle,
        iconClass: 'text-amber-600 dark:text-amber-400',
        bgClass: 'bg-amber-50 dark:bg-amber-900/20',
        borderClass: 'border-amber-200 dark:border-amber-800',
        countClass: 'text-amber-700 dark:text-amber-300',
        description: 'within 30 days',
        highlight: true,
    },
    {
        title: 'Expired',
        count: props.warrantyStatus.expired,
        percentage: getPercentage(props.warrantyStatus.expired),
        icon: XCircle,
        iconClass: 'text-red-600 dark:text-red-400',
        bgClass: 'bg-red-50 dark:bg-red-900/20',
        borderClass: 'border-red-200 dark:border-red-800',
        countClass: 'text-red-700 dark:text-red-300',
        description: 'warranty ended',
    },
    {
        title: 'Unknown',
        count: props.warrantyStatus.unknown,
        percentage: getPercentage(props.warrantyStatus.unknown),
        icon: HelpCircle,
        iconClass: 'text-gray-500 dark:text-gray-400',
        bgClass: 'bg-gray-50 dark:bg-gray-900/20',
        borderClass: 'border-gray-200 dark:border-gray-700',
        countClass: 'text-gray-600 dark:text-gray-300',
        description: 'not tracked',
    },
]);
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card
            v-for="card in cards"
            :key="card.title"
            :class="[
                'relative overflow-hidden transition-all duration-200 hover:shadow-md',
                card.borderClass,
                card.highlight
                    ? 'ring-2 ring-amber-300 dark:ring-amber-600'
                    : '',
            ]"
        >
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0 pb-2"
            >
                <CardTitle class="text-sm font-medium text-muted-foreground">
                    {{ card.title }}
                </CardTitle>
                <div :class="['rounded-full p-2', card.bgClass]">
                    <component
                        :is="card.icon"
                        :class="['size-4', card.iconClass]"
                    />
                </div>
            </CardHeader>
            <CardContent>
                <div class="flex items-baseline gap-2">
                    <span :class="['text-3xl font-bold', card.countClass]">
                        {{ card.count }}
                    </span>
                    <span class="text-sm text-muted-foreground">
                        ({{ card.percentage }}%)
                    </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ card.description }}
                </p>

                <!-- Highlight indicator for expiring soon -->
                <div
                    v-if="card.highlight && card.count > 0"
                    class="absolute top-0 right-0 rounded-bl-lg bg-amber-500 px-2 py-0.5 text-xs font-medium text-white"
                >
                    Action Needed
                </div>
            </CardContent>
        </Card>
    </div>
</template>

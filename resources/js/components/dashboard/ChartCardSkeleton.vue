<script setup lang="ts">
/**
 * ChartCardSkeleton Component
 *
 * Pulsing skeleton for chart loading states.
 * Matches Card component dimensions and follows existing skeleton patterns.
 */

import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <Card class="relative">
        <CardHeader class="pb-2">
            <div class="flex items-center justify-between">
                <!-- Title skeleton -->
                <Skeleton class="h-4 w-32" />
                <!-- Value skeleton -->
                <Skeleton class="h-6 w-16" />
            </div>
        </CardHeader>
        <CardContent>
            <!-- Chart area skeleton -->
            <div class="h-48 space-y-3">
                <!-- Simulate chart bars/lines -->
                <div class="flex h-full items-end gap-2">
                    <Skeleton class="h-3/4 w-full rounded" />
                    <Skeleton class="h-1/2 w-full rounded" />
                    <Skeleton class="h-2/3 w-full rounded" />
                    <Skeleton class="h-5/6 w-full rounded" />
                    <Skeleton class="h-1/3 w-full rounded" />
                    <Skeleton class="h-2/5 w-full rounded" />
                    <Skeleton class="h-4/5 w-full rounded" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>

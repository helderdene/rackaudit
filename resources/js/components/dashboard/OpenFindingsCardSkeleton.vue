<script setup lang="ts">
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <Card class="relative">
        <CardHeader class="pb-2">
            <!-- Title skeleton -->
            <Skeleton class="h-4 w-24" />
        </CardHeader>
        <CardContent>
            <div class="flex items-start justify-between gap-4">
                <!-- Left side: metric value and trend -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2">
                        <!-- Value skeleton -->
                        <Skeleton class="h-9 w-16" />
                        <!-- Trend skeleton -->
                        <Skeleton class="h-4 w-10" />
                    </div>
                    <!-- Change text skeleton -->
                    <Skeleton class="mt-2 h-3 w-20" />
                </div>
                <!-- Right side: sparkline skeleton -->
                <div class="shrink-0">
                    <Skeleton class="h-[30px] w-[80px]" />
                </div>
            </div>
            <!-- Severity breakdown skeleton badges -->
            <div class="mt-3 flex flex-wrap gap-2">
                <Skeleton class="h-5 w-16 rounded-full" />
                <Skeleton class="h-5 w-14 rounded-full" />
                <Skeleton class="h-5 w-16 rounded-full" />
                <Skeleton class="h-5 w-12 rounded-full" />
            </div>
        </CardContent>
    </Card>
</template>

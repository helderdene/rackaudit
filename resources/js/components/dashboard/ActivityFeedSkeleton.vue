<script setup lang="ts">
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <Card>
        <CardHeader>
            <!-- Title skeleton -->
            <Skeleton class="h-5 w-32" />
        </CardHeader>
        <CardContent>
            <div class="space-y-1">
                <!-- Activity row skeletons - show 5 rows to match typical feed content -->
                <div v-for="i in 5" :key="i" class="rounded-md px-3 py-2">
                    <div
                        class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <!-- Left side: User, action, entity -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- User name skeleton -->
                            <Skeleton class="h-4 w-20" />
                            <!-- Action badge skeleton -->
                            <Skeleton class="h-5 w-16 rounded-full" />
                            <!-- Entity type skeleton -->
                            <Skeleton class="h-4 w-12" />
                        </div>
                        <!-- Right side: Timestamp skeleton -->
                        <Skeleton class="h-3 w-16" />
                    </div>
                    <!-- Summary skeleton -->
                    <Skeleton class="mt-2 h-3 w-3/4" />
                </div>
            </div>
        </CardContent>
    </Card>
</template>

<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    CheckCircle,
    XCircle,
    AlertCircle,
    ArrowRightLeft,
    AlertTriangle,
    Clock,
} from 'lucide-vue-next';
import type { ComparisonStatistics } from '@/types/comparison';

interface Props {
    statistics: ComparisonStatistics;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-4 rounded-lg border bg-muted/30 p-4">
        <!-- Total -->
        <div class="flex items-center gap-2">
            <span class="text-sm font-medium">Total:</span>
            <Badge variant="secondary">{{ statistics.total }}</Badge>
        </div>

        <!-- Matched -->
        <div class="flex items-center gap-2">
            <CheckCircle class="size-4 text-green-600" />
            <span class="text-sm">Matched:</span>
            <Badge variant="outline" class="border-green-500 text-green-700 dark:text-green-400">
                {{ statistics.matched }}
            </Badge>
        </div>

        <!-- Missing -->
        <div class="flex items-center gap-2">
            <XCircle class="size-4 text-red-600" />
            <span class="text-sm">Missing:</span>
            <Badge variant="outline" class="border-red-500 text-red-700 dark:text-red-400">
                {{ statistics.missing }}
            </Badge>
        </div>

        <!-- Unexpected -->
        <div class="flex items-center gap-2">
            <AlertCircle class="size-4 text-orange-600" />
            <span class="text-sm">Unexpected:</span>
            <Badge variant="outline" class="border-orange-500 text-orange-700 dark:text-orange-400">
                {{ statistics.unexpected }}
            </Badge>
        </div>

        <!-- Mismatched -->
        <div class="flex items-center gap-2">
            <ArrowRightLeft class="size-4 text-amber-600" />
            <span class="text-sm">Mismatched:</span>
            <Badge variant="outline" class="border-amber-500 text-amber-700 dark:text-amber-400">
                {{ statistics.mismatched }}
            </Badge>
        </div>

        <!-- Conflicting -->
        <div class="flex items-center gap-2">
            <AlertTriangle class="size-4 text-purple-600" />
            <span class="text-sm">Conflicting:</span>
            <Badge variant="outline" class="border-purple-500 text-purple-700 dark:text-purple-400">
                {{ statistics.conflicting }}
            </Badge>
        </div>

        <!-- Acknowledged (right-aligned) -->
        <div class="ml-auto flex items-center gap-2">
            <Clock class="size-4 text-gray-500" />
            <span class="text-sm">Acknowledged:</span>
            <Badge variant="secondary">
                {{ statistics.acknowledged }}
            </Badge>
        </div>
    </div>
</template>

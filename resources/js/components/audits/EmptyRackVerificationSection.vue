<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { CheckCircle, Server, PackageX } from 'lucide-vue-next';

interface RackData {
    id: number;
    name: string;
}

interface RoomData {
    id: number;
    name: string;
}

interface EmptyRackVerification {
    id: number;
    rack: RackData;
    room: RoomData | null;
    verified: boolean;
    notes: string | null;
    verified_by: {
        id: number;
        name: string;
    } | null;
    verified_at: string | null;
}

interface Props {
    emptyRacks: EmptyRackVerification[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'verify-empty-rack', rackId: number): Promise<void>;
}>();

// Loading state per rack
const loadingRacks = ref<Set<number>>(new Set());

/**
 * Verify an empty rack
 */
async function handleVerify(rackId: number): Promise<void> {
    loadingRacks.value.add(rackId);

    try {
        await emit('verify-empty-rack', rackId);
    } finally {
        loadingRacks.value.delete(rackId);
    }
}

/**
 * Check if a rack verification is loading
 */
function isLoading(rackId: number): boolean {
    return loadingRacks.value.has(rackId);
}

/**
 * Format verified at date
 */
function formatDate(dateString: string | null): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Card v-if="emptyRacks.length > 0">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-lg">
                <PackageX class="size-5" />
                Empty Racks Confirmation
            </CardTitle>
        </CardHeader>
        <CardContent>
            <p class="mb-4 text-sm text-muted-foreground">
                The following racks have no documented devices. Please confirm they are empty.
            </p>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="rackVerification in emptyRacks"
                    :key="rackVerification.id"
                    class="flex items-center justify-between rounded-lg border p-3 transition-colors"
                    :class="{
                        'bg-green-50/50 dark:bg-green-900/10 border-green-200 dark:border-green-800': rackVerification.verified,
                        'bg-muted/30': !rackVerification.verified,
                    }"
                >
                    <div class="flex items-center gap-3">
                        <Server class="size-4 text-muted-foreground" />
                        <div class="flex flex-col">
                            <span class="font-medium">{{ rackVerification.rack.name }}</span>
                            <span v-if="rackVerification.room" class="text-xs text-muted-foreground">
                                {{ rackVerification.room.name }}
                            </span>
                        </div>
                    </div>

                    <div v-if="rackVerification.verified" class="flex flex-col items-end gap-1">
                        <Badge class="bg-green-600">
                            <CheckCircle class="mr-1 size-3" />
                            Confirmed Empty
                        </Badge>
                        <span v-if="rackVerification.verified_by" class="text-xs text-muted-foreground">
                            {{ rackVerification.verified_by.name }} - {{ formatDate(rackVerification.verified_at) }}
                        </span>
                    </div>

                    <Button
                        v-else
                        size="sm"
                        variant="outline"
                        :disabled="isLoading(rackVerification.rack.id)"
                        @click="handleVerify(rackVerification.rack.id)"
                    >
                        <Spinner v-if="isLoading(rackVerification.rack.id)" class="mr-1 size-3" />
                        <CheckCircle v-else class="mr-1 size-3" />
                        Confirm Empty
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

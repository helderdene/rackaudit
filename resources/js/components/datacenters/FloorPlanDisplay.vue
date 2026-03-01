<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ExternalLink, FileImage } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    floorPlanUrl: string | null;
    datacenterName: string;
}

const props = defineProps<Props>();

// Determine if the floor plan is a PDF
const isPdf = computed(() => {
    if (!props.floorPlanUrl) {
        return false;
    }
    return props.floorPlanUrl.toLowerCase().endsWith('.pdf');
});

// Open floor plan in new tab
const openInNewTab = () => {
    if (props.floorPlanUrl) {
        window.open(props.floorPlanUrl, '_blank');
    }
};
</script>

<template>
    <div class="space-y-4">
        <!-- Floor plan exists -->
        <div v-if="floorPlanUrl">
            <!-- Image display -->
            <div v-if="!isPdf" class="space-y-3">
                <div class="overflow-hidden rounded-lg border bg-muted/20">
                    <img
                        :src="floorPlanUrl"
                        :alt="`Floor plan for ${datacenterName}`"
                        class="max-h-[500px] w-full object-contain"
                    />
                </div>
                <div class="flex justify-end">
                    <Button variant="outline" size="sm" @click="openInNewTab">
                        <ExternalLink class="mr-2 size-4" />
                        Open Full Size
                    </Button>
                </div>
            </div>

            <!-- PDF display -->
            <div v-else class="space-y-3">
                <div
                    class="flex flex-col items-center justify-center rounded-lg border bg-muted/20 p-8"
                >
                    <FileImage class="size-16 text-muted-foreground" />
                    <p class="mt-4 font-medium">Floor Plan (PDF)</p>
                    <p class="text-sm text-muted-foreground">
                        Click below to view the floor plan
                    </p>
                    <Button
                        variant="outline"
                        class="mt-4"
                        @click="openInNewTab"
                    >
                        <ExternalLink class="mr-2 size-4" />
                        Open PDF
                    </Button>
                </div>
            </div>
        </div>

        <!-- No floor plan -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center"
        >
            <div class="rounded-full bg-muted p-3">
                <FileImage class="size-6 text-muted-foreground" />
            </div>
            <p class="mt-4 text-sm font-medium text-muted-foreground">
                No floor plan uploaded
            </p>
            <p class="text-xs text-muted-foreground">
                A floor plan can be added by editing this datacenter.
            </p>
        </div>
    </div>
</template>

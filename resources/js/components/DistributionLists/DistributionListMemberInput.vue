<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { GripVertical, Mail, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Member {
    email: string;
}

interface Props {
    modelValue: Member[];
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: Member[]): void;
}>();

const newEmail = ref('');
const inputError = ref('');

// Email validation regex
const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const members = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const validateEmail = (email: string): boolean => {
    if (!email.trim()) {
        inputError.value = 'Email is required';
        return false;
    }
    if (!emailRegex.test(email.trim())) {
        inputError.value = 'Please enter a valid email address';
        return false;
    }
    if (
        members.value.some(
            (m) => m.email.toLowerCase() === email.trim().toLowerCase(),
        )
    ) {
        inputError.value = 'This email is already in the list';
        return false;
    }
    inputError.value = '';
    return true;
};

const addMember = () => {
    const email = newEmail.value.trim();
    if (!validateEmail(email)) {
        return;
    }

    members.value = [...members.value, { email }];
    newEmail.value = '';
    inputError.value = '';
};

const removeMember = (index: number) => {
    const newMembers = [...members.value];
    newMembers.splice(index, 1);
    members.value = newMembers;
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        addMember();
    }
};

const clearInputError = () => {
    if (inputError.value) {
        inputError.value = '';
    }
};

// Get error for a specific member index from props.errors
const getMemberError = (index: number): string | undefined => {
    return props.errors?.[`members.${index}.email`];
};
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-2">
            <Label for="member-email">Add Member Email</Label>
            <div class="flex gap-2">
                <div class="flex-1">
                    <Input
                        id="member-email"
                        v-model="newEmail"
                        type="email"
                        placeholder="Enter email address"
                        :aria-invalid="!!inputError"
                        @keydown="handleKeydown"
                        @input="clearInputError"
                    />
                </div>
                <Button type="button" variant="outline" @click="addMember">
                    <Plus class="mr-2 h-4 w-4" />
                    Add
                </Button>
            </div>
            <InputError :message="inputError" />
        </div>

        <!-- Members list -->
        <div v-if="members.length > 0" class="space-y-2">
            <Label>Members ({{ members.length }})</Label>
            <div class="rounded-md border">
                <ul class="divide-y">
                    <li
                        v-for="(member, index) in members"
                        :key="`member-${index}`"
                        class="flex items-center gap-3 px-3 py-2 transition-colors hover:bg-muted/50"
                    >
                        <GripVertical
                            class="h-4 w-4 shrink-0 cursor-grab text-muted-foreground"
                        />
                        <Mail class="h-4 w-4 shrink-0 text-muted-foreground" />
                        <div class="min-w-0 flex-1">
                            <span class="truncate text-sm">{{
                                member.email
                            }}</span>
                            <InputError
                                v-if="getMemberError(index)"
                                :message="getMemberError(index)"
                                class="mt-1"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="shrink-0 text-destructive hover:bg-destructive/10 hover:text-destructive"
                            title="Remove member"
                            @click="removeMember(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Empty state for members -->
        <div
            v-else
            class="flex flex-col items-center justify-center rounded-lg border border-dashed py-8 text-center"
        >
            <Mail class="h-8 w-8 text-muted-foreground" />
            <p class="mt-2 text-sm text-muted-foreground">
                No members added yet. Enter an email address above to add
                members.
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { LoaderCircle } from 'lucide-vue-next';
import { useAuthStore } from '@/stores';

interface Props {
    status?: string;
}

defineProps<Props>();

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
    email: '',
});

const errors = ref<Record<string, string>>({});
const isProcessing = ref(false);
const successMessage = ref<string | null>(null);

const submit = async () => {
    if (isProcessing.value) return;
    
    errors.value = {};
    successMessage.value = null;
    isProcessing.value = true;
    
    try {
        const response = await authStore.forgotPassword(form.value.email);
        successMessage.value = response.message || 'Password reset link has been sent to your email.';
        form.value.email = ''; // Clear the form after success
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            errors.value.email = error.response.data.message;
        } else {
            errors.value.email = 'An error occurred while sending the password reset link';
        }
    } finally {
        isProcessing.value = false;
    }
};
</script>

<template>
    <AuthLayout title="Forgot password" description="Enter your email to receive a password reset link">
        <div v-if="successMessage" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ successMessage }}
        </div>

        <div class="space-y-6">
            <form @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input 
                        id="email" 
                        type="email" 
                        name="email" 
                        autocomplete="off" 
                        v-model="form.email" 
                        autofocus 
                        placeholder="email@example.com" 
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="my-6 flex items-center justify-start">
                    <Button class="w-full" :disabled="isProcessing">
                        <LoaderCircle v-if="isProcessing" class="h-4 w-4 animate-spin" />
                        Email password reset link
                    </Button>
                </div>
            </form>

            <div class="space-x-1 text-center text-sm text-muted-foreground">
                <span>Or, return to</span>
                <TextLink :to="{ name: 'login' }">log in</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
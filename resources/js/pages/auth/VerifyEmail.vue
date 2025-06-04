<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { LoaderCircle } from 'lucide-vue-next';
import { useAuthStore } from '@/stores';

interface Props {
    status?: string;
}

const props = defineProps<Props>();

const router = useRouter();
const authStore = useAuthStore();

const isProcessing = ref(false);
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const submit = async () => {
    if (isProcessing.value) return;
    
    isProcessing.value = true;
    errorMessage.value = null;
    
    try {
        await authStore.resendVerification();
        successMessage.value = 'A new verification link has been sent to your email address.';
    } catch (error: any) {
        if (error.response?.data?.message) {
            errorMessage.value = error.response.data.message;
        } else {
            errorMessage.value = 'An error occurred while sending the verification email';
        }
    } finally {
        isProcessing.value = false;
    }
};

const logout = async () => {
    try {
        await authStore.logout();
        router.push({ name: 'login' });
    } catch (error) {
        console.error('Logout failed:', error);
    }
};
</script>

<template>
    <AuthLayout title="Verify email" description="Please verify your email address by clicking on the link we just emailed to you.">
        <div v-if="successMessage" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ successMessage }}
        </div>

        <div v-if="errorMessage" class="mb-4 text-center text-sm font-medium text-red-600">
            {{ errorMessage }}
        </div>

        <div class="space-y-6 text-center">
            <Button @click="submit" :disabled="isProcessing" variant="secondary">
                <LoaderCircle v-if="isProcessing" class="h-4 w-4 animate-spin" />
                Resend verification email
            </Button>

            <Button @click="logout" variant="ghost" class="mx-auto block text-sm">
                Log out
            </Button>
        </div>
    </AuthLayout>
</template>
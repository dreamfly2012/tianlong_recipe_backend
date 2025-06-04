<script setup lang="ts">
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { LoaderCircle } from 'lucide-vue-next';
import { useAuthStore } from '@/stores';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

// Get token and email from route query parameters
const token = route.query.token as string;
const email = route.query.email as string;

if (!token || !email) {
    router.push({ name: 'login' });
}

const form = ref({
    token,
    email,
    password: '',
    password_confirmation: '',
});

const errors = ref<Record<string, string>>({});
const isProcessing = ref(false);

const submit = async () => {
    if (isProcessing.value) return;
    
    errors.value = {};
    isProcessing.value = true;
    
    try {
        await authStore.resetPassword(
            form.value.token,
            form.value.email,
            form.value.password,
            form.value.password_confirmation
        );
        router.push({ 
            name: 'login',
            query: { 
                reset: 'success',
                email: form.value.email 
            }
        });
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            errors.value.password = error.response.data.message;
        } else {
            errors.value.password = 'An error occurred while resetting your password';
        }
    } finally {
        isProcessing.value = false;
        form.value.password = '';
        form.value.password_confirmation = '';
    }
};
</script>

<template>
    <AuthLayout title="Reset password" description="Please enter your new password below">
        <form @submit.prevent="submit">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input 
                        id="email" 
                        type="email" 
                        name="email" 
                        autocomplete="email" 
                        v-model="form.email" 
                        class="mt-1 block w-full" 
                        readonly 
                    />
                    <InputError :message="errors.email" class="mt-2" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        v-model="form.password"
                        class="mt-1 block w-full"
                        autofocus
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        class="mt-1 block w-full"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-4 w-full" :disabled="isProcessing">
                    <LoaderCircle v-if="isProcessing" class="h-4 w-4 animate-spin" />
                    Reset password
                </Button>
            </div>
        </form>
    </AuthLayout>
</template>
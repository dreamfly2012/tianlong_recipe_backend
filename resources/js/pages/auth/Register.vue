<script setup lang="ts">
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { LoaderCircle } from 'lucide-vue-next';
import { useAuthStore } from '@/stores';

const router = useRouter();
const authStore = useAuthStore();

const form = ref({
    name: '',
    email: '',
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
        await authStore.register(
            form.value.name,
            form.value.email,
            form.value.password,
            form.value.password_confirmation
        );
        router.push('/dashboard');
    } catch (error: any) {
        if (error.response?.data?.errors) {
            errors.value = error.response.data.errors;
        } else if (error.response?.data?.message) {
            errors.value.email = error.response.data.message;
        } else {
            errors.value.email = 'An error occurred during registration';
        }
    } finally {
        isProcessing.value = false;
        form.value.password = '';
        form.value.password_confirmation = '';
    }
};
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" type="text" required autofocus :tabindex="1" autocomplete="name" v-model="form.name" placeholder="Full name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" required :tabindex="2" autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="isProcessing">
                    <LoaderCircle v-if="isProcessing" class="h-4 w-4 animate-spin" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :to="{ name: 'login' }" class="underline underline-offset-4" :tabindex="6">Log in</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
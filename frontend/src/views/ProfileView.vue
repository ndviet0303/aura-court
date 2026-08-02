<script setup lang="ts">
import { ref } from 'vue'
import { errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore(); const name = ref(auth.user?.name || ''); const error = ref(''); const notice = ref(''); const loading = ref(false)
async function submit() { loading.value = true; error.value = ''; notice.value = ''; try { await auth.updateProfile(name.value); notice.value = 'Đã cập nhật hồ sơ.' } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể cập nhật hồ sơ.') } finally { loading.value = false } }
</script>

<template><section class="card form"><div class="eyebrow">Account</div><h1>Hồ sơ của tôi</h1><p class="muted">{{ auth.user?.email }}</p><form @submit.prevent="submit"><div class="field"><label>Họ tên</label><input v-model="name" required maxlength="120" /></div><p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p><button class="btn" :disabled="loading">{{ loading ? 'Đang lưu…' : 'Lưu thay đổi' }}</button></form></section></template>

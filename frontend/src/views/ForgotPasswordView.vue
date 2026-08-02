<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { errorMessage } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore(); const email = ref(''); const error = ref(''); const notice = ref(''); const loading = ref(false)
async function submit() { loading.value = true; error.value = ''; notice.value = ''; try { await auth.forgotPassword(email.value); notice.value = 'Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi.' } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể gửi yêu cầu.') } finally { loading.value = false } }
</script>

<template><section class="card form"><h1>Quên mật khẩu</h1><p class="muted">Nhập email để nhận liên kết đặt lại mật khẩu.</p><form @submit.prevent="submit"><div class="field"><label>Email</label><input v-model="email" type="email" required /></div><p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p><button class="btn" :disabled="loading">{{ loading ? 'Đang gửi…' : 'Gửi liên kết' }}</button></form><RouterLink to="/login" class="muted" style="display:inline-block;margin-top:18px">Quay lại đăng nhập</RouterLink></section></template>

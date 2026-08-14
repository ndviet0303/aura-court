<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { api, errorMessage } from '@/lib/api'
import type { Membership } from '@/types'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const name = ref(auth.user?.name || '')
const currentPassword = ref('')
const newPassword = ref('')
const passwordConfirmation = ref('')
const membership = ref<Membership | null>(null)
const error = ref('')
const notice = ref('')
const loading = ref(false)
const passwordLoading = ref(false)
const verificationLoading = ref(false)

async function submit() { loading.value = true; error.value = ''; notice.value = ''; try { await auth.updateProfile(name.value); notice.value = 'Đã cập nhật hồ sơ.' } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể cập nhật hồ sơ.') } finally { loading.value = false } }
async function changePassword() { passwordLoading.value = true; error.value = ''; notice.value = ''; try { await auth.changePassword(currentPassword.value, newPassword.value, passwordConfirmation.value); currentPassword.value = ''; newPassword.value = ''; passwordConfirmation.value = ''; notice.value = 'Đã đổi mật khẩu.' } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể đổi mật khẩu.') } finally { passwordLoading.value = false } }
async function resendVerification() { verificationLoading.value = true; error.value = ''; try { await auth.resendVerification(); notice.value = 'Đã gửi lại email xác thực.' } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể gửi email xác thực.') } finally { verificationLoading.value = false } }
onMounted(async () => { try { membership.value = (await api.get<{ membership: Membership | null }>('/membership')).membership } catch { /* profile remains usable when promotion data is unavailable */ } })
</script>

<template>
  <section class="card form"><div class="eyebrow">Tài khoản</div><h1>Hồ sơ của tôi</h1><p class="muted">{{ auth.user?.email }}</p><p v-if="!auth.user?.email_verified_at" class="notice">Email chưa được xác thực. <button class="link-button" :disabled="verificationLoading" @click="resendVerification">{{ verificationLoading ? 'Đang gửi…' : 'Gửi lại email' }}</button></p><div v-if="membership" class="notice"><strong>Hội viên {{ membership.plan.name }}</strong><br />Ưu đãi {{ membership.plan.discount_percent }}% đến {{ new Date(membership.ends_at).toLocaleDateString('vi-VN') }}.</div><form @submit.prevent="submit"><div class="field"><label>Họ tên</label><input v-model="name" required maxlength="120" /></div><p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p><button class="btn" :disabled="loading">{{ loading ? 'Đang lưu…' : 'Lưu thay đổi' }}</button></form><hr /><h2>Đổi mật khẩu</h2><form @submit.prevent="changePassword"><div class="field"><label>Mật khẩu hiện tại</label><input v-model="currentPassword" type="password" minlength="8" required /></div><div class="field"><label>Mật khẩu mới</label><input v-model="newPassword" type="password" minlength="8" required /></div><div class="field"><label>Nhập lại mật khẩu mới</label><input v-model="passwordConfirmation" type="password" minlength="8" required /></div><button class="btn secondary" :disabled="passwordLoading">{{ passwordLoading ? 'Đang lưu…' : 'Đổi mật khẩu' }}</button></form></section>
</template>

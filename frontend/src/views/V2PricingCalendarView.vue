<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { apiV2, errorMessage } from '@/lib/api'

type Rule = { id: number; type: string; price: number; weekday?: number; exception_date?: string; starts_at?: string; ends_at?: string; version: number; is_active: boolean }
const rules = ref<Rule[]>([])
const form = ref({ type: 'base', price: 100000, weekday: 1, starts_at: '08:00', ends_at: '09:00', exception_date: '' })
const error = ref('')
const notice = ref('')
const money = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value)

async function load(): Promise<void> {
  try { rules.value = (await apiV2.get<{ rules: Rule[] }>('/pricing/calendar')).rules || [] }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không tải được lịch giá.') }
}

async function addRule(): Promise<void> {
  try {
    await apiV2.post('/price-rules', { ...form.value, weekday: form.value.type === 'weekly' ? form.value.weekday : null, exception_date: ['date_exception', 'holiday'].includes(form.value.type) ? form.value.exception_date : null, starts_at: form.value.type === 'base' ? null : form.value.starts_at, ends_at: form.value.type === 'base' ? null : form.value.ends_at })
    notice.value = 'Đã thêm quy tắc giá phiên bản mới.'
    await load()
  } catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể thêm quy tắc giá.') }
}

async function deactivate(rule: Rule): Promise<void> {
  try { await apiV2.delete(`/price-rules/${rule.id}`); notice.value = 'Đã tắt quy tắc giá.'; await load() }
  catch (cause: unknown) { error.value = errorMessage(cause, 'Không thể tắt quy tắc giá.') }
}

onMounted(load)
</script>

<template>
  <div class="p-6 md:p-10 max-w-6xl mx-auto w-full">
    <section class="row"><div><div class="eyebrow">API v2 · Owner</div><h1 class="page-title">Lịch giá Nâng cao v2</h1><p class="muted">Date exception → peak → weekly → base. Quy tắc mới không làm thay đổi quote đã chấp nhận.</p></div></section>
    <p v-if="error" class="error">{{ error }}</p><p v-if="notice" class="notice">{{ notice }}</p>
    <section class="card" style="margin-top: 18px"><h2>Thêm quy tắc</h2><form class="row" style="margin-top: 14px; align-items: end" @submit.prevent="addRule"><label class="field">Loại<select v-model="form.type"><option value="base">Base</option><option value="weekly">Weekly</option><option value="peak">Peak</option><option value="date_exception">Date exception</option></select></label><label class="field">Giá<input v-model.number="form.price" type="number" min="0" /></label><label v-if="form.type === 'weekly'" class="field">Thứ<input v-model.number="form.weekday" type="number" min="1" max="7" /></label><label v-if="['date_exception', 'holiday'].includes(form.type)" class="field">Ngày<input v-model="form.exception_date" type="date" /></label><label v-if="form.type !== 'base'" class="field">Bắt đầu<input v-model="form.starts_at" type="time" /></label><label v-if="form.type !== 'base'" class="field">Kết thúc<input v-model="form.ends_at" type="time" /></label><button class="btn">Thêm quy tắc</button></form></section>
    <section class="card" style="margin-top: 18px"><h2>Quy tắc đang áp dụng</h2><div v-for="rule in rules" :key="rule.id" class="row" style="padding: 14px 0; border-bottom: 1px solid var(--line)"><div><strong>{{ rule.type }}</strong><p class="muted">v{{ rule.version }} · {{ rule.starts_at || 'cả ngày' }}<span v-if="rule.ends_at">–{{ rule.ends_at }}</span></p></div><div class="actions"><strong>{{ money(rule.price) }}</strong><button class="link-button danger" @click="deactivate(rule)">Tắt</button></div></div></section>
  </div>
</template>

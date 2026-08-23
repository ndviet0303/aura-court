<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { errorMessage } from '@/lib/api'
import { useBookingStore } from '@/stores/booking'
import { formatCurrency } from '@/i18n'
import AuraLoading from '@/components/AuraLoading.vue'

const { t } = useI18n()
const store = useBookingStore()
const route = useRoute()
const payment = computed(() => store.payment)
const status = ref('pending_payment')
const error = ref('')
const demoLoading = ref(false)
const demoMode = import.meta.env.DEV
let timer: number | undefined

const statusLabel = computed(() => t(`common.status.${status.value}`, status.value))

async function demoPay() {
  demoLoading.value = true
  error.value = ''
  try {
    const result = await store.demoPay()
    status.value = result.status
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  } finally {
    demoLoading.value = false
  }
}

async function poll() {
  try {
    const result = await store.refreshPaymentStatus()
    if (result) {
      status.value = result.status
      if (['confirmed', 'expired', 'cancelled', 'refunded'].includes(result.status) && timer) window.clearInterval(timer)
    }
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
}

onMounted(async () => {
  try {
    if (route.params.code) {
      const result = await store.loadPayment(String(route.params.code))
      status.value = result.status
    } else {
      store.restorePayment()
    }
    await poll()
    if (status.value === 'pending_payment') timer = window.setInterval(poll, 5000)
  } catch (cause: unknown) {
    error.value = errorMessage(cause, t('common.error'))
  }
})
onUnmounted(() => { if (timer) window.clearInterval(timer) })
</script>

<template>
  <section class="page-shell">
    <div class="eyebrow">{{ t('payment.eyebrow') }}</div>
    <h1 class="page-title">{{ t('payment.title') }}</h1>
    <div v-if="payment" class="panel payment">
      <div class="demo-payment-warning" role="alert">
        <strong>{{ t('payment.demoWarningTitle') }}</strong>
        <span>{{ t('payment.demoWarningDesc') }}</span>
      </div>
      <span class="badge" :class="{ 'badge-success': status === 'confirmed', 'badge-danger': ['expired','cancelled'].includes(status) }">
        {{ statusLabel }}
      </span>
      <h2>{{ formatCurrency(payment.amount) }}</h2>
      <p class="muted">
        {{ status === 'confirmed' ? t('payment.confirmedDesc') : t('payment.pendingDesc') }}
      </p>
      <img :src="payment.qrUrl" :alt="t('payment.title')" />
      <div class="panel" style="text-align:left;background:#f8f9fa">
        <div class="row">
          <span class="muted">{{ t('payment.bank') }}</span>
          <strong>{{ payment.bank }}</strong>
        </div>
        <div class="row">
          <span class="muted">{{ t('payment.accountNumber') }}</span>
          <strong>{{ payment.accountNumber || t('payment.notConfigured') }}</strong>
        </div>
        <div class="row">
          <span class="muted">{{ t('payment.transferContent') }}</span>
          <strong>{{ payment.code }}</strong>
        </div>
      </div>
      <button v-if="demoMode && status === 'pending_payment'" class="btn secondary" style="margin-top:18px; display:inline-flex; align-items:center; justify-content:center; gap:8px;" :disabled="demoLoading" @click="demoPay">
        <AuraLoading v-if="demoLoading" size="sm" :showLogo="false" style="padding:0" />
        <span>{{ demoLoading ? t('payment.demoProcessing') : t('payment.demoPayBtn') }}</span>
      </button>
      <p v-if="demoMode && status === 'pending_payment'" class="muted" style="margin-top:8px">
        {{ t('payment.demoOnlyNote') }}
      </p>
      <p v-if="error" class="error">{{ error }}</p>
      <RouterLink to="/booking" class="btn" style="display:inline-block;margin-top:18px">
        {{ t('payment.viewBookings') }}
      </RouterLink>
    </div>
    <div v-else class="panel payment">
      <h2>{{ t('payment.noPaymentSession') }}</h2>
      <p class="muted">{{ t('payment.noPaymentDesc') }}</p>
      <RouterLink to="/courts" class="btn">{{ t('payment.chooseCourt') }}</RouterLink>
    </div>
  </section>
</template>

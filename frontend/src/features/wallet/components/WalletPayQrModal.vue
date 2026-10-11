<script setup lang="ts">
import { ref } from 'vue'
import type { VenueWallet } from '../composables/useWallet'

const props = defineProps<{
  show: boolean
  wallet: VenueWallet | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'copy', text: string, label: string): void
}>()

const refreshing = ref(false)
const refreshKey = ref(Date.now())

function refreshQr() {
  refreshing.value = true
  setTimeout(() => {
    refreshKey.value = Date.now()
    refreshing.value = false
  }, 400)
}

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div v-if="show && wallet" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-card modal-sm">
      <div class="modal-header">
        <div class="modal-title-box">
          <span class="material-symbols-outlined header-icon">qr_code_2</span>
          <h3 class="modal-title">{{ $t('wallet.modalQrTitle') }}</h3>
        </div>
        <button class="modal-close-btn" :aria-label="$t('common.close')" @click="emit('close')">
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body qr-modal-body">
        <div class="qr-club-badge">
          <span class="material-symbols-outlined badge-icon">verified</span>
          <span>{{ wallet.venue_name }}</span>
        </div>

        <p class="qr-sub">
          {{ $t('wallet.modalQrDesc') }}
        </p>

        <!-- QR Code Container -->
        <div class="qr-card-box">
          <img
            :src="`https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=AURA-PASS-${wallet.id}-${wallet.card_number}-${refreshKey}`"
            alt="Aura Member QR Pass"
            class="qr-img"
          />
        </div>

        <!-- Member Card Details Strip -->
        <div class="member-id-strip">
          <div class="card-num-box">
            <span class="label">{{ $t('wallet.cardNumber') }}</span>
            <strong class="val font-mono">{{ wallet.card_number }}</strong>
          </div>
          <button
            type="button"
            class="btn-copy-code"
            :title="$t('payment.copy')"
            @click="emit('copy', wallet.card_number, $t('wallet.copyCardSuccess'))"
          >
            <span class="material-symbols-outlined">content_copy</span>
          </button>
        </div>

        <!-- Balance Info -->
        <div class="wallet-balance-pill">
          <span class="bal-label">{{ $t('wallet.availableBalance') }}:</span>
          <strong class="bal-val text-primary font-mono">{{ formatMoney(wallet.balance) }}</strong>
        </div>

        <!-- Refresh Action -->
        <button type="button" class="btn-refresh-qr" :disabled="refreshing" @click="refreshQr">
          <span class="material-symbols-outlined" :class="{ spinning: refreshing }">refresh</span>
          <span>{{ $t('common.theme.toggle') ? $t('wallet.refreshBtn') : 'Refresh' }}</span>
        </button>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline full-width" @click="emit('close')">
          <span>{{ $t('common.close') }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-title-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.header-icon {
  font-size: 22px;
  color: var(--accent, #c9a15a);
}

.qr-modal-body {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  text-align: center;
}

.qr-club-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 12px;
  border-radius: 999px;
  background: var(--accent-container, rgba(201, 161, 90, 0.1));
  color: var(--on-accent-container, #684f17);
  font-size: 12px;
  font-weight: 700;
  border: 1px solid rgba(201, 161, 90, 0.3);
}

:global(html.dark) .qr-club-badge {
  color: var(--accent, #d8b46e);
  background: rgba(216, 180, 110, 0.15);
}

.badge-icon {
  font-size: 15px;
  color: var(--accent, #c9a15a);
}

.qr-sub {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
  line-height: 1.4;
  max-width: 320px;
}

.qr-card-box {
  width: 210px;
  height: 210px;
  padding: 12px;
  border-radius: 16px;
  border: 1px solid var(--border);
  background: #ffffff;
  box-shadow: var(--shadow-sm);
  display: flex;
  align-items: center;
  justify-content: center;
}

.qr-img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.member-id-strip {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 8px 14px;
  border-radius: 10px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border);
}

:global(html.dark) .member-id-strip {
  background: var(--surface-subtle, #262024);
}

.card-num-box {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
}

.card-num-box .label {
  font-size: 10.5px;
  color: var(--muted);
}

.card-num-box .val {
  font-size: 13px;
  color: var(--ink);
  letter-spacing: 0.05em;
}

.btn-copy-code {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--accent, #c9a15a);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-copy-code:hover {
  background: var(--accent-container);
}

.btn-copy-code .material-symbols-outlined {
  font-size: 15px;
}

.wallet-balance-pill {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border);
}

:global(html.dark) .wallet-balance-pill {
  background: var(--surface-subtle, #262024);
}

.bal-label {
  font-size: 12px;
  color: var(--muted);
}

.bal-val {
  font-size: 16px;
}

.text-primary {
  color: var(--primary, #6b2d3f);
}

:global(html.dark) .text-primary {
  color: var(--primary, #e58e9f);
}

.btn-refresh-qr {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: transparent;
  border: 0;
  color: var(--muted);
  font-size: 12px;
  cursor: pointer;
  padding: 4px 8px;
  transition: color 0.15s ease;
}

.btn-refresh-qr:hover {
  color: var(--ink);
}

.btn-refresh-qr .material-symbols-outlined {
  font-size: 15px;
}

.spinning {
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  100% {
    transform: rotate(360deg);
  }
}

.full-width {
  width: 100%;
}
</style>

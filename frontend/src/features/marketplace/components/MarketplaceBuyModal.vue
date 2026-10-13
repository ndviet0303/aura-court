<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import type { ResaleListing } from '../types/marketplace'
import { useResaleLock } from '../composables/useResaleLock'
import ResalePaymentDetails from './ResalePaymentDetails.vue'

const props = defineProps<{
  listing: ResaleListing | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'changed'): void
}>()

const { t } = useI18n()
const lock = useResaleLock(() => emit('changed'))
const { state, phase, remainingSeconds, busy, error, disputeOpened } = lock
const acceptFree = ref(false)
const isFree = computed(() => props.listing?.resalePrice === 0)
const countdown = computed(() => {
  const s = remainingSeconds.value
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
})

watch(
  () => props.listing?.id,
  (id) => {
    acceptFree.value = false
    if (id) void lock.open(id)
    else lock.close()
  },
  { immediate: true },
)

const unresolvedKey = computed(() =>
  state.value?.last_release?.by === 'seller' ? 'sellerNotReceived' : 'lapsed',
)

function close() {
  lock.close()
  emit('close')
}
</script>

<template>
  <div v-if="listing" class="modal-backdrop" @click.self="close">
    <div class="modal-card" role="dialog" aria-modal="true" data-testid="resale-buy-modal">
      <div class="modal-header">
        <div class="modal-title-wrap">
          <span class="material-symbols-outlined modal-icon">swap_horiz</span>
          <h3 class="modal-title">{{ t('resale.p2p.modalTitle') }}</h3>
        </div>
        <button
          type="button"
          class="modal-close-btn"
          :aria-label="t('resale.p2p.close')"
          @click="close"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="target-card">
          <strong class="target-court">{{ listing.courtName }}</strong>
          <div class="target-details">
            <span class="detail-item">
              <span class="material-symbols-outlined icon-14">location_on</span>
              <span>{{ listing.venueName }}</span>
            </span>
            <span class="detail-item">
              <span class="material-symbols-outlined icon-14">calendar_today</span>
              <span>{{ listing.dateStr }}</span>
            </span>
            <span class="detail-item highlight">
              <span class="material-symbols-outlined icon-14">schedule</span>
              <span class="font-mono">{{ listing.timeSlot }}</span>
            </span>
          </div>
        </div>

        <div class="price-breakdown">
          <div class="p-row">
            <span>{{ t('resale.p2p.originalPrice') }}</span>
            <span class="font-mono">{{ formatCurrency(listing.originalPrice) }}</span>
          </div>
          <div class="p-row">
            <span>{{ t('resale.p2p.feeRow') }}</span>
            <span class="text-green">{{ t('resale.p2p.feeNone') }}</span>
          </div>
          <div class="p-row total-row">
            <strong>{{ t('resale.p2p.askingPrice') }}</strong>
            <strong class="total-price font-mono">
              {{ isFree ? t('resale.p2p.free') : formatCurrency(listing.resalePrice) }}
            </strong>
          </div>
        </div>

        <p class="direct-notice" data-testid="resale-direct-notice">
          <span class="material-symbols-outlined notice-icon">info</span>
          <span>{{ t('resale.p2p.directNotice') }}</span>
        </p>

        <div v-if="error" class="state-box tone-error" role="alert">{{ error }}</div>

        <template v-if="phase === 'idle' || phase === 'expired'">
          <p v-if="phase === 'expired'" class="state-box">{{ t('resale.p2p.expired') }}</p>
          <label v-if="isFree" class="free-accept">
            <input v-model="acceptFree" type="checkbox" data-testid="resale-free-accept" />
            <span>{{ t('resale.p2p.freeAccept') }}</span>
          </label>
          <p v-else class="state-box">{{ t('resale.p2p.lockHint') }}</p>
        </template>

        <ResalePaymentDetails
          v-else-if="phase === 'holding' && state?.payment"
          :payment="state.payment"
          :receiver="state.receiver"
          :countdown="countdown"
        />

        <div
          v-else-if="phase === 'reported'"
          class="state-box tone-wait"
          data-testid="resale-reported"
        >
          <strong>{{ t('resale.p2p.reportedTitle') }}</strong>
          <span>{{ t('resale.p2p.reportedDesc') }}</span>
        </div>

        <div v-else-if="phase === 'sold'" class="state-box tone-ok" data-testid="resale-sold">
          <strong>{{ t('resale.p2p.soldTitle') }}</strong>
          <span>{{ t('resale.p2p.soldDesc') }}</span>
        </div>

        <div
          v-else-if="phase === 'unresolved'"
          class="state-box tone-error"
          data-testid="resale-unresolved"
        >
          <strong>{{ t(`resale.p2p.${unresolvedKey}`) }}</strong>
          <span v-if="state?.last_release?.reason">
            {{ t('resale.p2p.releaseReason', { reason: state.last_release.reason }) }}
          </span>
          <span>{{
            disputeOpened ? t('resale.p2p.disputeOpened') : t('resale.p2p.disputeHint')
          }}</span>
        </div>
      </div>

      <div class="modal-footer">
        <template v-if="phase === 'holding'">
          <AuraButton variant="secondary" size="md" :disabled="busy" @click="lock.cancel()">
            {{ t('resale.p2p.cancelHoldBtn') }}
          </AuraButton>
          <AuraButton
            variant="primary"
            size="md"
            icon="task_alt"
            :loading="busy"
            data-testid="resale-paid-btn"
            @click="lock.reportPaid()"
          >
            {{ t('resale.p2p.paidBtn') }}
          </AuraButton>
        </template>
        <template v-else>
          <AuraButton variant="secondary" size="md" @click="close">
            {{ t('resale.p2p.close') }}
          </AuraButton>
          <AuraButton
            v-if="phase === 'unresolved' && !disputeOpened"
            variant="danger"
            size="md"
            icon="support_agent"
            :loading="busy"
            data-testid="resale-dispute-btn"
            @click="lock.openDispute()"
          >
            {{ t('resale.p2p.disputeBtn') }}
          </AuraButton>
          <AuraButton
            v-else-if="phase === 'idle' || phase === 'expired'"
            variant="primary"
            size="md"
            icon="lock_clock"
            :loading="busy"
            :disabled="isFree && !acceptFree"
            data-testid="resale-lock-btn"
            @click="lock.lock(isFree)"
          >
            {{ isFree ? t('resale.p2p.freeAcceptBtn') : t('resale.p2p.lockBtn') }}
          </AuraButton>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 12, 14, 0.7);
  backdrop-filter: blur(8px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-card {
  width: 100%;
  max-width: 560px;
  max-height: calc(100vh - 32px);
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 24px;
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.modal-header,
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 24px;
  border-bottom: 1px solid var(--outline);
}

.modal-footer {
  justify-content: flex-end;
  border-top: 1px solid var(--outline);
  gap: 12px;
  flex-wrap: wrap;
  border-top: 1px solid var(--outline);
}

.modal-title-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
}

.modal-icon {
  font-size: 22px;
  color: var(--accent, #c9a15a);
}

.modal-title {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.modal-close-btn {
  background: transparent;
  border: 0;
  color: var(--muted);
  cursor: pointer;
  padding: 4px;
  display: flex;
  border-radius: 8px;
}

.modal-body {
  padding: 18px 24px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
}

.target-card,
.price-breakdown {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 14px 16px;
  border-radius: 16px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
}

.target-court {
  font-size: 15.5px;
  color: var(--ink);
}

.target-details {
  display: flex;
  gap: 14px;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--muted);
}

.detail-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.detail-item.highlight {
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}

.icon-14 {
  font-size: 14px;
}

.p-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
  color: var(--muted);
}

.text-green {
  color: #10b981;
  font-weight: 700;
}

.total-row {
  padding-top: 8px;
  border-top: 1px solid var(--outline);
  color: var(--ink);
}

.total-price {
  font-size: 18px;
  color: var(--primary, #6b2d3f);
}

.direct-notice {
  display: flex;
  gap: 8px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(201, 161, 90, 0.08);
  border: 1px solid rgba(201, 161, 90, 0.25);
  font-size: 12px;
  line-height: 1.45;
  color: var(--ink);
}

.notice-icon {
  font-size: 18px;
  color: var(--accent, #c9a15a);
}

.free-accept {
  display: flex;
  gap: 8px;
  align-items: flex-start;
  font-size: 13px;
  color: var(--ink);
}

.state-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  font-size: 12.5px;
  color: var(--ink);
}

.tone-wait {
  background: rgba(239, 108, 0, 0.1);
  color: #9a5a00;
}

.tone-ok {
  background: rgba(46, 125, 50, 0.12);
  color: #20733c;
}

.tone-error {
  background: rgba(198, 40, 40, 0.1);
  color: #9d2828;
}
</style>

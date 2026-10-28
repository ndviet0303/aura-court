<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import TransferConfirmDialog from '../components/TransferConfirmDialog.vue'
import TransferSummary from '../components/TransferSummary.vue'
import { useBookingTransfer } from '../composables/useBookingTransfer'

// UC-9.8, UC-9.9: both sides review the transaction; only the seller confirms, either side cancels.
const { t } = useI18n()
const route = useRoute()
const id = computed(() => Number(route.params.id))
const { transfer, refusals, loading, busy, error, notice, confirm, cancel } = useBookingTransfer(id)
const confirming = ref(false)

async function confirmTransfer() {
  await confirm()
  confirming.value = false
}
</script>

<template>
  <main class="resale-page">
    <RouterLink :to="{ name: 'resale-transfers' }" class="back">
      ← {{ t('resale.transfers.title') }}
    </RouterLink>
    <p v-if="loading && !transfer" class="muted">{{ t('common.loading') }}</p>

    <template v-if="transfer">
      <header class="transfer-head">
        <h1>{{ t('resale.transfer.title', { id: transfer.id }) }}</h1>
        <RouterLink
          v-if="transfer.listing_id"
          :to="{ name: 'resale-listing', params: { id: transfer.listing_id } }"
        >
          {{ t('resale.transfer.viewListing') }}
        </RouterLink>
      </header>

      <p class="payment-notice" role="note">{{ t('resale.transfer.paymentNotice') }}</p>
      <TransferSummary :transfer="transfer" :refusals="refusals" />

      <template v-if="transfer.status === 'pending'">
        <p class="muted">
          {{ t(transfer.is_seller ? 'resale.transfer.sellerHint' : 'resale.transfer.buyerHint') }}
        </p>
        <div class="actions">
          <AuraButton
            variant="outline"
            :loading="busy"
            data-testid="transfer-cancel"
            @click="cancel"
          >
            {{ t('resale.transfer.cancel') }}
          </AuraButton>
          <AuraButton
            v-if="transfer.is_seller"
            :disabled="busy"
            data-testid="transfer-confirm"
            @click="confirming = true"
          >
            {{ t('resale.transfer.confirm') }}
          </AuraButton>
        </div>
      </template>
      <p v-if="notice" class="notice" role="status">{{ notice }}</p>
    </template>
    <p v-if="error" class="form-error" role="alert">{{ error }}</p>

    <TransferConfirmDialog
      v-if="confirming && transfer"
      :transfer="transfer"
      :busy="busy"
      @confirm="confirmTransfer"
      @close="confirming = false"
    />
  </main>
</template>

<style scoped>
.resale-page {
  display: grid;
  gap: 14px;
  max-width: 880px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  color: var(--ink);
}
.back {
  color: var(--muted);
  font-size: 13px;
}
.transfer-head {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 16px;
  align-items: baseline;
  justify-content: space-between;
}
.transfer-head h1 {
  margin: 0;
  font-size: 22px;
}
.payment-notice {
  margin: 0;
  padding: 10px 12px;
  border-left: 3px solid #d97706;
  border-radius: 8px;
  background: color-mix(in srgb, #d97706 8%, transparent);
  font-size: 13px;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}
.notice {
  margin: 0;
  color: #16a34a;
  font-size: 13px;
}
.muted {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
}
</style>

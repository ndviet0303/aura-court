<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { SplitShare } from '../../types/split'

const props = defineProps<{ share: SplitShare }>()
const { t } = useI18n()

// Only the server's share state counts; it turns paid on a verified owner receipt, never on a client reference.
const attempt = computed(() =>
  props.share.status === 'pending' && props.share.payment_attempt?.status === 'pending'
    ? props.share.payment_attempt
    : null,
)
const statusText = computed(() => {
  if (props.share.status === 'paid') return t('splitBill.sharePaid')
  if (props.share.status === 'expired' || props.share.payment_attempt?.status === 'expired')
    return t('splitBill.shareExpired')
  return attempt.value ? t('splitBill.pendingEvidence') : t('splitBill.noPaymentInstructions')
})
</script>

<template>
  <li class="split-share" data-testid="split-share">
    <div class="share-head">
      <div>
        <strong>{{ t('splitBill.shareNumber', { number: share.share_number }) }}</strong>
        <p data-testid="split-share-status">{{ statusText }}</p>
      </div>
      <strong class="font-mono">{{ formatCurrency(share.amount) }}</strong>
    </div>
    <div v-if="attempt" class="share-payment">
      <img
        :src="attempt.qr_url"
        :alt="t('reservations.payment.qrAlt')"
        referrerpolicy="no-referrer"
        data-testid="split-share-qr"
      />
      <dl>
        <div>
          <dt>{{ t('reservations.payment.receiver') }}</dt>
          <dd>{{ attempt.receiver.bank_code }}</dd>
        </div>
        <div>
          <dt>{{ t('reservations.payment.accountName') }}</dt>
          <dd>{{ attempt.receiver.account_name }}</dd>
        </div>
        <div>
          <dt>{{ t('reservations.payment.accountNumber') }}</dt>
          <dd class="font-mono">{{ attempt.receiver.account_number }}</dd>
        </div>
        <div>
          <dt>{{ t('reservations.payment.transferContent') }}</dt>
          <dd class="font-mono select-all" data-testid="split-share-reference">
            {{ attempt.transfer_content }}
          </dd>
        </div>
      </dl>
    </div>
  </li>
</template>

<style scoped>
.split-share {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 14px 0;
  border-bottom: 1px solid var(--border, #382f35);
}
.share-head {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}
p,
dt {
  color: var(--muted, #9e939a);
}
.share-payment {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-start;
}
.share-payment img {
  width: 160px;
  max-width: 100%;
  border-radius: 10px;
  background: #fff;
}
dl {
  display: grid;
  gap: 8px;
  margin: 0;
  min-width: 0;
  flex: 1 1 200px;
}
dd {
  margin: 2px 0 0;
  font-weight: 700;
  overflow-wrap: anywhere;
}
</style>

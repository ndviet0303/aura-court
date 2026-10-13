<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import type { ResalePayment, ResaleReceiver } from '../types/marketplace'

defineProps<{
  payment: ResalePayment
  receiver: ResaleReceiver | null
  countdown: string
}>()

const { t } = useI18n()
</script>

<template>
  <p class="countdown" data-testid="resale-countdown">
    {{ t('resale.p2p.countdown', { time: countdown }) }}
  </p>
  <div class="pay-layout" data-testid="resale-pay-panel">
    <img
      :src="payment.qr_url"
      :alt="t('resale.p2p.qrAlt')"
      referrerpolicy="no-referrer"
      data-testid="resale-qr"
    />
    <dl>
      <template v-if="receiver">
        <div>
          <dt>{{ t('resale.p2p.bank') }}</dt>
          <dd>{{ receiver.bank_code }}</dd>
        </div>
        <div>
          <dt>{{ t('resale.p2p.accountNumber') }}</dt>
          <dd class="font-mono select-all">{{ receiver.account_number }}</dd>
        </div>
        <div>
          <dt>{{ t('resale.p2p.accountName') }}</dt>
          <dd>{{ receiver.account_name }}</dd>
        </div>
      </template>
      <div>
        <dt>{{ t('resale.p2p.amount') }}</dt>
        <dd class="font-mono">{{ formatCurrency(payment.amount) }}</dd>
      </div>
      <div>
        <dt>{{ t('resale.p2p.transferContent') }}</dt>
        <dd class="font-mono select-all">{{ payment.reference }}</dd>
      </div>
    </dl>
  </div>
</template>

<style scoped>
.countdown {
  margin: 0;
  font-size: 13px;
  font-weight: 800;
  color: #9a5a00;
}

.pay-layout {
  display: grid;
  grid-template-columns: 170px 1fr;
  align-items: center;
  gap: 15px;
}

.pay-layout img {
  width: 170px;
  aspect-ratio: 1;
  padding: 6px;
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: white;
}

dl {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
}

dt {
  color: var(--muted);
  font-size: 10.5px;
}

dd {
  margin: 0;
  overflow-wrap: anywhere;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

@media (max-width: 540px) {
  .pay-layout {
    grid-template-columns: 1fr;
    justify-items: center;
  }
  dl {
    width: 100%;
  }
}
</style>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton } from '@/shared/components'
import { useDeadlineCountdown } from '@/features/booking/composables/useDeadlineCountdown'
import type { PackagePaymentRequest } from '../types'

const props = defineProps<{ request: PackagePaymentRequest | null; busy: boolean; error: string }>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'refresh', code: string): void
}>()

const { t, d } = useI18n()
const time = (iso: string | null) => (iso ? d(new Date(iso), 'datetime') : '—')
const countdown = useDeadlineCountdown(() => props.request?.expires_at)
// D7: past its deadline the request reads as expired even before the server writes it.
const expired = computed(
  () =>
    props.request?.status === 'expired' ||
    (props.request?.status === 'pending' && countdown.value === '00:00'),
)

const copied = ref('')
watch(
  () => props.request?.request_code,
  () => (copied.value = ''),
)

async function copy(field: string, text: string | null) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    copied.value = field
  } catch {
    // Clipboard blocked: the value stays selectable.
  }
}
</script>

<template>
  <div v-if="request" class="modal-backdrop" @click.self="!busy && emit('close')">
    <div
      class="modal-card"
      role="dialog"
      aria-modal="true"
      aria-labelledby="package-payment-title"
      data-testid="package-payment-dialog"
    >
      <header class="modal-header">
        <h2 id="package-payment-title" class="modal-title">
          {{ t('admin.packages.payment.title') }}
        </h2>
        <button
          type="button"
          class="modal-close-btn"
          :disabled="busy"
          :aria-label="t('common.close')"
          @click="emit('close')"
        >
          <span class="material-symbols-outlined" aria-hidden="true">close</span>
        </button>
      </header>

      <div class="modal-body">
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <p class="summary">
          {{
            t('admin.packages.payment.summary', {
              kind: t(`admin.packages.kind.${request.kind}`),
              term: t('admin.packages.termLabel', { days: request.days }),
              code: request.request_code,
            })
          }}
        </p>

        <template v-if="request.status === 'paid'">
          <p class="state paid" data-testid="package-payment-paid">
            {{ t('admin.packages.payment.paid', { time: time(request.paid_at) }) }}
            {{
              t('admin.packages.payment.period', {
                start: time(request.period_starts_at),
                end: time(request.period_ends_at),
              })
            }}
          </p>
        </template>
        <template v-else-if="expired">
          <p class="state expired" data-testid="package-payment-expired">
            {{ t('admin.packages.payment.expired') }}
          </p>
          <p class="muted">{{ t('admin.packages.payment.expiredHint') }}</p>
        </template>
        <template v-else>
          <p class="state countdown" data-testid="package-payment-countdown">
            {{ t('admin.packages.payment.countdown', { time: countdown }) }}
          </p>
          <p class="muted">
            {{ t('admin.packages.payment.expiresAt', { time: time(request.expires_at) }) }}
          </p>
          <div class="pay-layout">
            <img
              v-if="request.qr_url"
              :src="request.qr_url"
              :alt="t('admin.packages.payment.qrAlt')"
              referrerpolicy="no-referrer"
              data-testid="package-payment-qr"
            />
            <dl>
              <div>
                <dt>{{ t('admin.packages.payment.bank') }}</dt>
                <dd>{{ request.receiver.bank_code }}</dd>
              </div>
              <div>
                <dt>{{ t('admin.packages.payment.accountNumber') }}</dt>
                <dd class="copyable">
                  <span class="font-mono select-all" data-testid="package-payment-account">
                    {{ request.receiver.account_number }}
                  </span>
                  <button
                    type="button"
                    class="copy-btn"
                    :aria-label="t('admin.packages.payment.copyAccount')"
                    data-testid="package-payment-copy-account"
                    @click="copy('account', request.receiver.account_number)"
                  >
                    <span class="material-symbols-outlined" aria-hidden="true">
                      {{ copied === 'account' ? 'check' : 'content_copy' }}
                    </span>
                  </button>
                </dd>
              </div>
              <div>
                <dt>{{ t('admin.packages.payment.accountName') }}</dt>
                <dd>{{ request.receiver.account_name }}</dd>
              </div>
              <div>
                <dt>{{ t('admin.packages.payment.amount') }}</dt>
                <dd class="font-mono" data-testid="package-payment-amount">
                  {{ formatCurrency(request.amount) }}
                </dd>
              </div>
              <div>
                <dt>{{ t('admin.packages.payment.paymentCode') }}</dt>
                <dd class="copyable">
                  <span class="font-mono select-all" data-testid="package-payment-code">
                    {{ request.payment_code }}
                  </span>
                  <button
                    type="button"
                    class="copy-btn"
                    :aria-label="t('admin.packages.payment.copyCode')"
                    data-testid="package-payment-copy-code"
                    @click="copy('code', request.payment_code)"
                  >
                    <span class="material-symbols-outlined" aria-hidden="true">
                      {{ copied === 'code' ? 'check' : 'content_copy' }}
                    </span>
                  </button>
                </dd>
              </div>
            </dl>
          </div>
          <p class="muted" role="status">
            {{ copied ? t('admin.packages.payment.copied') : t('admin.packages.payment.hint') }}
          </p>
        </template>
      </div>

      <footer class="modal-footer">
        <AuraButton
          variant="secondary"
          icon="refresh"
          :loading="busy"
          data-testid="package-payment-refresh"
          @click="emit('refresh', request.request_code)"
        >
          {{ t('admin.packages.payment.refresh') }}
        </AuraButton>
        <AuraButton :disabled="busy" @click="emit('close')">{{ t('common.close') }}</AuraButton>
      </footer>
    </div>
  </div>
</template>

<style scoped>
.modal-body {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.modal-body p {
  margin: 0;
}

.summary {
  font-weight: 700;
}

.state {
  font-size: 15px;
  font-weight: 800;
}

.countdown {
  color: #9a5a00;
}

.paid {
  color: var(--success-text);
}

.expired,
.error {
  color: var(--error-text);
}

.pay-layout {
  display: grid;
  grid-template-columns: 170px 1fr;
  align-items: center;
  gap: 16px;
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
  font-size: 12px;
  color: var(--muted);
}

dd {
  margin: 0;
  overflow-wrap: anywhere;
  font-weight: 700;
}

.copyable {
  display: flex;
  align-items: center;
  gap: 6px;
}

.copy-btn {
  display: inline-flex;
  padding: 2px;
  border: 0;
  background: transparent;
  color: var(--primary);
  cursor: pointer;
}

.copy-btn .material-symbols-outlined {
  font-size: 18px;
}

.modal-footer {
  justify-content: flex-end;
  gap: 8px;
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

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { AuraButton, SelectDropdown } from '@/shared/components'
import type { DropdownOption } from '@/shared/components'
import { useDeadlineCountdown } from '@/features/booking/composables/useDeadlineCountdown'
import type { PackageTermDays, PackageTermPrice } from '@/features/platform-fees/types/platformFee'
import type { VenuePackageOverview } from '../types'
import PackageRequestHistory from './PackageRequestHistory.vue'

const props = defineProps<{
  venue: VenuePackageOverview
  terms: PackageTermPrice[]
  busy: boolean
}>()

const emit = defineEmits<{
  (e: 'request', termDays: PackageTermDays): void
  (e: 'open-payment', code: string): void
  (e: 'terminate'): void
}>()

const { t, d } = useI18n()
const termLabel = (days: number) => t('admin.packages.termLabel', { days })
const time = (iso: string | null | undefined) => (iso ? d(new Date(iso), 'datetime') : '—')

const pending = computed(() => props.venue.pending_request)
const countdown = useDeadlineCountdown(() => pending.value?.expires_at)

// A renewal keeps the active package's term (PACKAGE_TERM_LOCKED); a purchase may pick any term.
const renewal = computed(() => props.venue.package_status === 'active' && !!props.venue.package)
const chosenTerm = ref<PackageTermDays>(30)
const term = computed(() =>
  renewal.value && props.venue.package ? props.venue.package.term_days : chosenTerm.value,
)
const price = computed(
  () => props.terms.find((row) => row.term_days === term.value)?.current_price ?? null,
)
const termOptions = computed<DropdownOption[]>(() =>
  props.terms.map((row) => ({
    value: row.term_days,
    label: termLabel(row.days),
    sublabel: row.current_price
      ? formatCurrency(row.current_price.amount)
      : t('admin.packages.buy.noPrice'),
  })),
)
</script>

<template>
  <article class="venue-package" :data-testid="`venue-package-${venue.venue_id}`">
    <header class="venue-head">
      <h2 class="venue-name">{{ venue.venue_name }}</h2>
      <span
        class="status"
        :class="`status-${venue.package_status}`"
        :data-testid="`venue-package-status-${venue.venue_id}`"
      >
        {{ t(`admin.packages.status.${venue.package_status}`) }}
      </span>
    </header>

    <dl v-if="venue.package" class="package-facts">
      <div>
        <dt>{{ t('admin.packages.fields.term') }}</dt>
        <dd>{{ termLabel(venue.package.term_days) }}</dd>
      </div>
      <div>
        <dt>{{ t('admin.packages.fields.startsAt') }}</dt>
        <dd>{{ time(venue.package.starts_at) }}</dd>
      </div>
      <div>
        <dt>{{ t('admin.packages.fields.endsAt') }}</dt>
        <dd>{{ time(venue.package.ends_at) }}</dd>
      </div>
      <div
        v-if="venue.package.terminated_at"
        :data-testid="`venue-package-terminated-${venue.venue_id}`"
      >
        <dt>{{ t('admin.packages.fields.terminatedAt') }}</dt>
        <dd>{{ time(venue.package.terminated_at) }}</dd>
      </div>
    </dl>

    <div v-if="pending" class="pending" :data-testid="`venue-package-pending-${venue.venue_id}`">
      <p>
        {{
          countdown === '00:00'
            ? t('admin.packages.pending.expired', { code: pending.request_code })
            : t('admin.packages.pending.label', { code: pending.request_code, time: countdown })
        }}
      </p>
      <AuraButton
        size="sm"
        variant="secondary"
        icon="qr_code_2"
        :disabled="busy"
        :data-testid="`venue-package-open-payment-${venue.venue_id}`"
        @click="emit('open-payment', pending.request_code)"
      >
        {{ t('admin.packages.pending.open') }}
      </AuraButton>
    </div>

    <p v-if="venue.venue_status === 'closed'" class="muted">
      {{ t('admin.packages.venueClosed') }}
    </p>
    <div v-else class="buy" :data-testid="`venue-package-buy-${venue.venue_id}`">
      <div class="buy-term">
        <span class="form-label">{{ t('admin.packages.buy.term') }}</span>
        <p
          v-if="renewal"
          class="locked"
          :data-testid="`venue-package-locked-term-${venue.venue_id}`"
        >
          {{ termLabel(term) }} · {{ t('admin.packages.buy.termLocked') }}
        </p>
        <SelectDropdown
          v-else
          :model-value="chosenTerm"
          :options="termOptions"
          variant="bordered"
          :aria-label="t('admin.packages.buy.term')"
          :data-testid="`venue-package-term-${venue.venue_id}`"
          @update:model-value="(value) => (chosenTerm = Number(value) as PackageTermDays)"
        />
      </div>
      <p class="amount" :data-testid="`venue-package-amount-${venue.venue_id}`">
        {{
          price
            ? t('admin.packages.buy.summary', {
                term: termLabel(term),
                amount: formatCurrency(price.amount),
              })
            : t('admin.packages.buy.noPrice')
        }}
      </p>
      <AuraButton
        :icon="renewal ? 'autorenew' : 'shopping_cart'"
        :disabled="busy || !price"
        :data-testid="`venue-package-submit-${venue.venue_id}`"
        @click="emit('request', term)"
      >
        {{ renewal ? t('admin.packages.buy.renew') : t('admin.packages.buy.submit') }}
      </AuraButton>
    </div>

    <AuraButton
      v-if="venue.package_status === 'active'"
      class="terminate"
      size="sm"
      variant="ghost"
      icon="block"
      :disabled="busy"
      :data-testid="`venue-package-terminate-${venue.venue_id}`"
      @click="emit('terminate')"
    >
      {{ t('admin.packages.terminate.action') }}
    </AuraButton>

    <PackageRequestHistory
      v-if="venue.requests.length"
      :requests="venue.requests"
      :expired-code="countdown === '00:00' ? pending?.request_code : undefined"
    />
  </article>
</template>

<style scoped>
.venue-package {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  border: 1px solid var(--outline);
  border-radius: 16px;
  background: var(--surface);
}

.venue-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.venue-name {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
}

.status {
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
  white-space: nowrap;
  background: var(--surface-variant, rgba(100, 116, 139, 0.12));
  color: var(--muted);
}

.status-active {
  background: var(--mint);
  color: #315b49;
}

.status-expired,
.status-terminated {
  background: var(--coral);
  color: #8b332d;
}

.package-facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 10px;
  margin: 0;
}

dt {
  font-size: 12px;
  color: var(--muted);
}

dd {
  margin: 0;
  font-weight: 700;
}

.pending,
.buy {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 12px;
}

.pending {
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(245, 158, 11, 0.12);
  color: #9a5a00;
  font-weight: 700;
}

.pending p,
.amount,
.locked,
.muted {
  margin: 0;
}

.buy-term {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 200px;
}

.locked {
  font-weight: 700;
}

.amount {
  flex: 1;
  font-weight: 700;
}

.terminate {
  align-self: flex-start;
}
</style>

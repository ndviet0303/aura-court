<script setup lang="ts">
import { computed, toRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import { statusLabel } from '@/features/booking/utils/reservation'
import type { PromotionProgram, VoucherUse } from '../../types/promotionProgram'
import { usePromotionProgramLabels } from '../../composables/usePromotionProgramLabels'
import { usePromotionProgramUsage } from '../../composables/usePromotionProgramUsage'

const props = defineProps<{ program: PromotionProgram }>()

const { t, d } = useI18n()
const { instant, courtName, money } = usePromotionProgramLabels()
const { used, held, total, loading, loaded, error, hasMore, loadMore } = usePromotionProgramUsage(
  toRef(props, 'program'),
)

// Held and used stay apart: a hold is not a use until its booking is confirmed (D11).
const sections = computed(() => [
  {
    key: 'held',
    rows: held.value,
    title: t('admin.promotions.usage.heldTitle', { count: held.value.length }),
    at: (use: VoucherUse) => use.held_at,
  },
  {
    key: 'used',
    rows: used.value,
    title: t('admin.promotions.usage.usedTitle', { count: total.value }),
    at: (use: VoucherUse) => use.used_at ?? use.held_at,
  },
])

const bookingStatus = ({ reservation: r }: VoucherUse) =>
  statusLabel(
    {
      code: r.code,
      status: r.status,
      metadata: { operational_status: r.operational_status ?? undefined },
    },
    t,
  )

function playTime(use: VoucherUse) {
  const { starts_at: from, ends_at: to } = use.reservation
  return from && to ? `${instant(from)}–${d(new Date(to), 'time')}` : ''
}
</script>

<template>
  <section class="usage-list" data-testid="promotion-program-usage">
    <p v-if="error" class="usage-error" role="alert">{{ error }}</p>
    <!-- No counts or empty state before the first load succeeds: they would claim the program has no usage. -->
    <p v-if="!loaded && loading" class="usage-empty">{{ t('common.loading') }}</p>
    <template v-if="loaded">
      <template v-for="section in sections" :key="section.key">
        <h3 class="section-title">{{ section.title }}</h3>
        <p v-if="section.rows.length === 0" class="usage-empty">
          {{ t('admin.promotions.usage.empty') }}
        </p>
        <div v-else class="table-scroll">
          <table class="usage-table">
            <thead>
              <tr>
                <th>{{ t('admin.promotions.usage.colBooking') }}</th>
                <th>{{ t('admin.promotions.usage.colCustomer') }}</th>
                <th>{{ t('admin.promotions.usage.colCourtTime') }}</th>
                <th>{{ t('admin.promotions.usage.colStatus') }}</th>
                <th>{{ t('admin.promotions.usage.colRental') }}</th>
                <th>{{ t('admin.promotions.usage.colDiscount') }}</th>
                <th>{{ t(`admin.promotions.usage.colAt.${section.key}`) }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="use in section.rows"
                :key="use.id"
                :data-testid="`voucher-use-${section.key}`"
              >
                <td>
                  <strong>{{ use.reservation.code }}</strong>
                  <span v-if="use.code" class="voucher-code">{{ use.code }}</span>
                  <span v-if="use.reservation.status === 'cancelled'" class="badge">
                    {{ t('admin.promotions.usage.cancelled') }}
                  </span>
                </td>
                <td>{{ use.reservation.customer_name ?? '—' }}</td>
                <td>
                  <span v-if="use.reservation.court">{{
                    courtName(use.reservation.court.name)
                  }}</span>
                  <span class="muted">{{ playTime(use) }}</span>
                </td>
                <td>{{ bookingStatus(use) }}</td>
                <td>{{ use.rental === null ? '—' : money(use.rental) }}</td>
                <td>{{ money(use.discount) }}</td>
                <td>{{ instant(section.at(use)) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
      <AuraButton
        v-if="hasMore"
        variant="secondary"
        size="sm"
        :disabled="loading"
        @click="loadMore"
      >
        {{ t('admin.promotions.usage.loadMore') }}
      </AuraButton>
    </template>
  </section>
</template>

<style scoped>
.usage-list {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
}

.section-title {
  margin: 8px 0 0;
  font-size: 14px;
}

.usage-empty,
.muted {
  color: var(--muted);
  font-size: 13px;
}

.usage-empty {
  margin: 0;
}

.usage-error {
  margin: 0;
  color: #b91c1c;
  font-size: 13px;
}

.table-scroll {
  width: 100%;
  overflow-x: auto;
}

.usage-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.usage-table th,
.usage-table td {
  padding: 6px 8px;
  border-bottom: 1px solid var(--outline);
  text-align: left;
  vertical-align: top;
}

.usage-table th {
  color: var(--muted);
  font-weight: 600;
}

.usage-table td span {
  display: block;
}

.voucher-code {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  letter-spacing: 0.08em;
}

.badge {
  width: fit-content;
  margin-top: 2px;
  padding: 2px 8px;
  border-radius: 999px;
  background: var(--soft, rgba(0, 0, 0, 0.05));
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
}
</style>

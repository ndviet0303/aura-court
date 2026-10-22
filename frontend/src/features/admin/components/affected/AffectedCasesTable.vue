<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { venueClockRange, venueDate } from '@/core/utils/venueTime'
import { AuraButton } from '@/shared/components'
import { OPEN_CASE_STATUSES, type AffectedCase, type CaseAction } from '../../types/affected'

defineProps<{
  cases: AffectedCase[]
  isOwnerOfCase: (row: AffectedCase) => boolean
}>()

const emit = defineEmits<{ action: [kind: CaseAction, row: AffectedCase] }>()

const { t, te } = useI18n()

const ACTIONS: CaseAction[] = ['outcome', 'move-court', 'change-time', 'operator-cancel']
const isOpen = (row: AffectedCase) => OPEN_CASE_STATUSES.includes(row.status)
// Reservation states other than held/confirmed/in_use come through unchanged.
const paymentLabel = (status: string) =>
  te(`admin.affected.payment.${status}`) ? t(`admin.affected.payment.${status}`) : status
const slot = (start: string | null, end: string | null) =>
  start ? `${venueDate(start)} · ${venueClockRange(start, end)}` : '—'
</script>

<template>
  <div class="table-wrap">
    <table class="table case-table">
      <thead>
        <tr>
          <th>{{ t('admin.affected.columns.booking') }}</th>
          <th>{{ t('admin.affected.columns.customer') }}</th>
          <th>{{ t('admin.affected.columns.usage') }}</th>
          <th>{{ t('admin.affected.columns.payment') }}</th>
          <th>{{ t('admin.affected.columns.source') }}</th>
          <th>{{ t('admin.affected.columns.status') }}</th>
          <th>{{ t('admin.affected.columns.actions') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in cases" :key="row.id">
          <td>
            <strong>{{ row.reservation.code }}</strong>
            <div class="muted">{{ resolveLocalizedText(row.venue.name) }}</div>
          </td>
          <td>
            {{ row.reservation.customer_name || '—' }}
            <div class="muted">{{ row.reservation.customer_phone || '' }}</div>
          </td>
          <td>
            {{ row.reservation.court_code ?? '—' }}
            <div class="muted">{{ slot(row.reservation.starts_at, row.reservation.ends_at) }}</div>
          </td>
          <td>{{ paymentLabel(row.reservation.payment_status) }}</td>
          <td>
            {{ t(`admin.affected.source.${row.source.type}`) }}
            <div class="muted">{{ slot(row.source.starts_at, row.source.ends_at) }}</div>
          </td>
          <td class="case-status">
            <span class="case-chip" :class="{ 'case-chip-open': isOpen(row) }">
              {{ t(`admin.affected.status.${row.status}`) }}
            </span>
            <div v-if="row.channel || row.note" class="muted case-note">
              <template v-if="row.channel">{{
                t(`admin.affected.channel.${row.channel}`)
              }}</template>
              {{ row.note ?? '' }}
            </div>
            <div v-if="row.refund_note && isOwnerOfCase(row)" class="case-note">
              {{ t('admin.affected.refundNoteShort', { note: row.refund_note }) }}
            </div>
            <div v-if="row.handled_by" class="muted">{{ row.handled_by.name }}</div>
          </td>
          <td>
            <!-- Design D8: commands only while the case is open; no refund action for any role. -->
            <div v-if="isOpen(row)" class="case-actions">
              <AuraButton
                v-for="kind in ACTIONS"
                :key="kind"
                size="sm"
                :variant="kind === 'operator-cancel' ? 'danger' : 'outline'"
                @click="emit('action', kind, row)"
              >
                {{ t(`admin.affected.action.${kind}`) }}
              </AuraButton>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.case-table td {
  vertical-align: top;
  white-space: normal;
}

.muted {
  color: var(--muted);
  font-size: 12px;
}

.case-status {
  min-width: 160px;
}

.case-chip {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  background: rgba(100, 116, 139, 0.12);
  color: #475569;
  font-size: 11px;
  font-weight: 700;
}

.case-chip-open {
  background: rgba(245, 158, 11, 0.14);
  color: #b45309;
}

.case-note {
  margin-top: 4px;
  font-size: 12px;
}

.case-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
</style>

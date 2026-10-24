<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { AuraButton } from '@/shared/components'
import type { MyEntry } from '../types/tournament'
import { formatMoney } from '../utils/tournament'
import EntryPaymentPanel from './EntryPaymentPanel.vue'
import TournamentStatusBadge from './TournamentStatusBadge.vue'

defineProps<{ entry: MyEntry; busy: boolean }>()
const emit = defineEmits<{ withdraw: []; pay: []; refresh: [] }>()
const { t, d } = useI18n()
</script>

<template>
  <article class="admin-detail-card my-entry">
    <header class="admin-detail-header">
      <div>
        <RouterLink :to="`/tournaments/${entry.tournament.id}`" class="my-entry__tournament">
          {{ entry.tournament.name }}
        </RouterLink>
        <p class="admin-detail-muted">
          {{ t('tournament.me.unit', { name: entry.name }) }} ·
          {{ entry.members.map((m) => m.full_name).join(', ') }}
        </p>
      </div>
      <TournamentStatusBadge :status="entry.status" kind="entry" />
    </header>

    <p v-if="entry.status === 'rejected' && entry.reject_reason" class="admin-detail-error">
      {{ t('tournament.me.rejectReason', { reason: entry.reject_reason }) }}
    </p>
    <p v-if="entry.status === 'paid'" class="admin-detail-notice">{{ t('tournament.me.paid') }}</p>
    <p v-if="entry.status === 'submitted'" class="admin-detail-muted">
      {{ t('tournament.me.waitingReview') }}
    </p>
    <p v-if="entry.status === 'approved'" class="admin-detail-muted">
      {{
        t('tournament.me.payBefore', {
          amount: formatMoney(entry.tournament.fee),
          time: entry.tournament.payment_deadline
            ? d(new Date(entry.tournament.payment_deadline), 'datetime')
            : '–',
        })
      }}
    </p>

    <EntryPaymentPanel
      v-if="entry.status === 'approved' && entry.payment"
      :payment="entry.payment"
      :refreshing="busy"
      @refresh="emit('refresh')"
    />

    <footer class="my-entry__actions">
      <AuraButton
        v-if="entry.status === 'approved' && entry.payment?.status !== 'pending'"
        :loading="busy"
        @click="emit('pay')"
      >
        {{ t('tournament.me.pay') }}
      </AuraButton>
      <AuraButton
        v-if="entry.status === 'submitted' || entry.status === 'approved'"
        variant="outline"
        :disabled="busy"
        @click="emit('withdraw')"
      >
        {{ t('tournament.me.withdraw') }}
      </AuraButton>
    </footer>
  </article>
</template>

<style scoped>
.my-entry {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.my-entry__tournament {
  color: var(--ink);
  font-size: 16px;
  font-weight: 800;
  text-decoration: none;
}

.my-entry__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>

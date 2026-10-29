<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { splitEvenly, useGroupSplitBill } from '../composables/useGroupSplitBill'
import SplitShareCard from '../components/split/SplitShareCard.vue'

const route = useRoute()
const { t, locale } = useI18n()
const { reservation, group, loading, creating, error, stale, loadSplit, createSplit } =
  useGroupSplitBill()
const code = computed(() => String(route.params.code))
const people = ref(2)
const PEOPLE_OPTIONS = [2, 3, 4, 5, 6, 7, 8]
const preview = computed(() =>
  reservation.value ? splitEvenly(reservation.value.total_amount, people.value) : [],
)

watch(code, (value) => void loadSplit(value), { immediate: true })

function formatExpiry(value: string): string {
  return new Intl.DateTimeFormat(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: 'Asia/Ho_Chi_Minh',
  }).format(new Date(value))
}
</script>

<template>
  <main class="group-split-bill-view" data-testid="split-screen">
    <nav class="split-nav-bar">
      <RouterLink to="/booking">{{ t('splitBill.backToBookings') }}</RouterLink>
      <button
        v-if="reservation"
        type="button"
        class="refresh-button"
        data-testid="split-refresh"
        :disabled="loading"
        @click="loadSplit(code)"
      >
        {{ t('splitBill.refresh') }}
      </button>
    </nav>

    <header>
      <h1>{{ t('splitBill.title') }}</h1>
      <p>{{ t('splitBill.subtitle') }}</p>
    </header>

    <p v-if="error" class="alert" role="alert" data-testid="split-error">{{ error }}</p>
    <p v-if="loading && !reservation" role="status">{{ t('splitBill.loading') }}</p>

    <section v-if="group" class="group-card" data-testid="split-group">
      <p v-if="stale" class="alert" data-testid="split-stale">{{ t('splitBill.staleWarning') }}</p>

      <dl class="group-details">
        <div>
          <dt>{{ t('splitBill.bookingCode') }}</dt>
          <dd>{{ group.reservation.code }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.acceptedTotal') }}</dt>
          <dd>{{ formatCurrency(group.expected_amount) }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.paidTotal') }}</dt>
          <dd data-testid="split-paid-total">{{ formatCurrency(group.paid_amount) }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.serverState') }}</dt>
          <dd data-testid="split-server-state">{{ t(`splitBill.status.${group.status}`) }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.expiresAt') }}</dt>
          <dd>{{ formatExpiry(group.expires_at) }}</dd>
        </div>
      </dl>

      <h2>{{ t('splitBill.shares') }}</h2>
      <p class="hint">{{ t('splitBill.shareInstructions') }}</p>
      <ol class="shares-list">
        <SplitShareCard v-for="share in group.shares" :key="share.id" :share="share" />
      </ol>
    </section>

    <form
      v-else-if="reservation?.status === 'held'"
      class="group-card create-form"
      data-testid="split-create"
      @submit.prevent="createSplit(people)"
    >
      <h2>{{ t('splitBill.createTitle') }}</h2>
      <p class="hint">{{ t('splitBill.createHint') }}</p>
      <label>
        <span>{{ t('splitBill.peopleCount') }}</span>
        <select v-model.number="people" data-testid="split-people" :disabled="creating">
          <option v-for="n in PEOPLE_OPTIONS" :key="n" :value="n">{{ n }}</option>
        </select>
      </label>
      <ol class="shares-list">
        <li v-for="(amount, i) in preview" :key="i" data-testid="split-preview-share">
          <span>{{ t('splitBill.shareNumber', { number: i + 1 }) }}</span>
          <strong class="font-mono">{{ formatCurrency(amount) }}</strong>
        </li>
      </ol>
      <button type="submit" class="refresh-button" :disabled="creating" data-testid="split-submit">
        {{ creating ? t('splitBill.creating') : t('splitBill.createSubmit') }}
      </button>
    </form>

    <p v-else-if="reservation" class="empty-state" data-testid="split-not-splittable">
      {{ t('splitBill.notSplittable') }}
    </p>
  </main>
</template>

<style scoped>
.group-split-bill-view {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 960px;
  margin: 0 auto;
  padding: 24px 20px 60px;
  color: var(--on-surface, #faf7f3);
}

.split-nav-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

a {
  color: var(--primary, #e58e9f);
}

.refresh-button {
  padding: 8px 14px;
  color: inherit;
  background: var(--surface, #1e191c);
  border: 1px solid var(--border, #382f35);
  border-radius: 8px;
  cursor: pointer;
}

.refresh-button:disabled {
  opacity: 0.6;
  cursor: wait;
}

h1 {
  font-size: 24px;
}
h2 {
  font-size: 18px;
}
header p,
dt,
.hint {
  color: var(--muted, #9e939a);
}

.group-card,
.empty-state {
  padding: 20px;
  border: 1px solid var(--border, #382f35);
  border-radius: 14px;
  background: var(--surface, #1e191c);
}

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.35);
}

.group-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
  gap: 16px;
}

dd {
  margin: 6px 0 0;
  font-weight: 700;
  overflow-wrap: anywhere;
}
.shares-list {
  list-style: none;
  padding: 0;
}
.create-form {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.create-form label {
  display: flex;
  align-items: center;
  gap: 12px;
}
.create-form select {
  padding: 6px 10px;
  color: inherit;
  background: var(--surface, #1e191c);
  border: 1px solid var(--border, #382f35);
  border-radius: 8px;
}
.create-form .shares-list li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border, #382f35);
}
</style>

<script setup lang="ts">
import { watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useGroupSplitBill } from '../composables/useGroupSplitBill'

const route = useRoute()
const { t, locale } = useI18n()
const { group, loading, error, stale, loadSplitGroup } = useGroupSplitBill()

watch(
  () => route.params.code ?? route.query.code,
  (identifier) => {
    void loadSplitGroup(typeof identifier === 'string' ? identifier : undefined)
  },
  { immediate: true },
)

function formatMoney(amount: number): string {
  return new Intl.NumberFormat(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    style: 'currency',
    currency: 'VND',
  }).format(amount)
}

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
        v-if="group"
        type="button"
        class="refresh-button"
        data-testid="split-refresh"
        :disabled="loading"
        @click="loadSplitGroup(String(group.id))"
      >
        {{ t('splitBill.refresh') }}
      </button>
    </nav>

    <header>
      <h1>{{ t('splitBill.title') }}</h1>
      <p>{{ t('splitBill.subtitle') }}</p>
    </header>

    <p v-if="error" class="alert" role="alert" data-testid="split-error">{{ error }}</p>
    <p v-if="loading && !group" role="status">{{ t('splitBill.loading') }}</p>

    <section v-if="group" class="group-card" data-testid="split-group">
      <p v-if="stale" class="alert" data-testid="split-stale">{{ t('splitBill.staleWarning') }}</p>

      <dl class="group-details">
        <div>
          <dt>{{ t('splitBill.groupId') }}</dt>
          <dd data-testid="split-group-id">{{ group.id }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.bookingCode') }}</dt>
          <dd>{{ group.reservation.code }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.acceptedTotal') }}</dt>
          <dd>{{ formatMoney(group.expected_amount) }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.serverState') }}</dt>
          <dd data-testid="split-server-state">{{ group.status }}</dd>
        </div>
        <div>
          <dt>{{ t('splitBill.expiresAt') }}</dt>
          <dd>{{ formatExpiry(group.expires_at) }}</dd>
        </div>
      </dl>

      <p class="evidence-warning" data-testid="split-evidence-warning">
        {{ t('splitBill.evidenceUnavailable') }}
      </p>
      <p class="evidence-warning">{{ t('splitBill.actionsUnavailable') }}</p>
      <h2>{{ t('splitBill.shares') }}</h2>
      <ol class="shares-list">
        <li v-for="share in group.shares" :key="share.id" data-testid="split-share">
          <div>
            <strong>{{ t('splitBill.shareNumber', { number: share.share_number }) }}</strong>
            <p>
              {{
                share.status === 'expired'
                  ? t('splitBill.shareExpired')
                  : t('splitBill.pendingEvidence')
              }}
            </p>
          </div>
          <strong>{{ formatMoney(share.amount) }}</strong>
        </li>
      </ol>
    </section>

    <p v-else-if="!loading" class="empty-state" data-testid="split-empty">
      {{ t('splitBill.emptyState') }}
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
li p {
  color: var(--muted, #9e939a);
}

.group-card,
.empty-state {
  padding: 20px;
  border: 1px solid var(--border, #382f35);
  border-radius: 14px;
  background: var(--surface, #1e191c);
}

.alert,
.evidence-warning {
  padding: 12px 16px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.35);
}

.group-details {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
.shares-list li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid var(--border, #382f35);
}
</style>

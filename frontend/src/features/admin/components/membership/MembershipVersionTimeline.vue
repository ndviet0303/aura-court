<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { resolveLocalizedText } from '@/i18n'
import { SkeletonLoader } from '@/shared/components'
import type { PlanVersionAudit } from '../../types/membership'

const props = defineProps<{
  audits: PlanVersionAudit[]
  loading?: boolean
}>()

const { t } = useI18n()
const isExpanded = ref(false)

function getVisibleAudits() {
  if (isExpanded.value) return props.audits
  return props.audits.slice(0, 6)
}
</script>

<template>
  <div class="version-timeline-wrapper">
    <div class="timeline-header-card">
      <div class="title-with-icon">
        <span class="material-symbols-outlined header-icon">history_edu</span>
        <div>
          <h3 class="card-title">{{ t('admin.membership.versionTimelineTitle') }}</h3>
          <p class="card-desc">{{ t('admin.membership.versionTimelineDesc') }}</p>
        </div>
      </div>
      <span v-if="!loading" class="audits-count-pill">{{ audits.length }} bản ghi</span>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="timeline-grid">
      <div v-for="i in 3" :key="i" class="version-card skeleton-card">
        <SkeletonLoader height="20px" width="40%" />
        <SkeletonLoader height="12px" width="60%" class="my-2" />
        <SkeletonLoader height="32px" width="100%" radius="10px" class="mt-2" />
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!audits.length" class="empty-timeline-box">
      <span class="material-symbols-outlined empty-icon">history</span>
      <p class="empty-text">{{ t('admin.membership.noHistoryRecorded') }}</p>
    </div>

    <!-- Version Cards Grid -->
    <div v-else class="timeline-grid">
      <div
        v-for="(item, idx) in getVisibleAudits()"
        :key="item.id || idx"
        class="version-card"
        :class="{ 'is-archived': item.status === 'archived' }"
      >
        <div class="version-card-head">
          <div class="version-title-block">
            <strong class="ver-plan-name">{{ resolveLocalizedText(item.planName) }}</strong>
            <span class="ver-badge">{{ item.version }}</span>
          </div>
          <span class="status-badge" :class="item.status === 'active' ? 'active' : 'archived'">
            {{ item.status === 'active' ? t('admin.membership.activeStatus') : t('admin.membership.archivedStatus') }}
          </span>
        </div>

        <div class="version-meta-row">
          <span class="meta-txt">{{ item.updatedAt }} • {{ t('admin.membership.updatedByLabel', { user: item.updatedBy }) }}</span>
          <span v-if="item.activeSubscribersCount > 0" class="subscribers-pill">
            <span class="material-symbols-outlined icon-14">group</span>
            {{ t('admin.membership.activeSubscribersCount', { count: item.activeSubscribersCount }) }}
          </span>
        </div>

        <div v-if="item.changes && item.changes.length" class="changes-chip-list">
          <div v-for="(change, cIdx) in item.changes" :key="cIdx" class="change-tag">
            <span class="material-symbols-outlined icon-14 text-pink">add_circle</span>
            <span>{{ resolveLocalizedText(change) }}</span>
          </div>
        </div>

        <p v-if="item.note" class="ver-note">{{ resolveLocalizedText(item.note) }}</p>
      </div>
    </div>

    <button
      v-if="!loading && audits.length > 6"
      type="button"
      class="btn-expand-timeline"
      @click="isExpanded = !isExpanded"
    >
      {{ isExpanded ? t('admin.membership.collapseHistoryBtn') : t('admin.membership.expandHistoryBtn') }}
    </button>
  </div>
</template>

<style scoped>
.version-timeline-wrapper { display: flex; flex-direction: column; gap: 16px; }
.timeline-header-card { display: flex; justify-content: space-between; align-items: center; background: var(--surface); border: 1px solid var(--outline); border-radius: 20px; padding: 20px 24px; }
:global(html.dark) .timeline-header-card { background: #181a20; border-color: #2a2d36; }
.title-with-icon { display: flex; align-items: center; gap: 14px; }
.header-icon { font-size: 28px; color: var(--pink-dark, #db2777); }
.card-title { margin: 0; font-size: 16px; font-weight: 800; color: var(--ink); }
.card-desc { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }
.audits-count-pill { font-size: 11.5px; font-weight: 700; padding: 3px 10px; border-radius: 9999px; background: rgba(0, 0, 0, 0.08); color: var(--muted); }
:global(html.dark) .audits-count-pill { background: #252830; }
.timeline-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.version-card { display: flex; flex-direction: column; gap: 10px; background: var(--surface); border: 1px solid var(--outline); border-radius: 18px; padding: 18px; transition: transform 0.2s, box-shadow 0.2s; }
:global(html.dark) .version-card { background: #181a20; border-color: #2a2d36; }
.version-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05); }
.version-card.is-archived { opacity: 0.65; }
.version-card-head { display: flex; justify-content: space-between; align-items: center; }
.version-title-block { display: flex; align-items: center; gap: 8px; }
.ver-plan-name { font-size: 15px; font-weight: 800; color: var(--ink); }
.ver-badge { font-size: 11px; font-weight: 800; padding: 2px 7px; border-radius: 6px; background: rgba(219, 39, 119, 0.12); color: var(--pink-dark, #db2777); font-family: monospace; }
.status-badge { font-size: 10.5px; font-weight: 700; padding: 2px 7px; border-radius: 6px; }
.status-badge.active { background: rgba(16, 185, 129, 0.12); color: #10b981; }
.status-badge.archived { background: var(--surface-container-high, #e5e7eb); color: var(--muted); }
:global(html.dark) .status-badge.archived { background: #252830; }
.version-meta-row { display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: var(--muted); flex-wrap: wrap; gap: 6px; }
.subscribers-pill { display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: var(--pink-dark, #db2777); }
.changes-chip-list { display: flex; flex-direction: column; gap: 4px; background: var(--surface-container-high, #f8f9fa); border-radius: 10px; padding: 8px 12px; }
:global(html.dark) .changes-chip-list { background: #1e2129; }
.change-tag { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ink); }
:global(html.dark) .change-tag { color: #d1d5db; }
.ver-note { margin: 0; font-size: 11.5px; font-style: italic; color: var(--muted); }
.btn-expand-timeline { padding: 10px; border-radius: 12px; border: 1px solid var(--outline); background: var(--surface); color: var(--muted); font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-expand-timeline:hover { color: var(--ink); background: var(--surface-container-high, #f3f4f6); }
:global(html.dark) .btn-expand-timeline { background: #181a20; border-color: #2a2d36; }
.empty-timeline-box { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px; background: var(--surface); border: 1px dashed var(--outline); border-radius: 20px; }
.empty-icon { font-size: 40px; color: var(--muted); margin-bottom: 8px; }
.empty-text { margin: 0; font-size: 13.5px; color: var(--muted); }
.text-pink { color: var(--pink-dark, #db2777); }
.icon-14 { font-size: 14px; }
.my-2 { margin: 8px 0; }
.mt-2 { margin-top: 8px; }
@media (max-width: 800px) { .timeline-grid { grid-template-columns: 1fr; } }
</style>

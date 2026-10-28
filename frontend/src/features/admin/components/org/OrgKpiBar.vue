<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'

defineProps<{
  totalVenues: number
  totalCourts: number
  totalMembers: number
  ownersCount: number
  staffCount: number
  autoApprove: boolean
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="org-kpi-bar">
    <template v-if="loading">
      <div v-for="i in 4" :key="i" class="kpi-card skeleton-card">
        <SkeletonLoader type="rect" height="74px" />
      </div>
    </template>

    <template v-else>
      <!-- KPI 1: Venues -->
      <div class="kpi-card">
        <div class="kpi-icon-box pink">
          <span class="material-symbols-outlined kpi-icon">location_on</span>
        </div>
        <div class="kpi-meta">
          <span class="kpi-label">{{ t('admin.org.kpiVenues') }}</span>
          <div class="kpi-val-row">
            <span class="kpi-number">{{ totalVenues }}</span>
            <span class="kpi-unit">{{
              t('admin.org.tabVenues', { count: totalVenues }).split(' ')[0]
            }}</span>
          </div>
        </div>
      </div>

      <!-- KPI 2: Active Courts -->
      <div class="kpi-card">
        <div class="kpi-icon-box emerald">
          <span class="material-symbols-outlined kpi-icon">sports_tennis</span>
        </div>
        <div class="kpi-meta">
          <span class="kpi-label">{{ t('admin.org.kpiCourts') }}</span>
          <div class="kpi-val-row">
            <span class="kpi-number">{{ totalCourts }}</span>
            <span class="kpi-unit">sân</span>
          </div>
        </div>
      </div>

      <!-- KPI 3: Management & Staff -->
      <div class="kpi-card">
        <div class="kpi-icon-box blue">
          <span class="material-symbols-outlined kpi-icon">group</span>
        </div>
        <div class="kpi-meta">
          <span class="kpi-label">{{ t('admin.org.kpiTeam') }}</span>
          <div class="kpi-val-row">
            <span class="kpi-number">{{ totalMembers }}</span>
            <span class="kpi-badge-sub"> {{ ownersCount }} O · {{ staffCount }} S </span>
          </div>
        </div>
      </div>

      <!-- KPI 4: Auto-Approve Mode -->
      <div class="kpi-card">
        <div class="kpi-icon-box purple">
          <span class="material-symbols-outlined kpi-icon">bolt</span>
        </div>
        <div class="kpi-meta">
          <span class="kpi-label">{{ t('admin.org.kpiAutoApprove') }}</span>
          <div class="kpi-val-row">
            <span class="kpi-status-tag" :class="autoApprove ? 'tag-enabled' : 'tag-disabled'">
              {{ autoApprove ? t('admin.org.enabled') : t('admin.org.disabled') }}
            </span>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.org-kpi-bar {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  width: 100%;
}

@media (max-width: 1024px) {
  .org-kpi-bar {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 640px) {
  .org-kpi-bar {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 440px) {
  .org-kpi-bar {
    grid-template-columns: 1fr;
  }
}

.kpi-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 16px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
  transition:
    transform 0.2s ease,
    border-color 0.2s ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  border-color: var(--pink-dark, #db2777);
}

.skeleton-card {
  padding: 0;
  border: 0;
  background: transparent;
}

.kpi-icon-box {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-icon {
  font-size: 24px;
}

.kpi-icon-box.pink {
  background: rgba(219, 39, 119, 0.12);
  color: #db2777;
}

.kpi-icon-box.emerald {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.kpi-icon-box.blue {
  background: rgba(37, 99, 235, 0.12);
  color: #2563eb;
}

.kpi-icon-box.purple {
  background: rgba(147, 51, 234, 0.12);
  color: #9333ea;
}

.kpi-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.kpi-label {
  font-size: 11.5px;
  font-weight: 600;
  color: var(--muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.kpi-val-row {
  display: flex;
  align-items: baseline;
  gap: 6px;
}

.kpi-number {
  font-size: 20px;
  font-weight: 800;
  line-height: 1.1;
  color: var(--ink);
  font-family: inherit;
}

.kpi-unit {
  font-size: 11.5px;
  color: var(--muted);
  font-weight: 600;
}

.kpi-badge-sub {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  white-space: nowrap;
}

.kpi-status-tag {
  font-size: 12px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
}

.tag-enabled {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.tag-disabled {
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
}

:global(html.dark) .kpi-card {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .kpi-card:hover {
  border-color: var(--pink-dark, #e58e9f);
}

:global(html.dark) .kpi-label {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .kpi-number {
  color: var(--ink, #faf7f3);
}

:global(html.dark) .kpi-unit {
  color: var(--muted, #a89fa4);
}

:global(html.dark) .kpi-badge-sub {
  color: var(--on-surface-variant, #c4b9be);
}
</style>

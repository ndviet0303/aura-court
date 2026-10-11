<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency, resolveLocalizedText } from '@/i18n'
import { AuraButton, SkeletonLoader } from '@/shared/components'
import type { MembershipPlan } from '../../types/membership'

const props = defineProps<{
  plans: MembershipPlan[]
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'edit-plan', plan: MembershipPlan): void
  (e: 'manage-version', plan: MembershipPlan): void
}>()

const { t } = useI18n()

function getCycleLabel(cycle: string) {
  if (cycle === 'quarterly') return t('admin.membership.cycleQuarterSuffix')
  if (cycle === 'yearly') return t('admin.membership.cycleYearSuffix')
  return t('admin.membership.cycleMonthSuffix')
}

function getTierBadgeClass(plan: MembershipPlan) {
  const code = (plan.code || '').toUpperCase()
  if (code.includes('DIAMOND') || code.includes('KC')) return 'tier-diamond'
  if (code.includes('GOLD') || code.includes('VANG')) return 'tier-gold'
  if (code.includes('PLATINUM') || code.includes('BACHKIM')) return 'tier-platinum'
  return 'tier-silver'
}
</script>

<template>
  <div class="plans-grid-wrapper">
    <div class="grid-header-row">
      <div class="title-meta">
        <h2 class="grid-title">{{ t('admin.membership.currentPlansTitle') }}</h2>
        <span v-if="!loading" class="count-pill">{{
          t('admin.membership.activePlansCount', { count: plans.length })
        }}</span>
      </div>
    </div>

    <!-- Skeleton Loading -->
    <div v-if="loading" class="vip-plans-grid">
      <div v-for="i in 3" :key="i" class="vip-plan-card skeleton-card">
        <div class="card-head">
          <SkeletonLoader height="22px" width="55%" />
          <SkeletonLoader height="32px" width="32px" radius="9999px" />
        </div>
        <SkeletonLoader height="12px" width="80%" class="mt-2" />
        <SkeletonLoader height="32px" width="45%" class="my-3" />
        <div class="skeleton-perks">
          <SkeletonLoader height="14px" width="90%" />
          <SkeletonLoader height="14px" width="75%" />
        </div>
        <div class="card-actions-row mt-auto pt-3">
          <SkeletonLoader height="36px" width="48%" />
          <SkeletonLoader height="36px" width="48%" />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!plans.length" class="empty-state-box">
      <span class="material-symbols-outlined empty-icon">workspace_premium</span>
      <p class="empty-text">{{ t('admin.membership.noPlansFound') }}</p>
    </div>

    <!-- VIP Cards Grid -->
    <div v-else class="vip-plans-grid">
      <div
        v-for="plan in plans"
        :key="plan.id"
        class="vip-plan-card"
        :class="[getTierBadgeClass(plan), { 'is-popular': plan.isPopular }]"
      >
        <div class="card-accent-bar"></div>
        <div v-if="plan.isPopular" class="popular-glow"></div>

        <div class="card-head">
          <div class="plan-header-info">
            <div class="plan-title-row">
              <h3 class="plan-name">{{ resolveLocalizedText(plan.name) }}</h3>
            </div>
            <div class="plan-meta-tags">
              <span class="version-chip">{{ plan.version }}</span>
              <span class="status-chip">{{
                plan.status === 'active'
                  ? t('admin.membership.activeStatus')
                  : t('admin.membership.archivedStatus')
              }}</span>
              <span v-if="plan.isPopular" class="popular-chip">BEST VALUE</span>
            </div>
          </div>
          <div class="tier-icon-circle">
            <span class="material-symbols-outlined icon-22">{{
              plan.isPopular ? 'workspace_premium' : 'military_tech'
            }}</span>
          </div>
        </div>

        <p v-if="plan.description" class="plan-desc">
          {{ resolveLocalizedText(plan.description) }}
        </p>

        <div class="card-price-section">
          <span class="price-amount">{{ formatCurrency(plan.price) }}</span>
          <span class="price-cycle">{{ getCycleLabel(plan.billingCycle) }}</span>
        </div>

        <div class="perks-container">
          <ul class="perks-list">
            <li v-for="(perk, idx) in plan.perks" :key="idx" class="perk-row">
              <span class="material-symbols-outlined perk-icon">check_circle</span>
              <span class="perk-label">{{ resolveLocalizedText(perk) }}</span>
            </li>
          </ul>
        </div>

        <div class="card-actions-row">
          <AuraButton
            variant="secondary"
            size="sm"
            class="action-btn"
            @click="emit('manage-version', plan)"
          >
            <span class="material-symbols-outlined icon-16">history</span>
            <span>{{ t('admin.membership.manageVersionBtn') }}</span>
          </AuraButton>
          <AuraButton
            variant="primary"
            size="sm"
            class="action-btn"
            @click="emit('edit-plan', plan)"
          >
            <span class="material-symbols-outlined icon-16">edit</span>
            <span>{{ t('admin.membership.editPlanBtn') }}</span>
          </AuraButton>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.plans-grid-wrapper {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.grid-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.title-meta {
  display: flex;
  align-items: center;
  gap: 12px;
}
.grid-title {
  margin: 0;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.01em;
}
.count-pill {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 9999px;
  background: var(--surface-container-high, #e5e7eb);
  color: var(--muted);
}
:global(html.dark) .count-pill {
  background: #252830;
  color: #9ca3af;
}
.vip-plans-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}
.vip-plan-card {
  position: relative;
  border-radius: 20px;
  padding: 22px;
  background: var(--surface);
  border: 1px solid var(--outline);
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  transition:
    transform 0.25s,
    box-shadow 0.25s,
    border-color 0.25s;
  overflow: hidden;
}
:global(html.dark) .vip-plan-card {
  background: #181a20;
  border-color: #2a2d36;
}
.vip-plan-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.08);
}
:global(html.dark) .vip-plan-card:hover {
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.4);
  border-color: #3b3f4c;
}
.vip-plan-card.is-popular {
  border-color: rgba(219, 39, 119, 0.4);
}
.card-accent-bar {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: transparent;
}
.tier-gold .card-accent-bar {
  background: linear-gradient(90deg, #f59e0b, #d97706);
}
.tier-diamond .card-accent-bar {
  background: linear-gradient(90deg, #06b6d4, #3b82f6);
}
.tier-platinum .card-accent-bar {
  background: linear-gradient(90deg, #8b5cf6, #ec4899);
}
.tier-silver .card-accent-bar {
  background: linear-gradient(90deg, #9ca3af, #6b7280);
}
.popular-glow {
  position: absolute;
  top: -30px;
  right: -30px;
  width: 140px;
  height: 140px;
  border-radius: 9999px;
  background: rgba(219, 39, 119, 0.12);
  filter: blur(32px);
  pointer-events: none;
}
.card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 12px;
}
.plan-header-info {
  flex: 1;
}
.plan-title-row {
  margin-bottom: 6px;
}
.plan-name {
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.01em;
}
.plan-meta-tags {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-wrap: wrap;
}
.version-chip,
.status-chip {
  font-size: 10.5px;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 6px;
  background: var(--surface-container-high, #f3f4f6);
  color: var(--muted);
}
:global(html.dark) .version-chip,
:global(html.dark) .status-chip {
  background: #23262f;
  color: #9ca3af;
}
.popular-chip {
  font-size: 10px;
  font-weight: 800;
  padding: 2px 7px;
  border-radius: 6px;
  background: rgba(219, 39, 119, 0.15);
  color: var(--pink-dark, #db2777);
  letter-spacing: 0.03em;
}
:global(html.dark) .popular-chip {
  color: #f472b6;
}
.tier-icon-circle {
  width: 38px;
  height: 38px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  background: var(--surface-container-high, #f3f4f6);
  color: var(--muted);
}
.tier-gold .tier-icon-circle {
  background: rgba(245, 158, 11, 0.15);
  color: #d97706;
}
:global(html.dark) .tier-gold .tier-icon-circle {
  color: #fbbf24;
}
.tier-diamond .tier-icon-circle {
  background: rgba(6, 182, 212, 0.15);
  color: #0284c7;
}
:global(html.dark) .tier-diamond .tier-icon-circle {
  color: #38bdf8;
}
.tier-platinum .tier-icon-circle {
  background: rgba(219, 39, 119, 0.15);
  color: #db2777;
}
.plan-desc {
  margin: 0 0 16px 0;
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--muted);
  min-height: 36px;
}
.card-price-section {
  display: flex;
  align-items: baseline;
  gap: 6px;
  margin-bottom: 16px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--outline);
}
:global(html.dark) .card-price-section {
  border-bottom-color: #262932;
}
.price-amount {
  font-size: 22px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.02em;
}
.price-cycle {
  font-size: 12px;
  color: var(--muted);
}
.perks-container {
  flex: 1;
  margin-bottom: 18px;
}
.perks-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 9px;
}
.perk-row {
  display: flex;
  align-items: flex-start;
  gap: 8px;
}
.perk-icon {
  font-size: 16px;
  color: #10b981;
  margin-top: 1px;
  flex-shrink: 0;
}
.perk-label {
  font-size: 12.5px;
  color: var(--ink);
  line-height: 1.4;
}
:global(html.dark) .perk-label {
  color: #d1d5db;
}
.card-actions-row {
  display: flex;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
}
:global(html.dark) .card-actions-row {
  border-top-color: #262932;
}
.action-btn {
  flex: 1;
  justify-content: center;
}
.empty-state-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  background: var(--surface);
  border: 1px dashed var(--outline);
  border-radius: 20px;
  text-align: center;
}
.empty-icon {
  font-size: 44px;
  color: var(--muted);
  margin-bottom: 12px;
}
.empty-text {
  margin: 0;
  font-size: 14px;
  color: var(--muted);
}
.skeleton-perks {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 12px 0;
}
.mt-2 {
  margin-top: 8px;
}
.my-3 {
  margin-top: 12px;
  margin-bottom: 12px;
}
.mt-auto {
  margin-top: auto;
}
.pt-3 {
  padding-top: 12px;
}
.icon-22 {
  font-size: 22px;
}
.icon-16 {
  font-size: 16px;
}
@media (max-width: 1200px) {
  .vip-plans-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 680px) {
  .vip-plans-grid {
    grid-template-columns: 1fr;
  }
}
</style>

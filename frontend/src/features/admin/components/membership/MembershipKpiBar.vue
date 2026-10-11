<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { formatCurrency } from '@/i18n'
import { SkeletonLoader } from '@/shared/components'
import type { MembershipKpiStats } from '../../types/membership'

defineProps<{
  stats: MembershipKpiStats
  loading?: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <div class="membership-kpi-grid">
    <!-- VIP Members -->
    <div class="kpi-card gold-accent">
      <div class="kpi-icon-box gold">
        <span class="material-symbols-outlined icon-22">workspace_premium</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">{{ t('admin.membership.kpiTotalMembers') }}</span>
        <div v-if="loading" class="kpi-skel-wrap">
          <SkeletonLoader height="22px" width="110px" />
          <SkeletonLoader height="12px" width="80px" />
        </div>
        <template v-else>
          <strong class="kpi-value">{{
            t('admin.membership.kpiTotalMembersCount', { count: stats.totalVipMembers })
          }}</strong>
          <span class="kpi-trend positive">
            <span class="material-symbols-outlined icon-14">trending_up</span>
            {{ t('admin.membership.kpiTotalMembersTrend') }}
          </span>
        </template>
      </div>
    </div>

    <!-- MRR -->
    <div class="kpi-card rose-accent">
      <div class="kpi-icon-box rose">
        <span class="material-symbols-outlined icon-22">payments</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">{{ t('admin.membership.kpiMrr') }}</span>
        <div v-if="loading" class="kpi-skel-wrap">
          <SkeletonLoader height="22px" width="130px" />
          <SkeletonLoader height="12px" width="90px" />
        </div>
        <template v-else>
          <strong class="kpi-value text-rose">{{
            formatCurrency(stats.monthlyRecurringRevenue)
          }}</strong>
          <span class="kpi-trend positive">
            <span class="material-symbols-outlined icon-14">autorenew</span>
            {{ t('admin.membership.kpiMrrTrend') }}
          </span>
        </template>
      </div>
    </div>

    <!-- Points Issued -->
    <div class="kpi-card purple-accent">
      <div class="kpi-icon-box purple">
        <span class="material-symbols-outlined icon-22">loyalty</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">{{ t('admin.membership.kpiPointsIssued') }}</span>
        <div v-if="loading" class="kpi-skel-wrap">
          <SkeletonLoader height="22px" width="110px" />
          <SkeletonLoader height="12px" width="80px" />
        </div>
        <template v-else>
          <strong class="kpi-value"
            >{{ stats.totalPointsIssued.toLocaleString() }}
            <span class="unit-sub">{{ t('admin.membership.pointsUnit') }}</span></strong
          >
          <span class="kpi-trend neutral">
            <span class="material-symbols-outlined icon-14">percent</span>
            {{ t('admin.membership.kpiPointsIssuedTrend') }}
          </span>
        </template>
      </div>
    </div>

    <!-- Redemption Rate -->
    <div class="kpi-card mint-accent">
      <div class="kpi-icon-box mint">
        <span class="material-symbols-outlined icon-22">redeem</span>
      </div>
      <div class="kpi-info">
        <span class="kpi-label">{{ t('admin.membership.kpiRedemptionRate') }}</span>
        <div v-if="loading" class="kpi-skel-wrap">
          <SkeletonLoader height="22px" width="80px" />
          <SkeletonLoader height="12px" width="100px" />
        </div>
        <template v-else>
          <strong class="kpi-value text-mint">{{ stats.redemptionRatePercent }}%</strong>
          <span class="kpi-trend positive">
            <span class="material-symbols-outlined icon-14">card_giftcard</span>
            {{ t('admin.membership.kpiRedemptionRateTrend') }}
          </span>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.membership-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
}
.kpi-card {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px 20px;
  border-radius: 20px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}
:global(html.dark) .kpi-card {
  background: #181a20;
  border-color: #2a2d36;
}
.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}
:global(html.dark) .kpi-card:hover {
  box-shadow: 0 10px 28px rgba(0, 0, 0, 0.35);
}
.kpi-icon-box {
  width: 46px;
  height: 46px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-icon-box.gold {
  background: rgba(245, 158, 11, 0.15);
  color: #d97706;
}
:global(html.dark) .kpi-icon-box.gold {
  color: #fbbf24;
}
.kpi-icon-box.rose {
  background: rgba(219, 39, 119, 0.15);
  color: var(--pink-dark, #db2777);
}
:global(html.dark) .kpi-icon-box.rose {
  color: #f472b6;
}
.kpi-icon-box.purple {
  background: rgba(139, 92, 246, 0.15);
  color: #8b5cf6;
}
:global(html.dark) .kpi-icon-box.purple {
  color: #a78bfa;
}
.kpi-icon-box.mint {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
}
:global(html.dark) .kpi-icon-box.mint {
  color: #34d399;
}
.kpi-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  flex: 1;
}
.kpi-label {
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.kpi-value {
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.01em;
}
.unit-sub {
  font-size: 12px;
  font-weight: 600;
  color: var(--muted);
}
.text-rose {
  color: var(--pink-dark, #db2777);
}
.text-mint {
  color: #10b981;
}
.kpi-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
}
.kpi-trend.positive {
  color: #10b981;
}
.kpi-trend.neutral {
  color: var(--muted);
}
.kpi-skel-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 2px;
}
.icon-22 {
  font-size: 22px;
}
.icon-14 {
  font-size: 14px;
}
@media (max-width: 1100px) {
  .membership-kpi-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 600px) {
  .membership-kpi-grid {
    grid-template-columns: 1fr;
  }
}
</style>

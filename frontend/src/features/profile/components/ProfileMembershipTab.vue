<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { User, Membership } from '@/core/types/common'

const props = defineProps<{
  user: User | null
  membership: Membership | null
}>()

const activeTierName = computed(() => {
  if (props.membership?.plan?.name) return props.membership.plan.name
  if (props.user?.role === 'admin') return 'AURA PLATINUM LEADER'
  return 'GOLD ATHLETE MEMBER'
})

const discountPercent = computed(() => {
  if (props.membership?.plan?.discount_percent) return props.membership.plan.discount_percent
  return 10
})
</script>

<template>
  <div class="profile-tab-card">
    <div class="section-title-row">
      <div class="title-with-icon">
        <span class="material-symbols-outlined title-icon">workspace_premium</span>
        <div>
          <h2 class="section-heading">{{ $t('profile.vipTitle') }}</h2>
          <p class="section-sub">{{ $t('profile.vipSubtitle') }}</p>
        </div>
      </div>
    </div>

    <!-- Active Tier Highlight Card -->
    <div class="tier-highlight-card">
      <div class="tier-card-header">
        <div class="tier-badge-group">
          <span class="material-symbols-outlined tier-crown-icon">military_tech</span>
          <span class="tier-title font-mono">{{ activeTierName }}</span>
        </div>
        <div class="tier-points-pill font-mono">
          <span class="material-symbols-outlined pt-icon">toll</span>
          <span>1,250 Aura Points</span>
        </div>
      </div>

      <div class="tier-benefit-highlight">
        <div class="discount-callout">
          <span class="discount-value font-mono">-{{ discountPercent }}%</span>
          <span class="discount-label">{{ $t('profile.vipDiscountCallout') }}</span>
        </div>
        <div class="tier-status-meta">
          <span class="status-active-pill">
            <span class="status-dot"></span>
            {{ $t('wallet.activeCard') }}
          </span>
          <span v-if="membership?.ends_at" class="expiry-text">
            {{ $t('profile.vipExpires') }}: {{ new Date(membership.ends_at).toLocaleDateString() }}
          </span>
          <span v-else class="expiry-text">
            {{ $t('profile.vipTier') }}
          </span>
        </div>
      </div>
    </div>

    <!-- Perks Grid -->
    <div class="perks-section">
      <h3 class="perks-heading font-mono">{{ $t('profile.vipBadge') }}:</h3>
      <div class="perks-grid">
        <div class="perk-card">
          <div class="perk-icon-box">
            <span class="material-symbols-outlined">event_available</span>
          </div>
          <div class="perk-content">
            <strong class="perk-title">{{ $t('profile.perk1') }}</strong>
            <p class="perk-desc">{{ $t('profile.vipSubtitle') }}</p>
          </div>
        </div>

        <div class="perk-card">
          <div class="perk-icon-box">
            <span class="material-symbols-outlined">support_agent</span>
          </div>
          <div class="perk-content">
            <strong class="perk-title">{{ $t('profile.perk2') }}</strong>
            <p class="perk-desc">{{ $t('profile.perk2Desc') }}</p>
          </div>
        </div>

        <div class="perk-card">
          <div class="perk-icon-box">
            <span class="material-symbols-outlined">toll</span>
          </div>
          <div class="perk-content">
            <strong class="perk-title">{{ $t('profile.perk3') }}</strong>
            <p class="perk-desc">{{ $t('profile.vipPointsDesc') }}</p>
          </div>
        </div>

        <div class="perk-card">
          <div class="perk-icon-box">
            <span class="material-symbols-outlined">checkroom</span>
          </div>
          <div class="perk-content">
            <strong class="perk-title">{{ $t('profile.perk4') }}</strong>
            <p class="perk-desc">
              {{ $t('courtDetail.amenityLockers') }} & {{ $t('courtDetail.amenityShower') }}
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Quick Navigation -->
    <div class="tier-footer-cta">
      <RouterLink to="/wallet" class="tier-cta-btn">
        <span class="material-symbols-outlined">account_balance_wallet</span>
        <span>{{ $t('profile.scWalletTitle') }}</span>
      </RouterLink>
      <RouterLink to="/booking" class="tier-cta-btn secondary">
        <span class="material-symbols-outlined">calendar_month</span>
        <span>{{ $t('profile.bookCourtNow') }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.profile-tab-card {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 28px;
  border-radius: 24px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface, #ffffff);
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
  overflow: hidden;
}

html.dark .profile-tab-card {
  background: var(--surface, #1e191d);
  border-color: var(--outline, #352c32);
}

.section-title-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.title-with-icon {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.title-icon {
  font-size: 26px;
  color: var(--primary, #6b2d3f);
  margin-top: 2px;
}

html.dark .title-icon {
  color: #dfba72;
}

.section-heading {
  margin: 0;
  font-size: 18px;
  font-weight: 750;
  color: var(--ink, #1c1618);
}

html.dark .section-heading {
  color: #faf7f3;
}

.section-sub {
  margin: 4px 0 0;
  font-size: 13.5px;
  color: var(--muted, #6e6668);
}

/* Tier Card */
.tier-highlight-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 22px 24px;
  border-radius: 18px;
  background: linear-gradient(135deg, rgba(107, 45, 63, 0.08) 0%, rgba(219, 39, 119, 0.04) 100%);
  border: 1px solid rgba(107, 45, 63, 0.18);
  box-sizing: border-box;
  width: 100%;
  min-width: 0;
}

html.dark .tier-highlight-card {
  background: linear-gradient(135deg, rgba(223, 186, 114, 0.12) 0%, rgba(223, 186, 114, 0.04) 100%);
  border-color: rgba(223, 186, 114, 0.28);
}

.tier-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}

.tier-badge-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.tier-crown-icon {
  font-size: 24px;
  color: var(--primary, #6b2d3f);
}

html.dark .tier-crown-icon {
  color: #dfba72;
}

.tier-title {
  font-size: 16px;
  font-weight: 800;
  letter-spacing: 0.04em;
  color: var(--ink, #1c1618);
}

html.dark .tier-title {
  color: #faf7f3;
}

.tier-points-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 12px;
  border-radius: 999px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2dad0);
  font-size: 13px;
  font-weight: 750;
  color: var(--pink-dark, #db2777);
}

html.dark .tier-points-pill {
  background: #141113;
  border-color: #3b3238;
  color: #dfba72;
}

.pt-icon {
  font-size: 16px;
}

.tier-benefit-highlight {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 16px;
  padding-top: 14px;
  border-top: 1px dashed rgba(107, 45, 63, 0.15);
}

html.dark .tier-benefit-highlight {
  border-top-color: rgba(223, 186, 114, 0.2);
}

.discount-callout {
  display: flex;
  flex-direction: column;
}

.discount-value {
  font-size: 32px;
  font-weight: 900;
  line-height: 1;
  color: var(--primary, #6b2d3f);
}

html.dark .discount-value {
  color: #dfba72;
}

.discount-label {
  font-size: 12.5px;
  color: var(--muted, #6e6668);
  margin-top: 4px;
}

.tier-status-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.status-active-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 6px;
  background: rgba(16, 185, 129, 0.12);
  color: #059669;
  font-size: 12px;
  font-weight: 700;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #10b981;
}

.expiry-text {
  font-size: 12px;
  color: var(--muted, #6e6668);
}

/* Perks */
.perks-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.perks-heading {
  font-size: 11px;
  font-weight: 750;
  color: var(--muted, #6e6668);
  letter-spacing: 0.05em;
  margin: 0;
}

.perks-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}

.perk-card {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline, #e2dad0);
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  box-sizing: border-box;
  min-width: 0;
  width: 100%;
}

html.dark .perk-card {
  background: rgba(255, 255, 255, 0.03);
  border-color: #352c32;
}

.perk-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  background: rgba(107, 45, 63, 0.08);
  color: var(--primary, #6b2d3f);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

html.dark .perk-icon-box {
  background: rgba(223, 186, 114, 0.15);
  color: #dfba72;
}

.perk-icon-box .material-symbols-outlined {
  font-size: 20px;
}

.perk-content {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
  overflow: hidden;
}

.perk-title {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink, #1c1618);
}

html.dark .perk-title {
  color: #faf7f3;
}

.perk-desc {
  margin: 0;
  font-size: 12px;
  color: var(--muted, #6e6668);
  line-height: 1.4;
}

.tier-footer-cta {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 14px;
  border-top: 1px solid var(--outline, #e2dad0);
}

.tier-cta-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  background: var(--primary, #6b2d3f);
  color: #ffffff;
  font-size: 13.5px;
  font-weight: 650;
  text-decoration: none;
  transition: all 0.2s ease;
}

html.dark .tier-cta-btn {
  background: #dfba72;
  color: #1c1618;
}

.tier-cta-btn.secondary {
  background: var(--surface-container, rgba(0, 0, 0, 0.04));
  color: var(--ink, #1c1618);
  border: 1px solid var(--outline, #e2dad0);
}

html.dark .tier-cta-btn.secondary {
  background: rgba(255, 255, 255, 0.05);
  color: #faf7f3;
  border-color: #3b3238;
}

@media (max-width: 640px) {
  .profile-tab-card {
    padding: 20px 16px;
  }
  .perks-grid {
    grid-template-columns: 1fr;
  }
  .tier-footer-cta {
    flex-direction: column;
    align-items: stretch;
  }
  .tier-cta-btn {
    justify-content: center;
  }
}
</style>

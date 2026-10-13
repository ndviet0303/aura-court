<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="brand-panel">
    <div class="brand-glow" aria-hidden="true"></div>
    <div class="brand-content">
      <div class="brand-badge">
        <span class="material-symbols-outlined icon-glow">sports_tennis</span>
        <span>AURA ATHLETICS CLUB</span>
      </div>

      <h2 class="brand-title">
        {{ t('auth.clubAuraTitle') }}
      </h2>
      <p class="brand-subtitle">
        {{ t('auth.clubAuraSubtitle') }}
      </p>

      <!-- Key Features / Perks List -->
      <div class="perks-list">
        <div class="perk-item">
          <div class="perk-icon-box">
            <span class="material-symbols-outlined">bolt</span>
          </div>
          <div class="perk-text">
            <h4>{{ t('auth.perk1Title') }}</h4>
            <p>{{ t('auth.perk1Desc') }}</p>
          </div>
        </div>

        <div class="perk-item">
          <div class="perk-icon-box">
            <span class="material-symbols-outlined">qr_code_2</span>
          </div>
          <div class="perk-text">
            <h4>{{ t('auth.perk2Title') }}</h4>
            <p>{{ t('auth.perk2Desc') }}</p>
          </div>
        </div>

        <div class="perk-item">
          <div class="perk-icon-box">
            <span class="material-symbols-outlined">verified</span>
          </div>
          <div class="perk-text">
            <h4>{{ t('auth.perk3Title') }}</h4>
            <p>{{ t('auth.perk3Desc') }}</p>
          </div>
        </div>
      </div>

      <!-- Community Trust & Member Privilege Card -->
      <div class="community-trust-card">
        <div class="trust-header">
          <div class="avatar-stack">
            <div class="avatar-circle av-1">
              <span class="material-symbols-outlined av-icon">sports_tennis</span>
            </div>
            <div class="avatar-circle av-2">
              <span class="material-symbols-outlined av-icon">fitness_center</span>
            </div>
            <div class="avatar-circle av-3">
              <span class="material-symbols-outlined av-icon">emoji_events</span>
            </div>
            <span class="avatar-count">+3.5k</span>
          </div>
          <div class="rating-badge">
            <span class="material-symbols-outlined star-icon">star</span>
            <strong>4.9 / 5.0</strong>
            <span class="rating-label">TrustScore</span>
          </div>
        </div>

        <div class="trust-content">
          <h4>{{ t('auth.trustCommunityTitle') }}</h4>
          <p>{{ t('auth.trustCommunityDesc') }}</p>
        </div>

        <div class="trust-tags">
          <span class="trust-tag">
            <span class="dot-online"></span> {{ t('auth.tagInstantBooking') }}
          </span>
          <span class="trust-tag">
            <span class="material-symbols-outlined tag-icon">leaderboard</span>
            {{ t('auth.tagLeaderboard') }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.brand-panel {
  background: linear-gradient(
    145deg,
    rgba(201, 161, 90, 0.12) 0%,
    rgba(107, 45, 63, 0.05) 50%,
    #faf7f3 100%
  );
  border-right: 1px solid var(--border, #ebdccb);
  padding: 44px 38px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
}

html.dark .brand-panel {
  background: linear-gradient(
    145deg,
    rgba(74, 29, 43, 0.5) 0%,
    rgba(30, 25, 28, 0.95) 50%,
    #171315 100%
  );
  border-color: #382f35;
}

.brand-glow {
  position: absolute;
  top: -20%;
  left: -20%;
  width: 280px;
  height: 280px;
  background: radial-gradient(
    circle,
    rgba(223, 186, 114, 0.25) 0%,
    rgba(107, 45, 63, 0.2) 60%,
    transparent 80%
  );
  filter: blur(50px);
  pointer-events: none;
}

.brand-content {
  position: relative;
  z-index: 1;
}

.brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(201, 161, 90, 0.15);
  color: #8f651b;
  border: 1px solid rgba(201, 161, 90, 0.35);
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.1em;
  margin-bottom: 20px;
  width: fit-content;
}

html.dark .brand-badge {
  background: rgba(223, 186, 114, 0.18);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
}

.icon-glow {
  font-size: 16px;
}

.brand-title {
  font-size: clamp(24px, 2.4vw, 30px);
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--ink, #191c1d);
  margin: 0 0 8px 0;
}

.brand-subtitle {
  font-size: 13.5px;
  line-height: 1.55;
  color: var(--muted, #6e6668);
  margin: 0 0 26px 0;
}

.perks-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 26px;
}

.perk-item {
  display: flex;
  align-items: flex-start;
  gap: 14px;
}

.perk-icon-box {
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: #ffffff;
  border: 1px solid #ebdccb;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #6b2d3f;
  flex-shrink: 0;
  box-shadow: 0 3px 10px rgba(107, 45, 63, 0.06);
}

html.dark .perk-icon-box {
  background: #251e22;
  border-color: rgba(223, 186, 114, 0.25);
  color: #dfba72;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
}

.perk-text h4 {
  font-size: 14px;
  font-weight: 750;
  color: var(--ink, #191c1d);
  margin: 0 0 2px;
}

.perk-text p {
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--muted, #6e6668);
  margin: 0;
}

/* Community Trust & Privilege Card */
.community-trust-card {
  position: relative;
  background: #ffffff;
  border: 1px solid #ebdccb;
  border-radius: 18px;
  padding: 18px 20px;
  box-shadow: 0 6px 24px rgba(107, 45, 63, 0.06);
  display: flex;
  flex-direction: column;
  gap: 12px;
}

html.dark .community-trust-card {
  background: #221a1e;
  border-color: #3d3137;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
}

.trust-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.avatar-stack {
  display: flex;
  align-items: center;
}

.avatar-circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: -8px;
  font-size: 12px;
}

html.dark .avatar-circle {
  border-color: #221a1e;
}

.avatar-circle:first-child {
  margin-left: 0;
}

.av-1 {
  background: #fbe8ec;
  color: #6b2d3f;
}

.av-2 {
  background: #fdf5e6;
  color: #b8860b;
}

.av-3 {
  background: #eef7ee;
  color: #16a34a;
}

.av-icon {
  font-size: 14px;
}

.avatar-count {
  margin-left: 8px;
  font-size: 11px;
  font-weight: 800;
  color: #6b2d3f;
  background: rgba(107, 45, 63, 0.1);
  padding: 2px 8px;
  border-radius: 999px;
}

html.dark .avatar-count {
  color: #dfba72;
  background: rgba(223, 186, 114, 0.15);
}

.rating-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: var(--ink, #191c1d);
}

.star-icon {
  font-size: 16px;
  color: #dfba72;
  font-variation-settings: 'FILL' 1;
}

.rating-label {
  font-size: 10.5px;
  color: var(--muted, #6e6668);
  margin-left: 2px;
}

.trust-content h4 {
  margin: 0 0 4px 0;
  font-size: 13.5px;
  font-weight: 750;
  color: var(--ink, #191c1d);
}

.trust-content p {
  margin: 0;
  font-size: 12px;
  line-height: 1.45;
  color: var(--muted, #6e6668);
}

.trust-tags {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  padding-top: 6px;
  border-top: 1px dashed #ebdccb;
}

html.dark .trust-tags {
  border-color: #382f35;
}

.trust-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  font-weight: 600;
  color: var(--ink, #191c1d);
  background: rgba(201, 161, 90, 0.08);
  padding: 3px 8px;
  border-radius: 6px;
}

html.dark .trust-tag {
  background: rgba(223, 186, 114, 0.1);
  color: #dfba72;
}

.tag-icon {
  font-size: 13px;
  color: #6b2d3f;
}

html.dark .tag-icon {
  color: #dfba72;
}

.dot-online {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #16a34a;
  box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.2);
}

@media (max-width: 900px) {
  .brand-panel {
    display: none !important;
  }
}
</style>

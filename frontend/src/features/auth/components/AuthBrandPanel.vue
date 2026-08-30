<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
</script>

<template>
  <div class="brand-panel">
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
          <h4>Cộng đồng thể thao Aura Club</h4>
          <p>Kết nối hơn 3,500+ vận động viên, tự động ghép kèo đấu ELO và nhận ưu đãi tích điểm thành viên khi đặt sân.</p>
        </div>

        <div class="trust-tags">
          <span class="trust-tag">
            <span class="dot-online"></span> Đặt sân tức thì
          </span>
          <span class="trust-tag">
            <span class="material-symbols-outlined tag-icon">verified_user</span> Bảo chứng hoàn tiền
          </span>
          <span class="trust-tag">
            <span class="material-symbols-outlined tag-icon">leaderboard</span> BXH & Xếp hạng
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.brand-panel {
  background: linear-gradient(145deg, rgba(255, 209, 220, 0.35) 0%, rgba(248, 249, 250, 0.95) 40%, var(--surface-subtle, #f9fafb) 100%);
  border-right: 1px solid var(--border, var(--outline, #e2e8f0));
  padding: 40px 36px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
}

html.dark .brand-panel {
  background: linear-gradient(145deg, rgba(120, 85, 94, 0.3) 0%, rgba(34, 36, 42, 0.95) 40%, var(--surface-subtle, #1e293b) 100%);
}

.brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(120, 85, 94, 0.12);
  color: var(--pink-dark, #db2777);
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  margin-bottom: 20px;
  width: fit-content;
}

html.dark .brand-badge {
  background: rgba(255, 209, 220, 0.15);
  color: #f8b4c4;
}

.icon-glow {
  font-size: 16px;
}

.brand-title {
  font-size: clamp(24px, 2.4vw, 30px);
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--ink, #0f172a);
  margin: 0 0 8px 0;
}

.brand-subtitle {
  font-size: 13.5px;
  line-height: 1.55;
  color: var(--muted, #64748b);
  margin: 0 0 24px 0;
}

.perks-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
}

.perk-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.perk-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, var(--outline, #e2e8f0));
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--pink-dark, #db2777);
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

html.dark .perk-icon-box {
  color: #f8b4c4;
}

.perk-text h4 {
  font-size: 13.5px;
  font-weight: 700;
  color: var(--ink, #0f172a);
  margin: 0 0 2px;
}

.perk-text p {
  font-size: 12px;
  line-height: 1.45;
  color: var(--muted, #64748b);
  margin: 0;
}

/* Community Trust & Privilege Card */
.community-trust-card {
  position: relative;
  background: var(--surface, #ffffff);
  border: 1px solid var(--border, var(--outline, #e2e8f0));
  border-radius: 16px;
  padding: 16px 18px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
  display: flex;
  flex-direction: column;
  gap: 10px;
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
  border: 2px solid var(--surface, #ffffff);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: -8px;
  font-size: 12px;
}

.avatar-circle:first-child {
  margin-left: 0;
}

.av-1 {
  background: #fce7f3;
  color: #db2777;
}

.av-2 {
  background: #e0e7ff;
  color: #4f46e5;
}

.av-3 {
  background: #fef3c7;
  color: #d97706;
}

.av-icon {
  font-size: 14px;
}

.avatar-count {
  margin-left: 8px;
  font-size: 11px;
  font-weight: 800;
  color: var(--pink-dark, #db2777);
  background: rgba(219, 39, 119, 0.1);
  padding: 2px 7px;
  border-radius: 999px;
}

html.dark .avatar-count {
  color: #f8b4c4;
  background: rgba(248, 180, 196, 0.15);
}

.rating-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  color: var(--ink, #0f172a);
}

.star-icon {
  font-size: 16px;
  color: #f59e0b;
  font-variation-settings: 'FILL' 1;
}

.rating-label {
  font-size: 10.5px;
  color: var(--muted, #64748b);
  margin-left: 2px;
}

.trust-content h4 {
  margin: 0 0 3px 0;
  font-size: 13px;
  font-weight: 700;
  color: var(--ink, #0f172a);
}

.trust-content p {
  margin: 0;
  font-size: 11.5px;
  line-height: 1.45;
  color: var(--muted, #64748b);
}

.trust-tags {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  padding-top: 4px;
  border-top: 1px dashed var(--border, var(--outline, #e2e8f0));
}

.trust-tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 10.5px;
  font-weight: 600;
  color: var(--ink, #0f172a);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  padding: 3px 8px;
  border-radius: 6px;
}

.tag-icon {
  font-size: 12px;
  color: var(--pink-dark, #db2777);
}

html.dark .tag-icon {
  color: #f8b4c4;
}

.dot-online {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}
</style>

<script setup lang="ts">
import { AuraButton } from '@/shared/components'
import type { PartnerPlayer } from '../types/social'

defineProps<{
  partners: PartnerPlayer[]
}>()

const emit = defineEmits<{
  (e: 'connect', partner: PartnerPlayer): void
}>()
</script>

<template>
  <div class="partner-finder-section">
    <div class="partner-section-header">
      <div>
        <h3 class="section-title">Vận Động Viên Tìm Bạn Đấu & Ghép Cặp</h3>
        <p class="section-sub">Tìm kiếm bạn chơi thể thao phù hợp trình độ và khung giờ để tập luyện hoặc thi đấu giải đôi.</p>
      </div>
    </div>

    <div class="partner-grid">
      <article
        v-for="p in partners"
        :key="p.id"
        class="partner-card"
      >
        <div class="partner-card-header">
          <div class="partner-avatar-wrap">
            <span class="avatar-fallback">{{ p.name.charAt(0) }}</span>
            <span v-if="p.is_online" class="online-indicator" title="Đang trực tuyến"></span>
          </div>

          <div class="partner-main-meta">
            <div class="partner-name-row">
              <h4 class="partner-name">{{ p.name }}</h4>
              <span class="reputation-badge" title="Điểm uy tín">
                <span class="material-symbols-outlined rep-ico">verified</span>
                {{ p.reputation_score }}%
              </span>
            </div>
            <span class="partner-sport-level">{{ p.sport_name }} · {{ p.level_label }}</span>
          </div>
        </div>

        <p v-if="p.bio" class="partner-bio">{{ p.bio }}</p>

        <div class="partner-info-box">
          <div class="info-line">
            <span class="material-symbols-outlined info-ico">location_on</span>
            <span class="info-text">{{ p.city }} · Thường chơi tại: {{ p.favorite_venues.join(', ') }}</span>
          </div>

          <div class="info-line">
            <span class="material-symbols-outlined info-ico">sports_score</span>
            <span class="info-text font-mono">Đã tham gia {{ p.matches_played }} trận giao lưu</span>
          </div>
        </div>

        <div class="partner-card-footer">
          <AuraButton
            variant="secondary"
            size="sm"
            icon="chat_bubble"
            block
            @click="emit('connect', p)"
          >
            Nhắn Tin / Mời Đấu
          </AuraButton>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.partner-finder-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.partner-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.section-title {
  margin: 0 0 4px;
  font-size: 18px;
  font-weight: 800;
  color: var(--ink);
}

.section-sub {
  margin: 0;
  font-size: 13px;
  color: var(--muted);
}

.partner-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.partner-card {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 18px;
  background: var(--surface);
  border-radius: 18px;
  border: 1px solid var(--outline);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
  transition: transform 0.2s ease, border-color 0.2s ease;
}

.partner-card:hover {
  transform: translateY(-2px);
  border-color: rgba(219, 39, 119, 0.35);
}

.partner-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
}

.partner-avatar-wrap {
  position: relative;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(219, 39, 119, 0.1);
  color: var(--pink-dark, #db2777);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 800;
  flex-shrink: 0;
}

.online-indicator {
  position: absolute;
  bottom: -2px;
  right: -2px;
  width: 11px;
  height: 11px;
  border-radius: 50%;
  background: #10b981;
  border: 2px solid var(--surface);
}

.partner-main-meta {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.partner-name-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.partner-name {
  margin: 0;
  font-size: 14.5px;
  font-weight: 750;
  color: var(--ink);
}

.reputation-badge {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  font-size: 10.5px;
  font-weight: 800;
  color: #10b981;
  background: rgba(16, 185, 129, 0.1);
  padding: 1px 5px;
  border-radius: 4px;
}

.rep-ico {
  font-size: 12px;
}

.partner-sport-level {
  font-size: 11.5px;
  font-weight: 650;
  color: var(--pink-dark, #db2777);
}

.partner-bio {
  margin: 0 0 10px;
  font-size: 12.5px;
  line-height: 1.45;
  color: var(--muted);
}

.partner-info-box {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--surface-container, rgba(0, 0, 0, 0.02));
  border: 1px solid var(--outline);
  margin-bottom: 12px;
}

.info-line {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  font-size: 11.5px;
  color: var(--muted);
}

.info-ico {
  font-size: 15px;
  color: var(--pink-dark, #db2777);
  flex-shrink: 0;
  margin-top: 1px;
}

.partner-card-footer {
  margin-top: auto;
}
</style>

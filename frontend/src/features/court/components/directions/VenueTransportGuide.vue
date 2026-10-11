<script setup lang="ts">
import type { Venue } from '../../types/court'

defineProps<{
  venue: Venue
}>()
</script>

<template>
  <div class="transport-guide-card">
    <h3 class="section-title">
      <span class="material-symbols-outlined icon-20">commute</span>
      <span>{{ $t('directions.transportTitle') }}</span>
    </h3>

    <div class="transport-grid">
      <!-- Ô tô -->
      <div class="transport-item">
        <div class="mode-icon-wrap car">
          <span class="material-symbols-outlined">directions_car</span>
        </div>
        <div class="mode-content">
          <strong class="mode-title">{{ $t('directions.carTitle') }}</strong>
          <p class="mode-desc">{{ $t('directions.carDesc') }}</p>
        </div>
      </div>

      <!-- Xe máy -->
      <div class="transport-item">
        <div class="mode-icon-wrap bike">
          <span class="material-symbols-outlined">two_wheeler</span>
        </div>
        <div class="mode-content">
          <strong class="mode-title">{{ $t('directions.bikeTitle') }}</strong>
          <p class="mode-desc">{{ $t('directions.bikeDesc') }}</p>
        </div>
      </div>

      <!-- Phương tiện công cộng -->
      <div class="transport-item">
        <div class="mode-icon-wrap transit">
          <span class="material-symbols-outlined">directions_bus</span>
        </div>
        <div class="mode-content">
          <strong class="mode-title">{{ $t('directions.transitTitle') }}</strong>
          <p class="mode-desc">{{ $t('directions.transitDesc') }}</p>
        </div>
      </div>

      <!-- Sảnh lễ tân & Lối vào -->
      <div class="transport-item">
        <div class="mode-icon-wrap lobby">
          <span class="material-symbols-outlined">meeting_room</span>
        </div>
        <div class="mode-content">
          <strong class="mode-title">{{ $t('directions.lobbyTitle') }}</strong>
          <p class="mode-desc">{{ $t('directions.lobbyDesc') }}</p>
        </div>
      </div>
    </div>

    <!-- Contact & Hotline Banner -->
    <div class="contact-support-banner">
      <div class="cs-left">
        <span class="material-symbols-outlined cs-icon">support_agent</span>
        <div class="cs-text">
          <strong class="cs-title">Cần trợ giúp chỉ đường tức thì?</strong>
          <span class="cs-desc"
            >Liên hệ hotline lễ tân trực tiếp tại cơ sở để được hỗ trợ đón tiếp.</span
          >
        </div>
      </div>
      <a href="tel:0988123456" class="btn-hotline">
        <span class="material-symbols-outlined icon-16">call</span>
        <span>0988 123 456</span>
      </a>
    </div>
  </div>
</template>

<style scoped>
.transport-guide-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .transport-guide-card {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.icon-20 {
  font-size: 20px;
  color: var(--primary);
}

:global(html.dark) .icon-20 {
  color: #e58e9f;
}

.transport-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
}

.transport-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 14px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle, rgba(0, 0, 0, 0.02));
}

:global(html.dark) .transport-item {
  background: #231c20;
  border-color: #382d33;
}

.mode-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 10px;
  flex-shrink: 0;
}

.mode-icon-wrap.car {
  background: rgba(201, 161, 90, 0.15);
  color: var(--accent);
}

.mode-icon-wrap.bike {
  background: rgba(46, 125, 50, 0.15);
  color: var(--success-text, #2e7d32);
}

.mode-icon-wrap.transit {
  background: rgba(107, 45, 63, 0.15);
  color: var(--primary);
}

:global(html.dark) .mode-icon-wrap.transit {
  background: rgba(229, 142, 159, 0.15);
  color: #e58e9f;
}

.mode-icon-wrap.lobby {
  background: rgba(123, 31, 162, 0.15);
  color: var(--purple-text, #7b1fa2);
}

.mode-content {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.mode-title {
  font-size: 13px;
  font-weight: 800;
  color: var(--ink);
}

.mode-desc {
  margin: 0;
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.45;
}

.contact-support-banner {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 14px 18px;
  border-radius: 14px;
  background: var(--primary-container);
  border: 1px solid rgba(107, 45, 63, 0.15);
}

:global(html.dark) .contact-support-banner {
  background: linear-gradient(145deg, #2b171f 0%, #1c0e14 100%);
  border-color: rgba(229, 142, 159, 0.25);
}

.cs-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.cs-icon {
  font-size: 24px;
  color: var(--primary);
}

:global(html.dark) .cs-icon {
  color: #e58e9f;
}

.cs-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.cs-title {
  font-size: 13px;
  font-weight: 800;
  color: var(--ink);
}

.cs-desc {
  font-size: 11.5px;
  color: var(--muted);
}

.btn-hotline {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 10px;
  background: var(--primary);
  color: var(--on-primary);
  font-size: 12.5px;
  font-weight: 800;
  text-decoration: none;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.25);
  transition: all 0.2s ease;
}

.btn-hotline:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
}

:global(html.dark) .btn-hotline {
  background: #e58e9f;
  color: #1c0a10;
}

.icon-16 {
  font-size: 16px;
}

@media (max-width: 768px) {
  .transport-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<script setup lang="ts">
import { computed } from 'vue'
import type { Venue } from '../../types/court'

const props = defineProps<{
  venue: Venue
  copied: boolean
}>()

const emit = defineEmits<{
  (e: 'openMaps'): void
  (e: 'copyAddress'): void
  (e: 'share'): void
}>()

const encodedAddress = computed(() => {
  return encodeURIComponent(props.venue.address || `${props.venue.name}, Hà Nội`)
})
</script>

<template>
  <div class="map-action-card">
    <!-- Header with Title & Action Controls -->
    <div class="card-header">
      <div class="header-title-col">
        <h2 class="card-title">
          <span class="material-symbols-outlined icon-22">explore</span>
          <span>{{ $t('directions.mapTitle') }}</span>
        </h2>
        <p class="card-sub">{{ $t('directions.mapSub') }}</p>
      </div>

      <div class="action-buttons-group">
        <button type="button" class="btn-primary-map" @click="emit('openMaps')">
          <span class="material-symbols-outlined icon-18">directions</span>
          <span>{{ $t('directions.openGoogleMaps') }}</span>
        </button>

        <button
          type="button"
          class="btn-secondary-action"
          :class="{ copied }"
          @click="emit('copyAddress')"
        >
          <span class="material-symbols-outlined icon-16">
            {{ copied ? 'check' : 'content_copy' }}
          </span>
          <span>{{ copied ? $t('directions.copied') : $t('directions.copyAddress') }}</span>
        </button>

        <button
          type="button"
          class="btn-secondary-action btn-icon-only"
          :title="$t('directions.shareLocation')"
          @click="emit('share')"
        >
          <span class="material-symbols-outlined icon-16">share</span>
        </button>
      </div>
    </div>

    <!-- Prominent Address Display Box (High Contrast in both Dark and Light mode) -->
    <div class="address-highlight-box">
      <div class="pin-icon-wrap">
        <span class="material-symbols-outlined pin-icon">location_on</span>
      </div>
      <div class="address-text-col">
        <div class="venue-label-row">
          <strong class="venue-brand-name">{{ venue.name }}</strong>
          <span class="verified-tag">
            <span class="material-symbols-outlined icon-12">verified</span>
            <span>Địa chỉ chính xác</span>
          </span>
        </div>
        <p class="venue-full-address">{{ venue.address || 'Hà Nội' }}</p>
      </div>
      <button
        type="button"
        class="btn-quick-copy"
        @click="emit('copyAddress')"
        title="Sao chép địa chỉ"
      >
        <span class="material-symbols-outlined icon-16">
          {{ copied ? 'check' : 'content_copy' }}
        </span>
      </button>
    </div>

    <!-- Map Preview Box -->
    <div class="map-embed-wrapper">
      <iframe
        class="map-iframe"
        :src="`https://maps.google.com/maps?q=${encodedAddress}&t=&z=15&ie=UTF8&iwloc=&output=embed`"
        loading="lazy"
        allowfullscreen
        title="Google Maps"
      ></iframe>
    </div>
  </div>
</template>

<style scoped>
.map-action-card {
  display: flex;
  flex-direction: column;
  gap: 18px;
  background: var(--surface);
  border: 1px solid var(--outline);
  border-radius: 20px;
  padding: 24px;
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
}

:global(html.dark) .map-action-card {
  background: #1a1518;
  border-color: #382d33;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.header-title-col {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.card-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 17px;
  font-weight: 800;
  color: var(--ink);
}

.icon-22 {
  font-size: 22px;
  color: var(--primary);
}

:global(html.dark) .icon-22 {
  color: #e58e9f;
}

.card-sub {
  margin: 0;
  font-size: 12.5px;
  color: var(--muted);
}

.action-buttons-group {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.btn-primary-map {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: 12px;
  border: none;
  background: linear-gradient(145deg, var(--primary) 0%, var(--primary-hover, #562332) 100%);
  color: var(--on-primary);
  font-size: 13px;
  font-weight: 800;
  cursor: pointer;
  box-shadow: 0 4px 16px rgba(107, 45, 63, 0.25);
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-primary-map:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(107, 45, 63, 0.35);
}

:global(html.dark) .btn-primary-map {
  background: linear-gradient(145deg, #e58e9f 0%, #c46d80 100%);
  color: #1c0a10;
  box-shadow: 0 4px 16px rgba(229, 142, 159, 0.3);
}

:global(html.dark) .btn-primary-map:hover {
  background: linear-gradient(145deg, #f0a3b2 0%, #d87d90 100%);
  box-shadow: 0 6px 24px rgba(229, 142, 159, 0.45);
}

.btn-secondary-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12.5px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

:global(html.dark) .btn-secondary-action {
  background: #231c20;
  border-color: #382d33;
  color: #e8e2e5;
}

.btn-secondary-action:hover {
  border-color: var(--accent);
  color: var(--accent);
  transform: translateY(-1px);
}

.btn-secondary-action.copied {
  background: var(--success-bg, #e8f5e9);
  color: var(--success-text, #2e7d32);
  border-color: rgba(46, 125, 50, 0.3);
}

:global(html.dark) .btn-secondary-action.copied {
  background: rgba(46, 125, 50, 0.25);
  color: #4ade80;
  border-color: rgba(74, 222, 128, 0.35);
}

.btn-icon-only {
  padding: 9px 10px;
}

.icon-18 {
  font-size: 18px;
}

.icon-16 {
  font-size: 16px;
}

/* High Contrast Address Display Box */
.address-highlight-box {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px 18px;
  border-radius: 16px;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--outline, #dcd3c5);
}

:global(html.dark) .address-highlight-box {
  background: #231c20;
  border-color: #3d3137;
}

.pin-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--primary-container, #f8e8ec);
  color: var(--primary, #6b2d3f);
  flex-shrink: 0;
}

:global(html.dark) .pin-icon-wrap {
  background: rgba(229, 142, 159, 0.2);
  color: #e58e9f;
}

.pin-icon {
  font-size: 24px;
}

.address-text-col {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  overflow: hidden;
}

.venue-label-row {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.venue-brand-name {
  font-size: 14.5px;
  font-weight: 800;
  color: var(--ink, #1c1618);
}

:global(html.dark) .venue-brand-name {
  color: #faf7f3;
}

.verified-tag {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 10.5px;
  font-weight: 700;
  padding: 1px 7px;
  border-radius: 4px;
  background: rgba(201, 161, 90, 0.15);
  color: var(--accent, #c9a15a);
}

:global(html.dark) .verified-tag {
  color: #d8b46e;
}

.icon-12 {
  font-size: 12px;
}

.venue-full-address {
  margin: 0;
  font-size: 13px;
  color: var(--muted, #6e6668);
  line-height: 1.4;
}

:global(html.dark) .venue-full-address {
  color: #c4b9be;
}

.btn-quick-copy {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: 1px solid var(--outline, #dcd3c5);
  background: var(--surface, #ffffff);
  color: var(--muted, #6e6668);
  cursor: pointer;
  flex-shrink: 0;
  transition: all 0.15s ease;
}

:global(html.dark) .btn-quick-copy {
  background: #1a1518;
  border-color: #382d33;
  color: #c4b9be;
}

.btn-quick-copy:hover {
  border-color: var(--accent);
  color: var(--accent);
  transform: translateY(-1px);
}

/* Map Embed Container */
.map-embed-wrapper {
  position: relative;
  width: 100%;
  height: 380px;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid var(--outline);
}

.map-iframe {
  width: 100%;
  height: 100%;
  border: none;
}
</style>

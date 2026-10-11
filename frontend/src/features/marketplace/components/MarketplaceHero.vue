<script setup lang="ts">
import { AuraButton } from '@/shared/components'

defineProps<{
  activeTab: 'buy' | 'sell' | 'my-listings'
  activeListingsCount: number
  myListingsCount: number
  eligibleSlotsCount: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'buy' | 'sell' | 'my-listings'): void
  (e: 'refresh'): void
}>()
</script>

<template>
  <section class="marketplace-hero">
    <div class="hero-content">
      <div class="category-eyebrow">
        <span class="material-symbols-outlined eyebrow-icon">storefront</span>
        <span class="font-mono">{{ $t('resale.eyebrow') }}</span>
      </div>

      <h1 class="hero-title">{{ $t('resale.title') }}</h1>
      <p class="hero-sub">
        {{ $t('resale.subtitle') }}
      </p>

      <div class="hero-badges">
        <div class="trust-pill">
          <span class="material-symbols-outlined icon-trust">verified_user</span>
          <span>{{ $t('resale.escrowBadge') }}</span>
        </div>
        <div class="trust-pill">
          <span class="material-symbols-outlined icon-trust">bolt</span>
          <span>{{ $t('resale.instantTransferBadge') }}</span>
        </div>
      </div>
    </div>

    <div class="hero-tabs-wrap">
      <div class="tab-switcher" role="tablist">
        <button
          type="button"
          role="tab"
          class="tab-btn"
          :class="{ active: activeTab === 'buy' }"
          :aria-selected="activeTab === 'buy'"
          @click="emit('update:activeTab', 'buy')"
        >
          <span class="material-symbols-outlined tab-icon">shopping_bag</span>
          <span>{{ $t('resale.findSlotsTab') }}</span>
          <span v-if="activeListingsCount > 0" class="tab-count-badge">{{
            activeListingsCount
          }}</span>
        </button>

        <button
          type="button"
          role="tab"
          class="tab-btn"
          :class="{ active: activeTab === 'sell' }"
          :aria-selected="activeTab === 'sell'"
          @click="emit('update:activeTab', 'sell')"
        >
          <span class="material-symbols-outlined tab-icon">sell</span>
          <span>{{ $t('resale.myPassTab') }}</span>
          <span v-if="eligibleSlotsCount > 0" class="tab-count-badge gold">{{
            eligibleSlotsCount
          }}</span>
        </button>

        <button
          type="button"
          role="tab"
          class="tab-btn"
          :class="{ active: activeTab === 'my-listings' }"
          :aria-selected="activeTab === 'my-listings'"
          @click="emit('update:activeTab', 'my-listings')"
        >
          <span class="material-symbols-outlined tab-icon">receipt_long</span>
          <span>{{ $t('resale.myListingsTab') }}</span>
          <span v-if="myListingsCount > 0" class="tab-count-badge">{{ myListingsCount }}</span>
        </button>
      </div>

      <AuraButton
        variant="ghost"
        size="sm"
        icon="refresh"
        :loading="loading"
        title="Làm mới dữ liệu"
        @click="emit('refresh')"
      />
    </div>
  </section>
</template>

<style scoped>
.marketplace-hero {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 28px 32px;
  border-radius: 24px;
  background: var(--surface);
  border: 1px solid var(--outline);
  box-shadow: var(--shadow-sm);
  position: relative;
  overflow: hidden;
}

.marketplace-hero::before {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  width: 320px;
  height: 100%;
  background: radial-gradient(circle at 100% 0%, rgba(201, 161, 90, 0.08), transparent 70%);
  pointer-events: none;
}

.hero-content {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 780px;
}

.category-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 800;
  color: var(--accent, #c9a15a);
  letter-spacing: 0.08em;
}

.eyebrow-icon {
  font-size: 16px;
}

.hero-title {
  margin: 0;
  font-size: 26px;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ink);
  line-height: 1.2;
}

.hero-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--muted);
  line-height: 1.6;
}

.hero-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 4px;
}

.trust-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--outline);
  font-size: 11.5px;
  font-weight: 600;
  color: var(--ink);
}

.icon-trust {
  font-size: 14px;
  color: var(--accent, #c9a15a);
}

.hero-tabs-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding-top: 14px;
  border-top: 1px solid var(--outline);
}

.tab-switcher {
  display: flex;
  gap: 6px;
  padding: 4px;
  border-radius: 14px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.04));
  border: 1px solid var(--outline);
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 10px;
  border: 0;
  background: transparent;
  color: var(--muted);
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
}

.tab-icon {
  font-size: 18px;
}

.tab-btn:hover {
  color: var(--ink);
}

.tab-btn.active {
  background: var(--surface);
  color: var(--primary, #6b2d3f);
  box-shadow: 0 2px 10px rgba(107, 45, 63, 0.08);
}

html.dark .tab-btn.active {
  background: var(--surface);
  color: var(--accent, #c9a15a);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
}

.tab-count-badge {
  font-size: 10px;
  font-weight: 800;
  padding: 1px 6px;
  border-radius: 999px;
  background: var(--primary, #6b2d3f);
  color: #ffffff;
}

.tab-count-badge.gold {
  background: var(--accent, #c9a15a);
  color: #1c1618;
}

.btn-refresh {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--muted);
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-refresh:hover:not(:disabled) {
  color: var(--primary, #6b2d3f);
  border-color: var(--primary, #6b2d3f);
}

.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 640px) {
  .marketplace-hero {
    padding: 20px 16px;
  }
  .tab-switcher {
    width: 100%;
    overflow-x: auto;
  }
  .tab-btn {
    flex: 1;
    justify-content: center;
    padding: 8px 12px;
    font-size: 12px;
  }
}
</style>

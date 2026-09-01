<script setup lang="ts">
import { RouterLink } from 'vue-router'

defineProps<{
  totalCount: number
  upcomingCount: number
}>()
</script>

<template>
  <div class="booking-hero-card">
    <div class="hero-left">
      <div class="hero-tag">
        <span class="material-symbols-outlined tag-icon">confirmation_number</span>
        <span class="font-mono">AURA RESERVATIONS</span>
      </div>
      <h1 class="page-title">{{ $t('booking.title') }}</h1>
      <p class="page-sub">
        {{ $t('booking.subtitle') }}
      </p>

      <div class="stats-pills">
        <div class="stat-pill">
          <span class="stat-num font-mono">{{ upcomingCount }}</span>
          <span class="stat-lbl">{{ $t('booking.upcomingCountLabel') }}</span>
        </div>
        <div class="stat-pill">
          <span class="stat-num font-mono">{{ totalCount }}</span>
          <span class="stat-lbl">{{ $t('booking.totalBookingsLabel') }}</span>
        </div>
      </div>
    </div>

    <div class="hero-actions">
      <RouterLink to="/courts" class="btn-action-primary">
        <span class="material-symbols-outlined">add_circle</span>
        <span>{{ $t('booking.newBookingBtn') }}</span>
      </RouterLink>

      <RouterLink to="/recurring-booking" class="btn-action-secondary">
        <span class="material-symbols-outlined">event_repeat</span>
        <span>{{ $t('booking.recurringBookingBtn') }}</span>
      </RouterLink>

      <RouterLink to="/resale" class="btn-action-secondary">
        <span class="material-symbols-outlined">swap_horiz</span>
        <span>{{ $t('booking.resaleMarketplaceBtn') }}</span>
      </RouterLink>
    </div>
  </div>
</template>

<style scoped>
.booking-hero-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 24px;
  padding: 28px 32px;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  box-shadow: 0 4px 20px rgba(107, 45, 63, 0.04);
  position: relative;
  overflow: hidden;
}

.booking-hero-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 5px;
  height: 100%;
  background: linear-gradient(180deg, var(--primary, #6B2D3F) 0%, var(--accent, #C9A15A) 100%);
}

.hero-left {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-width: 600px;
}

.hero-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 800;
  color: var(--primary, #6B2D3F);
  background: var(--primary-container, rgba(107, 45, 63, 0.08));
  padding: 4px 10px;
  border-radius: 6px;
  width: fit-content;
  letter-spacing: 0.5px;
}

.tag-icon {
  font-size: 15px;
}

.page-title {
  margin: 0;
  font-size: 24px;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.5px;
}

.page-sub {
  margin: 0;
  font-size: 14px;
  color: var(--muted);
  line-height: 1.5;
}

.stats-pills {
  display: flex;
  gap: 12px;
  margin-top: 8px;
  flex-wrap: wrap;
}

.stat-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.03));
  border: 1px solid var(--border);
  padding: 6px 14px;
  border-radius: 9999px;
}

.stat-num {
  font-size: 14px;
  font-weight: 800;
  color: var(--primary, #6B2D3F);
}

.stat-lbl {
  font-size: 12px;
  color: var(--muted);
  font-weight: 500;
}

.hero-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
  min-width: 200px;
}

.btn-action-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 11px 20px;
  border-radius: 12px;
  border: 0;
  background: var(--primary, #6B2D3F);
  color: var(--on-primary, #fff);
  font-size: 13.5px;
  font-weight: 700;
  text-decoration: none;
  cursor: pointer;
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.2);
  transition: all 0.2s ease;
}

.btn-action-primary:hover {
  filter: brightness(1.08);
  transform: translateY(-1px);
  box-shadow: 0 6px 18px rgba(107, 45, 63, 0.28);
}

.btn-action-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 9px 18px;
  border-radius: 12px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-action-secondary:hover {
  background: var(--surface-subtle, rgba(0, 0, 0, 0.04));
  border-color: var(--primary, #6B2D3F);
  color: var(--primary, #6B2D3F);
}

.btn-action-primary .material-symbols-outlined,
.btn-action-secondary .material-symbols-outlined {
  font-size: 18px;
}

@media (max-width: 768px) {
  .booking-hero-card {
    padding: 20px;
  }
  .hero-actions {
    width: 100%;
    flex-direction: row;
    flex-wrap: wrap;
  }
  .btn-action-primary, .btn-action-secondary {
    flex: 1 1 calc(50% - 6px);
  }
}
</style>

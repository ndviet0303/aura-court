<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { Court } from '../../types/court'

defineProps<{
  court: Court
  index: number
}>()

function formatMoney(amount: number): string {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0)
}
</script>

<template>
  <div class="court-card-item">
    <div class="card-img-wrap">
      <img
        :src="court.image_url || `https://images.unsplash.com/photo-1626224583764-f87db24ac4ea?w=800&q=80`"
        :alt="court.name"
        class="court-img"
      />
      <span class="sport-tag font-mono">🏸 BWF Tarafex</span>
    </div>

    <div class="card-body">
      <div class="court-title-meta">
        <h3 class="c-name">{{ court.name }}</h3>
        <span class="c-venue">📍 {{ court.venue?.name || 'Aura Club' }}</span>
        <span class="c-addr">{{ court.venue?.address }}</span>
      </div>

      <div class="card-bottom">
        <div class="pricing-box">
          <span class="p-lbl">Giá từ:</span>
          <strong class="p-val text-pink font-mono">
            {{ formatMoney(court.price_slots?.[0]?.price || 120000) }} /h
          </strong>
        </div>

        <div class="card-actions">
          <RouterLink :to="`/courts/${court.id}`" class="btn-detail">
            Chi Tiết
          </RouterLink>
          <RouterLink :to="`/schedule/${court.id}`" class="btn-book">
            Đặt Sân
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.court-card-item {
  display: flex;
  flex-direction: column;
  border-radius: 20px;
  border: 1px solid var(--outline);
  background: var(--surface);
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
  transition: transform 0.15s, box-shadow 0.15s;
}

.court-card-item:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.06);
}

.card-img-wrap {
  position: relative;
  width: 100%;
  height: 200px;
}

.court-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sport-tag {
  position: absolute;
  top: 12px;
  left: 12px;
  padding: 4px 10px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.7);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  backdrop-filter: blur(4px);
}

.card-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 20px;
  flex: 1;
}

.court-title-meta {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.c-name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
}

.c-venue {
  font-size: 13px;
  font-weight: 600;
  color: var(--pink-dark, #db2777);
}

.c-addr {
  font-size: 11px;
  color: var(--muted);
}

.card-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
}

.pricing-box {
  display: flex;
  flex-direction: column;
}

.p-lbl { font-size: 10px; color: var(--muted); }
.p-val { font-size: 14px; }
.text-pink { color: var(--pink-dark, #db2777); }

.card-actions {
  display: flex;
  gap: 8px;
}

.btn-detail {
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
}

.btn-book {
  padding: 8px 16px;
  border-radius: 8px;
  border: 0;
  background: var(--pink-dark, #db2777);
  color: #fff;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
}
</style>

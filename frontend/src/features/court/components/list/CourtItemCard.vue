<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { formatCurrency } from '@/i18n'
import type { Court } from '../../types/court'
import { resolveCourtImage } from '@/lib/courtImage'

const props = defineProps<{
  court: Court
  index: number
}>()

const sportTag = computed(() => {
  if (props.court.sport_zone?.name) {
    return props.court.sport_zone.name
  }
  const name = props.court.name.toLowerCase()
  if (name.includes('pickleball')) return 'Pickleball Pro'
  if (name.includes('tennis')) return 'Hard Court Tennis'
  if (name.includes('bóng rổ') || name.includes('basketball')) return 'FIBA Hardwood'
  if (name.includes('bóng đá') || name.includes('football')) return 'FIFA Turf'
  return 'BWF Taraflex'
})

const sportIcon = computed(() => {
  const type = props.court.sport_zone?.sport_type?.toLowerCase() || ''
  const name = props.court.name.toLowerCase()
  if (type === 'basketball' || name.includes('bóng rổ') || name.includes('basketball'))
    return 'sports_basketball'
  if (type.startsWith('football') || name.includes('bóng đá') || name.includes('football'))
    return 'sports_soccer'
  if (type === 'table_tennis' || name.includes('bóng bàn')) return 'table_restaurant'
  if (type === 'volleyball' || name.includes('bóng chuyền')) return 'sports_volleyball'
  return 'sports_tennis'
})

function handleImageError(event: Event) {
  const target = event.target as HTMLImageElement | null
  if (target) {
    const fallback = resolveCourtImage({ ...props.court, image_url: undefined })
    if (target.src !== fallback) {
      target.src = fallback
    }
  }
}
</script>

<template>
  <div class="court-card-item">
    <div class="card-img-wrap">
      <img
        :src="resolveCourtImage(court)"
        :alt="court.name"
        class="court-img"
        loading="lazy"
        @error="handleImageError"
      />
      <span class="sport-tag font-mono">
        <span class="material-symbols-outlined tag-icon">{{ sportIcon }}</span>
        <span>{{ sportTag }}</span>
      </span>
    </div>

    <div class="card-body">
      <div class="court-title-meta">
        <h3 class="c-name">{{ court.name }}</h3>
        <div class="c-venue">
          <span class="material-symbols-outlined icon-venue">location_on</span>
          <span>{{ court.venue?.name || 'Aura Club' }}</span>
        </div>
        <RouterLink
          v-if="court.venue?.address"
          :to="`/courts/${court.slug || court.id}/directions`"
          class="c-addr-link"
          :title="$t('courts.directionsBtn')"
        >
          <span class="material-symbols-outlined icon-addr">near_me</span>
          <span>{{ court.venue.address }}</span>
          <span class="material-symbols-outlined icon-12">open_in_new</span>
        </RouterLink>
      </div>

      <div class="card-bottom">
        <div class="pricing-box">
          <span class="p-lbl">{{ $t('courts.fromPrice') }}</span>
          <strong class="p-val text-pink font-mono">
            {{ formatCurrency(court.price_slots?.[0]?.price || 120000) }}
            <small class="p-unit">/h</small>
          </strong>
        </div>

        <div class="card-actions">
          <RouterLink :to="`/courts/${court.slug || court.id}`" class="btn-detail">
            <span class="material-symbols-outlined btn-icon">info</span>
            <span>{{ $t('courts.detailBtn') }}</span>
          </RouterLink>
          <RouterLink :to="`/courts/${court.slug || court.id}/schedule`" class="btn-book">
            <span class="material-symbols-outlined btn-icon">event_available</span>
            <span>{{ $t('courts.bookBtn') }}</span>
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
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease,
    border-color 0.2s ease;
}

.court-card-item:hover {
  transform: translateY(-3px);
  border-color: var(--accent);
  box-shadow: 0 12px 28px rgba(107, 45, 63, 0.08);
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
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border-radius: 8px;
  background: rgba(20, 17, 19, 0.82);
  border: 1px solid rgba(201, 161, 90, 0.35);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  backdrop-filter: blur(8px);
}

.tag-icon {
  font-size: 14px;
  color: var(--accent);
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
  gap: 4px;
}

.c-name {
  margin: 0;
  font-size: 16px;
  font-weight: 800;
  color: var(--ink);
}

.c-venue {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 13px;
  font-weight: 600;
  color: var(--primary);
}

.icon-venue {
  font-size: 16px;
  color: var(--primary);
}

.c-addr,
.c-addr-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11.5px;
  color: var(--muted);
  line-height: 1.4;
  text-decoration: none;
  width: fit-content;
  transition: all 0.15s ease;
}

.c-addr-link:hover {
  color: var(--primary);
  text-decoration: underline;
}

.icon-12 {
  font-size: 12px;
  opacity: 0.7;
}

.icon-addr {
  font-size: 14px;
  color: var(--muted);
  flex-shrink: 0;
}

.card-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid var(--outline);
  gap: 12px;
}

.pricing-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
  white-space: nowrap;
  flex-shrink: 0;
}

.p-lbl {
  font-size: 10px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
}

.p-val {
  font-size: 15px;
  font-weight: 800;
  color: var(--primary);
  white-space: nowrap;
}

.p-unit {
  font-size: 12px;
  font-weight: 500;
  color: var(--muted);
}

.text-pink {
  color: var(--primary);
}

.card-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-shrink: 0;
}

.btn-detail {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  border-radius: 10px;
  border: 1px solid var(--outline);
  background: var(--surface);
  color: var(--ink);
  font-size: 11.5px;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: all 0.15s ease;
}

.btn-detail:hover {
  background: var(--soft);
  border-color: var(--accent);
  color: var(--primary);
}

:global(html.dark) .btn-detail:hover {
  background: #281d23;
  color: #e58e9f;
}

.btn-book {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 16px;
  border-radius: 10px;
  border: 0;
  background: var(--primary);
  color: var(--on-primary);
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
  transition: all 0.15s ease;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.2);
}

.btn-book:hover {
  background: var(--primary-hover);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(107, 45, 63, 0.3);
}

:global(html.dark) .btn-book {
  box-shadow: 0 2px 8px rgba(229, 142, 159, 0.25);
}

:global(html.dark) .btn-book:hover {
  box-shadow: 0 4px 14px rgba(229, 142, 159, 0.35);
}

.btn-icon {
  font-size: 15px;
}
</style>

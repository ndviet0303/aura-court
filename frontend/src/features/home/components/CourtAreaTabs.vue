<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { CourtArea } from '../utils/courtArea'
import type { LocateError } from '../composables/useCourtAreaFilter'

defineProps<{
  areas: CourtArea[]
  total: number
  selected: string | null
  locating: boolean
  located: boolean
  locateError: LocateError
}>()
const emit = defineEmits<{ select: [key: string | null]; locate: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="area-filter">
    <button
      type="button"
      class="area-pill near-me"
      :class="{ active: located }"
      :aria-pressed="located"
      :disabled="locating"
      @click="emit('locate')"
    >
      <span class="material-symbols-outlined icon-16" aria-hidden="true">near_me</span>
      {{ locating ? t('home.availability.locating') : t('home.availability.nearMe') }}
    </button>
    <div
      v-if="areas.length > 1"
      class="area-tabs-scroll"
      role="tablist"
      :aria-label="t('home.availability.areaTabs')"
    >
      <button
        type="button"
        role="tab"
        class="area-pill"
        :aria-selected="selected === null"
        :class="{ active: selected === null }"
        @click="emit('select', null)"
      >
        {{ t('home.availability.allAreas') }}
        <span class="tab-badge">{{ total }}</span>
      </button>
      <button
        v-for="area in areas"
        :key="area.key"
        type="button"
        role="tab"
        class="area-pill"
        :aria-selected="selected === area.key"
        :class="{ active: selected === area.key }"
        @click="emit('select', area.key)"
      >
        {{ area.label }}
        <span class="tab-badge">{{ area.count }}</span>
      </button>
    </div>
    <p v-if="locateError" class="locate-error" role="alert">
      {{
        locateError === 'denied'
          ? t('home.availability.locateDenied')
          : t('home.availability.locateUnavailable')
      }}
    </p>
  </div>
</template>

<style scoped>
.area-filter {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
  flex: 1;
  min-width: 0;
  /* The mobile header is a wrapping column, which would otherwise size this to its max-content. */
  max-width: 100%;
}

.area-tabs-scroll {
  display: flex;
  align-items: center;
  gap: 6px;
  overflow-x: auto;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  flex: 1;
  min-width: 0;
  /* Soft edge so an overflowing pill reads as "scroll for more", not as clipped. */
  padding-right: 24px;
  mask-image: linear-gradient(to right, #000 calc(100% - 32px), transparent);
}

.area-tabs-scroll::-webkit-scrollbar {
  display: none;
}

.area-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid var(--outline);
  background: var(--surface-subtle);
  color: var(--muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.2s ease;
}

.area-pill:hover:not(.active):not(:disabled) {
  border-color: var(--accent);
  color: var(--ink);
}

.area-pill:disabled {
  cursor: progress;
  opacity: 0.7;
}

.area-pill.active {
  background: var(--primary);
  border-color: var(--primary);
  color: var(--on-primary);
}

.near-me {
  flex-shrink: 0;
}

:global(html.dark) .area-pill {
  background: #231c20;
  border-color: #382d33;
  color: var(--muted);
}

:global(html.dark) .area-pill.active {
  background: #e58e9f;
  border-color: #e58e9f;
  color: #1c0a10;
}

.tab-badge {
  font-size: 10.5px;
  font-weight: 700;
  opacity: 0.75;
}

.locate-error {
  flex-basis: 100%;
  margin: 0;
  font-size: 12px;
  color: var(--error-text);
}
</style>

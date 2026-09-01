<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const specs = computed(() => [
  { icon: 'layers', label: t('courtDetail.specMat'), val: t('courtDetail.specMatDesc') },
  { icon: 'height', label: t('courtDetail.specCeiling'), val: t('courtDetail.specCeilingDesc') },
  { icon: 'lightbulb', label: t('courtDetail.specLighting'), val: t('courtDetail.specLightingDesc') },
  { icon: 'mode_fan', label: t('courtDetail.specVentilation'), val: t('courtDetail.specVentilationDesc') },
])
</script>

<template>
  <div class="court-specs-section">
    <h3 class="section-title">
      <span class="material-symbols-outlined title-icon">straighten</span>
      <span>{{ $t('courtDetail.specsTitle') }}</span>
    </h3>
    <div class="specs-grid">
      <div v-for="s in specs" :key="s.label" class="spec-card">
        <span class="material-symbols-outlined s-icon">{{ s.icon }}</span>
        <span class="s-lbl">{{ s.label }}</span>
        <strong class="s-val">{{ s.val }}</strong>
      </div>
    </div>
  </div>
</template>

<style scoped>
.court-specs-section {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 16px;
  font-weight: 700;
}

.title-icon {
  font-size: 18px;
  color: var(--primary);
}

.specs-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
}

.spec-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 16px;
  border-radius: 14px;
  border: 1px solid var(--outline);
  background: var(--surface);
  transition: border-color 0.15s ease;
}

.spec-card:hover {
  border-color: var(--accent);
}

.s-icon {
  font-size: 20px;
  color: var(--accent);
}

.s-lbl {
  font-size: 11px;
  color: var(--muted);
}

.s-val {
  font-size: 13px;
  color: var(--ink);
}

@media (max-width: 768px) {
  .specs-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
</style>

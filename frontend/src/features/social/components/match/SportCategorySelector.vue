<script setup lang="ts">
defineProps<{
  selectedSport: 'badminton' | 'pickleball' | 'tennis'
}>()

const emit = defineEmits<{
  (e: 'update:selectedSport', sport: 'badminton' | 'pickleball' | 'tennis'): void
}>()

const sports = [
  { id: 'badminton', name: 'Cầu Lông (Badminton)', icon: '🏸', desc: 'Sàn thảm tiêu chuẩn BWF' },
  { id: 'pickleball', name: 'Pickleball', icon: '🏓', desc: 'Sân ngoài trời / Trong nhà' },
  { id: 'tennis', name: 'Quần Vợt (Tennis)', icon: '🎾', desc: 'Sân cứng tiêu chuẩn' },
]
</script>

<template>
  <div class="sports-selector-grid">
    <div
      v-for="s in sports"
      :key="s.id"
      class="sport-select-card"
      :class="{ active: selectedSport === s.id }"
      @click="emit('update:selectedSport', s.id as any)"
    >
      <span class="s-icon">{{ s.icon }}</span>
      <strong class="s-name">{{ s.name }}</strong>
      <span class="s-desc">{{ s.desc }}</span>
    </div>
  </div>
</template>

<style scoped>
.sports-selector-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.sport-select-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 6px;
  padding: 16px 12px;
  border-radius: 14px;
  border: 2px solid var(--outline);
  background: var(--surface);
  cursor: pointer;
  transition: all 0.15s;
}

.sport-select-card:hover {
  border-color: rgba(236, 72, 153, 0.4);
}

.sport-select-card.active {
  border-color: var(--pink-dark, #db2777);
  background: rgba(236, 72, 153, 0.05);
}

.s-icon {
  font-size: 28px;
}

.s-name {
  font-size: 14px;
}

.s-desc {
  font-size: 11px;
  color: var(--muted);
}

@media (max-width: 600px) {
  .sports-selector-grid {
    grid-template-columns: 1fr;
  }
}
</style>

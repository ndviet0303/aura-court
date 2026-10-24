<script setup lang="ts" generic="T extends string">
const active = defineModel<T>({ required: true })
defineProps<{ tabs: { value: T; label: string }[]; label: string }>()
</script>

<template>
  <div class="tournament-tabs" role="tablist" :aria-label="label">
    <button
      v-for="tab in tabs"
      :key="tab.value"
      type="button"
      role="tab"
      class="tournament-tab"
      :class="{ active: active === tab.value }"
      :aria-selected="active === tab.value"
      @click="active = tab.value"
    >
      {{ tab.label }}
    </button>
  </div>
</template>

<style scoped>
.tournament-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tournament-tab {
  padding: 6px 14px;
  border: 1px solid var(--outline);
  border-radius: 999px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  cursor: pointer;
}

.tournament-tab.active {
  border-color: var(--primary, #6b2d3f);
  background: var(--primary, #6b2d3f);
  color: var(--on-primary, #fff);
  font-weight: 700;
}
</style>

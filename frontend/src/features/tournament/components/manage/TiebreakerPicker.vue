<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { TIEBREAKERS, type Tiebreaker } from '../../types/tournament'

// D19: an ordered pick from the fixed list, applied after points; still equal means tied.
const picked = defineModel<Tiebreaker[]>({ required: true })
const { t } = useI18n()
const available = computed(() => TIEBREAKERS.filter((x) => !picked.value.includes(x)))

function move(index: number, by: number) {
  const next = [...picked.value]
  const [item] = next.splice(index, 1)
  next.splice(index + by, 0, item!)
  picked.value = next
}
</script>

<template>
  <div class="tiebreakers">
    <ol class="tiebreakers__list">
      <li class="admin-detail-muted">{{ t('tournament.tiebreaker.points') }}</li>
      <li v-for="(item, index) in picked" :key="item">
        <span>{{ t(`tournament.tiebreaker.${item}`) }}</span>
        <button
          type="button"
          class="tiebreakers__btn"
          :disabled="index === 0"
          :aria-label="t('tournament.setup.moveUp')"
          @click="move(index, -1)"
        >
          <span class="material-symbols-outlined">arrow_upward</span>
        </button>
        <button
          type="button"
          class="tiebreakers__btn"
          :disabled="index === picked.length - 1"
          :aria-label="t('tournament.setup.moveDown')"
          @click="move(index, 1)"
        >
          <span class="material-symbols-outlined">arrow_downward</span>
        </button>
        <button
          type="button"
          class="tiebreakers__btn"
          :aria-label="t('common.delete')"
          @click="picked = picked.filter((x) => x !== item)"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </li>
    </ol>
    <div v-if="available.length" class="tiebreakers__add">
      <button
        v-for="item in available"
        :key="item"
        type="button"
        class="tiebreakers__chip"
        @click="picked = [...picked, item]"
      >
        + {{ t(`tournament.tiebreaker.${item}`) }}
      </button>
    </div>
    <p class="form-hint">{{ t('tournament.setup.tiebreakerHint') }}</p>
  </div>
</template>

<style scoped>
.tiebreakers__list {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0 0 8px;
  padding-left: 20px;
  font-size: 14px;
}

.tiebreakers__list li {
  display: flex;
  align-items: center;
  gap: 4px;
}

.tiebreakers__list li > span:first-child {
  flex: 1;
}

.tiebreakers__btn {
  display: inline-flex;
  padding: 2px;
  border: 0;
  background: none;
  color: var(--muted);
  cursor: pointer;
}

.tiebreakers__btn:disabled {
  opacity: 0.3;
  cursor: default;
}

.tiebreakers__btn .material-symbols-outlined {
  font-size: 18px;
}

.tiebreakers__add {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.tiebreakers__chip {
  padding: 4px 10px;
  border: 1px dashed var(--outline);
  border-radius: 999px;
  background: var(--surface);
  color: var(--ink);
  font-size: 12.5px;
  cursor: pointer;
}
</style>

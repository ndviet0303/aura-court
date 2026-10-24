<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

// Free-text labels such as the extra athlete fields; Enter or comma adds one.
const tags = defineModel<string[]>({ required: true })
const props = defineProps<{ max: number; placeholder?: string }>()
const { t } = useI18n()
const draft = ref('')

function add() {
  const value = draft.value.trim().replace(/,$/, '').trim()
  if (value && !tags.value.includes(value) && tags.value.length < props.max) {
    tags.value = [...tags.value, value]
  }
  draft.value = ''
}

function onKey(event: KeyboardEvent) {
  if (event.key !== 'Enter' && event.key !== ',') return
  event.preventDefault()
  add()
}
</script>

<template>
  <div class="tag-input">
    <span v-for="tag in tags" :key="tag" class="tag-input__tag">
      {{ tag }}
      <button
        type="button"
        :aria-label="t('common.delete')"
        @click="tags = tags.filter((x) => x !== tag)"
      >
        ×
      </button>
    </span>
    <input
      v-if="tags.length < max"
      v-model="draft"
      class="tag-input__field"
      maxlength="50"
      :placeholder="placeholder"
      @keydown="onKey"
      @blur="add"
    />
  </div>
</template>

<style scoped>
.tag-input {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border: 1px solid var(--outline);
  border-radius: 10px;
  background: var(--surface);
}

.tag-input__tag {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  border-radius: 999px;
  background: var(--surface-subtle, rgba(0, 0, 0, 0.05));
  font-size: 13px;
}

.tag-input__tag button {
  border: 0;
  background: none;
  color: var(--muted);
  cursor: pointer;
}

.tag-input__field {
  flex: 1;
  min-width: 140px;
  padding: 4px;
  border: 0;
  outline: 0;
  background: transparent;
  color: var(--ink);
  font-size: 14px;
}
</style>

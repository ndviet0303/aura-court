<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ modelValue: string; placeholder?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string]; change: [value: string] }>()
const { t } = useI18n()
const root = ref<HTMLElement | null>(null)
const open = ref(false)
const options = Array.from(
  { length: 48 },
  (_, index) => `${String(Math.floor(index / 2)).padStart(2, '0')}:${index % 2 ? '30' : '00'}`,
)
const displayValue = () =>
  props.modelValue ? props.modelValue.slice(0, 5) : props.placeholder || t('home.search.timeSlot')
function select(value: string) {
  emit('update:modelValue', value)
  emit('change', value)
  open.value = false
}
function handleOutside(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) open.value = false
}
onMounted(() => document.addEventListener('click', handleOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleOutside))
</script>

<template>
  <div ref="root" class="time-picker">
    <button
      type="button"
      class="time-picker-trigger"
      :aria-expanded="open"
      aria-haspopup="listbox"
      @click.stop="open = !open"
    >
      <span :class="{ 'date-picker-placeholder': !modelValue }">{{ displayValue() }}</span>
      <span class="material-symbols-outlined" aria-hidden="true">schedule</span>
    </button>
    <div
      v-if="open"
      class="time-picker-popover"
      role="listbox"
      :aria-label="t('home.search.timeSlot')"
    >
      <button
        v-for="option in options"
        :key="option"
        type="button"
        class="time-picker-option"
        :class="{ selected: option === modelValue.slice(0, 5) }"
        @click="select(option)"
      >
        {{ option }}
      </button>
    </div>
  </div>
</template>

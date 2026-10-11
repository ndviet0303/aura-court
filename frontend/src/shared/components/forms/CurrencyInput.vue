<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: number | null | undefined
    placeholder?: string
    disabled?: boolean
    min?: number
    max?: number
    suffix?: string
    size?: 'sm' | 'md' | 'lg'
    inputClass?: string
  }>(),
  {
    placeholder: '0',
    disabled: false,
    min: 0,
    suffix: '₫',
    size: 'md',
    inputClass: '',
  },
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: number): void
  (e: 'change', value: number): void
  (e: 'blur', event: FocusEvent): void
  (e: 'focus', event: FocusEvent): void
}>()

const inputRef = ref<HTMLInputElement | null>(null)
const displayValue = ref('')

function formatNumber(val: number | null | undefined): string {
  if (val === null || val === undefined || isNaN(val)) return ''
  return val.toLocaleString('vi-VN')
}

function parseNumber(str: string): number {
  const clean = str.replace(/\D/g, '')
  return clean ? parseInt(clean, 10) : 0
}

watch(
  () => props.modelValue,
  (newVal) => {
    displayValue.value = formatNumber(newVal)
  },
  { immediate: true },
)

function onInput(event: Event) {
  const target = event.target as HTMLInputElement
  const rawCursor = target.selectionStart || 0
  const oldVal = target.value

  const numericVal = parseNumber(target.value)

  // Clamp if min / max provided
  let finalNumeric = numericVal
  if (props.max !== undefined && finalNumeric > props.max) {
    finalNumeric = props.max
  }

  const formatted =
    finalNumeric > 0 || target.value.trim() === '0' ? formatNumber(finalNumeric) : ''
  displayValue.value = formatted

  emit('update:modelValue', finalNumeric)

  nextTick(() => {
    if (!inputRef.value) return
    // Adjust cursor position based on newly added/removed dots
    const digitsBeforeCursor = oldVal.slice(0, rawCursor).replace(/\D/g, '').length
    let newCursor = 0
    let digitCount = 0

    for (let i = 0; i < formatted.length; i++) {
      const char = formatted.charAt(i)
      if (/\d/.test(char)) {
        digitCount++
      }
      if (digitCount === digitsBeforeCursor) {
        newCursor = i + 1
        break
      }
    }

    if (newCursor === 0 && digitsBeforeCursor === 0) {
      newCursor = 0
    } else if (digitCount < digitsBeforeCursor) {
      newCursor = formatted.length
    }

    inputRef.value.setSelectionRange(newCursor, newCursor)
  })
}

function onBlur(e: FocusEvent) {
  displayValue.value = formatNumber(props.modelValue)
  emit('blur', e)
  emit('change', props.modelValue || 0)
}

function onFocus(e: FocusEvent) {
  emit('focus', e)
}
</script>

<template>
  <div class="currency-input-wrapper" :class="[`size-${size}`, { 'is-disabled': disabled }]">
    <input
      ref="inputRef"
      type="text"
      inputmode="numeric"
      :value="displayValue"
      :placeholder="placeholder"
      :disabled="disabled"
      class="currency-input-field font-mono"
      :class="inputClass"
      @input="onInput"
      @blur="onBlur"
      @focus="onFocus"
    />
    <span v-if="suffix" class="currency-suffix font-mono">{{ suffix }}</span>
  </div>
</template>

<style scoped>
.currency-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
  background: var(--surface, #ffffff);
  border: 1px solid var(--outline, #e2e8f0);
  border-radius: 10px;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.currency-input-wrapper:focus-within {
  border-color: var(--pink-dark, #db2777);
  box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.12);
}

.currency-input-field {
  flex: 1;
  width: 100%;
  background: transparent;
  border: none;
  color: var(--ink, #0f172a);
  font-weight: 700;
  outline: none;
  box-sizing: border-box;
  padding-left: 12px;
  padding-right: 32px;
}

.currency-suffix {
  position: absolute;
  right: 12px;
  color: var(--muted, #64748b);
  font-size: 13px;
  font-weight: 700;
  pointer-events: none;
  user-select: none;
}

/* Sizes */
.size-sm {
  height: 34px;
}
.size-sm .currency-input-field {
  font-size: 13px;
  padding-left: 10px;
  padding-right: 28px;
}

.size-md {
  height: 42px;
}
.size-md .currency-input-field {
  font-size: 15px;
  padding-left: 12px;
  padding-right: 32px;
}

.size-lg {
  height: 50px;
}
.size-lg .currency-input-field {
  font-size: 18px;
  font-weight: 800;
  padding-left: 14px;
  padding-right: 36px;
}

.is-disabled {
  opacity: 0.6;
  background: var(--surface-secondary, rgba(0, 0, 0, 0.04));
  cursor: not-allowed;
}

.is-disabled .currency-input-field {
  cursor: not-allowed;
}

:global(html.dark) .currency-input-wrapper {
  background: var(--surface, #1e191c);
  border-color: var(--outline, #4d4049);
}

:global(html.dark) .currency-input-field {
  color: var(--ink, #f8fafc);
}

:global(html.dark) .currency-suffix {
  color: var(--muted, #94a3b8);
}
</style>

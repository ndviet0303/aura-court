<script setup lang="ts">
import { computed, getCurrentInstance } from 'vue'
import { useI18n } from 'vue-i18n'

interface Props {
  modelValue?: boolean
  disabled?: boolean
  label?: string
  role?: 'switch' | 'menuitemcheckbox'
  showLabel?: boolean
  id?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: false,
  disabled: false,
  label: undefined,
  role: 'switch',
  showLabel: true,
  id: undefined,
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'change', value: boolean): void
}>()

let translateFn: (key: string) => string = (k) => k
try {
  const { t } = useI18n()
  translateFn = t
} catch {
  const vm = getCurrentInstance()
  const globalT = vm?.appContext.config.globalProperties.$t
  if (typeof globalT === 'function') {
    translateFn = (key: string) => globalT(key)
  }
}

const effectiveLabel = computed(() => props.label || translateFn('nav.erabookV2'))

function handleToggle(): void {
  if (props.disabled) return
  const nextValue = !props.modelValue
  emit('update:modelValue', nextValue)
  emit('change', nextValue)
}

function handleKeydown(event: KeyboardEvent): void {
  if (props.disabled) return
  if (event.key === ' ' || event.key === 'Enter') {
    event.preventDefault()
    handleToggle()
  }
}
</script>

<template>
  <button
    :id="id"
    type="button"
    :role="role"
    :aria-checked="modelValue"
    :aria-label="showLabel ? undefined : effectiveLabel"
    :disabled="disabled"
    class="aura-ui-version-switch"
    :class="{ 'is-checked': modelValue, 'is-disabled': disabled }"
    @click="handleToggle"
    @keydown="handleKeydown"
  >
    <span v-if="showLabel" class="switch-label">
      {{ effectiveLabel }}
    </span>

    <span class="switch-track" aria-hidden="true">
      <span class="switch-thumb">
        <span v-if="modelValue" class="thumb-badge">V2</span>
        <span v-else class="thumb-badge">V1</span>
      </span>
    </span>
  </button>
</template>

<style scoped>
.aura-ui-version-switch {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: transparent;
  border: none;
  padding: 6px 8px;
  border-radius: 12px;
  cursor: pointer;
  color: var(--ink, #212121);
  font-family: inherit;
  font-size: 13.5px;
  font-weight: 500;
  text-align: left;
  user-select: none;
  transition: background-color 0.15s ease, opacity 0.15s ease;
  min-height: 40px;
}

.aura-ui-version-switch:hover:not(:disabled) {
  background: rgba(0, 0, 0, 0.04);
}

html.dark .aura-ui-version-switch:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.06);
}

.aura-ui-version-switch:focus-visible {
  outline: 2px solid var(--primary, #F89300);
  outline-offset: 2px;
}

.aura-ui-version-switch.is-disabled {
  opacity: 0.45;
  cursor: not-allowed;
}

.switch-label {
  flex: 1;
  font-size: 13.5px;
  line-height: 1.4;
  color: inherit;
}

.switch-track {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
  border-radius: 9999px;
  background: #E8E1D7;
  border: 1px solid rgba(0, 0, 0, 0.08);
  transition: background-color 0.25s cubic-bezier(0.4, 0, 0.2, 1),
              border-color 0.25s cubic-bezier(0.4, 0, 0.2, 1);
  flex-shrink: 0;
}

html.dark .switch-track {
  background: #35383F;
  border-color: rgba(255, 255, 255, 0.1);
}

.is-checked .switch-track {
  background: #F89300;
  border-color: #E08500;
}

.switch-thumb {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  border-radius: 9999px;
  background: #FFFFFF;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.is-checked .switch-thumb {
  transform: translateX(22px);
}

.thumb-badge {
  font-size: 8.5px;
  font-weight: 700;
  line-height: 1;
  color: #616161;
  letter-spacing: -0.2px;
}

.is-checked .thumb-badge {
  color: #F89300;
}
</style>

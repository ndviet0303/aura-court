<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

export interface DropdownOption {
  value: string | number
  label: string
  icon?: string
  sublabel?: string
}

const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null
    options: (DropdownOption | string | number)[]
    placeholder?: string
    disabled?: boolean
    ariaLabel?: string
    variant?: 'inline' | 'bordered'
    align?: 'left' | 'right'
  }>(),
  {
    modelValue: undefined,
    placeholder: '',
    disabled: false,
    ariaLabel: 'Dropdown',
    variant: 'inline',
    align: 'left',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
  change: [value: string | number]
}>()

const root = ref<HTMLElement | null>(null)
const open = ref(false)

const normalizedOptions = computed<DropdownOption[]>(() => {
  return props.options.map((opt) => {
    if (typeof opt === 'object' && opt !== null) {
      return opt
    }
    return { value: opt, label: String(opt) }
  })
})

const selectedOption = computed(() => {
  return normalizedOptions.value.find((opt) => opt.value === props.modelValue)
})

const displayLabel = computed(() => {
  return selectedOption.value?.label || props.placeholder || ''
})

function toggle() {
  if (props.disabled) return
  open.value = !open.value
}

function selectOption(val: string | number) {
  emit('update:modelValue', val)
  emit('change', val)
  open.value = false
}

function handleOutside(event: MouseEvent) {
  if (root.value && !root.value.contains(event.target as Node)) {
    open.value = false
  }
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape' && open.value) {
    open.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleOutside)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutside)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div ref="root" class="custom-dropdown" :class="[variant, `align-${align}`, { open, disabled }]">
    <button
      type="button"
      class="custom-dropdown-trigger"
      :aria-expanded="open"
      :aria-label="ariaLabel || placeholder"
      :disabled="disabled"
      @click.stop="toggle"
    >
      <div class="trigger-content">
        <span v-if="selectedOption?.icon" class="material-symbols-outlined trigger-icon">
          {{ selectedOption.icon }}
        </span>
        <span class="trigger-label" :class="{ 'is-placeholder': !selectedOption }">
          {{ displayLabel }}
        </span>
      </div>
      <span class="material-symbols-outlined dropdown-arrow" aria-hidden="true"> expand_more </span>
    </button>

    <transition name="dropdown-fade">
      <div v-if="open" class="custom-dropdown-menu" role="listbox" :aria-label="ariaLabel">
        <button
          v-for="item in normalizedOptions"
          :key="String(item.value)"
          type="button"
          class="dropdown-item"
          :class="{ selected: item.value === modelValue }"
          role="option"
          :aria-selected="item.value === modelValue"
          @click="selectOption(item.value)"
        >
          <div class="item-text-wrap">
            <span v-if="item.icon" class="material-symbols-outlined item-icon">{{
              item.icon
            }}</span>
            <div class="item-labels-box">
              <span class="item-label">{{ item.label }}</span>
              <span v-if="item.sublabel" class="item-sublabel">{{ item.sublabel }}</span>
            </div>
          </div>
          <span v-if="item.value === modelValue" class="material-symbols-outlined item-check">
            check
          </span>
        </button>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.custom-dropdown {
  position: relative;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  user-select: none;
}

.custom-dropdown.open {
  z-index: 50;
}

.custom-dropdown-trigger {
  width: 100%;
  max-width: 100%;
  min-width: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  background: transparent;
  border: 0;
  padding: 2px 0;
  color: var(--ink);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  text-align: left;
  transition: color 0.2s ease;
  font-family: inherit;
  box-sizing: border-box;
}

.custom-dropdown.bordered .custom-dropdown-trigger {
  padding: 10px 14px;
  border: 1px solid var(--outline);
  border-radius: 12px;
  background-color: var(--surface);
}

.custom-dropdown-trigger:focus-visible {
  outline: 2px solid var(--pink-dark);
  outline-offset: 2px;
  border-radius: 6px;
}

.trigger-content {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  flex: 1 1 auto;
  overflow: hidden;
}

.trigger-icon {
  font-size: 18px;
  color: var(--pink-dark);
  flex-shrink: 0;
}

.trigger-label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 13.5px;
  line-height: 1.4;
  min-width: 0;
  display: block;
}

.trigger-label.is-placeholder {
  color: var(--muted);
  font-weight: 500;
}

.dropdown-arrow {
  font-size: 20px;
  color: var(--muted);
  transition:
    transform 0.2s ease,
    color 0.2s ease;
  flex-shrink: 0;
  line-height: 1;
}

.custom-dropdown-trigger:hover .dropdown-arrow,
.custom-dropdown.open .dropdown-arrow {
  color: var(--pink-dark);
}

.custom-dropdown.open .dropdown-arrow {
  transform: rotate(180deg);
}

.custom-dropdown.disabled {
  opacity: 0.6;
  pointer-events: none;
}

/* Menu Popover */
.custom-dropdown-menu {
  position: absolute;
  top: calc(100% + 6px);
  left: 0;
  right: auto;
  min-width: 100%;
  width: max-content;
  max-width: min(360px, calc(100vw - 24px));
  z-index: 999;
  background: var(--surface, #1e293b);
  border: 1px solid var(--outline, rgba(255, 255, 255, 0.15));
  border-radius: 12px;
  box-shadow:
    0 16px 40px rgba(0, 0, 0, 0.35),
    0 4px 12px rgba(0, 0, 0, 0.12);
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  max-height: 280px;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  backdrop-filter: blur(16px);
}

.custom-dropdown.align-right .custom-dropdown-menu {
  left: auto;
  right: 0;
}

.custom-dropdown-menu::-webkit-scrollbar {
  width: 5px;
}

.custom-dropdown-menu::-webkit-scrollbar-track {
  background: transparent;
}

.custom-dropdown-menu::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.2);
  border-radius: 999px;
}

.dropdown-item {
  border: 0;
  background: transparent;
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  cursor: pointer;
  color: var(--ink, #ffffff);
  font-size: 13px;
  font-weight: 500;
  text-align: left;
  transition: all 0.15s ease;
  font-family: inherit;
}

.dropdown-item:hover {
  background: var(--soft, rgba(255, 255, 255, 0.08));
  color: var(--primary, #10b981);
}

.dropdown-item.selected {
  background: rgba(16, 185, 129, 0.15);
  color: var(--primary, #10b981);
  font-weight: 700;
}

.item-text-wrap {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.item-icon {
  font-size: 18px;
  color: var(--primary, #10b981);
  flex-shrink: 0;
  margin-top: 1px;
}

.item-labels-box {
  display: flex;
  flex-direction: column;
  min-width: 0;
  gap: 2px;
}

.item-label {
  white-space: normal;
  line-height: 1.3;
  word-break: break-word;
  font-size: 12.5px;
  font-weight: 600;
}

.item-sublabel {
  font-size: 11px;
  color: var(--muted, #94a3b8);
  white-space: normal;
  line-height: 1.2;
}

.item-check {
  font-size: 18px;
  color: var(--primary, #10b981);
  font-weight: 700;
  flex-shrink: 0;
}

/* Animations */
.dropdown-fade-enter-active,
.dropdown-fade-leave-active {
  transition:
    opacity 0.15s ease,
    transform 0.15s ease;
}

.dropdown-fade-enter-from,
.dropdown-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
}
</style>

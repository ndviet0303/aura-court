<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  modelValue: string
  min?: string
  max?: string
  placeholder?: string
}>(), {
  placeholder: 'Chọn ngày',
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  change: [value: string]
}>()

const root = ref<HTMLElement | null>(null)
const open = ref(false)
const currentMonth = ref(startOfMonth(parseDate(props.modelValue) || new Date()))
const weekdays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']

function parseDate(value: string): Date | null {
  if (!value) return null
  const [year, month, day] = value.split('-').map(Number)
  if (!year || !month || !day) return null
  return new Date(year, month - 1, day)
}

function startOfMonth(value: Date): Date { return new Date(value.getFullYear(), value.getMonth(), 1) }
function toInput(value: Date): string { return [value.getFullYear(), String(value.getMonth() + 1).padStart(2, '0'), String(value.getDate()).padStart(2, '0')].join('-') }
function sameMonth(a: Date, b: Date): boolean { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() }

const displayValue = computed(() => {
  const date = parseDate(props.modelValue)
  return date ? new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(date) : props.placeholder
})
const monthLabel = computed(() => {
  const label = new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric' }).format(currentMonth.value)
  return label.charAt(0).toUpperCase() + label.slice(1)
})
const calendarDays = computed(() => {
  const first = startOfMonth(currentMonth.value)
  const offset = (first.getDay() + 6) % 7
  const start = new Date(first)
  start.setDate(first.getDate() - offset)
  return Array.from({ length: 42 }, (_, index) => {
    const date = new Date(start)
    date.setDate(start.getDate() + index)
    const value = toInput(date)
    return { date, value, day: date.getDate(), inMonth: sameMonth(date, currentMonth.value), disabled: Boolean((props.min && value < props.min) || (props.max && value > props.max)) }
  })
})

function selectDate(value: string) {
  const day = calendarDays.value.find(item => item.value === value)
  if (day?.disabled) return
  emit('update:modelValue', value)
  emit('change', value)
  open.value = false
}

function clear() {
  emit('update:modelValue', '')
  emit('change', '')
  open.value = false
}

function today() { selectDate(toInput(new Date())) }
function moveMonth(offset: number) { currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + offset, 1) }
function handleOutside(event: MouseEvent) { if (root.value && !root.value.contains(event.target as Node)) open.value = false }

watch(() => props.modelValue, value => { const parsed = parseDate(value); if (parsed) currentMonth.value = startOfMonth(parsed) })
onMounted(() => document.addEventListener('click', handleOutside))
onBeforeUnmount(() => document.removeEventListener('click', handleOutside))
</script>

<template>
  <div ref="root" class="date-picker">
    <button type="button" class="date-picker-trigger" :aria-expanded="open" aria-haspopup="dialog" @click.stop="open = !open">
      <span :class="{ 'date-picker-placeholder': !modelValue }">{{ displayValue }}</span>
      <span class="material-symbols-outlined" aria-hidden="true">calendar_month</span>
    </button>
    <div v-if="open" class="date-picker-popover" role="dialog" aria-label="Chọn ngày">
      <div class="date-picker-header">
        <strong>{{ monthLabel }}</strong>
        <div class="date-picker-nav">
          <button type="button" aria-label="Tháng trước" @click="moveMonth(-1)"><span class="material-symbols-outlined">chevron_left</span></button>
          <button type="button" aria-label="Tháng sau" @click="moveMonth(1)"><span class="material-symbols-outlined">chevron_right</span></button>
        </div>
      </div>
      <div class="date-picker-weekdays"><span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span></div>
      <div class="date-picker-grid">
        <button v-for="item in calendarDays" :key="item.value" type="button" class="date-picker-day" :class="{ muted: !item.inMonth, selected: item.value === modelValue, today: item.value === toInput(new Date()) }" :disabled="item.disabled" @click="selectDate(item.value)">{{ item.day }}</button>
      </div>
      <div class="date-picker-footer">
        <button v-if="modelValue" type="button" class="date-picker-action" @click="clear">Xóa</button>
        <button type="button" class="date-picker-action" @click="today">Hôm nay</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue'
import TimePicker from '@/components/TimePicker.vue'

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string]; change: [value: string] }>()
const datePart = () => props.modelValue ? props.modelValue.slice(0, 10) : ''
const timePart = () => props.modelValue ? props.modelValue.slice(11, 16) : ''
function updateDate(value: string) { const next = value ? `${value}T${timePart() || '00:00'}` : ''; emit('update:modelValue', next); emit('change', next) }
function updateTime(value: string) { const next = datePart() ? `${datePart()}T${value}` : ''; emit('update:modelValue', next); emit('change', next) }
</script>

<template>
  <div class="date-time-picker">
    <DatePicker :model-value="datePart()" @update:model-value="updateDate" />
    <TimePicker :model-value="timePart()" @update:model-value="updateTime" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import QRCode from 'qrcode'

const props = withDefaults(defineProps<{ value: string; alt: string; size?: number }>(), {
  size: 220,
})

const src = ref('')

// Drawn on the device: the payload (a booking code, a card number) never goes to a third-party QR service.
watch(
  () => [props.value, props.size] as const,
  async ([value, size]) => {
    src.value = ''
    const url = await QRCode.toDataURL(value, { width: size, margin: 2 })
    if (value === props.value) src.value = url
  },
  { immediate: true },
)
</script>

<template>
  <img v-if="src" :src="src" :alt="alt" :width="size" :height="size" />
</template>

<script setup lang="ts">
import IconBase from './IconBase.vue'

interface Props {
  size?: number | string
  color?: string
}

defineProps<Props>()
</script>

<template>
  <IconBase :size="size" :color="color" aria-label="Profile">
    <g transform="translate(-908 -473)">
      <path
        fill-rule="evenodd"
        clip-rule="evenodd"
        d="M925.294 480.291C925.294 483.228 922.939 485.583 920 485.583C917.062 485.583 914.706 483.228 914.706 480.291C914.706 477.354 917.062 475 920 475C922.939 475 925.294 477.354 925.294 480.291ZM920 495C915.662 495 912 494.295 912 491.575C912 488.854 915.685 488.174 920 488.174C924.339 488.174 928 488.879 928 491.599C928 494.32 924.315 495 920 495Z"
      />
    </g>
  </IconBase>
</template>

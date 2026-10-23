<script setup lang="ts">
defineProps<{ rating: number }>()
</script>

<template>
  <span class="stars" role="img" :aria-label="$t('reviews.ratingLabel', { rating })">
    <span
      v-for="star in 5"
      :key="star"
      class="material-symbols-outlined"
      :class="{ off: star > Math.round(rating) }"
      aria-hidden="true"
      >star</span
    >
  </span>
</template>

<style scoped>
.stars {
  display: inline-flex;
  color: #f59e0b;
}

.stars .material-symbols-outlined {
  font-size: 16px;
  font-variation-settings: 'FILL' 1;
}

.stars .off {
  color: var(--outline);
}
</style>

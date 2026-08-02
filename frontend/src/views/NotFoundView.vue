<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <section class="page-shell not-found">
    <div class="not-found-art" aria-hidden="true"><strong>404</strong><span>court not found</span></div>
    <div class="eyebrow">Lạc đường một chút</div>
    <h1 class="page-title">Không tìm thấy trang này.</h1>
    <p class="muted">Đường dẫn bạn mở không tồn tại hoặc đã được chuyển sang một vị trí khác.</p>
    <div class="actions">
      <RouterLink to="/" class="btn">Về trang chủ</RouterLink>
      <RouterLink to="/courts" class="btn secondary">Xem danh sách sân</RouterLink>
    </div>
  </section>
</template>

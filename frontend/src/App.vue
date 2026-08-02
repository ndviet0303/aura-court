<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
</script>

<template>
  <header class="topbar">
    <RouterLink to="/" class="brand"><img src="/aura/logo.png" alt="Aura Court" /><span>Aura Court</span></RouterLink>
    <nav class="main-nav"><RouterLink to="/">Trang chủ</RouterLink><RouterLink to="/courts">Danh sách sân</RouterLink><RouterLink v-if="auth.user" to="/booking">Lịch đặt sân</RouterLink><RouterLink v-if="auth.user?.role === 'admin'" to="/admin">Quản trị</RouterLink><RouterLink v-if="auth.user?.role === 'admin'" to="/admin/manage">Quản lý</RouterLink></nav>
    <div class="nav-actions"><RouterLink v-if="!auth.user" to="/login" class="icon-button" aria-label="Đăng nhập">person</RouterLink><template v-else><RouterLink to="/profile" class="icon-button" aria-label="Hồ sơ">account_circle</RouterLink><button class="icon-button" aria-label="Đăng xuất" @click="auth.logout">logout</button></template></div>
  </header>
  <main class="site-main"><RouterView /></main>
  <footer class="site-footer"><div class="footer-inner"><span class="brand footer-brand"><img src="/aura/logo.png" alt="" /> Aura Court</span><span>© 2026 Aura Court · Đặt sân cầu lông.</span><span>Chính sách · Hỗ trợ</span></div></footer>
</template>

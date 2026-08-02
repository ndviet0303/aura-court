<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
</script>

<template>
  <header class="topbar"><RouterLink to="/" class="brand">Cầu Lông 24</RouterLink><nav><RouterLink to="/">Đặt sân</RouterLink><RouterLink v-if="auth.user" to="/booking">Booking của tôi</RouterLink><RouterLink v-if="auth.user?.role === 'admin'" to="/admin">Quản trị</RouterLink><RouterLink v-if="!auth.user" to="/login">Đăng nhập</RouterLink><button v-else class="link-button" @click="auth.logout">Đăng xuất</button></nav></header>
  <main class="container"><RouterView /></main>
</template>

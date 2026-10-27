<script setup lang="ts">
function share() {
  const url = window.location.origin
  if (navigator.share) void navigator.share({ title: document.title, url }).catch(() => {})
  else void navigator.clipboard?.writeText(url)
}
</script>

<template>
  <div class="social-row">
    <a
      href="https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fcourt.ziet.dev%2F"
      target="_blank"
      rel="noopener"
      class="social-btn"
      title="Facebook"
      aria-label="Facebook"
    >
      <span class="material-symbols-outlined">public</span>
    </a>
    <a
      href="https://zalo.me/0963792413"
      target="_blank"
      rel="noopener"
      class="social-btn"
      title="Zalo / Chat"
      aria-label="Zalo / Chat"
    >
      <span class="material-symbols-outlined">chat</span>
    </a>
    <button type="button" class="social-btn" title="Chia sẻ" aria-label="Share" @click="share">
      <span class="material-symbols-outlined">share</span>
    </button>
  </div>
</template>

<style scoped>
.social-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
}

.social-btn {
  width: 36px;
  padding: 0;
  cursor: pointer;
  height: 36px;
  border-radius: 50%;
  background: var(--surface-subtle, #f7f3ed);
  border: 1px solid var(--border, #e8e1d7);
  color: var(--muted, #6e6668);
  display: grid;
  place-items: center;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-btn:hover {
  background: var(--accent-container, #faf2e3);
  color: var(--on-accent-container, #684f17);
  border-color: rgba(201, 161, 90, 0.4);
  transform: translateY(-2px);
}

:global(html.dark .social-btn) {
  background: #262024;
  color: #faf7f3;
  border-color: #382f35;
}

:global(html.dark .social-btn:hover) {
  background: rgba(223, 186, 114, 0.18);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.45);
}

.social-btn .material-symbols-outlined {
  font-size: 18px;
}
</style>

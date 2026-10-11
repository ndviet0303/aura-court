<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <footer class="site-footer">
    <div class="footer-container">
      <!-- Main Footer Grid (4 columns) -->
      <div class="footer-grid">
        <!-- Col 1: Brand Info -->
        <div class="footer-col brand-col">
          <RouterLink to="/" class="footer-brand" title="Aura Court">
            <div class="brand-icon-box">
              <img src="/aura/badge-logo.png" alt="Aura Court" class="brand-img" />
            </div>
            <div class="brand-title-box">
              <span class="brand-name">Aura Court</span>
              <span class="brand-subtitle">Badminton &amp; Sports Club</span>
            </div>
          </RouterLink>
          <p class="brand-tagline">
            {{ $t('common.footer.tagline') }}
          </p>
          <div class="footer-badges-row">
            <div class="working-hours-pill">
              <span class="material-symbols-outlined icon-clock">schedule</span>
              <span>{{ $t('common.footer.workingHours') }}</span>
            </div>
            <div class="bwf-badge">
              <span class="material-symbols-outlined icon-shield">verified</span>
              <span>BWF Standard</span>
            </div>
          </div>
        </div>

        <!-- Col 2: Quick Links / Explore -->
        <div class="footer-col">
          <h3 class="col-title">{{ $t('common.footer.explore') }}</h3>
          <ul class="col-links">
            <li>
              <RouterLink to="/">{{ $t('nav.home') }}</RouterLink>
            </li>
            <li>
              <RouterLink to="/courts">{{ $t('nav.courts') }}</RouterLink>
            </li>
            <li>
              <RouterLink to="/booking">{{ $t('nav.booking') }}</RouterLink>
            </li>
            <li>
              <RouterLink to="/resale">{{ $t('nav.resale') }}</RouterLink>
            </li>
            <li>
              <RouterLink to="/about">{{ $t('nav.about') }}</RouterLink>
            </li>
          </ul>
        </div>

        <!-- Col 3: Support & Legal Rules -->
        <div class="footer-col">
          <h3 class="col-title">{{ $t('common.footer.support') }}</h3>
          <ul class="col-links">
            <li>
              <RouterLink to="/terms#court-rules">{{ $t('common.footer.courtRules') }}</RouterLink>
            </li>
            <li>
              <RouterLink to="/terms#refund-policy">{{
                $t('common.footer.refundPolicy')
              }}</RouterLink>
            </li>
            <li>
              <RouterLink to="/terms">{{ $t('common.footer.termsOfService') }}</RouterLink>
            </li>
            <li>
              <RouterLink to="/privacy">{{ $t('common.footer.privacyPolicy') }}</RouterLink>
            </li>
          </ul>
        </div>

        <!-- Col 4: Contact & Social -->
        <div class="footer-col">
          <h3 class="col-title">{{ $t('common.footer.contact') }}</h3>
          <div class="contact-info">
            <a href="tel:0963792413" class="contact-item contact-link">
              <span class="material-symbols-outlined contact-icon">call</span>
              <span>0963 792 413</span>
            </a>
            <a href="mailto:ndviet.dev@gmail.com" class="contact-item contact-link">
              <span class="material-symbols-outlined contact-icon">mail</span>
              <span>ndviet.dev@gmail.com</span>
            </a>
            <div class="contact-item">
              <span class="material-symbols-outlined contact-icon">location_on</span>
              <span>{{ $t('common.footer.address') }}</span>
            </div>
          </div>

          <!-- Social Badges -->
          <div class="social-row">
            <a href="javascript:void(0)" class="social-btn" title="Facebook" aria-label="Facebook">
              <span class="material-symbols-outlined">public</span>
            </a>
            <a
              href="javascript:void(0)"
              class="social-btn"
              title="Zalo / Chat"
              aria-label="Zalo / Chat"
            >
              <span class="material-symbols-outlined">chat</span>
            </a>
            <a href="javascript:void(0)" class="social-btn" title="Chia sẻ" aria-label="Share">
              <span class="material-symbols-outlined">share</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="footer-bottom">
        <div class="copyright-group">
          <span class="copyright">{{ $t('common.footer.copyright') }}</span>
          <span class="demo-chip">
            <span class="material-symbols-outlined icon-demo">info</span>
            <span>{{ $t('common.demoFooter') }}</span>
          </span>
        </div>
        <div class="bottom-links">
          <RouterLink to="/terms" class="footer-bottom-link">{{
            $t('common.footer.termsOfService')
          }}</RouterLink>
          <span class="divider">·</span>
          <RouterLink to="/privacy" class="footer-bottom-link">{{
            $t('common.footer.privacyPolicy')
          }}</RouterLink>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  background: var(--surface, #ffffff);
  border-top: 1px solid var(--border, #e8e1d7);
  margin-top: 60px;
  position: relative;
  z-index: 10;
  width: 100%;
  transition:
    background-color 0.3s ease,
    border-color 0.3s ease;
}

:global(html.dark .site-footer) {
  background: var(--surface, #1e191c);
  border-color: var(--border, #382f35);
}

.footer-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 56px 28px 28px;
}

/* Grid Layout */
.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1.1fr 1.2fr 1.5fr;
  gap: 40px;
  margin-bottom: 48px;
}

.footer-col {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* Brand Column */
.brand-col {
  gap: 16px;
}

.footer-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.brand-icon-box {
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: #faf7f3;
  border: 1px solid #ebdccb;
  display: grid;
  place-items: center;
  box-shadow: 0 2px 8px rgba(107, 45, 63, 0.08);
  flex-shrink: 0;
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.footer-brand:hover .brand-icon-box {
  transform: scale(1.05);
  box-shadow: 0 4px 14px rgba(107, 45, 63, 0.16);
}

:global(html.dark .brand-icon-box) {
  background: #251e22;
  border: 1px solid rgba(223, 186, 114, 0.25);
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
}

:global(html.dark .footer-brand:hover .brand-icon-box) {
  border-color: rgba(223, 186, 114, 0.45);
  box-shadow: 0 4px 16px rgba(223, 186, 114, 0.2);
}

.brand-img {
  width: 26px;
  height: 26px;
  object-fit: contain;
}

.brand-title-box {
  display: flex;
  flex-direction: column;
}

.brand-name {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #1c1618);
  letter-spacing: -0.03em;
  transition: color 0.3s ease;
  line-height: 1.15;
}

:global(html.dark .brand-name) {
  color: #faf7f3;
}

.brand-subtitle {
  font-size: 11px;
  font-weight: 600;
  color: var(--muted, #6e6668);
  letter-spacing: 0.02em;
}

:global(html.dark .brand-subtitle) {
  color: #dfba72;
}

.brand-tagline {
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--muted, #6e6668);
  margin: 0;
  max-width: 380px;
}

:global(html.dark .brand-tagline) {
  color: #a89fa4;
}

.footer-badges-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.working-hours-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(201, 161, 90, 0.1);
  border: 1px solid rgba(201, 161, 90, 0.3);
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 650;
  color: var(--on-accent-container, #684f17);
  width: fit-content;
}

:global(html.dark .working-hours-pill) {
  background: rgba(223, 186, 114, 0.12);
  border-color: rgba(223, 186, 114, 0.3);
  color: #dfba72;
}

.icon-clock {
  font-size: 16px;
  color: var(--accent, #c9a15a);
}

:global(html.dark .icon-clock) {
  color: #dfba72;
}

.bwf-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(201, 161, 90, 0.12);
  border: 1px solid rgba(201, 161, 90, 0.35);
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 11.5px;
  font-weight: 700;
  color: var(--on-accent-container, #684f17);
}

:global(html.dark .bwf-badge) {
  background: rgba(223, 186, 114, 0.15);
  border-color: rgba(223, 186, 114, 0.35);
  color: #dfba72;
}

.icon-shield {
  font-size: 15px;
  color: var(--accent, #c9a15a);
}

:global(html.dark .icon-shield) {
  color: #dfba72;
}

/* Column Titles & Links */
.col-title {
  font-size: 13.5px;
  font-weight: 750;
  color: var(--ink, #1c1618);
  letter-spacing: 0.04em;
  margin: 0 0 4px;
  text-transform: uppercase;
}

:global(html.dark .col-title) {
  color: #faf7f3;
}

.col-links {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.col-links a {
  text-decoration: none;
  font-size: 13.5px;
  color: var(--muted, #6e6668);
  transition:
    color 0.18s ease,
    transform 0.18s ease;
  display: inline-block;
}

.col-links a:hover {
  color: var(--primary, #6b2d3f);
  transform: translateX(3px);
}

:global(html.dark .col-links a) {
  color: #a89fa4;
}

:global(html.dark .col-links a:hover) {
  color: #dfba72;
}

/* Contact Info */
.contact-info {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.contact-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 13px;
  color: var(--muted, #6e6668);
  text-decoration: none;
}

:global(html.dark .contact-item) {
  color: #a89fa4;
}

.contact-link {
  transition: color 0.18s ease;
}

.contact-link:hover {
  color: var(--primary, #6b2d3f);
}

:global(html.dark .contact-link:hover) {
  color: #dfba72;
}

.contact-icon {
  font-size: 18px;
  color: var(--accent, #c9a15a);
  flex-shrink: 0;
}

:global(html.dark .contact-icon) {
  color: #dfba72;
}

/* Social Buttons */
.social-row {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 6px;
}

.social-btn {
  width: 36px;
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

/* Bottom Bar */
.footer-bottom {
  padding-top: 24px;
  border-top: 1px solid var(--border, #e8e1d7);
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
  font-size: 13px;
  color: var(--muted, #6e6668);
}

:global(html.dark .footer-bottom) {
  border-color: #382f35;
  color: #a89fa4;
}

.copyright-group {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.copyright {
  font-weight: 500;
}

.demo-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(201, 161, 90, 0.12);
  color: var(--on-accent-container, #684f17);
  border: 1px solid rgba(201, 161, 90, 0.35);
  padding: 3px 11px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
}

:global(html.dark .demo-chip) {
  background: rgba(223, 186, 114, 0.15);
  color: #dfba72;
  border-color: rgba(223, 186, 114, 0.35);
}

.icon-demo {
  font-size: 14px;
  color: var(--accent, #c9a15a);
}

:global(html.dark .icon-demo) {
  color: #dfba72;
}

.bottom-links {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.divider {
  color: var(--outline, #dcd3c5);
}

:global(html.dark .divider) {
  color: #4d4049;
}

.footer-bottom-link {
  color: var(--muted, #6e6668);
  text-decoration: none;
  font-weight: 500;
  transition: color 0.15s ease;
}

.footer-bottom-link:hover {
  color: var(--primary, #6b2d3f);
}

:global(html.dark .footer-bottom-link) {
  color: #a89fa4;
}

:global(html.dark .footer-bottom-link:hover) {
  color: #dfba72;
}

/* Responsive */
@media (max-width: 960px) {
  .footer-grid {
    grid-template-columns: 1fr 1fr;
    gap: 32px;
  }
}

@media (max-width: 600px) {
  .footer-container {
    padding: 40px 20px 24px;
  }
  .footer-grid {
    grid-template-columns: 1fr;
    gap: 28px;
  }
  .footer-bottom {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>

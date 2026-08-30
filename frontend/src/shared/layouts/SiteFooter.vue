<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <footer class="site-footer">
    <div class="footer-container">
      <!-- Main Footer Grid -->
      <div class="footer-grid">
        <!-- Brand Info -->
        <div class="footer-col brand-col">
          <RouterLink to="/" class="footer-brand" title="Aura Court">
            <div class="brand-icon-box">
              <img src="/aura/badge-logo.png" alt="Aura Court" class="brand-img" />
            </div>
            <span class="brand-name">Aura Court</span>
          </RouterLink>
          <p class="brand-tagline">
            {{ $t('common.footer.tagline') }}
          </p>
          <div class="working-hours-pill">
            <span class="material-symbols-outlined icon-clock">schedule</span>
            <span>{{ $t('common.footer.workingHours') }}</span>
          </div>
        </div>

        <!-- Col 2: Quick Links -->
        <div class="footer-col">
          <h3 class="col-title">{{ $t('common.footer.explore') }}</h3>
          <ul class="col-links">
            <li><RouterLink to="/">{{ $t('nav.home') }}</RouterLink></li>
            <li><RouterLink to="/courts">{{ $t('nav.courts') }}</RouterLink></li>
            <li><RouterLink to="/booking">{{ $t('nav.booking') }}</RouterLink></li>
            <li><RouterLink to="/about">{{ $t('nav.about') }}</RouterLink></li>
          </ul>
        </div>

        <!-- Col 3: Support & Rules -->
        <div class="footer-col">
          <h3 class="col-title">{{ $t('common.footer.support') }}</h3>
          <ul class="col-links">
            <li><RouterLink to="/terms#court-rules">{{ $t('common.footer.courtRules') }}</RouterLink></li>
            <li><RouterLink to="/terms#refund-policy">{{ $t('common.footer.refundPolicy') }}</RouterLink></li>
            <li><RouterLink to="/terms">{{ $t('common.footer.termsOfService') }}</RouterLink></li>
            <li><RouterLink to="/privacy">{{ $t('common.footer.privacyPolicy') }}</RouterLink></li>
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
            <a href="javascript:void(0)" class="social-btn" title="Chat" aria-label="Chat">
              <span class="material-symbols-outlined">chat</span>
            </a>
            <a href="javascript:void(0)" class="social-btn" title="Share" aria-label="Share">
              <span class="material-symbols-outlined">share</span>
            </a>
          </div>
        </div>
      </div>

      <!-- Bottom Bar -->
      <div class="footer-bottom">
        <span class="copyright">{{ $t('common.footer.copyright') }}</span>
        <div class="bottom-links">
          <span class="demo-chip">{{ $t('common.demoFooter') }}</span>
          <span class="divider">·</span>
          <RouterLink to="/terms" class="footer-bottom-link">{{ $t('common.privacyAndSupport') }}</RouterLink>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  background: var(--surface, #ffffff);
  border-top: 1px solid var(--outline, rgba(225, 227, 228, 0.7));
  margin-top: 60px;
  position: relative;
  z-index: 10;
  width: 100%;
  transition: background-color 0.3s ease, border-color 0.3s ease;
}

:global(html.dark) .site-footer {
  background: var(--surface, #22242a);
  border-color: var(--border, #353942);
}

.footer-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 56px 28px 28px;
}

/* Grid Layout */
.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1.2fr 1.5fr;
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
  width: 38px;
  height: 38px;
  border-radius: 12px;
  background: var(--pink-light, #ffd9e2);
  display: grid;
  place-items: center;
  box-shadow: 0 2px 8px rgba(255, 209, 220, 0.3);
}

:global(html.dark) .brand-icon-box {
  background: #53333e;
  box-shadow: 0 2px 8px rgba(248, 180, 196, 0.2);
}

.brand-img {
  width: 24px;
  height: 24px;
  object-fit: contain;
}

.brand-name {
  font-size: 20px;
  font-weight: 800;
  color: var(--ink, #191c1d);
  letter-spacing: -0.03em;
  transition: color 0.3s ease;
}

:global(html.dark) .brand-name {
  color: #ffffff;
}

.brand-tagline {
  font-size: 13.5px;
  line-height: 1.6;
  color: var(--muted, #5d5f5f);
  margin: 0;
  max-width: 360px;
}

:global(html.dark) .brand-tagline {
  color: #9ca3af;
}

.working-hours-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--soft, #f8f9fa);
  border: 1px solid var(--outline, #e7e8e9);
  border-radius: 999px;
  padding: 6px 14px;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--pink-dark, #5e3e47);
  width: fit-content;
}

:global(html.dark) .working-hours-pill {
  background: #2b2e36;
  border-color: #353942;
  color: #f8b4c4;
}

.icon-clock {
  font-size: 16px;
  color: var(--pink-dark, #78555e);
}

:global(html.dark) .icon-clock {
  color: #f8b4c4;
}

/* Column Titles & Links */
.col-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink, #191c1d);
  letter-spacing: 0.02em;
  margin: 0 0 4px;
  text-transform: uppercase;
}

:global(html.dark) .col-title {
  color: #ffffff;
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
  color: var(--muted, #5d5f5f);
  transition: color 0.18s ease, transform 0.18s ease;
  display: inline-block;
}

.col-links a:hover {
  color: var(--pink-dark, #78555e);
  transform: translateX(2px);
}

:global(html.dark) .col-links a {
  color: #9ca3af;
}

:global(html.dark) .col-links a:hover {
  color: #E58E9F;
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

:global(html.dark) .contact-item {
  color: #a89fa4;
}

.contact-link {
  transition: color 0.18s ease;
}

.contact-link:hover {
  color: var(--primary, #6B2D3F);
}

:global(html.dark) .contact-link:hover {
  color: #E58E9F;
}

.contact-icon {
  font-size: 18px;
  color: var(--primary, #6B2D3F);
}

:global(html.dark) .contact-icon {
  color: #E58E9F;
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
  background: var(--soft, #f0eae1);
  color: var(--muted, #6e6668);
  display: grid;
  place-items: center;
  text-decoration: none;
  transition: all 0.2s ease;
}

.social-btn:hover {
  background: var(--primary-container, #F8E8EC);
  color: var(--primary, #6B2D3F);
  transform: translateY(-2px);
}

:global(html.dark) .social-btn {
  background: #262024;
  color: #FAF7F3;
  border: 1px solid #382f35;
}

:global(html.dark) .social-btn:hover {
  background: #441a26;
  color: #E58E9F;
  border-color: rgba(229, 142, 159, 0.4);
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
  font-size: 12.5px;
  color: var(--muted, #6e6668);
}

:global(html.dark) .footer-bottom {
  border-color: #382f35;
  color: #a89fa4;
}

.copyright {
  font-weight: 500;
}

.bottom-links {
  display: flex;
  align-items: center;
  gap: 10px;
}

.demo-chip {
  background: var(--primary-container, #F8E8EC);
  color: var(--on-primary-container, #4e1827);
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
}

:global(html.dark) .demo-chip {
  background: rgba(248, 180, 196, 0.18);
  color: #f8b4c4;
  border: 1px solid rgba(248, 180, 196, 0.35);
}

.divider {
  color: var(--outline, #d3c3c5);
}

:global(html.dark) .divider {
  color: #4a4e59;
}

.footer-bottom-link {
  color: inherit;
  text-decoration: none;
  transition: color 0.15s ease;
}

.footer-bottom-link:hover {
  color: var(--pink-dark, #78555e);
}

:global(html.dark) .footer-bottom-link:hover {
  color: #f8b4c4;
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

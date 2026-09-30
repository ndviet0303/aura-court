<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'

const { t } = useI18n()

function scrollToPillars() {
  const el = document.getElementById('pillars')
  if (el) {
    const yOffset = -90
    const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset
    window.scrollTo({ top: y, behavior: 'smooth' })
  }
}
</script>

<template>
  <div class="about-page-wrapper">
    <!-- Ambient Light Glow Background -->
    <div class="aura-glow-bg" aria-hidden="true"></div>

    <!-- 1. Hero Section -->
    <section class="about-hero-section">
      <div class="container">
        <div class="hero-content">
          <div class="brand-badge">
            <span class="material-symbols-outlined icon-18">verified</span>
            <span>{{ t('about.badge') }}</span>
          </div>
          
          <h1 class="hero-headline">
            {{ t('about.title') }}
          </h1>
          
          <p class="hero-subtext">
            {{ t('about.subtitle') }}
          </p>

          <div class="hero-actions">
            <RouterLink to="/" class="btn btn-gold">
              <span class="material-symbols-outlined">sports_tennis</span>
              <span>{{ t('about.bookCourtBtn') }}</span>
            </RouterLink>

            <button type="button" class="btn btn-outline" @click="scrollToPillars">
              <span class="material-symbols-outlined">explore</span>
              <span>{{ t('about.exploreFeaturesBtn') }}</span>
            </button>
          </div>
        </div>

        <!-- Stats Ribbon -->
        <div class="stats-ribbon">
          <div class="stat-card">
            <strong class="stat-num">{{ t('about.stats.sportsCount') }}</strong>
            <span class="stat-lbl">{{ t('about.stats.sportsLbl') }}</span>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat-card">
            <strong class="stat-num">{{ t('about.stats.bookingSpeed') }}</strong>
            <span class="stat-lbl">{{ t('about.stats.bookingSpeedLbl') }}</span>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat-card">
            <strong class="stat-num">{{ t('about.stats.floorQuality') }}</strong>
            <span class="stat-lbl">{{ t('about.stats.floorQualityLbl') }}</span>
          </div>
          <div class="stat-divider" aria-hidden="true"></div>
          <div class="stat-card">
            <strong class="stat-num">{{ t('about.stats.lightingSpec') }}</strong>
            <span class="stat-lbl">{{ t('about.stats.lightingSpecLbl') }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- 2. Story & Mission Section -->
    <section class="story-section">
      <div class="container">
        <div class="story-grid">
          <!-- Left: Narrative -->
          <div class="story-text">
            <div class="section-badge">
              <span class="material-symbols-outlined icon-18">flag</span>
              <span>{{ t('about.mission.badge') }}</span>
            </div>
            <h2 class="section-heading">
              {{ t('about.mission.title') }}
            </h2>
            <p class="paragraph">
              {{ t('about.mission.p1') }}
            </p>
            <p class="paragraph">
              {{ t('about.mission.p2') }}
            </p>

            <ul class="highlight-checklist">
              <li>
                <span class="material-symbols-outlined check-icon">check_circle</span>
                <span>{{ t('about.mission.highlight1') }}</span>
              </li>
              <li>
                <span class="material-symbols-outlined check-icon">check_circle</span>
                <span>{{ t('about.mission.highlight2') }}</span>
              </li>
              <li>
                <span class="material-symbols-outlined check-icon">check_circle</span>
                <span>{{ t('about.mission.highlight3') }}</span>
              </li>
            </ul>
          </div>

          <!-- Right: Visual Collage -->
          <div class="story-visual">
            <div class="image-card tall-card">
              <img src="/aura/court-0.jpg" alt="Aura Court Badminton Arena" loading="lazy" />
              <div class="image-overlay">
                <span class="chip-venue">BWF Standard Mat</span>
              </div>
            </div>
            <div class="image-stack">
              <div class="image-card short-card">
                <img src="/courts/pickleball-neon.jpg" alt="Aura Court Pickleball Arena" loading="lazy" />
                <div class="image-overlay">
                  <span class="chip-venue">USA Pickleball Certified</span>
                </div>
              </div>
              <div class="image-card short-card">
                <img src="/aura/court-2.jpg" alt="Aura Court Arena Lighting" loading="lazy" />
                <div class="image-overlay">
                  <span class="chip-venue">Anti-Glare 800 Lux</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 3. Four Pillars of Excellence -->
    <section id="pillars" class="pillars-section">
      <div class="container">
        <div class="section-header-center">
          <div class="section-badge">
            <span class="material-symbols-outlined icon-18">diamond</span>
            <span>{{ t('about.pillars.badge') }}</span>
          </div>
          <h2 class="section-heading">
            {{ t('about.pillars.title') }}
          </h2>
          <p class="section-desc">
            {{ t('about.pillars.subtitle') }}
          </p>
        </div>

        <div class="pillars-grid">
          <!-- Pillar 1 -->
          <article class="pillar-card">
            <div class="pillar-icon-box">
              <span class="material-symbols-outlined">stadium</span>
            </div>
            <h3>{{ t('about.pillars.p1Title') }}</h3>
            <p>{{ t('about.pillars.p1Desc') }}</p>
          </article>

          <!-- Pillar 2 -->
          <article class="pillar-card">
            <div class="pillar-icon-box">
              <span class="material-symbols-outlined">videocam</span>
            </div>
            <h3>{{ t('about.pillars.p2Title') }}</h3>
            <p>{{ t('about.pillars.p2Desc') }}</p>
          </article>

          <!-- Pillar 3 -->
          <article class="pillar-card">
            <div class="pillar-icon-box">
              <span class="material-symbols-outlined">qr_code_scanner</span>
            </div>
            <h3>{{ t('about.pillars.p3Title') }}</h3>
            <p>{{ t('about.pillars.p3Desc') }}</p>
          </article>

          <!-- Pillar 4 -->
          <article class="pillar-card">
            <div class="pillar-icon-box">
              <span class="material-symbols-outlined">room_service</span>
            </div>
            <h3>{{ t('about.pillars.p4Title') }}</h3>
            <p>{{ t('about.pillars.p4Desc') }}</p>
          </article>
        </div>
      </div>
    </section>

    <!-- 4. Sports Disciplines Showcase -->
    <section class="sports-showcase-section">
      <div class="container">
        <div class="section-header-center">
          <div class="section-badge">
            <span class="material-symbols-outlined icon-18">sports</span>
            <span>{{ t('about.sports.badge') }}</span>
          </div>
          <h2 class="section-heading">
            {{ t('about.sports.title') }}
          </h2>
        </div>

        <div class="sports-grid">
          <div class="sport-card">
            <div class="sport-media">
              <img src="/aura/court-0.jpg" alt="Badminton" loading="lazy" />
              <div class="sport-tag">Olympic Class</div>
            </div>
            <div class="sport-body">
              <h3>{{ t('about.sports.badminton') }}</h3>
              <p>{{ t('about.sports.badmintonDesc') }}</p>
            </div>
          </div>

          <div class="sport-card">
            <div class="sport-media">
              <img src="/courts/pickleball-outdoor.jpg" alt="Pickleball" loading="lazy" />
              <div class="sport-tag">Trending #1</div>
            </div>
            <div class="sport-body">
              <h3>{{ t('about.sports.pickleball') }}</h3>
              <p>{{ t('about.sports.pickleballDesc') }}</p>
            </div>
          </div>

          <div class="sport-card">
            <div class="sport-media">
              <img src="/courts/football-mini-turf.jpg" alt="Football 5v5" loading="lazy" />
              <div class="sport-tag">FIFA Turf</div>
            </div>
            <div class="sport-body">
              <h3>{{ t('about.sports.football') }}</h3>
              <p>{{ t('about.sports.footballDesc') }}</p>
            </div>
          </div>

          <div class="sport-card">
            <div class="sport-media">
              <img src="/courts/table-tennis-1.jpg" alt="Table Tennis" loading="lazy" />
              <div class="sport-tag">ITTF Arena</div>
            </div>
            <div class="sport-body">
              <h3>{{ t('about.sports.tableTennis') }}</h3>
              <p>{{ t('about.sports.tableTennisDesc') }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 5. Core Values Section -->
    <section class="values-section">
      <div class="container">
        <div class="section-header-center">
          <div class="section-badge">
            <span class="material-symbols-outlined icon-18">shield_with_heart</span>
            <span>{{ t('about.values.badge') }}</span>
          </div>
          <h2 class="section-heading">
            {{ t('about.values.title') }}
          </h2>
        </div>

        <div class="values-grid">
          <div class="value-card">
            <div class="value-num">01</div>
            <h3>{{ t('about.values.v1Title') }}</h3>
            <p>{{ t('about.values.v1Desc') }}</p>
          </div>

          <div class="value-card">
            <div class="value-num">02</div>
            <h3>{{ t('about.values.v2Title') }}</h3>
            <p>{{ t('about.values.v2Desc') }}</p>
          </div>

          <div class="value-card">
            <div class="value-num">03</div>
            <h3>{{ t('about.values.v3Title') }}</h3>
            <p>{{ t('about.values.v3Desc') }}</p>
          </div>

          <div class="value-card">
            <div class="value-num">04</div>
            <h3>{{ t('about.values.v4Title') }}</h3>
            <p>{{ t('about.values.v4Desc') }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 6. Call to Action Banner -->
    <section class="cta-section">
      <div class="container">
        <div class="cta-box">
          <div class="cta-content">
            <h2 class="cta-title">{{ t('about.cta.title') }}</h2>
            <p class="cta-subtitle">{{ t('about.cta.subtitle') }}</p>
            <div class="cta-actions">
              <RouterLink to="/" class="btn btn-gold btn-lg">
                <span class="material-symbols-outlined">calendar_month</span>
                <span>{{ t('about.cta.btnBook') }}</span>
              </RouterLink>
              <RouterLink to="/tournaments" class="btn btn-outline btn-lg">
                <span class="material-symbols-outlined">trophy</span>
                <span>{{ t('about.cta.btnCommunity') }}</span>
              </RouterLink>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.about-page-wrapper {
  position: relative;
  overflow: hidden;
  padding-bottom: 80px;
}

/* Ambient Glow Background */
.aura-glow-bg {
  position: absolute;
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 1000px;
  height: 600px;
  background: radial-gradient(circle at center, rgba(201, 161, 90, 0.12) 0%, rgba(107, 45, 63, 0.08) 45%, transparent 70%);
  pointer-events: none;
  z-index: 0;
}

:global(html.dark .aura-glow-bg) {
  background: radial-gradient(circle at center, rgba(216, 180, 110, 0.15) 0%, rgba(68, 26, 38, 0.25) 50%, transparent 75%);
}

.container {
  position: relative;
  z-index: 1;
}

/* Badges */
.brand-badge,
.section-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: var(--primary-container, #F8E8EC);
  color: var(--primary, #6B2D3F);
  padding: 6px 14px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 750;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  margin-bottom: 18px;
  border: 1px solid rgba(107, 45, 63, 0.15);
}

:global(html.dark .brand-badge),
:global(html.dark .section-badge) {
  background: rgba(68, 26, 38, 0.85);
  color: #E58E9F;
  border-color: rgba(229, 142, 159, 0.3);
}

.icon-18 {
  font-size: 18px;
}

/* 1. Hero Section */
.about-hero-section {
  padding: 50px 0 60px;
}

.hero-content {
  max-width: 860px;
  margin: 0 auto 50px;
  text-align: center;
}

.hero-headline {
  font-size: clamp(34px, 5vw, 54px);
  font-weight: 850;
  line-height: 1.15;
  letter-spacing: -0.035em;
  color: var(--ink, #191c1d);
  margin: 0 0 20px;
}

.hero-subtext {
  font-size: clamp(16px, 2vw, 19px);
  line-height: 1.6;
  color: var(--muted, #6e6668);
  margin: 0 auto 36px;
  max-width: 760px;
}

.hero-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  flex-wrap: wrap;
}

/* Stats Ribbon */
.stats-ribbon {
  display: flex;
  align-items: center;
  justify-content: space-around;
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e1e3e4);
  border-radius: 24px;
  padding: 28px 36px;
  box-shadow: var(--shadow-sm, 0 4px 16px rgba(107, 45, 63, 0.06));
}

:global(html.dark .stats-ribbon) {
  background: #1e191c;
  border-color: #382f35;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.stat-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: 6px;
}

.stat-num {
  font-size: clamp(28px, 3.5vw, 40px);
  font-weight: 850;
  color: var(--primary, #6B2D3F);
  letter-spacing: -0.03em;
  line-height: 1;
  font-family: monospace, ui-monospace, sans-serif;
}

:global(html.dark .stat-num) {
  color: #D8B46E;
  text-shadow: 0 0 16px rgba(216, 180, 110, 0.35);
}

.stat-lbl {
  font-size: 13px;
  color: var(--muted, #6e6668);
  font-weight: 600;
  max-width: 170px;
}

.stat-divider {
  width: 1px;
  height: 48px;
  background: var(--border, #e1e3e4);
}

/* Section Common */
.section-header-center {
  text-align: center;
  max-width: 700px;
  margin: 0 auto 50px;
}

.section-heading {
  font-size: clamp(26px, 3.5vw, 36px);
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--ink, #191c1d);
  margin: 0 0 14px;
  line-height: 1.25;
}

.section-desc {
  font-size: 16px;
  line-height: 1.6;
  color: var(--muted, #6e6668);
  margin: 0;
}

/* 2. Story Section */
.story-section {
  padding: 80px 0;
}

.story-grid {
  display: grid;
  grid-template-columns: 1.1fr 1fr;
  gap: 50px;
  align-items: center;
}

.story-text .paragraph {
  font-size: 16px;
  line-height: 1.7;
  color: var(--ink, #191c1d);
  margin: 0 0 18px;
  opacity: 0.9;
}

.highlight-checklist {
  list-style: none;
  padding: 0;
  margin: 28px 0 0;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.highlight-checklist li {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 15px;
  font-weight: 600;
  color: var(--ink, #191c1d);
}

.check-icon {
  color: #16a34a;
  font-size: 22px;
  flex-shrink: 0;
}

:global(html.dark .check-icon) {
  color: #4ade80;
}

/* Story Visual Collage */
.story-visual {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.image-card {
  position: relative;
  border-radius: 22px;
  overflow: hidden;
  border: 1px solid var(--border, #e1e3e4);
  box-shadow: var(--shadow-sm, 0 4px 16px rgba(0, 0, 0, 0.08));
}

:global(html.dark .image-card) {
  border-color: #382f35;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
}

.image-card img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  transition: transform 0.4s ease;
}

.image-card:hover img {
  transform: scale(1.05);
}

.tall-card {
  height: 380px;
}

.image-stack {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.short-card {
  height: 183px;
}

.image-overlay {
  position: absolute;
  bottom: 12px;
  left: 12px;
  right: 12px;
}

.chip-venue {
  display: inline-block;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  color: #ffffff;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 700;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

/* 3. Pillars of Excellence */
.pillars-section {
  padding: 80px 0;
  background: var(--surface-subtle, rgba(247, 243, 237, 0.5));
  border-top: 1px solid var(--border, #e1e3e4);
  border-bottom: 1px solid var(--border, #e1e3e4);
}

:global(html.dark .pillars-section) {
  background: rgba(30, 25, 28, 0.4);
  border-color: #382f35;
}

.pillars-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
}

.pillar-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e1e3e4);
  border-radius: 22px;
  padding: 30px 24px;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  display: flex;
  flex-direction: column;
}

:global(html.dark .pillar-card) {
  background: #1e191c;
  border-color: #382f35;
}

.pillar-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-md, 0 12px 30px rgba(107, 45, 63, 0.12));
  border-color: rgba(201, 161, 90, 0.5);
}

.pillar-icon-box {
  width: 52px;
  height: 52px;
  border-radius: 16px;
  background: var(--primary-container, #F8E8EC);
  color: var(--primary, #6B2D3F);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 20px;
}

:global(html.dark .pillar-icon-box) {
  background: #3B1822;
  color: #D8B46E;
  border: 1px solid rgba(216, 180, 110, 0.3);
}

.pillar-icon-box .material-symbols-outlined {
  font-size: 28px;
}

.pillar-card h3 {
  font-size: 18px;
  font-weight: 750;
  color: var(--ink, #191c1d);
  margin: 0 0 10px;
  letter-spacing: -0.02em;
}

.pillar-card p {
  font-size: 14px;
  line-height: 1.6;
  color: var(--muted, #6e6668);
  margin: 0;
}

/* 4. Sports Showcase Section */
.sports-showcase-section {
  padding: 80px 0;
}

.sports-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.sport-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e1e3e4);
  border-radius: 20px;
  overflow: hidden;
  transition: all 0.3s ease;
}

:global(html.dark .sport-card) {
  background: #1e191c;
  border-color: #382f35;
}

.sport-card:hover {
  transform: translateY(-3px);
  box-shadow: var(--shadow-md, 0 10px 24px rgba(0, 0, 0, 0.1));
}

.sport-media {
  position: relative;
  height: 180px;
}

.sport-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.sport-tag {
  position: absolute;
  top: 12px;
  right: 12px;
  background: rgba(201, 161, 90, 0.92);
  color: #1c1202;
  font-size: 11px;
  font-weight: 800;
  padding: 3px 9px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.sport-body {
  padding: 20px;
}

.sport-body h3 {
  font-size: 17px;
  font-weight: 750;
  color: var(--ink, #191c1d);
  margin: 0 0 8px;
}

.sport-body p {
  font-size: 13.5px;
  line-height: 1.55;
  color: var(--muted, #6e6668);
  margin: 0;
}

/* 5. Values Section */
.values-section {
  padding: 80px 0;
  background: var(--surface-subtle, rgba(247, 243, 237, 0.4));
}

:global(html.dark .values-section) {
  background: rgba(24, 20, 22, 0.5);
}

.values-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 20px;
}

.value-card {
  background: var(--card-bg, #ffffff);
  border: 1px solid var(--border, #e1e3e4);
  border-radius: 20px;
  padding: 26px 20px;
  position: relative;
}

:global(html.dark .value-card) {
  background: #1e191c;
  border-color: #382f35;
}

.value-num {
  font-size: 30px;
  font-weight: 900;
  font-family: monospace, ui-monospace, sans-serif;
  margin-bottom: 14px;
  line-height: 1;
  letter-spacing: -0.02em;
  display: inline-block;
  background: linear-gradient(135deg, #a67324 0%, #6B2D3F 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

:global(html.dark .value-num) {
  background: linear-gradient(135deg, #ffe094 0%, #dfba72 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  filter: drop-shadow(0 2px 10px rgba(223, 186, 114, 0.4));
}

.value-card h3 {
  font-size: 16.5px;
  font-weight: 750;
  color: var(--ink, #191c1d);
  margin: 0 0 8px;
}

.value-card p {
  font-size: 13.5px;
  line-height: 1.55;
  color: var(--muted, #6e6668);
  margin: 0;
}

/* 6. Call to Action Banner */
.cta-section {
  padding: 80px 0 20px;
}

.cta-box {
  background: linear-gradient(135deg, #6B2D3F 0%, #441A26 60%, #2A1017 100%);
  border-radius: 28px;
  padding: 60px 40px;
  text-align: center;
  color: #ffffff;
  position: relative;
  overflow: hidden;
  box-shadow: 0 20px 50px rgba(107, 45, 63, 0.3);
  border: 1px solid rgba(201, 161, 90, 0.3);
}

:global(html.dark .cta-box) {
  background: linear-gradient(135deg, #3B1822 0%, #240F16 60%, #15090D 100%);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
  border-color: rgba(216, 180, 110, 0.35);
}

.cta-box::before {
  content: '';
  position: absolute;
  top: -50%;
  left: 50%;
  transform: translateX(-50%);
  width: 500px;
  height: 500px;
  background: radial-gradient(circle, rgba(201, 161, 90, 0.22) 0%, transparent 60%);
  pointer-events: none;
}

.cta-content {
  position: relative;
  z-index: 1;
  max-width: 680px;
  margin: 0 auto;
}

.cta-title {
  font-size: clamp(26px, 3.5vw, 38px);
  font-weight: 850;
  letter-spacing: -0.03em;
  margin: 0 0 16px;
  line-height: 1.2;
}

.cta-subtitle {
  font-size: 17px;
  line-height: 1.6;
  opacity: 0.9;
  margin: 0 0 32px;
}

.cta-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  flex-wrap: wrap;
}

.btn-lg {
  padding: 12px 28px;
  font-size: 15px;
}

/* Responsive Adaptations */
@media (max-width: 1024px) {
  .pillars-grid,
  .sports-grid,
  .values-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .stats-ribbon {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    padding: 24px;
  }

  .stat-divider {
    display: none;
  }

  .story-grid {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}

@media (max-width: 640px) {
  .pillars-grid,
  .sports-grid,
  .values-grid {
    grid-template-columns: 1fr;
  }

  .stats-ribbon {
    grid-template-columns: 1fr 1fr;
    padding: 18px;
    gap: 16px;
  }

  .stat-card {
    gap: 4px;
  }

  .stat-num {
    font-size: 26px;
  }

  .stat-lbl {
    font-size: 11.5px;
  }

  .cta-box {
    padding: 40px 20px;
  }

  .cta-actions {
    flex-direction: column;
    width: 100%;
  }

  .cta-actions .btn {
    width: 100%;
  }
}
</style>


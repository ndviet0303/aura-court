import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { createRouter, createWebHistory } from 'vue-router'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import { i18n } from '@/i18n'
import { useAppearanceStore } from '@/features/appearance/stores/appearanceStore'
import TermsView from '@/features/legal/views/TermsView.vue'
import PrivacyView from '@/features/legal/views/PrivacyView.vue'
import NotFoundView from '@/shared/views/NotFoundView.vue'

describe('Legal & Not Found Views V2 Adaptation (Task 5.2)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } },
      { path: '/courts', component: { template: '<div>Courts</div>' } },
      { path: '/terms', component: TermsView },
      { path: '/privacy', component: PrivacyView },
      { path: '/:pathMatch(.*)*', component: NotFoundView },
    ],
  })

  beforeEach(async () => {
    setActivePinia(createPinia())
    localStorage.clear()
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
    await router.push('/terms')
    await router.isReady()
  })

  it('contains V2 legal styling rules in erabook.css', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .legal-page-wrapper .sidebar-sticky-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .legal-badge')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .nav-link-btn.active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .doc-tab.active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .page-shell.not-found .eyebrow')
  })

  it('renders TermsView with sticky navigation and preserves section selection across V1 <-> V2 switches', async () => {
    const appearance = useAppearanceStore()
    appearance.setUiVersion('v1')

    const wrapper = mount(TermsView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.legal-page-wrapper').exists()).toBe(true)
    const navLinks = wrapper.findAll('.nav-link-btn')
    expect(navLinks.length).toBeGreaterThan(0)

    // First section is active by default
    expect(navLinks[0]?.classes()).toContain('active')

    // Click second section
    await navLinks[1]?.trigger('click')
    expect(navLinks[1]?.classes()).toContain('active')

    // Switch to V2
    appearance.setUiVersion('v2')
    await wrapper.vm.$nextTick()
    expect(navLinks[1]?.classes()).toContain('active')

    // Switch back to V1
    appearance.setUiVersion('v1')
    await wrapper.vm.$nextTick()
    expect(navLinks[1]?.classes()).toContain('active')
  })

  it('renders PrivacyView with doc tabs and content cards', () => {
    const wrapper = mount(PrivacyView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.legal-page-wrapper').exists()).toBe(true)
    expect(wrapper.find('.doc-switcher').exists()).toBe(true)
    expect(wrapper.findAll('.content-card').length).toBeGreaterThan(0)
  })

  it('renders NotFoundView with 404 eyebrow and functional navigation links', () => {
    const wrapper = mount(NotFoundView, {
      global: {
        plugins: [i18n, router],
      },
    })

    expect(wrapper.find('.page-shell.not-found').exists()).toBe(true)
    expect(wrapper.find('.eyebrow').text()).toContain('404')
    const actionLinks = wrapper.findAll('a.btn')
    expect(actionLinks.length).toBe(2)
  })
})

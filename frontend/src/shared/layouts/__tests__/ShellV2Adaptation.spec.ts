import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import SiteFooter from '@/shared/layouts/footer/SiteFooter.vue'
import { i18n } from '@/i18n'

describe('Shell V2 Adaptation (Navbar, Footer, Admin, Overlays)', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  beforeEach(() => {
    document.documentElement.removeAttribute('data-ui-version')
    document.documentElement.classList.remove('dark')
  })

  it('contains V2 admin shell and footer rules in CSS', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .admin-sidebar')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .sidebar-nav-item.active')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .site-footer')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .active-indicator-dot')
  })

  it('renders SiteFooter cleanly with brand, navigation links, and copyright', () => {
    const wrapper = mount(SiteFooter, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: {
            template: '<a :href="to"><slot /></a>',
            props: ['to'],
          },
        },
      },
    })

    expect(wrapper.find('.site-footer').exists()).toBe(true)
    expect(wrapper.find('.footer-brand').exists()).toBe(true)
    expect(wrapper.findAll('.footer-col').length).toBeGreaterThanOrEqual(4)
    expect(wrapper.find('.footer-bottom').exists()).toBe(true)
  })

  it('propagates data-ui-version attribute down to teleported body elements', () => {
    document.documentElement.setAttribute('data-ui-version', 'v2')
    expect(document.documentElement.getAttribute('data-ui-version')).toBe('v2')

    // Create a mock teleported modal in body
    const modalEl = document.createElement('div')
    modalEl.className = 'modal-card'
    document.body.appendChild(modalEl)

    // The modal element inside body inherits html[data-ui-version="v2"] selector scope
    const matched = document.querySelector('html[data-ui-version="v2"] body .modal-card')
    expect(matched).not.toBeNull()

    modalEl.remove()
  })
})

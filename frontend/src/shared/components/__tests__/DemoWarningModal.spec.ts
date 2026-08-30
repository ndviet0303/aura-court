import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import DemoWarningModal from '../DemoWarningModal.vue'

describe('DemoWarningModal', () => {
  let container: HTMLDivElement

  beforeEach(() => {
    sessionStorage.clear()
    container = document.createElement('div')
    document.body.appendChild(container)
  })

  afterEach(() => {
    document.body.innerHTML = ''
    sessionStorage.clear()
  })

  it('renders modal when not previously acknowledged', async () => {
    mount(DemoWarningModal, {
      attachTo: container,
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    await nextTick()

    const overlay = document.querySelector('.demo-modal-overlay')
    expect(overlay).not.toBeNull()
    const card = document.querySelector('.demo-modal-card')
    expect(card).not.toBeNull()
  })

  it('does not show modal if already acknowledged in sessionStorage', async () => {
    sessionStorage.setItem('aura_demo_warned', 'true')
    mount(DemoWarningModal, {
      attachTo: container,
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    await nextTick()

    const overlay = document.querySelector('.demo-modal-overlay')
    expect(overlay).toBeNull()
  })

  it('dismisses modal and sets sessionStorage flag when clicking understand button', async () => {
    mount(DemoWarningModal, {
      attachTo: container,
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    await nextTick()

    const btn = document.querySelector('.btn-understand') as HTMLButtonElement | null
    expect(btn).not.toBeNull()
    btn?.click()
    await nextTick()

    expect(sessionStorage.getItem('aura_demo_warned')).toBe('true')
  })

  it('dismisses modal when close button is clicked', async () => {
    mount(DemoWarningModal, {
      attachTo: container,
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    await nextTick()

    const closeBtn = document.querySelector('.demo-modal-close-btn') as HTMLButtonElement | null
    expect(closeBtn).not.toBeNull()
    closeBtn?.click()
    await nextTick()

    expect(sessionStorage.getItem('aura_demo_warned')).toBe('true')
  })
})

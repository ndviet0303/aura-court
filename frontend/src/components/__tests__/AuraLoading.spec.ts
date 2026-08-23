import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import AuraLoading from '../AuraLoading.vue'

describe('AuraLoading', () => {
  it('renders with default props', () => {
    const wrapper = mount(AuraLoading, {
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    expect(wrapper.find('.aura-loading-root').exists()).toBe(true)
    expect(wrapper.find('.orbit-ring').exists()).toBe(true)
    expect(wrapper.find('.logo-pulse-box').exists()).toBe(true)
  })

  it('renders fullscreen mode when prop is set', () => {
    const wrapper = mount(AuraLoading, {
      props: {
        fullscreen: true,
        text: 'Đang tải dữ liệu...',
      },
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    expect(wrapper.find('.is-fullscreen').exists()).toBe(true)
    expect(wrapper.text()).toContain('Đang tải dữ liệu...')
  })

  it('supports size variations', () => {
    const wrapper = mount(AuraLoading, {
      props: {
        size: 'sm',
      },
      global: {
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    expect(wrapper.find('.size-sm').exists()).toBe(true)
  })
})

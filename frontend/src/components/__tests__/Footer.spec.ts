import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import SiteFooter from '../SiteFooter.vue'

describe('SiteFooter', () => {
  it('renders brand name and columns', () => {
    const wrapper = mount(SiteFooter, {
      global: {
        stubs: {
          RouterLink: { template: '<a><slot /></a>' },
        },
        mocks: {
          $t: (key: string) => key,
        },
      },
    })
    expect(wrapper.find('.site-footer').exists()).toBe(true)
    expect(wrapper.find('.brand-name').text()).toBe('Aura Court')
    expect(wrapper.findAll('.footer-col')).toHaveLength(4)
  })
})

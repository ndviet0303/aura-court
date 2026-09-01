import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import TermsView from '../TermsView.vue'
import PrivacyView from '../PrivacyView.vue'
import { i18n } from '@/i18n'

describe('Legal Pages', () => {
  it('renders TermsView with all sections', () => {
    const wrapper = mount(TermsView, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: true,
        },
      },
    })
    expect(wrapper.find('h1').text()).toContain('Điều khoản dịch vụ')
    expect(wrapper.find('#general').exists()).toBe(true)
    expect(wrapper.find('#refund-policy').exists()).toBe(true)
    expect(wrapper.find('#court-rules').exists()).toBe(true)
  })

  it('renders PrivacyView with all security pillars and sections', () => {
    const wrapper = mount(PrivacyView, {
      global: {
        plugins: [i18n],
        stubs: {
          RouterLink: true,
        },
      },
    })
    expect(wrapper.find('h1').text()).toContain('Chính sách bảo mật')
    expect(wrapper.find('#collection').exists()).toBe(true)
    expect(wrapper.find('#payment-security').exists()).toBe(true)
    expect(wrapper.find('#user-rights').exists()).toBe(true)
  })
})

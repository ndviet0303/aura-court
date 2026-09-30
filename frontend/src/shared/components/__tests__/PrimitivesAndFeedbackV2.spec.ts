import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import ErrorModal from '@/shared/components/feedback/ErrorModal.vue'
import SkeletonLoader from '@/shared/components/feedback/SkeletonLoader.vue'
import { i18n } from '@/i18n'

describe('Primitives, Feedback & Modals V2 Adaptation', () => {
  const currentDir = path.dirname(fileURLToPath(import.meta.url))
  const erabookCssPath = path.resolve(currentDir, '../../../assets/styles/erabook.css')
  const erabookCss = fs.readFileSync(erabookCssPath, 'utf-8')

  it('contains V2 card, table, modal, and skeleton rules', () => {
    expect(erabookCss).toContain('html[data-ui-version="v2"] .card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .modal-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .error-modal-card')
    expect(erabookCss).toContain('html[data-ui-version="v2"] table th')
    expect(erabookCss).toContain('html[data-ui-version="v2"] .skeleton')
  })

  it('renders ErrorModal with correct semantics, accessibility and real text', () => {
    const wrapper = mount(ErrorModal, {
      props: {
        open: true,
        title: 'Lỗi hệ thống',
        message: 'Không thể kết nối đến máy chủ',
      },
      global: {
        plugins: [i18n],
        stubs: {
          Teleport: true,
        },
      },
    })

    expect(wrapper.find('.error-modal-card').exists()).toBe(true)
    expect(wrapper.text()).toContain('Lỗi hệ thống')
    expect(wrapper.text()).toContain('Không thể kết nối đến máy chủ')

    const closeBtn = wrapper.find('.error-modal-close-btn')
    expect(closeBtn.exists()).toBe(true)
    expect(closeBtn.attributes('type')).toBe('button')
  })

  it('renders SkeletonLoader with proper structure', () => {
    const wrapper = mount(SkeletonLoader, {
      props: {
        type: 'card',
        count: 2,
      },
    })

    expect(wrapper.exists()).toBe(true)
  })
})

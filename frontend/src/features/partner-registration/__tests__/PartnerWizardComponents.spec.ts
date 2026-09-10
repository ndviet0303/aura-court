import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BusinessInfoStep from '../components/BusinessInfoStep.vue'
import DocumentUploadStep from '../components/DocumentUploadStep.vue'
import ConsentAndReviewStep from '../components/ConsentAndReviewStep.vue'
import type { PartnerApplication } from '../types'

const baseMockApp: PartnerApplication = {
  id: 1,
  user_id: 1,
  status: 'draft',
  version: 1,
  business_type: 'enterprise',
  business_name: 'CLB The Thao Hoang Gia',
  tax_code: '0109988776',
  contact_name: 'Nguyen Van B',
  contact_phone: '0912345678',
  contact_email: 'b@hoanggia.vn',
  metadata: null,
  draft_payload: null,
  documents: [
    {
      id: 101,
      application_id: 1,
      document_type: 'business_license',
      file_path: 'docs/gpkd.pdf',
      original_name: 'gpkd.pdf',
      mime_type: 'application/pdf',
      file_size_bytes: 204800,
      version: 1,
      created_at: '2026-09-10T00:00:00Z',
    },
  ],
  consents: [],
  review_history: [],
  created_at: '2026-09-10T00:00:00Z',
  updated_at: '2026-09-10T00:00:00Z',
}

describe('BusinessInfoStep', () => {
  it('pre-fills existing draft values and emits save', async () => {
    const wrapper = mount(BusinessInfoStep, {
      props: {
        application: baseMockApp,
        fieldErrors: {},
        saving: false,
      },
    })

    const businessNameInput = wrapper.find<HTMLInputElement>('input#businessName')
    expect(businessNameInput.element.value).toBe('CLB The Thao Hoang Gia')

    const saveBtn = wrapper.find('button[type="button"]')
    await saveBtn.trigger('click')

    expect(wrapper.emitted('save')).toBeTruthy()
    const emittedPayload = wrapper.emitted('save')?.[0]?.[0] as any
    expect(emittedPayload.business_name).toBe('CLB The Thao Hoang Gia')
  })

  it('renders field errors inline', () => {
    const wrapper = mount(BusinessInfoStep, {
      props: {
        application: baseMockApp,
        fieldErrors: {
          tax_code: ['Mã số thuế không hợp lệ.'],
        },
        saving: false,
      },
    })

    expect(wrapper.text()).toContain('Mã số thuế không hợp lệ.')
  })
})

describe('DocumentUploadStep', () => {
  it('computes enterprise requirements and displays uploaded document status', () => {
    const wrapper = mount(DocumentUploadStep, {
      props: {
        application: baseMockApp,
        uploading: false,
        fieldErrors: {},
      },
    })

    expect(wrapper.text()).toContain('Giấy chứng nhận đăng ký kinh doanh')
    expect(wrapper.text()).toContain('Đã tải lên')
    expect(wrapper.text()).toContain('gpkd.pdf')
  })

  it('computes individual requirements without business license', () => {
    const individualApp: PartnerApplication = {
      ...baseMockApp,
      business_type: 'individual',
      documents: [],
    }

    const wrapper = mount(DocumentUploadStep, {
      props: {
        application: individualApp,
        uploading: false,
        fieldErrors: {},
      },
    })

    expect(wrapper.text()).not.toContain('Giấy chứng nhận đăng ký kinh doanh')
    expect(wrapper.text()).toContain('CCCD / Hộ chiếu')
  })
})

describe('ConsentAndReviewStep', () => {
  it('blocks submit until consents are checked', async () => {
    const wrapper = mount(ConsentAndReviewStep, {
      props: {
        application: baseMockApp,
        submitting: false,
        fieldErrors: {},
      },
    })

    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Nộp Hồ sơ'))
    expect(submitBtn).toBeDefined()
    await submitBtn?.trigger('click')

    expect(wrapper.emitted('submit')).toBeFalsy()
    expect(wrapper.text()).toContain('Vui lòng xác nhận toàn bộ cam kết')
  })

  it('emits submit when consents are checked', async () => {
    const wrapper = mount(ConsentAndReviewStep, {
      props: {
        application: baseMockApp,
        submitting: false,
        fieldErrors: {},
      },
    })

    const checkboxes = wrapper.findAll('input[type="checkbox"]')
    expect(checkboxes.length).toBe(2)
    const firstCheckbox = checkboxes[0]
    const secondCheckbox = checkboxes[1]
    if (firstCheckbox && secondCheckbox) {
      await firstCheckbox.setValue(true)
      await secondCheckbox.setValue(true)
    }
    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Nộp Hồ sơ'))
    await submitBtn?.trigger('click')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const payload = wrapper.emitted('submit')?.[0]?.[0] as any
    expect(payload.consent_partner).toBe(true)
    expect(payload.consent_data).toBe(true)
    expect(payload.terms_version).toBe('v1.0')
  })
})

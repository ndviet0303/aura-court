import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import BusinessInfoStep from '../components/BusinessInfoStep.vue'
import DocumentUploadStep from '../components/DocumentUploadStep.vue'
import ConsentAndReviewStep from '../components/ConsentAndReviewStep.vue'
import type { PartnerApplication } from '../types'

const baseMockApp: PartnerApplication = {
  id: 1,
  kyc_code: 'KYC-1',
  status: 'draft',
  version: 1,
  is_current: true,
  business_type: 'company',
  business_name: 'CLB The Thao Hoang Gia',
  legal_name: 'Hoang Gia Sports',
  tax_code: '0109988776',
  tax_status: null,
  representative_name: 'Nguyen Van B',
  id_card_number: '001099123456',
  id_card_number_masked: '001*****3456',
  bank_name: 'VCB',
  bank_code: 'VCB',
  bank_account_number: '123456789',
  bank_account_number_masked: '*****6789',
  bank_account_holder: 'NGUYEN VAN B',
  contact_name: 'Nguyen Van B',
  contact_phone: '0912345678',
  contact_email: 'b@hoanggia.vn',
  address_line: '1 Hoang Gia Street',
  ward: null,
  district: null,
  city: 'Hanoi',
  postal_code: null,
  sports: ['badminton'],
  court_scale: 4,
  lighting_spec: null,
  investigation_status: null,
  rejection_reason: null,
  submitted_at: null,
  reviewed_at: null,
  documents: [
    {
      id: 101,
      document_type: 'business_license',
      file_name: 'gpkd.pdf',
      mime_type: 'application/pdf',
      file_size: 204800,
      version: 1,
      status: 'uploaded',
      read_url: '/signed-document',
      created_at: '2026-09-10T00:00:00Z',
    },
  ],
  consents: [],
  review_histories: [],
  created_at: '2026-09-10T00:00:00Z',
  updated_at: '2026-09-10T00:00:00Z',
}

describe('BusinessInfoStep', () => {
  it('pre-fills server values and saves the backend payload', async () => {
    const save = vi.fn().mockResolvedValue(true)
    const wrapper = mount(BusinessInfoStep, {
      props: {
        application: baseMockApp,
        fieldErrors: {},
        saving: false,
        save,
      },
    })

    const businessNameInput = wrapper.find<HTMLInputElement>('input#business_name')
    expect(businessNameInput.element.value).toBe('CLB The Thao Hoang Gia')

    const saveBtn = wrapper.find('button[type="button"]')
    await saveBtn.trigger('click')

    expect(save).toHaveBeenCalledWith(expect.objectContaining({
      business_name: 'CLB The Thao Hoang Gia',
      business_type: 'company',
      expected_version: 1,
    }))
  })

  it('renders field errors inline', () => {
    const wrapper = mount(BusinessInfoStep, {
      props: {
        application: baseMockApp,
        fieldErrors: {
          tax_code: ['Mã số thuế không hợp lệ.'],
        },
        saving: false,
        save: vi.fn().mockResolvedValue(true),
      },
    })

    expect(wrapper.text()).toContain('Mã số thuế không hợp lệ.')
  })
})

describe('DocumentUploadStep', () => {
  it('computes company requirements and displays uploaded document status', () => {
    const wrapper = mount(DocumentUploadStep, {
      props: {
        application: baseMockApp,
        uploading: false,
        fieldErrors: {},
      },
    })

    expect(wrapper.text()).toContain('Giấy phép kinh doanh')
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

    expect(wrapper.text()).not.toContain('Giấy phép kinh doanh')
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
    expect(checkboxes.length).toBe(5)
    for (const checkbox of checkboxes) await checkbox.setValue(true)
    const submitBtn = wrapper.findAll('button').find((b) => b.text().includes('Nộp Hồ sơ'))
    await submitBtn?.trigger('click')

    expect(wrapper.emitted('submit')).toBeTruthy()
    const payload = wrapper.emitted('submit')?.[0]?.[0] as any
    expect(payload.expected_version).toBe(1)
    expect(payload.consents).toHaveLength(5)
    expect(payload.consents.map((item: any) => item.consent_type)).toEqual([
      'cooperation_terms',
      'platform_fee_policy',
      'settlement_refund_terms',
      'data_processing_agreement',
      'service_responsibility',
    ])
  })
})

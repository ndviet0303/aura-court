import { api } from '@/core/api/client'
import type {
  PlatformPlan,
  PlatformSubscription,
  PaymentAttempt,
  SelectPlanPayload,
  ActivateTrialPayload,
  InitiatePaymentPayload,
  ConfirmPaymentPayload,
} from '../types'

export const platformSubscriptionApi = {
  getPlans: async (): Promise<{ data: PlatformPlan[] }> => {
    return api.get<{ data: PlatformPlan[] }>('/partner/plans')
  },

  getCurrentSubscription: async (
    organizationId: number
  ): Promise<{
    data: PlatformSubscription | null
    trial_eligible: boolean
    can_take_bookings: boolean
    quotas?: {
      venues_count: number
      max_venues: number
      courts_count: number
      max_courts: number
      staff_count: number
      max_staff: number
    }
  }> => {
    return api.get(`/partner/organizations/${organizationId}/subscription`)
  },

  selectPlan: async (
    organizationId: number,
    payload: SelectPlanPayload
  ): Promise<{ data: PlatformPlan; message: string }> => {
    return api.post(`/partner/organizations/${organizationId}/subscription/select`, payload)
  },

  activateTrial: async (
    organizationId: number,
    payload: ActivateTrialPayload
  ): Promise<{ data: PlatformSubscription; message: string }> => {
    return api.post(`/partner/organizations/${organizationId}/subscription/activate-trial`, payload)
  },

  initiatePayment: async (
    organizationId: number,
    payload: InitiatePaymentPayload
  ): Promise<{
    data: PaymentAttempt
    checkout_url: string
    qr_code_url: string
    message: string
  }> => {
    return api.post(`/partner/organizations/${organizationId}/subscription/initiate-payment`, payload)
  },

  confirmPayment: async (
    payload: ConfirmPaymentPayload
  ): Promise<{ data: PlatformSubscription; message: string }> => {
    return api.post('/partner/subscription/confirm-payment', payload)
  },

  cancelRenewal: async (
    organizationId: number
  ): Promise<{ data: PlatformSubscription; message: string }> => {
    return api.post(`/partner/organizations/${organizationId}/subscription/cancel-renewal`, {})
  },

  renew: async (
    organizationId: number
  ): Promise<{ data: PlatformSubscription; message: string }> => {
    return api.post(`/partner/organizations/${organizationId}/subscription/renew`, {})
  },
}

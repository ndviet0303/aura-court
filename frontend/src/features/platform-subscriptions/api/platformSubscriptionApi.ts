import { api } from '@/core/api/client'
import type {
  PlatformPlan,
  PlatformSubscription,
  PaymentAttempt,
  SelectPlanPayload,
  PlanSelectionSnapshot,
  ActivateTrialPayload,
  InitiatePaymentPayload,
  CurrentSubscriptionResponse,
} from '../types'

export const platformSubscriptionApi = {
  getPlans: async (): Promise<{ data: PlatformPlan[] }> => {
    return api.get<{ data: PlatformPlan[] }>('/platform/plans')
  },

  getCurrentSubscription: async (organizationId: number): Promise<CurrentSubscriptionResponse> => {
    return api.get<CurrentSubscriptionResponse>(
      `/partner/organizations/${organizationId}/subscription/current`,
    )
  },

  selectPlan: async (
    organizationId: number,
    payload: SelectPlanPayload,
  ): Promise<{ data: PlanSelectionSnapshot; message: string }> => {
    return api.post<{ data: PlanSelectionSnapshot; message: string }>(
      `/partner/organizations/${organizationId}/subscription/select-plan`,
      payload,
    )
  },

  activateTrial: async (
    organizationId: number,
    payload: ActivateTrialPayload,
  ): Promise<{ data: PlatformSubscription; message: string }> => {
    return api.post<{ data: PlatformSubscription; message: string }>(
      `/partner/organizations/${organizationId}/subscription/activate-trial`,
      payload,
    )
  },

  initiatePayment: async (
    organizationId: number,
    payload: InitiatePaymentPayload,
  ): Promise<{ data: PaymentAttempt; message: string }> => {
    return api.post<{ data: PaymentAttempt; message: string }>(
      `/partner/organizations/${organizationId}/subscription/initiate-payment`,
      payload,
    )
  },

  cancelRenewal: async (
    organizationId: number,
  ): Promise<{ data: PlatformSubscription; message: string }> => {
    return api.post<{ data: PlatformSubscription; message: string }>(
      `/partner/organizations/${organizationId}/subscription/cancel-renewal`,
      {},
    )
  },

  renew: async (
    organizationId: number,
    payload: Pick<InitiatePaymentPayload, 'return_url' | 'cancel_url'>,
  ): Promise<{ data: PaymentAttempt; message: string }> => {
    return api.post<{ data: PaymentAttempt; message: string }>(
      `/partner/organizations/${organizationId}/subscription/renew`,
      payload,
    )
  },
}

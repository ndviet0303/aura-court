import { apiV2 } from '@/core/api/client'
import type {
  MembershipPlan,
  LoyaltyConversionConfig,
  MultiplierRule,
  PlanVersionAudit,
  MembershipKpiStats,
} from '../types/membership'

export const membershipApi = {
  // Membership Plans
  getPlans: () => apiV2.get<MembershipPlan[]>('/admin/memberships/plans'),
  createPlan: (payload: Partial<MembershipPlan>) =>
    apiV2.post<MembershipPlan>('/admin/memberships/plans', payload),
  updatePlan: (id: string | number, payload: Partial<MembershipPlan>) =>
    apiV2.patch<MembershipPlan>(`/admin/memberships/plans/${id}`, payload),
  archivePlan: (id: string | number) =>
    apiV2.post<{ message: string }>(`/admin/memberships/plans/${id}/archive`, {}),

  // Loyalty Points Configuration
  getLoyaltyConfig: () => apiV2.get<LoyaltyConversionConfig>('/admin/memberships/loyalty-config'),
  updateLoyaltyConfig: (payload: Partial<LoyaltyConversionConfig>) =>
    apiV2.patch<LoyaltyConversionConfig>('/admin/memberships/loyalty-config', payload),

  // Multiplier Rules
  getMultiplierRules: () => apiV2.get<MultiplierRule[]>('/admin/memberships/multiplier-rules'),
  createMultiplierRule: (payload: Partial<MultiplierRule>) =>
    apiV2.post<MultiplierRule>('/admin/memberships/multiplier-rules', payload),
  updateMultiplierRule: (id: string, payload: Partial<MultiplierRule>) =>
    apiV2.patch<MultiplierRule>(`/admin/memberships/multiplier-rules/${id}`, payload),
  deleteMultiplierRule: (id: string) =>
    apiV2.delete<{ message: string }>(`/admin/memberships/multiplier-rules/${id}`),

  // Version Audits & History
  getVersionHistory: () => apiV2.get<PlanVersionAudit[]>('/admin/memberships/version-history'),
  getStats: () => apiV2.get<MembershipKpiStats>('/admin/memberships/stats'),
}

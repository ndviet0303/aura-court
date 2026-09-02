export type RiskLevel = 'critical' | 'medium' | 'low'
export type ExceptionStatus = 'open' | 'pending' | 'resolved' | 'investigating'
export type AuditLogType = 'override' | 'flag' | 'block' | 'unlock'

export interface ExceptionUser {
  name: string
  id: string
  avatar?: string
  tier?: string
}

export interface FinancialExceptionMetadata {
  shift?: string
  user?: ExceptionUser
  reason?: string
  court?: string
  gateway?: string
  expected_amount?: number
  actual_amount?: number
  [key: string]: any
}

export interface FinancialException {
  id: string | number
  organization_id?: number
  type: string
  severity: RiskLevel
  status: ExceptionStatus
  amount: number
  currency?: string
  reference?: string
  description: string
  links?: Record<string, string>
  metadata?: FinancialExceptionMetadata
  detected_at?: string
  due_at?: string
  resolved_at?: string
  resolved_by?: number
  resolver?: {
    id: number
    name: string
    email: string
  }
  resolution?: string
  created_at?: string
}

export interface AuditLog {
  id: string | number
  author: string
  role: string
  time: string
  action: string
  details: string
  hash: string
  type: AuditLogType
  created_at?: string
}

export interface ExceptionStats {
  critical_count: number
  pending_count: number
  resolved_count: number
  total_risk_value: number
}

export type ExceptionFilterTab = 'all' | 'critical' | 'refund' | 'deposit' | 'deposit_hold'

export interface ManualOverridePayload {
  actionType: string
  reason: string
  proofFileName?: string
  signatureData?: string
}


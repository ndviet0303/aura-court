/**
 * Generates or resolves avatar URL for users and athletes.
 * If user has a real avatar (Google OAuth, uploaded, etc.), it uses that.
 * Otherwise, generates a clean, branded UI avatar with user's initials.
 */
export function getAvatarUrl(name?: string | null, customUrl?: string | null): string {
  if (
    customUrl &&
    !customUrl.includes('unsplash.com') &&
    !customUrl.includes('/avatars/athlete-')
  ) {
    return customUrl
  }
  const cleanName = name?.trim() || 'Aura'
  return `https://ui-avatars.com/api/?name=${encodeURIComponent(cleanName)}&background=F8E8EC&color=6B2D3F&bold=true&rounded=true`
}

export function getUserInitials(name?: string | null): string {
  if (!name) return 'AC'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return (parts[0]?.substring(0, 2) ?? 'AC').toUpperCase()
  return ((parts[0]?.[0] ?? '') + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase()
}

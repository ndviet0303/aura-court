/** BR-03, mirrors backend App\Rules\AccountPassword: 8–64 chars, upper, lower, digit, no whitespace. */
export const isAccountPassword = (value: string): boolean =>
  /^(?=.*\p{Lu})(?=.*\p{Ll})(?=.*\d)\S{8,64}$/u.test(value)

/** The i18n key of the first rule a new password and its confirmation break, or null. */
export const newPasswordError = (password: string, confirmation: string): string | null =>
  !isAccountPassword(password)
    ? 'auth.passwordTooShort'
    : password !== confirmation
      ? 'auth.passwordsDoNotMatch'
      : null

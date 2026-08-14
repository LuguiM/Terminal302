const digitsOnly = (value) => String(value ?? '').replace(/\D/g, '')

export const hasRepeatedDigits = (value) => {
  const digits = digitsOnly(value)
  return digits.length > 0 && new Set(digits).size === 1
}

export const formatDui = (value) => {
  const digits = digitsOnly(value).slice(0, 9)
  return digits.length > 8 ? `${digits.slice(0, 8)}-${digits.slice(8)}` : digits
}

export const formatNit = (value) => {
  const digits = digitsOnly(value).slice(0, 14)
  const parts = [digits.slice(0, 4), digits.slice(4, 10), digits.slice(10, 13), digits.slice(13)]
  return parts.filter(Boolean).join('-')
}

export const formatPhone = (value) => {
  const digits = digitsOnly(value).slice(0, 8)
  return digits.length > 4 ? `${digits.slice(0, 4)}-${digits.slice(4)}` : digits
}

export const hasValidDuiFormat = (value) => /^\d{8}-\d$/.test(value)

export const hasValidDuiCheckDigit = (value) => {
  if (!hasValidDuiFormat(value) || hasRepeatedDigits(value)) return false

  const digits = digitsOnly(value)
  const sum = [...digits.slice(0, 8)].reduce(
    (total, digit, index) => total + Number(digit) * (9 - index),
    0,
  )

  return (10 - (sum % 10)) % 10 === Number(digits[8])
}

export const hasValidNitFormat = (value) => /^\d{4}-\d{6}-\d{3}-\d$/.test(value)

export const hasValidNitCheckDigit = (value) => {
  if (!hasValidNitFormat(value) || hasRepeatedDigits(value)) return false

  const digits = digitsOnly(value)
  const sequence = Number(digits.slice(10, 13))
  const sum = [...digits.slice(0, 13)].reduce((total, digit, index) => {
    const weight = sequence <= 100
      ? 14 - index
      : (index + 3 <= 9 ? index + 3 : index - 5)
    return total + Number(digit) * weight
  }, 0)
  const result = 11 - (sum % 11)
  const checkDigit = result >= 10 ? 0 : result

  return checkDigit === Number(digits[13])
}

export const hasValidPhoneFormat = (value) => /^[267]\d{3}-\d{4}$/.test(value)

export const isValidSalvadoranPhone = (value) => (
  hasValidPhoneFormat(value) && !hasRepeatedDigits(value)
)

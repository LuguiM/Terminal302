function isValidDate(date) {
  return date instanceof Date && !Number.isNaN(date.getTime())
}

export function toApiDate(value) {
  if (!value) {
    return undefined
  }

  if (typeof value === 'string') {
    const apiDate = value.match(/^(\d{4})-(\d{2})-(\d{2})$/)

    if (apiDate) {
      return value
    }

    const displayDate = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)

    if (displayDate) {
      return `${displayDate[3]}-${displayDate[2]}-${displayDate[1]}`
    }
  }

  const date = value instanceof Date ? value : new Date(value)

  if (!isValidDate(date)) {
    return undefined
  }

  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export function formatDisplayDate(value) {
  const apiDate = toApiDate(value)

  if (!apiDate) {
    return ''
  }

  const [year, month, day] = apiDate.split('-')

  return `${day}/${month}/${year}`
}

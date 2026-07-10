export const getRoleName = (user) => {
  return user?.role?.nombre?.toString().trim().toLowerCase() ?? ''
}

export const isValidatorUser = (user) => {
  return getRoleName(user) === 'validador'
}

export const getAuthenticatedHomeRoute = ({
  user,
  mustChangePassword = false,
  requiresOperatorRegistration = false,
} = {}) => {
  if (mustChangePassword) {
    return { name: 'change-password' }
  }

  if (requiresOperatorRegistration) {
    return { name: 'operator-registration' }
  }

  if (isValidatorUser(user)) {
    return { name: 'validator-ticket-welcome' }
  }

  return { name: 'inicio' }
}

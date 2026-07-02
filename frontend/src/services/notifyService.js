import { toast } from 'vue3-toastify'

const defaultOptions = {
  autoClose: 4500,
  clearOnUrlChange: true,
  closeOnClick: true,
  hideProgressBar: false,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  position: toast.POSITION.BOTTOM_RIGHT,
  theme: 'colored',
}

const show = (message, options = {}) => {
  if (!message) {
    return null
  }

  return toast(message, {
    ...defaultOptions,
    ...options,
  })
}

export const notify = {
  show,
  success(message, options = {}) {
    return toast.success(message, {
      ...defaultOptions,
      ...options,
    })
  },
  error(message, options = {}) {
    return toast.error(message, {
      ...defaultOptions,
      ...options,
    })
  },
  warning(message, options = {}) {
    return toast.warn(message, {
      ...defaultOptions,
      ...options,
    })
  },
  info(message, options = {}) {
    return toast.info(message, {
      ...defaultOptions,
      ...options,
    })
  },
  clear() {
    toast.clearAll()
  },
}

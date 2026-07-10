import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

export const getApiErrorMessage = (error) => {
  const data = error.response?.data

  if (data?.message) {
    return data.message
  }

  if (error.message) {
    return error.message
  }

  return 'No se pudo completar la consulta.'
}

export default api

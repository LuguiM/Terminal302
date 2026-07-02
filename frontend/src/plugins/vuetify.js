import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

const terminal302Theme = {
  dark: false,
  colors: {
    primary: '#1C1E4D',
    secondary: '#F5A524',
    background: '#F8F9FA',
    surface: '#FFFFFF',
    error: '#D32F2F',
    success: '#2E7D32',
    warning: '#F9A825',
    info: '#0288D1',
  },
}

export default createVuetify({
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: {
      mdi,
    },
  },
  theme: {
    defaultTheme: 'terminal302',
    themes: {
      terminal302: terminal302Theme,
    },
  },
})

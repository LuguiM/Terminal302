import 'vuetify/styles'

import { createVuetify } from 'vuetify'
import { aliases, mdi } from 'vuetify/iconsets/mdi'

const terminal302Theme = {
  dark: false,
  colors: {
    primary: '#001233',
    secondary: '#33415C',
    background: '#F8F9FA',
    surface: '#FFFFFF',
    error: '#B9292C',
    success: '#19AD27',
    warning: '#F9A825',
    info: '#0288D1',
    redSystem: '#B9292C',
    blueLigth: '#023E7D',
    greyLigth: '#ededf1',
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

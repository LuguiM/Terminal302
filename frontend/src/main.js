import { createApp } from 'vue'
import { createPinia } from 'pinia'

import './style.css'
import 'vue3-toastify/dist/index.css'
import App from './App.vue'
import vuetify from './plugins/vuetify'
import router from './router'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)

app.mount('#app')

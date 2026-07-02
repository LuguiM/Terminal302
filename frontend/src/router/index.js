import { createRouter, createWebHistory } from 'vue-router'

import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { useAuthStore } from '@/stores/authStore'
import { useMenuStore } from '@/stores/menuStore'

const routes = [
  {
    path: '/',
    component: DashboardLayout,
    meta: { requiresAuth: true },
    children: [
      {
        path: '',
        redirect: '/inicio',
      },
      {
        path: 'inicio',
        name: 'inicio',
        component: () => import('@/views/home/HomeView.vue'),
        meta: {
          requiresAuth: true,
          skipMenuPermission: true,
        },
      },
      {
        path: 'avisos',
        name: 'avisos',
        component: () => import('@/views/notices/NoticesView.vue'),
        meta: {
          requiresAuth: true,
          permissionPath: '/avisos',
        },
      },
    ],
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/LoginView.vue'),
    meta: {
      requiresAuth: false,
    },
  },
  {
    path: '/403',
    name: 'forbidden',
    component: () => import('@/views/errors/ForbiddenView.vue'),
    meta: {
      requiresAuth: false,
    },
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/inicio',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore()
  const menuStore = useMenuStore()
  const accessToken = localStorage.getItem('access_token')

  authStore.loadSession()

  if (to.meta.requiresAuth && !accessToken) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.name === 'login' && accessToken) {
    return { name: 'inicio' }
  }

  if (!to.meta.requiresAuth || to.meta.skipMenuPermission) {
    return true
  }

  try {
    if (!menuStore.loaded) {
      await menuStore.fetchMenu()
    }
  } catch (error) {
    if (error?.response?.status === 401) {
      await authStore.logout()

      return {
        name: 'login',
        query: { redirect: to.fullPath },
      }
    }

    return { name: 'forbidden' }
  }

  const permissionPath = to.meta.permissionPath ?? to.path

  if (!menuStore.allowedRoutes.includes(permissionPath)) {
    return { name: 'forbidden' }
  }

  return true
})

export default router

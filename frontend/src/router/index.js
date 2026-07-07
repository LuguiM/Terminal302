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
      {
        path: 'admin',
        children: [
          {
            path: 'gestiones',
            children: [
              {
                path: 'usuarios',
                name: 'admin-users',
                component: () => import('@/views/admin/users/UserManagementView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/gestiones/usuarios',
                },
              },
              {
                path: 'operadores',
                name: 'admin-operators',
                component: () => import('@/views/admin/operators/AdminOperatorManagementView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/gestiones/operadores',
                },
              },
              {
                path: 'operadores/:id',
                name: 'admin-operator-detail',
                component: () => import('@/views/admin/operators/AdminOperatorDetailView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/gestiones/operadores',
                },
              },
              {
                path: 'horarios',
                name: 'admin-schedules',
                component: () => import('@/views/admin/schedules/AdminScheduleRoutesView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/gestiones/horarios',
                },
              },
              {
                path: 'horarios/:rutaId',
                name: 'admin-schedules-route',
                component: () => import('@/views/admin/schedules/AdminScheduleRouteManagementView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/gestiones/horarios',
                },
              },
            ],
          },
          {
            path: 'catalogos',
            children: [
              {
                path: 'rutas',
                name: 'admin-routes',
                component: () => import('@/views/admin/catalogos/RouteCatalogView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/catalogos/rutas',
                },
              },
            ],
          },
          {
            path: 'configuracion',
            children: [
              {
                path: 'plantilla',
                name: 'admin-ticket-templates',
                component: () => import('@/views/admin/ticket-templates/AdminTicketTemplatesView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/configuracion/plantilla',
                },
              },
              {
                path: 'plantilla/:id/editar',
                name: 'admin-ticket-template-editor',
                component: () => import('@/views/admin/ticket-templates/AdminTicketTemplateEditorView.vue'),
                meta: {
                  requiresAuth: true,
                  permissionPath: '/admin/configuracion/plantilla',
                },
              },
            ],
          },
        ],
      },
      {
        path: 'operador',
        children: [
          {
            path: 'rutas',
            name: 'operator-routes',
            component: () => import('@/views/operators/routes/OperatorRoutesView.vue'),
            meta: {
              requiresAuth: true,
              permissionPath: '/operador/rutas',
            },
          },
          {
            path: 'unidades',
            name: 'operator-buses',
            component: () => import('@/views/operators/buses/OperatorBusesView.vue'),
            meta: {
              requiresAuth: true,
              permissionPath: '/operador/unidades',
            },
          },
          {
            path: 'empleados',
            name: 'operator-employees',
            component: () => import('@/views/operators/employees/OperatorEmployeesView.vue'),
            meta: {
              requiresAuth: true,
              permissionPath: '/operador/empleados',
            },
          },
          {
            path: 'horarios',
            name: 'operator-schedules',
            component: () => import('@/views/operators/schedules/OperatorScheduleRoutesView.vue'),
            meta: {
              requiresAuth: true,
              permissionPath: '/operador/horarios',
            },
          },
          {
            path: 'horarios/:rutaId',
            name: 'operator-schedules-route',
            component: () => import('@/views/operators/schedules/OperatorScheduleRouteView.vue'),
            meta: {
              requiresAuth: true,
              permissionPath: '/operador/horarios',
            },
          },
        ],
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
    path: '/cambiar-contrasena',
    name: 'change-password',
    component: () => import('@/views/auth/ChangePasswordView.vue'),
    meta: {
      requiresAuth: true,
      skipMenuPermission: true,
      isPasswordChangeRoute: true,
    },
  },
  {
    path: '/registro-operador',
    name: 'operator-registration',
    component: () => import('@/views/operators/register/OperatorRegistrationView.vue'),
    meta: {
      requiresAuth: true,
      skipMenuPermission: true,
      isOperatorRegistrationRoute: true,
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
    if (authStore.mustChangePassword) {
      return { name: 'change-password' }
    }

    return authStore.requiresOperatorRegistration
      ? { name: 'operator-registration' }
      : { name: 'inicio' }
  }

  if (
    accessToken
    && authStore.mustChangePassword
    && !to.meta.isPasswordChangeRoute
    && to.name !== 'login'
  ) {
    return { name: 'change-password' }
  }

  if (
    accessToken
    && !authStore.mustChangePassword
    && authStore.requiresOperatorRegistration
    && !to.meta.isOperatorRegistrationRoute
    && to.name !== 'login'
  ) {
    return { name: 'operator-registration' }
  }

  if (
    accessToken
    && !authStore.requiresOperatorRegistration
    && to.meta.isOperatorRegistrationRoute
  ) {
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

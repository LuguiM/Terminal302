import { createRouter, createWebHistory } from 'vue-router'

import PublicLayout from '@/layouts/PublicLayout.vue'

const routes = [
  {
    path: '/',
    component: PublicLayout,
    children: [
      {
        path: '',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
      },
      {
        path: 'consulta-ticket',
        name: 'ticket-search',
        component: () => import('@/views/TicketSearchView.vue'),
      },
      {
        path: 'tickets/:codigo',
        name: 'ticket-detail',
        component: () => import('@/views/TicketDetailView.vue'),
        props: true,
      },
      {
        path: 'rutas',
        name: 'routes',
        component: () => import('@/views/RoutesView.vue'),
      },
      {
        path: 'rutas/:id/horarios',
        name: 'route-schedules',
        component: () => import('@/views/RouteSchedulesView.vue'),
        props: true,
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/',
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router

import { createRouter, createWebHistory } from 'vue-router';

const routes = [
  { path: '/', name: 'Home', component: () => import('@/pages/HomePage.vue'), meta: { title: 'Início' } },
  { path: '/news', name: 'News', component: () => import('@/pages/NewsListPage.vue'), meta: { title: 'Notícias' } },
  { path: '/news/:id', name: 'NewsDetail', component: () => import('@/pages/NewsDetailPage.vue'), meta: { title: 'Notícia' } },
  { path: '/sources', name: 'Sources', component: () => import('@/pages/SourcesPage.vue'), meta: { title: 'Fontes' } },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.afterEach((to) => {
  const title = to.meta.title ? `${to.meta.title} | Futebola` : 'Futebola';
  document.title = title;
});

export default router;

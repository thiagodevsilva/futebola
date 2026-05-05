import axios from 'axios';

const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
});

export default api;
export const getNews = (params) => api.get('/news', { params });
export const getNewsItem = (id) => api.get(`/news/${id}`);
export const getStandings = (params) => api.get('/standings', { params });
export const getFixtures = (params) => api.get('/fixtures', { params });
export const getSources = (params) => api.get('/sources', { params });
export const getSourceTopics = () => api.get('/sources/topics');
export const getSerieATeams = (params) => api.get('/teams/serie-a', { params });

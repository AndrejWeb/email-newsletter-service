import '../css/app.css';
import api from './utils/api.js';
import { registerRoute, initRouter, navigate } from './utils/router.js';

import authPage from './pages/auth.js';
import dashboardPage from './pages/dashboard.js';
import subscribersPage from './pages/subscribers.js';
import subscriberDetailPage from './pages/subscriber-detail.js';
import listsPage from './pages/lists.js';
import tagsPage from './pages/tags.js';
import templatesPage from './pages/templates.js';
import templateEditorPage from './pages/template-editor.js';
import campaignsPage from './pages/campaigns.js';
import campaignDetailPage from './pages/campaign-detail.js';
import settingsPage from './pages/settings.js';

function requireAuth(handler) {
  return async (container, params) => {
    if (!api.isAuthenticated()) {
      navigate('/login');
      return;
    }

    if (!api.getUser()) {
      try {
        const resp = await api.get('/auth/me');
        api.setUser(resp?.data || resp);
      } catch {
        api.logout();
        navigate('/login');
        return;
      }
    }

    return handler(container, params);
  };
}

// Public routes
registerRoute('/login', authPage);
registerRoute('/register', authPage);

// Protected routes
registerRoute('/', requireAuth(dashboardPage));
registerRoute('/dashboard', requireAuth(dashboardPage));
registerRoute('/subscribers', requireAuth(subscribersPage));
registerRoute('/subscribers/:id', requireAuth(subscriberDetailPage));
registerRoute('/lists', requireAuth(listsPage));
registerRoute('/tags', requireAuth(tagsPage));
registerRoute('/templates', requireAuth(templatesPage));
registerRoute('/templates/:id/edit', requireAuth(templateEditorPage));
registerRoute('/campaigns', requireAuth(campaignsPage));
registerRoute('/campaigns/:id', requireAuth(campaignDetailPage));
registerRoute('/settings', requireAuth(settingsPage));

// Boot
initRouter();

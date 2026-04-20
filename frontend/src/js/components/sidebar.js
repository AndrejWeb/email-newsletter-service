import $ from 'jquery';
import api from '../utils/api.js';
import { navigate, getCurrentRoute } from '../utils/router.js';

export function renderSidebar() {
  const user = api.getUser();
  if (!user) return '';

  const initials = (user.name || user.email || 'U').charAt(0).toUpperCase();
  const role = user.role === 'admin' ? 'Administrator' : 'Editor';

  return `
    <aside class="sidebar" id="sidebar">
      <div class="sidebar-header">
        <div class="logo">✉️</div>
        <h1>Newsletter<small>Email Campaign Manager</small></h1>
      </div>

      <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="#/dashboard" data-route="/dashboard">
          <span class="nav-icon">📊</span> Dashboard
        </a>

        <div class="nav-section">Audience</div>
        <a href="#/subscribers" data-route="/subscribers">
          <span class="nav-icon">👥</span> Subscribers
        </a>
        <a href="#/lists" data-route="/lists">
          <span class="nav-icon">📋</span> Lists
        </a>
        <a href="#/tags" data-route="/tags">
          <span class="nav-icon">🏷️</span> Tags
        </a>

        <div class="nav-section">Content</div>
        <a href="#/templates" data-route="/templates">
          <span class="nav-icon">🎨</span> Templates
        </a>

        <div class="nav-section">Campaigns</div>
        <a href="#/campaigns" data-route="/campaigns">
          <span class="nav-icon">📧</span> Campaigns
        </a>

        <div class="nav-section">Account</div>
        <a href="#/settings" data-route="/settings">
          <span class="nav-icon">⚙️</span> Settings
        </a>
      </nav>

      <div class="sidebar-user">
        <div class="avatar">${initials}</div>
        <div class="user-info">
          <div class="name">${user.name || user.email}</div>
          <div class="role">${role}</div>
        </div>
        <button class="logout-btn" id="logout-btn" title="Logout">🚪</button>
      </div>
    </aside>
  `;
}

export function initSidebar() {
  $(document).on('click', '#logout-btn', () => {
    api.logout();
    navigate('/login');
  });

  updateActiveNav();
  $(window).on('hashchange.sidebar', updateActiveNav);
}

function updateActiveNav() {
  const route = getCurrentRoute();
  $('.sidebar-nav a').removeClass('active');
  $('.sidebar-nav a').each(function () {
    const href = $(this).data('route');
    if (href && route.startsWith(href)) {
      $(this).addClass('active');
    }
  });
}

export function destroySidebar() {
  $(window).off('hashchange.sidebar');
}

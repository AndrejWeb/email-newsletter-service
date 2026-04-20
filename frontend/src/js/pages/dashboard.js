import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import { formatNumber, formatPercent, formatDate, escapeHtml, renderLoading } from '../utils/helpers.js';

export default async function dashboardPage(container) {
  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div class="page-header">
          <div>
            <h1>📊 Dashboard</h1>
            <div class="subtitle">Overview of your email marketing performance</div>
          </div>
        </div>
        <div id="dashboard-content">${renderLoading()}</div>
      </main>
    </div>
  `);
  initSidebar();

  try {
    const resp = await api.get('/dashboard');
    const stats = resp?.data || resp;
    renderDashboard(stats);
  } catch (err) {
    $('#dashboard-content').html(`<div class="empty-state"><div class="icon">⚠️</div><h3>Error</h3><p>${escapeHtml(err.message)}</p></div>`);
  }
}

function renderDashboard(stats) {
  const openRate = stats.avgOpenRate || 0;
  const clickRate = stats.avgClickRate || 0;

  let html = `
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon primary">👥</div>
        <div class="stat-info">
          <h4>Total Subscribers</h4>
          <div class="value">${formatNumber(stats.totalSubscribers)}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon success">✅</div>
        <div class="stat-info">
          <h4>Active Subscribers</h4>
          <div class="value">${formatNumber(stats.activeSubscribers)}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon info">📧</div>
        <div class="stat-info">
          <h4>Campaigns Sent</h4>
          <div class="value">${formatNumber(stats.sentCampaigns)}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon warning">📬</div>
        <div class="stat-info">
          <h4>Avg Open Rate</h4>
          <div class="value">${formatPercent(openRate)}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon secondary">🖱️</div>
        <div class="stat-info">
          <h4>Avg Click Rate</h4>
          <div class="value">${formatPercent(clickRate)}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon danger">📋</div>
        <div class="stat-info">
          <h4>Total Campaigns</h4>
          <div class="value">${formatNumber(stats.totalCampaigns)}</div>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3>📈 Subscriber Growth (30 days)</h3></div>
        <div class="card-body">
          ${renderGrowthChart(stats.subscriberGrowth || [])}
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>📧 Recent Campaigns</h3></div>
        <div class="card-body" style="padding:0;">
          ${renderRecentCampaigns(stats.recentCampaigns || [])}
        </div>
      </div>
    </div>
  `;

  $('#dashboard-content').html(html);
}

function renderGrowthChart(growth) {
  if (!growth.length) {
    return '<div class="empty-state"><p>No growth data yet</p></div>';
  }

  const maxVal = Math.max(...growth.map(g => g.count || 0), 1);

  let bars = '';
  growth.forEach(g => {
    const height = Math.max(4, ((g.count || 0) / maxVal) * 180);
    bars += `
      <div class="chart-bar" style="height:${height}px;">
        <div class="bar-tooltip">${g.date}: ${g.count}</div>
      </div>
    `;
  });

  return `<div class="chart-container">${bars}</div>`;
}

function renderRecentCampaigns(campaigns) {
  if (!campaigns.length) {
    return '<div class="empty-state" style="padding:20px;"><p>No campaigns yet</p></div>';
  }

  let html = '<table><thead><tr><th>Campaign</th><th>Sent</th><th>Opens</th><th>Clicks</th></tr></thead><tbody>';
  campaigns.forEach(c => {
    const openRate = c.totalRecipients > 0 ? ((c.openCount / c.totalRecipients) * 100) : 0;
    const clickRate = c.totalRecipients > 0 ? ((c.clickCount / c.totalRecipients) * 100) : 0;
    html += `
      <tr style="cursor:pointer;" onclick="window.location.hash='#/campaigns/${c.id}'">
        <td><strong>${escapeHtml(c.name)}</strong></td>
        <td>${formatDate(c.sentAt)}</td>
        <td>${formatPercent(openRate)}</td>
        <td>${formatPercent(clickRate)}</td>
      </tr>
    `;
  });
  html += '</tbody></table>';
  return html;
}

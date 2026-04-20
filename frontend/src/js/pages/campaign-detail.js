import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import {
  showToast, showConfirm, escapeHtml, formatDate, formatDateTime, formatNumber,
  formatPercent, campaignStatusBadge, renderPagination, renderLoading
} from '../utils/helpers.js';
import { navigate } from '../utils/router.js';

export default async function campaignDetailPage(container, params) {
  const id = params.id;

  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div id="campaign-detail">${renderLoading()}</div>
      </main>
    </div>
  `);
  initSidebar();

  try {
    const resp = await api.get(`/campaigns/${id}`);
    const campaign = resp?.data || resp;
    renderCampaignDetail(campaign);
  } catch (err) {
    $('#campaign-detail').html(`<div class="empty-state"><div class="icon">⚠️</div><h3>Error</h3><p>${escapeHtml(err.message)}</p></div>`);
  }
}

function renderCampaignDetail(campaign) {
  const c = campaign;
  const stats = c.stats || {};
  const totalRecipients = stats.totalRecipients || c.totalRecipients || 0;
  const openCount = stats.opened || c.openCount || 0;
  const clickCount = stats.clicked || c.clickCount || 0;
  const bounceCount = stats.bounced || c.bounceCount || 0;
  const unsubCount = stats.unsubscribed || c.unsubscribeCount || 0;
  const sentCount = stats.sent || c.sentCount || 0;
  const openRate = totalRecipients > 0 ? (openCount / totalRecipients) * 100 : 0;
  const clickRate = totalRecipients > 0 ? (clickCount / totalRecipients) * 100 : 0;
  const bounceRate = totalRecipients > 0 ? (bounceCount / totalRecipients) * 100 : 0;
  const unsubRate = totalRecipients > 0 ? (unsubCount / totalRecipients) * 100 : 0;

  let html = `
    <div class="page-header">
      <div>
        <h1>📧 ${escapeHtml(c.name)}</h1>
        <div class="subtitle">${escapeHtml(c.subject)} — ${campaignStatusBadge(c.status)}</div>
      </div>
      <div class="actions">
        <button class="btn btn-secondary" onclick="window.location.hash='#/campaigns'">← Back</button>
        ${c.status === 'draft' ? `
          <button class="btn btn-warning" id="schedule-btn">📅 Schedule</button>
          <button class="btn btn-success" id="send-btn">🚀 Send Now</button>
        ` : ''}
        ${c.status === 'scheduled' ? `
          <button class="btn btn-warning" id="cancel-btn">❌ Cancel</button>
        ` : ''}
      </div>
    </div>

    <div class="campaign-stats">
      <div class="campaign-stat">
        <div class="stat-value" style="color:var(--primary);">${formatNumber(totalRecipients)}</div>
        <div class="stat-label">Recipients</div>
      </div>
      <div class="campaign-stat">
        <div class="stat-value" style="color:var(--info);">${formatNumber(sentCount)}</div>
        <div class="stat-label">Sent</div>
      </div>
      <div class="campaign-stat">
        <div class="stat-value" style="color:var(--success);">${formatNumber(openCount)}</div>
        <div class="stat-label">Opens</div>
        <div class="stat-pct">${formatPercent(openRate)}</div>
      </div>
      <div class="campaign-stat">
        <div class="stat-value" style="color:var(--secondary);">${formatNumber(clickCount)}</div>
        <div class="stat-label">Clicks</div>
        <div class="stat-pct">${formatPercent(clickRate)}</div>
      </div>
      <div class="campaign-stat">
        <div class="stat-value" style="color:var(--danger);">${formatNumber(bounceCount)}</div>
        <div class="stat-label">Bounces</div>
        <div class="stat-pct">${formatPercent(bounceRate)}</div>
      </div>
      <div class="campaign-stat">
        <div class="stat-value" style="color:var(--warning);">${formatNumber(unsubCount)}</div>
        <div class="stat-label">Unsubscribes</div>
        <div class="stat-pct">${formatPercent(unsubRate)}</div>
      </div>
    </div>

    <div class="grid-2 mb-3">
      <div class="card">
        <div class="card-header"><h3>ℹ️ Campaign Details</h3></div>
        <div class="card-body">
          <table>
            <tr><td style="width:130px;font-weight:600;">From</td><td>${escapeHtml(c.fromName)} &lt;${escapeHtml(c.fromEmail)}&gt;</td></tr>
            <tr><td style="font-weight:600;">Reply-To</td><td>${escapeHtml(c.replyTo || '—')}</td></tr>
            <tr><td style="font-weight:600;">List</td><td>${escapeHtml(c.subscriberList?.name || c.listName || '—')}</td></tr>
            <tr><td style="font-weight:600;">Template</td><td>${escapeHtml(c.template?.name || c.templateName || '—')}</td></tr>
            <tr><td style="font-weight:600;">Created</td><td>${formatDateTime(c.createdAt)}</td></tr>
            ${c.scheduledAt ? `<tr><td style="font-weight:600;">Scheduled</td><td>${formatDateTime(c.scheduledAt)}</td></tr>` : ''}
            ${c.sentAt ? `<tr><td style="font-weight:600;">Sent At</td><td>${formatDateTime(c.sentAt)}</td></tr>` : ''}
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h3>📊 Performance</h3></div>
        <div class="card-body">
          ${renderProgressBars(c)}
        </div>
      </div>
    </div>

    ${c.status === 'sent' ? `
      <div class="card">
        <div class="card-header">
          <h3>👥 Recipients</h3>
        </div>
        <div id="recipients-area">${renderLoading()}</div>
      </div>
    ` : ''}
  `;

  $('#campaign-detail').html(html);

  if (c.status === 'sent') {
    loadRecipients(c.id, 1);
  }

  // Action buttons
  $('#send-btn').on('click', () => {
    showConfirm('Send Campaign', `Send "${c.name}" immediately?`, async () => {
      try {
        await api.post(`/campaigns/${c.id}/send`);
        showToast('Campaign sent!');
        navigate(`/campaigns/${c.id}`);
        setTimeout(() => location.reload(), 500);
      } catch (err) { showToast(err.message, 'error'); }
    }, '🚀 Send');
  });

  $('#schedule-btn').on('click', () => {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(9, 0, 0, 0);
    const val = tomorrow.toISOString().slice(0, 16);

    const $modal = $(`
      <div class="modal-overlay">
        <div class="modal" style="max-width:400px;">
          <div class="modal-header"><h3>📅 Schedule Campaign</h3><button class="modal-close">&times;</button></div>
          <div class="modal-body">
            <div class="form-group">
              <label>Send At</label>
              <input type="datetime-local" class="form-control" id="schedule-at" value="${val}" />
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-secondary cancel-btn">Cancel</button>
            <button class="btn btn-primary" id="confirm-schedule">Schedule</button>
          </div>
        </div>
      </div>
    `);

    $('body').append($modal);
    $modal.find('.modal-close, .cancel-btn').on('click', () => $modal.remove());
    $modal.find('#confirm-schedule').on('click', async () => {
      const scheduledAt = $modal.find('#schedule-at').val();
      if (!scheduledAt) { showToast('Select a date', 'warning'); return; }
      try {
        await api.post(`/campaigns/${c.id}/schedule`, { scheduledAt: new Date(scheduledAt).toISOString() });
        showToast('Campaign scheduled');
        $modal.remove();
        location.reload();
      } catch (err) { showToast(err.message, 'error'); }
    });
  });

  $('#cancel-btn').on('click', () => {
    showConfirm('Cancel Campaign', `Cancel scheduled campaign?`, async () => {
      try {
        await api.post(`/campaigns/${c.id}/cancel`);
        showToast('Campaign cancelled');
        location.reload();
      } catch (err) { showToast(err.message, 'error'); }
    }, 'Cancel', true);
  });
}

function renderProgressBars(c) {
  const stats = c.stats || {};
  const total = stats.totalRecipients || c.totalRecipients || 1;
  const opens = stats.opened || c.openCount || 0;
  const clicks = stats.clicked || c.clickCount || 0;
  const bounces = stats.bounced || c.bounceCount || 0;
  const unsubs = stats.unsubscribed || c.unsubscribeCount || 0;
  return `
    <div class="mb-2">
      <div class="d-flex align-center" style="justify-content:space-between;">
        <span class="text-sm">Open Rate</span>
        <span class="text-sm fw-600">${formatPercent((opens / total) * 100)}</span>
      </div>
      <div class="progress-bar"><div class="progress-fill success" style="width:${(opens / total) * 100}%"></div></div>
    </div>
    <div class="mb-2">
      <div class="d-flex align-center" style="justify-content:space-between;">
        <span class="text-sm">Click Rate</span>
        <span class="text-sm fw-600">${formatPercent((clicks / total) * 100)}</span>
      </div>
      <div class="progress-bar"><div class="progress-fill primary" style="width:${(clicks / total) * 100}%"></div></div>
    </div>
    <div class="mb-2">
      <div class="d-flex align-center" style="justify-content:space-between;">
        <span class="text-sm">Bounce Rate</span>
        <span class="text-sm fw-600">${formatPercent((bounces / total) * 100)}</span>
      </div>
      <div class="progress-bar"><div class="progress-fill danger" style="width:${(bounces / total) * 100}%"></div></div>
    </div>
    <div>
      <div class="d-flex align-center" style="justify-content:space-between;">
        <span class="text-sm">Unsubscribe Rate</span>
        <span class="text-sm fw-600">${formatPercent((unsubs / total) * 100)}</span>
      </div>
      <div class="progress-bar"><div class="progress-fill warning" style="width:${(unsubs / total) * 100}%"></div></div>
    </div>
  `;
}

async function loadRecipients(campaignId, page) {
  try {
    const resp = await api.get(`/campaigns/${campaignId}/recipients?page=${page}&limit=20`);
    const recipients = resp?.data || [];
    const meta = resp?.meta || {};

    if (!recipients.length) {
      $('#recipients-area').html('<div class="empty-state" style="padding:20px;"><p>No recipients</p></div>');
      return;
    }

    let html = '<div class="table-container"><table><thead><tr>';
    html += '<th>Subscriber</th><th>Status</th><th>Sent</th><th>Opened</th><th>Clicked</th>';
    html += '</tr></thead><tbody>';

    recipients.forEach(r => {
      const statusClass = {
        pending: 'badge-gray', sent: 'badge-info', delivered: 'badge-info',
        opened: 'badge-success', clicked: 'badge-primary',
        bounced: 'badge-danger', unsubscribed: 'badge-warning'
      }[r.status] || 'badge-gray';

      html += `
        <tr>
          <td>${escapeHtml(r.subscriber?.email || r.email || '—')}</td>
          <td><span class="badge ${statusClass}">${r.status}</span></td>
          <td class="text-sm">${formatDateTime(r.sentAt)}</td>
          <td class="text-sm">${r.openedAt ? formatDateTime(r.openedAt) : '—'}</td>
          <td class="text-sm">${r.clickedAt ? formatDateTime(r.clickedAt) : '—'}</td>
        </tr>
      `;
    });

    html += '</tbody></table></div>';
    const total = meta.total || recipients.length;
    html += renderPagination(page, total, 20, (p) => loadRecipients(campaignId, p));

    $('#recipients-area').html(html);
  } catch (err) {
    $('#recipients-area').html(`<div class="empty-state"><p>Error: ${escapeHtml(err.message)}</p></div>`);
  }
}

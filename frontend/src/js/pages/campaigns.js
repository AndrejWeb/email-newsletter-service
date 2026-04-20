import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import {
  showToast, showModal, showConfirm, escapeHtml, formatDate, formatNumber,
  formatPercent, campaignStatusBadge, renderPagination, renderLoading, debounce
} from '../utils/helpers.js';
import { navigate } from '../utils/router.js';

let currentPage = 1;
let currentStatus = '';
let currentSearch = '';

export default async function campaignsPage(container) {
  currentPage = 1;
  currentStatus = '';
  currentSearch = '';

  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div class="page-header">
          <div>
            <h1>📧 Campaigns</h1>
            <div class="subtitle">Create and manage email campaigns</div>
          </div>
          <div class="actions">
            <button class="btn btn-primary" id="create-campaign-btn">➕ New Campaign</button>
          </div>
        </div>

        <div class="filter-bar">
          <div class="search-input">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-input" placeholder="Search campaigns..." />
          </div>
          <select id="filter-status">
            <option value="">All Statuses</option>
            <option value="draft">Draft</option>
            <option value="scheduled">Scheduled</option>
            <option value="sending">Sending</option>
            <option value="sent">Sent</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="card">
          <div id="campaigns-table">${renderLoading()}</div>
        </div>
        <div id="campaigns-pagination"></div>
      </main>
    </div>
  `);
  initSidebar();

  loadCampaigns();

  const debouncedSearch = debounce(() => {
    currentSearch = $('#search-input').val();
    currentPage = 1;
    loadCampaigns();
  });

  $('#search-input').on('input', debouncedSearch);
  $('#filter-status').on('change', function () { currentStatus = $(this).val(); currentPage = 1; loadCampaigns(); });
  $('#create-campaign-btn').on('click', showCreateModal);
}

async function loadCampaigns() {
  const params = new URLSearchParams({ page: currentPage, limit: 15 });
  if (currentStatus) params.set('status', currentStatus);
  if (currentSearch) params.set('search', currentSearch);

  try {
    const resp = await api.get(`/campaigns?${params}`);
    const campaigns = resp?.data || [];
    const meta = resp?.meta || {};

    if (!campaigns.length) {
      $('#campaigns-table').html('<div class="empty-state"><div class="icon">📧</div><h3>No campaigns</h3><p>Create your first email campaign</p></div>');
      $('#campaigns-pagination').html('');
      return;
    }

    let html = '<div class="table-container"><table><thead><tr>';
    html += '<th>Campaign</th><th>Status</th><th>List</th><th>Recipients</th><th>Open Rate</th><th>Click Rate</th><th>Sent</th><th>Actions</th>';
    html += '</tr></thead><tbody>';

    campaigns.forEach(c => {
      const openRate = c.totalRecipients > 0 ? (c.openCount / c.totalRecipients) * 100 : 0;
      const clickRate = c.totalRecipients > 0 ? (c.clickCount / c.totalRecipients) * 100 : 0;

      html += `
        <tr>
          <td><a href="#/campaigns/${c.id}"><strong>${escapeHtml(c.name)}</strong></a><br><span class="text-xs text-muted">${escapeHtml(c.subject || '')}</span></td>
          <td>${campaignStatusBadge(c.status)}</td>
          <td>${escapeHtml(c.subscriberList?.name || c.listName || '—')}</td>
          <td>${formatNumber(c.totalRecipients)}</td>
          <td>${c.status === 'sent' ? formatPercent(openRate) : '—'}</td>
          <td>${c.status === 'sent' ? formatPercent(clickRate) : '—'}</td>
          <td class="text-sm text-muted">${c.sentAt ? formatDate(c.sentAt) : (c.scheduledAt ? `📅 ${formatDate(c.scheduledAt)}` : '—')}</td>
          <td class="actions">
            <button class="btn btn-sm btn-ghost view-campaign" data-id="${c.id}">👁️</button>
            ${c.status === 'draft' ? `
              <button class="btn btn-sm btn-ghost edit-campaign" data-id="${c.id}">✏️</button>
              <button class="btn btn-sm btn-success send-campaign" data-id="${c.id}" data-name="${escapeHtml(c.name)}">🚀</button>
              <button class="btn btn-sm btn-ghost del-campaign" data-id="${c.id}" data-name="${escapeHtml(c.name)}">🗑️</button>
            ` : ''}
            ${c.status === 'scheduled' ? `
              <button class="btn btn-sm btn-warning cancel-campaign" data-id="${c.id}" data-name="${escapeHtml(c.name)}">❌</button>
            ` : ''}
          </td>
        </tr>
      `;
    });

    html += '</tbody></table></div>';
    $('#campaigns-table').html(html);

    const total = meta.total || campaigns.length;
    $('#campaigns-pagination').html(renderPagination(currentPage, total, 15, (p) => {
      currentPage = p;
      loadCampaigns();
    }));

    $('.view-campaign').on('click', function () { navigate(`/campaigns/${$(this).data('id')}`); });
    $('.edit-campaign').on('click', function () { navigate(`/campaigns/${$(this).data('id')}`); });

    $('.send-campaign').on('click', function () {
      const id = $(this).data('id');
      const name = $(this).data('name');
      showConfirm('Send Campaign', `Send "${name}" now to all subscribers in the list?`, async () => {
        try {
          await api.post(`/campaigns/${id}/send`);
          showToast('Campaign sent!', 'success');
          loadCampaigns();
        } catch (err) { showToast(err.message, 'error'); }
      }, '🚀 Send Now');
    });

    $('.cancel-campaign').on('click', function () {
      const id = $(this).data('id');
      const name = $(this).data('name');
      showConfirm('Cancel Campaign', `Cancel scheduled campaign "${name}"?`, async () => {
        try {
          await api.post(`/campaigns/${id}/cancel`);
          showToast('Campaign cancelled');
          loadCampaigns();
        } catch (err) { showToast(err.message, 'error'); }
      }, 'Cancel Campaign', true);
    });

    $('.del-campaign').on('click', function () {
      const id = $(this).data('id');
      const name = $(this).data('name');
      showConfirm('Delete Campaign', `Delete "${name}"?`, async () => {
        try {
          await api.delete(`/campaigns/${id}`);
          showToast('Campaign deleted');
          loadCampaigns();
        } catch (err) { showToast(err.message, 'error'); }
      }, 'Delete', true);
    });
  } catch (err) {
    $('#campaigns-table').html(`<div class="empty-state"><p>Error: ${escapeHtml(err.message)}</p></div>`);
  }
}

async function showCreateModal() {
  let lists = [], templates = [];
  try {
    const [lr, tr] = await Promise.all([api.get('/lists'), api.get('/templates')]);
    lists = lr?.data || [];
    templates = tr?.data || [];
  } catch {}

  const $modal = showModal({
    title: 'Create Campaign',
    size: 'lg',
    content: `
      <form>
        <div class="form-group">
          <label>Campaign Name *</label>
          <input type="text" class="form-control" id="camp-name" placeholder="e.g., January Newsletter" required />
        </div>
        <div class="form-group">
          <label>Subject Line *</label>
          <input type="text" class="form-control" id="camp-subject" placeholder="e.g., Your monthly update is here!" required />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>From Name *</label>
            <input type="text" class="form-control" id="camp-from-name" value="Newsletter" />
          </div>
          <div class="form-group">
            <label>From Email *</label>
            <input type="email" class="form-control" id="camp-from-email" value="newsletter@example.com" />
          </div>
        </div>
        <div class="form-group">
          <label>Reply-To Email</label>
          <input type="email" class="form-control" id="camp-reply" placeholder="reply@example.com" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label>Subscriber List *</label>
            <select class="form-control" id="camp-list" required>
              <option value="">Select a list...</option>
              ${lists.map(l => `<option value="${l.id}">${escapeHtml(l.name)} (${l.subscriberCount || 0})</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label>Template</label>
            <select class="form-control" id="camp-template">
              <option value="">No template</option>
              ${templates.map(t => `<option value="${t.id}">${escapeHtml(t.name)}</option>`).join('')}
            </select>
          </div>
        </div>
      </form>
    `,
    footer: `
      <button class="btn btn-secondary modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="create-camp-btn">Create Campaign</button>
    `
  });

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.find('#create-camp-btn').on('click', async () => {
    const data = {
      name: $modal.find('#camp-name').val(),
      subject: $modal.find('#camp-subject').val(),
      fromName: $modal.find('#camp-from-name').val(),
      fromEmail: $modal.find('#camp-from-email').val(),
      replyTo: $modal.find('#camp-reply').val() || null,
      listId: parseInt($modal.find('#camp-list').val()),
      templateId: $modal.find('#camp-template').val() ? parseInt($modal.find('#camp-template').val()) : null
    };

    if (!data.name || !data.subject || !data.listId) {
      showToast('Fill in all required fields', 'warning');
      return;
    }

    try {
      const resp = await api.post('/campaigns', data);
      const campaign = resp?.data || resp;
      showToast('Campaign created');
      $modal.remove();
      navigate(`/campaigns/${campaign.id}`);
    } catch (err) { showToast(err.message, 'error'); }
  });
}

import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import {
  showToast, showModal, showConfirm, escapeHtml, formatDate, formatNumber,
  subscriberStatusBadge, renderPagination, renderLoading, debounce
} from '../utils/helpers.js';
import { navigate } from '../utils/router.js';

let currentPage = 1;
let currentStatus = '';
let currentList = '';
let currentTag = '';
let currentSearch = '';

export default async function subscribersPage(container) {
  currentPage = 1;
  currentStatus = '';
  currentList = '';
  currentTag = '';
  currentSearch = '';

  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div class="page-header">
          <div>
            <h1>👥 Subscribers</h1>
            <div class="subtitle">Manage your email subscriber list</div>
          </div>
          <div class="actions">
            <button class="btn btn-secondary" id="import-btn">📥 Import CSV</button>
            <button class="btn btn-secondary" id="export-btn">📤 Export</button>
            <button class="btn btn-primary" id="add-subscriber-btn">➕ Add Subscriber</button>
          </div>
        </div>

        <div id="subscriber-stats" class="mb-2"></div>

        <div class="filter-bar">
          <div class="search-input">
            <span class="search-icon">🔍</span>
            <input type="text" id="search-input" placeholder="Search by email or name..." />
          </div>
          <select id="filter-status">
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="unsubscribed">Unsubscribed</option>
            <option value="bounced">Bounced</option>
            <option value="complained">Complained</option>
          </select>
          <select id="filter-list"><option value="">All Lists</option></select>
          <select id="filter-tag"><option value="">All Tags</option></select>
        </div>

        <div class="card">
          <div id="subscribers-table">${renderLoading()}</div>
        </div>
        <div id="subscribers-pagination"></div>
      </main>
    </div>
  `);
  initSidebar();

  loadFilters();
  loadStats();
  loadSubscribers();

  const debouncedSearch = debounce(() => {
    currentSearch = $('#search-input').val();
    currentPage = 1;
    loadSubscribers();
  });

  $('#search-input').on('input', debouncedSearch);
  $('#filter-status').on('change', function () { currentStatus = $(this).val(); currentPage = 1; loadSubscribers(); });
  $('#filter-list').on('change', function () { currentList = $(this).val(); currentPage = 1; loadSubscribers(); });
  $('#filter-tag').on('change', function () { currentTag = $(this).val(); currentPage = 1; loadSubscribers(); });
  $('#add-subscriber-btn').on('click', showAddModal);
  $('#import-btn').on('click', showImportModal);
  $('#export-btn').on('click', exportSubscribers);
}

async function loadFilters() {
  try {
    const [listsResp, tagsResp] = await Promise.all([
      api.get('/lists'),
      api.get('/tags')
    ]);
    const lists = listsResp?.data || [];
    const tags = tagsResp?.data || [];

    lists.forEach(l => $('#filter-list').append(`<option value="${l.id}">${escapeHtml(l.name)}</option>`));
    tags.forEach(t => $('#filter-tag').append(`<option value="${t.id}">${escapeHtml(t.name)}</option>`));
  } catch {}
}

async function loadStats() {
  try {
    const resp = await api.get('/subscribers/stats');
    const stats = resp?.data || resp;
    $('#subscriber-stats').html(`
      <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card">
          <div class="stat-icon success">✅</div>
          <div class="stat-info"><h4>Active</h4><div class="value">${formatNumber(stats.active || 0)}</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon secondary">🚫</div>
          <div class="stat-info"><h4>Unsubscribed</h4><div class="value">${formatNumber(stats.unsubscribed || 0)}</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon danger">⛔</div>
          <div class="stat-info"><h4>Bounced</h4><div class="value">${formatNumber(stats.bounced || 0)}</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon warning">⚠️</div>
          <div class="stat-info"><h4>Complained</h4><div class="value">${formatNumber(stats.complained || 0)}</div></div>
        </div>
      </div>
    `);
  } catch {}
}

async function loadSubscribers() {
  const params = new URLSearchParams({ page: currentPage, limit: 20 });
  if (currentStatus) params.set('status', currentStatus);
  if (currentList) params.set('list_id', currentList);
  if (currentTag) params.set('tag_id', currentTag);
  if (currentSearch) params.set('search', currentSearch);

  try {
    const resp = await api.get(`/subscribers?${params}`);
    const subscribers = resp?.data || [];
    const meta = resp?.meta || {};

    if (!subscribers.length) {
      $('#subscribers-table').html('<div class="empty-state"><div class="icon">👥</div><h3>No subscribers found</h3><p>Add your first subscriber or adjust filters</p></div>');
      $('#subscribers-pagination').html('');
      return;
    }

    let html = `
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Email</th>
              <th>Name</th>
              <th>Status</th>
              <th>Lists</th>
              <th>Subscribed</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
    `;

    subscribers.forEach(s => {
      const name = [s.firstName, s.lastName].filter(Boolean).join(' ') || '—';
      const lists = (s.lists || []).map(l => `<span class="tag">${escapeHtml(l.name || l)}</span>`).join(' ');
      html += `
        <tr>
          <td><a href="#/subscribers/${s.id}"><strong>${escapeHtml(s.email)}</strong></a></td>
          <td>${escapeHtml(name)}</td>
          <td>${subscriberStatusBadge(s.status)}</td>
          <td>${lists || '—'}</td>
          <td class="text-sm text-muted">${formatDate(s.subscribedAt || s.createdAt)}</td>
          <td class="actions">
            <button class="btn btn-sm btn-ghost edit-sub" data-id="${s.id}">✏️</button>
            <button class="btn btn-sm btn-ghost delete-sub" data-id="${s.id}" data-email="${escapeHtml(s.email)}">🗑️</button>
          </td>
        </tr>
      `;
    });

    html += '</tbody></table></div>';
    $('#subscribers-table').html(html);

    const total = meta.total || subscribers.length;
    $('#subscribers-pagination').html(renderPagination(currentPage, total, 20, (p) => {
      currentPage = p;
      loadSubscribers();
    }));

    $('.edit-sub').on('click', function () { navigate(`/subscribers/${$(this).data('id')}`); });
    $('.delete-sub').on('click', function () {
      const id = $(this).data('id');
      const email = $(this).data('email');
      showConfirm('Delete Subscriber', `Remove ${email}?`, async () => {
        try {
          await api.delete(`/subscribers/${id}`);
          showToast('Subscriber deleted');
          loadSubscribers();
          loadStats();
        } catch (err) { showToast(err.message, 'error'); }
      }, 'Delete', true);
    });
  } catch (err) {
    $('#subscribers-table').html(`<div class="empty-state"><p>Error: ${escapeHtml(err.message)}</p></div>`);
  }
}

function showAddModal() {
  const $modal = showModal({
    title: 'Add Subscriber',
    content: `
      <form id="add-sub-form">
        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" class="form-control" id="sub-first" placeholder="John" />
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" class="form-control" id="sub-last" placeholder="Doe" />
          </div>
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" class="form-control" id="sub-email" placeholder="john@example.com" required />
        </div>
        <div class="form-group">
          <label>Lists</label>
          <div id="sub-lists-checkboxes">Loading...</div>
        </div>
      </form>
    `,
    footer: `
      <button class="btn btn-secondary modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="save-sub-btn">Add Subscriber</button>
    `
  });

  api.get('/lists').then(resp => {
    const lists = resp?.data || [];
    let html = lists.map(l => `
      <label style="display:block;margin-bottom:4px;">
        <input type="checkbox" class="sub-list-cb" value="${l.id}" ${l.isDefault ? 'checked' : ''}>
        ${escapeHtml(l.name)}
      </label>
    `).join('');
    $modal.find('#sub-lists-checkboxes').html(html || 'No lists');
  });

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.find('#save-sub-btn').on('click', async () => {
    const listIds = [];
    $modal.find('.sub-list-cb:checked').each(function () { listIds.push(parseInt($(this).val())); });

    try {
      await api.post('/subscribers', {
        email: $modal.find('#sub-email').val(),
        firstName: $modal.find('#sub-first').val(),
        lastName: $modal.find('#sub-last').val(),
        listIds
      });
      showToast('Subscriber added!');
      $modal.remove();
      loadSubscribers();
      loadStats();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

function showImportModal() {
  const $modal = showModal({
    title: 'Import Subscribers (CSV)',
    content: `
      <div class="form-group">
        <label>CSV File</label>
        <input type="file" class="form-control" id="csv-file" accept=".csv" />
        <div class="form-hint">Expected columns: email, first_name, last_name</div>
      </div>
    `,
    footer: `
      <button class="btn btn-secondary modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="import-csv-btn">📥 Import</button>
    `
  });

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.find('#import-csv-btn').on('click', async () => {
    const file = $modal.find('#csv-file')[0]?.files[0];
    if (!file) { showToast('Select a file', 'warning'); return; }

    const formData = new FormData();
    formData.append('file', file);

    try {
      const resp = await api.upload('/subscribers/import', formData);
      const result = resp?.data || resp;
      showToast(`Imported: ${result.imported || 0}, Skipped: ${result.skipped || 0}`);
      $modal.remove();
      loadSubscribers();
      loadStats();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

async function exportSubscribers() {
  try {
    const params = currentList ? `?list_id=${currentList}` : '';
    const resp = await api.get(`/subscribers/export${params}`);
    const csv = resp?.csv || resp?.data || '';
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'subscribers.csv';
    a.click();
    URL.revokeObjectURL(url);
    showToast('Export downloaded!');
  } catch (err) { showToast(err.message, 'error'); }
}

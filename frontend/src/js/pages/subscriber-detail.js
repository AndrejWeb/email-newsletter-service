import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import {
  showToast, showModal, showConfirm, escapeHtml, formatDate, formatNumber,
  subscriberStatusBadge, renderLoading
} from '../utils/helpers.js';
import { navigate } from '../utils/router.js';

export default async function subscriberDetailPage(container, params) {
  const id = params.id;

  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div id="subscriber-detail">${renderLoading()}</div>
      </main>
    </div>
  `);
  initSidebar();

  try {
    const resp = await api.get(`/subscribers/${id}`);
    const sub = resp?.data || resp;
    renderDetail(sub);
  } catch (err) {
    $('#subscriber-detail').html(`<div class="empty-state"><div class="icon">⚠️</div><h3>Error</h3><p>${escapeHtml(err.message)}</p></div>`);
  }
}

function renderDetail(sub) {
  const name = [sub.firstName, sub.lastName].filter(Boolean).join(' ') || '—';
  const lists = (sub.lists || []);
  const tags = (sub.tags || []);

  let html = `
    <div class="page-header">
      <div>
        <h1>👤 ${escapeHtml(sub.email)}</h1>
        <div class="subtitle">${escapeHtml(name)} — ${subscriberStatusBadge(sub.status)}</div>
      </div>
      <div class="actions">
        <button class="btn btn-secondary" onclick="window.location.hash='#/subscribers'">← Back</button>
        <button class="btn btn-primary" id="edit-sub-btn">✏️ Edit</button>
        ${sub.status === 'active' ? '<button class="btn btn-warning" id="unsub-btn">🚫 Unsubscribe</button>' : ''}
        <button class="btn btn-danger" id="del-sub-btn">🗑️ Delete</button>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header"><h3>ℹ️ Details</h3></div>
        <div class="card-body">
          <table>
            <tr><td style="width:140px;font-weight:600;">Email</td><td>${escapeHtml(sub.email)}</td></tr>
            <tr><td style="font-weight:600;">First Name</td><td>${escapeHtml(sub.firstName || '—')}</td></tr>
            <tr><td style="font-weight:600;">Last Name</td><td>${escapeHtml(sub.lastName || '—')}</td></tr>
            <tr><td style="font-weight:600;">Status</td><td>${subscriberStatusBadge(sub.status)}</td></tr>
            <tr><td style="font-weight:600;">Subscribed</td><td>${formatDate(sub.subscribedAt || sub.createdAt)}</td></tr>
            ${sub.unsubscribedAt ? `<tr><td style="font-weight:600;">Unsubscribed</td><td>${formatDate(sub.unsubscribedAt)}</td></tr>` : ''}
            <tr><td style="font-weight:600;">IP Address</td><td>${escapeHtml(sub.ipAddress || '—')}</td></tr>
          </table>
        </div>
      </div>

      <div>
        <div class="card mb-2">
          <div class="card-header">
            <h3>📋 Lists (${lists.length})</h3>
            <button class="btn btn-sm btn-secondary" id="manage-lists-btn">Manage</button>
          </div>
          <div class="card-body">
            ${lists.length ? lists.map(l => `
              <div class="d-flex align-center gap-1 mb-1">
                <span class="tag">${escapeHtml(l.name || l)}</span>
                <button class="btn btn-sm btn-ghost remove-list-btn" data-id="${l.id}">✕</button>
              </div>
            `).join('') : '<p class="text-muted text-sm">Not in any lists</p>'}
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3>🏷️ Tags (${tags.length})</h3>
            <button class="btn btn-sm btn-secondary" id="manage-tags-btn">Manage</button>
          </div>
          <div class="card-body">
            ${tags.length ? tags.map(t => `
              <div class="d-flex align-center gap-1 mb-1" style="display:inline-flex;margin-right:8px;">
                <span class="tag"><span class="tag-dot" style="background:${t.color || '#6366f1'};"></span> ${escapeHtml(t.name || t)}</span>
                <button class="btn btn-sm btn-ghost remove-tag-btn" data-id="${t.id}">✕</button>
              </div>
            `).join('') : '<p class="text-muted text-sm">No tags</p>'}
          </div>
        </div>
      </div>
    </div>
  `;

  $('#subscriber-detail').html(html);

  $('#edit-sub-btn').on('click', () => showEditModal(sub));
  $('#del-sub-btn').on('click', () => {
    showConfirm('Delete Subscriber', `Permanently delete ${sub.email}?`, async () => {
      try {
        await api.delete(`/subscribers/${sub.id}`);
        showToast('Subscriber deleted');
        navigate('/subscribers');
      } catch (err) { showToast(err.message, 'error'); }
    }, 'Delete', true);
  });

  $('#unsub-btn').on('click', () => {
    showConfirm('Unsubscribe', `Unsubscribe ${sub.email}?`, async () => {
      try {
        await api.put(`/subscribers/${sub.id}`, { status: 'unsubscribed' });
        showToast('Subscriber unsubscribed');
        location.reload();
      } catch (err) { showToast(err.message, 'error'); }
    }, 'Unsubscribe');
  });

  $('.remove-list-btn').on('click', async function () {
    const listId = $(this).data('id');
    try {
      await api.delete(`/subscribers/${sub.id}/lists/${listId}`);
      showToast('Removed from list');
      location.reload();
    } catch (err) { showToast(err.message, 'error'); }
  });

  $('.remove-tag-btn').on('click', async function () {
    const tagId = $(this).data('id');
    try {
      await api.delete(`/subscribers/${sub.id}/tags/${tagId}`);
      showToast('Tag removed');
      location.reload();
    } catch (err) { showToast(err.message, 'error'); }
  });

  $('#manage-lists-btn').on('click', () => showManageListsModal(sub));
  $('#manage-tags-btn').on('click', () => showManageTagsModal(sub));
}

function showEditModal(sub) {
  const $modal = showModal({
    title: 'Edit Subscriber',
    content: `
      <form>
        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" class="form-control" id="edit-first" value="${escapeHtml(sub.firstName || '')}" />
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" class="form-control" id="edit-last" value="${escapeHtml(sub.lastName || '')}" />
          </div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" class="form-control" id="edit-email" value="${escapeHtml(sub.email)}" />
        </div>
      </form>
    `,
    footer: `
      <button class="btn btn-secondary modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="save-edit-btn">Save Changes</button>
    `
  });

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.find('#save-edit-btn').on('click', async () => {
    try {
      await api.put(`/subscribers/${sub.id}`, {
        email: $modal.find('#edit-email').val(),
        firstName: $modal.find('#edit-first').val(),
        lastName: $modal.find('#edit-last').val()
      });
      showToast('Subscriber updated');
      $modal.remove();
      location.reload();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

async function showManageListsModal(sub) {
  const $modal = showModal({
    title: 'Add to List',
    content: '<div id="avail-lists">Loading...</div>',
    footer: '<button class="btn btn-secondary modal-cancel">Close</button>'
  });

  try {
    const resp = await api.get('/lists');
    const lists = resp?.data || [];
    const currentIds = (sub.lists || []).map(l => l.id);
    const available = lists.filter(l => !currentIds.includes(l.id));

    if (!available.length) {
      $modal.find('#avail-lists').html('<p class="text-muted">Already in all lists</p>');
    } else {
      let html = available.map(l => `
        <button class="btn btn-secondary mb-1 add-to-list-btn" data-id="${l.id}" style="display:block;width:100;text-align:left;">
          📋 ${escapeHtml(l.name)}
        </button>
      `).join('');
      $modal.find('#avail-lists').html(html);
    }
  } catch (err) { $modal.find('#avail-lists').html('Error loading lists'); }

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.on('click', '.add-to-list-btn', async function () {
    const listId = $(this).data('id');
    try {
      await api.post(`/subscribers/${sub.id}/lists/${listId}`);
      showToast('Added to list');
      $modal.remove();
      location.reload();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

async function showManageTagsModal(sub) {
  const $modal = showModal({
    title: 'Add Tag',
    content: '<div id="avail-tags">Loading...</div>',
    footer: '<button class="btn btn-secondary modal-cancel">Close</button>'
  });

  try {
    const resp = await api.get('/tags');
    const tags = resp?.data || [];
    const currentIds = (sub.tags || []).map(t => t.id);
    const available = tags.filter(t => !currentIds.includes(t.id));

    if (!available.length) {
      $modal.find('#avail-tags').html('<p class="text-muted">All tags assigned</p>');
    } else {
      let html = available.map(t => `
        <button class="btn btn-secondary mb-1 add-tag-btn" data-id="${t.id}" style="display:inline-flex;margin-right:8px;">
          <span class="tag-dot" style="background:${t.color};width:8px;height:8px;border-radius:50;display:inline-block;margin-right:4px;"></span>
          ${escapeHtml(t.name)}
        </button>
      `).join('');
      $modal.find('#avail-tags').html(html);
    }
  } catch (err) { $modal.find('#avail-tags').html('Error loading tags'); }

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.on('click', '.add-tag-btn', async function () {
    const tagId = $(this).data('id');
    try {
      await api.post(`/subscribers/${sub.id}/tags/${tagId}`);
      showToast('Tag added');
      $modal.remove();
      location.reload();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

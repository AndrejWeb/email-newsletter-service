import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import {
  showToast, showModal, showConfirm, escapeHtml, formatDate, formatNumber, renderLoading
} from '../utils/helpers.js';

export default async function listsPage(container) {
  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div class="page-header">
          <div>
            <h1>📋 Subscriber Lists</h1>
            <div class="subtitle">Organize subscribers into targeted groups</div>
          </div>
          <div class="actions">
            <button class="btn btn-primary" id="add-list-btn">➕ New List</button>
          </div>
        </div>
        <div id="lists-content">${renderLoading()}</div>
      </main>
    </div>
  `);
  initSidebar();

  loadLists();

  $('#add-list-btn').on('click', () => showListModal());
}

async function loadLists() {
  try {
    const resp = await api.get('/lists');
    const lists = resp?.data || [];

    if (!lists.length) {
      $('#lists-content').html('<div class="empty-state"><div class="icon">📋</div><h3>No lists yet</h3><p>Create your first subscriber list</p></div>');
      return;
    }

    let html = '<div class="grid-3">';
    lists.forEach(l => {
      html += `
        <div class="card">
          <div class="card-body">
            <div class="d-flex align-center" style="justify-content:space-between;margin-bottom:12px;">
              <h3 style="font-size:16px;">${escapeHtml(l.name)}</h3>
              ${l.isDefault ? '<span class="badge badge-primary">Default</span>' : ''}
            </div>
            <p class="text-sm text-muted mb-2">${escapeHtml(l.description || 'No description')}</p>
            <div class="d-flex align-center gap-1">
              <span class="stat-icon primary" style="width:32px;height:32px;font-size:14px;border-radius:6px;">👥</span>
              <span class="fw-600">${formatNumber(l.subscriberCount || 0)}</span>
              <span class="text-muted text-sm">subscribers</span>
            </div>
            <div class="text-xs text-muted mt-1">Created ${formatDate(l.createdAt)}</div>
          </div>
          <div class="card-footer" style="display:flex;gap:8px;">
            <button class="btn btn-sm btn-secondary flex-1 edit-list" data-id="${l.id}">✏️ Edit</button>
            ${!l.isDefault ? `<button class="btn btn-sm btn-danger delete-list" data-id="${l.id}" data-name="${escapeHtml(l.name)}">🗑️</button>` : ''}
          </div>
        </div>
      `;
    });
    html += '</div>';

    $('#lists-content').html(html);

    $('.edit-list').on('click', async function () {
      const id = $(this).data('id');
      try {
        const resp = await api.get(`/lists/${id}`);
        showListModal(resp?.data || resp);
      } catch (err) { showToast(err.message, 'error'); }
    });

    $('.delete-list').on('click', function () {
      const id = $(this).data('id');
      const name = $(this).data('name');
      showConfirm('Delete List', `Delete "${name}"? Subscribers won't be deleted.`, async () => {
        try {
          await api.delete(`/lists/${id}`);
          showToast('List deleted');
          loadLists();
        } catch (err) { showToast(err.message, 'error'); }
      }, 'Delete', true);
    });
  } catch (err) {
    $('#lists-content').html(`<div class="empty-state"><p>Error: ${escapeHtml(err.message)}</p></div>`);
  }
}

function showListModal(list = null) {
  const isEdit = !!list;
  const $modal = showModal({
    title: isEdit ? 'Edit List' : 'Create List',
    content: `
      <form>
        <div class="form-group">
          <label>Name *</label>
          <input type="text" class="form-control" id="list-name" value="${escapeHtml(list?.name || '')}" required />
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea class="form-control" id="list-desc" rows="3">${escapeHtml(list?.description || '')}</textarea>
        </div>
      </form>
    `,
    footer: `
      <button class="btn btn-secondary modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="save-list-btn">${isEdit ? 'Save Changes' : 'Create List'}</button>
    `
  });

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.find('#save-list-btn').on('click', async () => {
    const data = {
      name: $modal.find('#list-name').val(),
      description: $modal.find('#list-desc').val()
    };

    try {
      if (isEdit) {
        await api.put(`/lists/${list.id}`, data);
        showToast('List updated');
      } else {
        await api.post('/lists', data);
        showToast('List created');
      }
      $modal.remove();
      loadLists();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

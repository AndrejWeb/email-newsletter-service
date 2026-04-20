import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import {
  showToast, showModal, showConfirm, escapeHtml, formatNumber, renderLoading
} from '../utils/helpers.js';

export default async function tagsPage(container) {
  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div class="page-header">
          <div>
            <h1>🏷️ Tags</h1>
            <div class="subtitle">Categorize and segment your subscribers</div>
          </div>
          <div class="actions">
            <button class="btn btn-primary" id="add-tag-btn">➕ New Tag</button>
          </div>
        </div>
        <div id="tags-content">${renderLoading()}</div>
      </main>
    </div>
  `);
  initSidebar();

  loadTags();
  $('#add-tag-btn').on('click', () => showTagModal());
}

async function loadTags() {
  try {
    const resp = await api.get('/tags');
    const tags = resp?.data || [];

    if (!tags.length) {
      $('#tags-content').html('<div class="empty-state"><div class="icon">🏷️</div><h3>No tags yet</h3><p>Create tags to categorize subscribers</p></div>');
      return;
    }

    let html = '<div class="card"><div class="card-body" style="padding:0;"><div class="table-container"><table>';
    html += '<thead><tr><th>Tag</th><th>Color</th><th>Subscribers</th><th>Actions</th></tr></thead><tbody>';

    tags.forEach(t => {
      html += `
        <tr>
          <td>
            <span class="tag">
              <span class="tag-dot" style="background:${t.color || '#6366f1'};"></span>
              ${escapeHtml(t.name)}
            </span>
          </td>
          <td><input type="color" value="${t.color || '#6366f1'}" disabled style="border:none;width:32px;height:24px;" /></td>
          <td>${formatNumber(t.subscriberCount || 0)}</td>
          <td class="actions">
            <button class="btn btn-sm btn-ghost edit-tag" data-id="${t.id}" data-name="${escapeHtml(t.name)}" data-color="${t.color || '#6366f1'}">✏️</button>
            <button class="btn btn-sm btn-ghost delete-tag" data-id="${t.id}" data-name="${escapeHtml(t.name)}">🗑️</button>
          </td>
        </tr>
      `;
    });

    html += '</tbody></table></div></div></div>';
    $('#tags-content').html(html);

    $('.edit-tag').on('click', function () {
      showTagModal({ id: $(this).data('id'), name: $(this).data('name'), color: $(this).data('color') });
    });

    $('.delete-tag').on('click', function () {
      const id = $(this).data('id');
      const name = $(this).data('name');
      showConfirm('Delete Tag', `Delete tag "${name}"?`, async () => {
        try {
          await api.delete(`/tags/${id}`);
          showToast('Tag deleted');
          loadTags();
        } catch (err) { showToast(err.message, 'error'); }
      }, 'Delete', true);
    });
  } catch (err) {
    $('#tags-content').html(`<div class="empty-state"><p>Error: ${escapeHtml(err.message)}</p></div>`);
  }
}

function showTagModal(tag = null) {
  const isEdit = !!tag;
  const $modal = showModal({
    title: isEdit ? 'Edit Tag' : 'Create Tag',
    content: `
      <form>
        <div class="form-row">
          <div class="form-group" style="flex:2;">
            <label>Name *</label>
            <input type="text" class="form-control" id="tag-name" value="${escapeHtml(tag?.name || '')}" required />
          </div>
          <div class="form-group" style="flex:1;">
            <label>Color</label>
            <input type="color" class="form-control" id="tag-color" value="${tag?.color || '#6366f1'}" style="height:38px;padding:4px;" />
          </div>
        </div>
      </form>
    `,
    footer: `
      <button class="btn btn-secondary modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="save-tag-btn">${isEdit ? 'Save' : 'Create Tag'}</button>
    `
  });

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.find('#save-tag-btn').on('click', async () => {
    const data = {
      name: $modal.find('#tag-name').val(),
      color: $modal.find('#tag-color').val()
    };
    try {
      if (isEdit) {
        await api.put(`/tags/${tag.id}`, data);
        showToast('Tag updated');
      } else {
        await api.post('/tags', data);
        showToast('Tag created');
      }
      $modal.remove();
      loadTags();
    } catch (err) { showToast(err.message, 'error'); }
  });
}

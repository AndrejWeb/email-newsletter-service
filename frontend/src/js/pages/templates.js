import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import {
  showToast, showModal, showConfirm, escapeHtml, formatDate, renderLoading
} from '../utils/helpers.js';
import { navigate } from '../utils/router.js';

export default async function templatesPage(container) {
  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div class="page-header">
          <div>
            <h1>🎨 Email Templates</h1>
            <div class="subtitle">Design reusable email templates</div>
          </div>
          <div class="actions">
            <button class="btn btn-primary" id="create-template-btn">➕ New Template</button>
          </div>
        </div>
        <div id="templates-content">${renderLoading()}</div>
      </main>
    </div>
  `);
  initSidebar();

  loadTemplates();

  $('#create-template-btn').on('click', () => {
    showNewTemplateModal();
  });
}

async function loadTemplates() {
  try {
    const resp = await api.get('/templates');
    const templates = resp?.data || [];

    if (!templates.length) {
      $('#templates-content').html('<div class="empty-state"><div class="icon">🎨</div><h3>No templates yet</h3><p>Create your first email template</p></div>');
      return;
    }

    let html = '<div class="grid-3">';
    templates.forEach(t => {
      const blockCount = (t.content || []).length;
      html += `
        <div class="card" style="cursor:pointer;" data-id="${t.id}">
          <div class="card-body">
            <div style="height:120px;background:var(--gray-100);border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;font-size:36px;color:var(--gray-300);">
              📧
            </div>
            <h3 style="font-size:15px;margin-bottom:4px;">${escapeHtml(t.name)}</h3>
            <div class="text-sm text-muted mb-1">${escapeHtml(t.subject || 'No subject')}</div>
            <div class="d-flex align-center gap-1" style="justify-content:space-between;">
              <span class="badge badge-gray">${blockCount} blocks</span>
              <span class="badge badge-primary">${escapeHtml(t.category || 'general')}</span>
            </div>
            <div class="text-xs text-muted mt-1">${formatDate(t.createdAt)}</div>
          </div>
          <div class="card-footer" style="display:flex;gap:8px;">
            <button class="btn btn-sm btn-primary flex-1 edit-template" data-id="${t.id}">✏️ Edit</button>
            <button class="btn btn-sm btn-secondary preview-template" data-id="${t.id}">👁️</button>
            <button class="btn btn-sm btn-secondary dup-template" data-id="${t.id}">📋</button>
            <button class="btn btn-sm btn-danger del-template" data-id="${t.id}" data-name="${escapeHtml(t.name)}">🗑️</button>
          </div>
        </div>
      `;
    });
    html += '</div>';
    $('#templates-content').html(html);

    $('.edit-template').on('click', function (e) {
      e.stopPropagation();
      navigate(`/templates/${$(this).data('id')}/edit`);
    });

    $('.preview-template').on('click', async function (e) {
      e.stopPropagation();
      const id = $(this).data('id');
      try {
        const resp = await api.post(`/templates/${id}/preview`);
        const html = resp?.data?.html || resp?.html || '<p>No content</p>';
        showPreviewModal(html);
      } catch (err) { showToast(err.message, 'error'); }
    });

    $('.dup-template').on('click', async function (e) {
      e.stopPropagation();
      const id = $(this).data('id');
      try {
        await api.post(`/templates/${id}/duplicate`);
        showToast('Template duplicated');
        loadTemplates();
      } catch (err) { showToast(err.message, 'error'); }
    });

    $('.del-template').on('click', function (e) {
      e.stopPropagation();
      const id = $(this).data('id');
      const name = $(this).data('name');
      showConfirm('Delete Template', `Delete "${name}"?`, async () => {
        try {
          await api.delete(`/templates/${id}`);
          showToast('Template deleted');
          loadTemplates();
        } catch (err) { showToast(err.message, 'error'); }
      }, 'Delete', true);
    });

    $('#templates-content .card[data-id]').on('click', function () {
      navigate(`/templates/${$(this).data('id')}/edit`);
    });
  } catch (err) {
    $('#templates-content').html(`<div class="empty-state"><p>Error: ${escapeHtml(err.message)}</p></div>`);
  }
}

function showNewTemplateModal() {
  const $modal = showModal({
    title: 'New Template',
    content: `
      <form>
        <div class="form-group">
          <label>Template Name *</label>
          <input type="text" class="form-control" id="tpl-name" placeholder="e.g., Monthly Newsletter" required />
        </div>
        <div class="form-group">
          <label>Subject Line</label>
          <input type="text" class="form-control" id="tpl-subject" placeholder="e.g., Your Monthly Update" />
        </div>
        <div class="form-group">
          <label>Category</label>
          <select class="form-control" id="tpl-category">
            <option value="general">General</option>
            <option value="newsletter">Newsletter</option>
            <option value="promotional">Promotional</option>
            <option value="transactional">Transactional</option>
            <option value="welcome">Welcome</option>
          </select>
        </div>
      </form>
    `,
    footer: `
      <button class="btn btn-secondary modal-cancel">Cancel</button>
      <button class="btn btn-primary" id="create-tpl-btn">Create & Edit</button>
    `
  });

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
  $modal.find('#create-tpl-btn').on('click', async () => {
    const data = {
      name: $modal.find('#tpl-name').val(),
      subject: $modal.find('#tpl-subject').val(),
      category: $modal.find('#tpl-category').val(),
      content: []
    };

    try {
      const resp = await api.post('/templates', data);
      const tpl = resp?.data || resp;
      showToast('Template created');
      $modal.remove();
      navigate(`/templates/${tpl.id}/edit`);
    } catch (err) { showToast(err.message, 'error'); }
  });
}

function showPreviewModal(htmlContent) {
  const $modal = showModal({
    title: 'Template Preview',
    size: 'lg',
    content: `
      <div class="preview-frame">
        <iframe id="preview-iframe" sandbox="allow-same-origin"></iframe>
      </div>
    `,
    footer: '<button class="btn btn-secondary modal-cancel">Close</button>'
  });

  setTimeout(() => {
    const iframe = document.getElementById('preview-iframe');
    if (iframe) {
      const doc = iframe.contentDocument || iframe.contentWindow.document;
      doc.open();
      doc.write(htmlContent);
      doc.close();
      iframe.style.height = Math.max(doc.body.scrollHeight + 30, 300) + 'px';
    }
  }, 100);

  $modal.find('.modal-cancel').on('click', () => $modal.remove());
}

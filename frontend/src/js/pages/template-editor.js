import $ from 'jquery';
import Sortable from 'sortablejs';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import { showToast, escapeHtml, renderLoading } from '../utils/helpers.js';
import { navigate } from '../utils/router.js';

const BLOCK_TYPES = [
  { type: 'header', icon: '🔤', label: 'Header', defaults: { text: 'Your Heading', level: 1, align: 'center' } },
  { type: 'text', icon: '📝', label: 'Text Block', defaults: { content: 'Enter your text here...', align: 'left' } },
  { type: 'image', icon: '🖼️', label: 'Image', defaults: { src: '/template-images/featured-article.svg', alt: 'Image', width: '100%', align: 'center', link: '' } },
  { type: 'button', icon: '🔘', label: 'Button', defaults: { text: 'Click Here', url: '#', color: '#ffffff', bgColor: '#6366f1', align: 'center' } },
  { type: 'divider', icon: '➖', label: 'Divider', defaults: { color: '#e5e7eb', thickness: 1 } },
  { type: 'spacer', icon: '↕️', label: 'Spacer', defaults: { height: 20 } },
  { type: 'social', icon: '🌐', label: 'Social Links', defaults: { networks: [{ name: 'Twitter', url: '#' }, { name: 'Facebook', url: '#' }, { name: 'LinkedIn', url: '#' }] } },
  { type: 'html', icon: '💻', label: 'Custom HTML', defaults: { content: '<p>Custom HTML</p>' } }
];

let template = null;
let blocks = [];
let selectedBlockIndex = -1;

export default async function templateEditorPage(container, params) {
  const id = params.id;

  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div id="editor-area">${renderLoading()}</div>
      </main>
    </div>
  `);
  initSidebar();

  try {
    const resp = await api.get(`/templates/${id}`);
    template = resp?.data || resp;
    blocks = Array.isArray(template.content) ? [...template.content] : [];
    renderEditor();
  } catch (err) {
    $('#editor-area').html(`<div class="empty-state"><p>Error: ${escapeHtml(err.message)}</p></div>`);
  }
}

function renderEditor() {
  let html = `
    <div class="page-header">
      <div>
        <h1>✏️ ${escapeHtml(template.name)}</h1>
        <div class="subtitle">${escapeHtml(template.subject || 'No subject')}</div>
      </div>
      <div class="actions">
        <button class="btn btn-secondary" id="back-btn">← Back</button>
        <button class="btn btn-secondary" id="preview-btn">👁️ Preview</button>
        <button class="btn btn-primary" id="save-btn">💾 Save</button>
      </div>
    </div>

    <div class="form-row mb-2">
      <div class="form-group">
        <label>Template Name</label>
        <input type="text" class="form-control" id="tpl-name" value="${escapeHtml(template.name)}" />
      </div>
      <div class="form-group">
        <label>Subject Line</label>
        <input type="text" class="form-control" id="tpl-subject" value="${escapeHtml(template.subject || '')}" />
      </div>
    </div>

    <div class="template-editor">
      <div class="block-palette">
        <h4>📦 Blocks</h4>
        <p class="text-xs text-muted mb-2">Click to add blocks</p>
        <div id="block-palette-items">
          ${BLOCK_TYPES.map(bt => `
            <div class="block-type" data-type="${bt.type}">
              <span class="block-icon">${bt.icon}</span>
              <span>${bt.label}</span>
            </div>
          `).join('')}
        </div>
      </div>

      <div class="editor-canvas ${blocks.length ? 'has-blocks' : ''}" id="editor-canvas">
        ${blocks.length ? '' : `
          <div class="editor-empty">
            <div class="icon">📧</div>
            <p>Click blocks from the palette to add them here</p>
          </div>
        `}
        <div id="blocks-container"></div>
      </div>

      <div class="block-properties" id="block-properties">
        <h4>⚙️ Properties</h4>
        <div id="props-content">
          <p class="text-sm text-muted">Select a block to edit its properties</p>
        </div>
      </div>
    </div>
  `;

  $('#editor-area').html(html);
  renderBlocks();
  initSortable();

  // Event handlers
  $('#back-btn').on('click', () => navigate('/templates'));
  $('#save-btn').on('click', saveTemplate);
  $('#preview-btn').on('click', previewTemplate);

  $('.block-type').on('click', function () {
    const type = $(this).data('type');
    const bt = BLOCK_TYPES.find(b => b.type === type);
    if (bt) {
      blocks.push({ type: bt.type, ...JSON.parse(JSON.stringify(bt.defaults)) });
      selectedBlockIndex = blocks.length - 1;
      renderBlocks();
      renderProperties();
    }
  });
}

function renderBlocks() {
  if (!blocks.length) {
    $('#editor-canvas').removeClass('has-blocks');
    $('#blocks-container').html('');
    return;
  }

  $('#editor-canvas').addClass('has-blocks');
  let html = '';

  blocks.forEach((block, i) => {
    const isSelected = i === selectedBlockIndex;
    html += `
      <div class="editor-block ${isSelected ? 'selected' : ''}" data-index="${i}">
        <div class="block-actions">
          <button class="move-up" data-index="${i}" title="Move up">↑</button>
          <button class="move-down" data-index="${i}" title="Move down">↓</button>
          <button class="delete-block" data-index="${i}" title="Delete">🗑️</button>
        </div>
        ${renderBlockPreview(block)}
      </div>
    `;
  });

  $('#blocks-container').html(html);

  $('.editor-block').on('click', function (e) {
    if ($(e.target).closest('.block-actions').length) return;
    selectedBlockIndex = parseInt($(this).data('index'));
    renderBlocks();
    renderProperties();
  });

  $('.move-up').on('click', function (e) {
    e.stopPropagation();
    const i = parseInt($(this).data('index'));
    if (i > 0) {
      [blocks[i - 1], blocks[i]] = [blocks[i], blocks[i - 1]];
      selectedBlockIndex = i - 1;
      renderBlocks();
      renderProperties();
    }
  });

  $('.move-down').on('click', function (e) {
    e.stopPropagation();
    const i = parseInt($(this).data('index'));
    if (i < blocks.length - 1) {
      [blocks[i], blocks[i + 1]] = [blocks[i + 1], blocks[i]];
      selectedBlockIndex = i + 1;
      renderBlocks();
      renderProperties();
    }
  });

  $('.delete-block').on('click', function (e) {
    e.stopPropagation();
    const i = parseInt($(this).data('index'));
    blocks.splice(i, 1);
    if (selectedBlockIndex >= blocks.length) selectedBlockIndex = blocks.length - 1;
    renderBlocks();
    renderProperties();
  });
}

function renderBlockPreview(block) {
  switch (block.type) {
    case 'header':
      return `<h${block.level || 1} style="text-align:${block.align || 'center'};margin:0;color:var(--gray-800);">${escapeHtml(block.text || 'Heading')}</h${block.level || 1}>`;
    case 'text':
      return `<p style="text-align:${block.align || 'left'};margin:0;font-size:14px;color:var(--gray-600);">${escapeHtml(block.content || 'Text content')}</p>`;
    case 'image':
      return `<div style="text-align:${block.align || 'center'};"><img src="${escapeHtml(block.src)}" alt="${escapeHtml(block.alt)}" style="max-width:${block.width || '100%'};height:auto;border-radius:4px;" onerror="this.style.background='var(--gray-200)';this.style.height='100px';this.alt='Image placeholder'" /></div>`;
    case 'button':
      return `<div style="text-align:${block.align || 'center'};"><span style="display:inline-block;padding:10px 24px;background:${block.bgColor || '#6366f1'};color:${block.color || '#fff'};border-radius:6px;font-weight:600;font-size:14px;">${escapeHtml(block.text || 'Button')}</span></div>`;
    case 'divider':
      return `<hr style="border:none;border-top:${block.thickness || 1}px solid ${block.color || '#e5e7eb'};margin:8px 0;" />`;
    case 'spacer':
      return `<div style="height:${block.height || 20}px;background:repeating-linear-gradient(45deg,transparent,transparent 5px,var(--gray-100) 5px,var(--gray-100) 10px);border-radius:4px;"></div>`;
    case 'social':
      const links = (block.networks || []).map(n => `<span style="display:inline-block;padding:4px 12px;margin:0 4px;background:var(--gray-100);border-radius:4px;font-size:13px;">${escapeHtml(n.name)}</span>`).join('');
      return `<div style="text-align:center;">${links}</div>`;
    case 'html':
      return `<div style="padding:8px;background:var(--gray-50);border-radius:4px;font-family:monospace;font-size:12px;color:var(--gray-600);"><code>${escapeHtml((block.content || '').substring(0, 100))}</code></div>`;
    default:
      return `<p class="text-muted">Unknown block: ${block.type}</p>`;
  }
}

function renderProperties() {
  if (selectedBlockIndex < 0 || selectedBlockIndex >= blocks.length) {
    $('#props-content').html('<p class="text-sm text-muted">Select a block to edit its properties</p>');
    return;
  }

  const block = blocks[selectedBlockIndex];
  const bt = BLOCK_TYPES.find(b => b.type === block.type);
  let html = `<div class="mb-2"><span class="badge badge-primary">${bt?.icon || ''} ${bt?.label || block.type}</span></div>`;

  switch (block.type) {
    case 'header':
      html += `
        <div class="form-group"><label>Text</label><input type="text" class="form-control prop-input" data-prop="text" value="${escapeHtml(block.text || '')}" /></div>
        <div class="form-group"><label>Level</label><select class="form-control prop-input" data-prop="level" data-type="int">
          ${[1,2,3,4].map(l => `<option value="${l}" ${block.level == l ? 'selected' : ''}>H${l}</option>`).join('')}
        </select></div>
        <div class="form-group"><label>Align</label><select class="form-control prop-input" data-prop="align">
          <option value="left" ${block.align === 'left' ? 'selected' : ''}>Left</option>
          <option value="center" ${block.align === 'center' ? 'selected' : ''}>Center</option>
          <option value="right" ${block.align === 'right' ? 'selected' : ''}>Right</option>
        </select></div>
      `;
      break;
    case 'text':
      html += `
        <div class="form-group"><label>Content</label><textarea class="form-control prop-input" data-prop="content" rows="4">${escapeHtml(block.content || '')}</textarea></div>
        <div class="form-group"><label>Align</label><select class="form-control prop-input" data-prop="align">
          <option value="left" ${block.align === 'left' ? 'selected' : ''}>Left</option>
          <option value="center" ${block.align === 'center' ? 'selected' : ''}>Center</option>
          <option value="right" ${block.align === 'right' ? 'selected' : ''}>Right</option>
        </select></div>
      `;
      break;
    case 'image':
      html += `
        <div class="form-group"><label>Image URL</label><input type="url" class="form-control prop-input" data-prop="src" value="${escapeHtml(block.src || '')}" /></div>
        <div class="form-group"><label>Alt Text</label><input type="text" class="form-control prop-input" data-prop="alt" value="${escapeHtml(block.alt || '')}" /></div>
        <div class="form-group"><label>Width</label><input type="text" class="form-control prop-input" data-prop="width" value="${escapeHtml(block.width || '100%')}" /></div>
        <div class="form-group"><label>Link URL</label><input type="url" class="form-control prop-input" data-prop="link" value="${escapeHtml(block.link || '')}" /></div>
        <div class="form-group"><label>Align</label><select class="form-control prop-input" data-prop="align">
          <option value="left" ${block.align === 'left' ? 'selected' : ''}>Left</option>
          <option value="center" ${block.align === 'center' ? 'selected' : ''}>Center</option>
          <option value="right" ${block.align === 'right' ? 'selected' : ''}>Right</option>
        </select></div>
      `;
      break;
    case 'button':
      html += `
        <div class="form-group"><label>Button Text</label><input type="text" class="form-control prop-input" data-prop="text" value="${escapeHtml(block.text || '')}" /></div>
        <div class="form-group"><label>URL</label><input type="url" class="form-control prop-input" data-prop="url" value="${escapeHtml(block.url || '')}" /></div>
        <div class="form-row">
          <div class="form-group"><label>Text Color</label><input type="color" class="form-control prop-input" data-prop="color" value="${block.color || '#ffffff'}" style="height:38px;" /></div>
          <div class="form-group"><label>BG Color</label><input type="color" class="form-control prop-input" data-prop="bgColor" value="${block.bgColor || '#6366f1'}" style="height:38px;" /></div>
        </div>
        <div class="form-group"><label>Align</label><select class="form-control prop-input" data-prop="align">
          <option value="left" ${block.align === 'left' ? 'selected' : ''}>Left</option>
          <option value="center" ${block.align === 'center' ? 'selected' : ''}>Center</option>
          <option value="right" ${block.align === 'right' ? 'selected' : ''}>Right</option>
        </select></div>
      `;
      break;
    case 'divider':
      html += `
        <div class="form-group"><label>Color</label><input type="color" class="form-control prop-input" data-prop="color" value="${block.color || '#e5e7eb'}" style="height:38px;" /></div>
        <div class="form-group"><label>Thickness (px)</label><input type="number" class="form-control prop-input" data-prop="thickness" data-type="int" value="${block.thickness || 1}" min="1" max="10" /></div>
      `;
      break;
    case 'spacer':
      html += `
        <div class="form-group"><label>Height (px)</label><input type="number" class="form-control prop-input" data-prop="height" data-type="int" value="${block.height || 20}" min="5" max="200" /></div>
      `;
      break;
    case 'social':
      html += '<div id="social-networks">';
      (block.networks || []).forEach((n, i) => {
        html += `
          <div class="form-row mb-1 social-row" data-index="${i}">
            <div class="form-group"><input type="text" class="form-control social-name" value="${escapeHtml(n.name)}" placeholder="Network" /></div>
            <div class="form-group"><input type="url" class="form-control social-url" value="${escapeHtml(n.url)}" placeholder="URL" /></div>
            <button class="btn btn-sm btn-ghost remove-social" data-index="${i}">✕</button>
          </div>
        `;
      });
      html += '</div><button class="btn btn-sm btn-secondary mt-1" id="add-social">+ Add Network</button>';
      break;
    case 'html':
      html += `
        <div class="form-group"><label>HTML Content</label><textarea class="form-control prop-input" data-prop="content" rows="8" style="font-family:monospace;font-size:12px;">${escapeHtml(block.content || '')}</textarea></div>
      `;
      break;
  }

  $('#props-content').html(html);

  // Bind property changes
  $('.prop-input').on('input change', function () {
    const prop = $(this).data('prop');
    let val = $(this).val();
    if ($(this).data('type') === 'int') val = parseInt(val) || 0;
    blocks[selectedBlockIndex][prop] = val;
    renderBlocks();
  });

  // Social network handlers
  $('#add-social').on('click', () => {
    blocks[selectedBlockIndex].networks = blocks[selectedBlockIndex].networks || [];
    blocks[selectedBlockIndex].networks.push({ name: 'Website', url: '#' });
    renderProperties();
    renderBlocks();
  });

  $('.remove-social').on('click', function () {
    const i = parseInt($(this).data('index'));
    blocks[selectedBlockIndex].networks.splice(i, 1);
    renderProperties();
    renderBlocks();
  });

  $('.social-name, .social-url').on('input', function () {
    const row = $(this).closest('.social-row');
    const i = parseInt(row.data('index'));
    blocks[selectedBlockIndex].networks[i] = {
      name: row.find('.social-name').val(),
      url: row.find('.social-url').val()
    };
    renderBlocks();
  });
}

function initSortable() {
  const container = document.getElementById('blocks-container');
  if (container) {
    new Sortable(container, {
      animation: 150,
      handle: '.editor-block',
      ghostClass: 'sortable-ghost',
      onEnd: (evt) => {
        const item = blocks.splice(evt.oldIndex, 1)[0];
        blocks.splice(evt.newIndex, 0, item);
        selectedBlockIndex = evt.newIndex;
        renderBlocks();
        renderProperties();
      }
    });
  }
}

async function saveTemplate() {
  try {
    await api.put(`/templates/${template.id}`, {
      name: $('#tpl-name').val(),
      subject: $('#tpl-subject').val(),
      content: blocks
    });
    showToast('Template saved!');
  } catch (err) {
    showToast(err.message, 'error');
  }
}

async function previewTemplate() {
  try {
    const resp = await api.post(`/templates/${template.id}/preview`);
    const htmlContent = resp?.data?.html || resp?.html || '<p>No content</p>';

    const $modal = $(`
      <div class="modal-overlay">
        <div class="modal modal-lg">
          <div class="modal-header">
            <h3>📧 Email Preview</h3>
            <button class="modal-close">&times;</button>
          </div>
          <div class="modal-body">
            <div class="preview-frame">
              <iframe id="preview-iframe" sandbox="allow-same-origin"></iframe>
            </div>
          </div>
          <div class="modal-footer"><button class="btn btn-secondary modal-close-btn">Close</button></div>
        </div>
      </div>
    `);

    $('body').append($modal);
    $modal.find('.modal-close, .modal-close-btn').on('click', () => $modal.remove());
    $modal.on('click', (e) => { if ($(e.target).hasClass('modal-overlay')) $modal.remove(); });

    setTimeout(() => {
      const iframe = document.getElementById('preview-iframe');
      if (iframe) {
        const doc = iframe.contentDocument || iframe.contentWindow.document;
        doc.open();
        doc.write(htmlContent);
        doc.close();
        iframe.style.height = Math.max(doc.body.scrollHeight + 30, 400) + 'px';
      }
    }, 100);
  } catch (err) { showToast(err.message, 'error'); }
}

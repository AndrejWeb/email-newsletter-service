import $ from 'jquery';

export function showToast(message, type = 'success') {
  let $container = $('.toast-container');
  if (!$container.length) {
    $container = $('<div class="toast-container"></div>').appendTo('body');
  }

  const $toast = $(`<div class="toast ${type}">${escapeHtml(message)}</div>`);
  $container.append($toast);

  setTimeout(() => {
    $toast.css({ opacity: 0, transition: 'opacity 0.3s' });
    setTimeout(() => $toast.remove(), 300);
  }, 3500);
}

export function showModal(options) {
  const { title, content, size = '', footer = '' } = options;
  const sizeClass = size ? `modal-${size}` : '';

  const $overlay = $(`
    <div class="modal-overlay">
      <div class="modal ${sizeClass}">
        <div class="modal-header">
          <h3>${escapeHtml(title)}</h3>
          <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">${content}</div>
        ${footer ? `<div class="modal-footer">${footer}</div>` : ''}
      </div>
    </div>
  `);

  $('body').append($overlay);

  $overlay.find('.modal-close').on('click', () => $overlay.remove());
  $overlay.on('click', (e) => {
    if ($(e.target).hasClass('modal-overlay')) $overlay.remove();
  });

  return $overlay;
}

export function showConfirm(title, message, onConfirm, confirmText = 'Confirm', danger = false) {
  const $overlay = $(`
    <div class="modal-overlay">
      <div class="modal confirm-dialog">
        <div class="modal-body">
          <div class="icon">${danger ? '⚠️' : '❓'}</div>
          <h3>${escapeHtml(title)}</h3>
          <p>${escapeHtml(message)}</p>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary cancel-btn">Cancel</button>
          <button class="btn ${danger ? 'btn-danger' : 'btn-primary'} confirm-btn">${escapeHtml(confirmText)}</button>
        </div>
      </div>
    </div>
  `);

  $('body').append($overlay);

  $overlay.find('.cancel-btn').on('click', () => $overlay.remove());
  $overlay.find('.confirm-btn').on('click', () => {
    $overlay.remove();
    onConfirm();
  });
  $overlay.on('click', (e) => {
    if ($(e.target).hasClass('modal-overlay')) $overlay.remove();
  });
}

export function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = String(str);
  return div.innerHTML;
}

export function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

export function formatDateTime(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit'
  });
}

export function formatNumber(num) {
  if (num === null || num === undefined) return '0';
  return Number(num).toLocaleString();
}

export function formatPercent(num) {
  if (num === null || num === undefined) return '0%';
  return Number(num).toFixed(1) + '%';
}

export function campaignStatusBadge(status) {
  const labels = {
    draft: 'Draft', scheduled: 'Scheduled', sending: 'Sending',
    sent: 'Sent', cancelled: 'Cancelled'
  };
  return `<span class="badge status-${status}">${labels[status] || status}</span>`;
}

export function subscriberStatusBadge(status) {
  const labels = {
    active: 'Active', unsubscribed: 'Unsubscribed',
    bounced: 'Bounced', complained: 'Complained'
  };
  return `<span class="badge sub-${status}">${labels[status] || status}</span>`;
}

export function renderPagination(page, total, limit, onChange) {
  const totalPages = Math.ceil(total / limit);
  if (totalPages <= 1) return '';

  let html = '<div class="pagination">';
  html += `<button ${page <= 1 ? 'disabled' : ''} data-page="${page - 1}">← Prev</button>`;

  const start = Math.max(1, page - 2);
  const end = Math.min(totalPages, page + 2);

  if (start > 1) {
    html += `<button data-page="1">1</button>`;
    if (start > 2) html += `<span class="page-info">...</span>`;
  }

  for (let i = start; i <= end; i++) {
    html += `<button data-page="${i}" class="${i === page ? 'active' : ''}">${i}</button>`;
  }

  if (end < totalPages) {
    if (end < totalPages - 1) html += `<span class="page-info">...</span>`;
    html += `<button data-page="${totalPages}">${totalPages}</button>`;
  }

  html += `<button ${page >= totalPages ? 'disabled' : ''} data-page="${page + 1}">Next →</button>`;
  html += '</div>';

  setTimeout(() => {
    $('.pagination button[data-page]').off('click').on('click', function () {
      const p = parseInt($(this).data('page'));
      if (p >= 1 && p <= totalPages) onChange(p);
    });
  }, 0);

  return html;
}

export function renderLoading() {
  return '<div class="loading-overlay"><div class="spinner"></div><span>Loading...</span></div>';
}

export function debounce(fn, delay = 300) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(this, args), delay);
  };
}

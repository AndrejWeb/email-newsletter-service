import $ from 'jquery';
import api from '../utils/api.js';
import { renderSidebar, initSidebar } from '../components/sidebar.js';
import { showToast, escapeHtml } from '../utils/helpers.js';

export default async function settingsPage(container) {
  const user = api.getUser();

  $(container).html(`
    <div class="app-layout">
      ${renderSidebar()}
      <main class="main-content">
        <div class="page-header">
          <div>
            <h1>⚙️ Settings</h1>
            <div class="subtitle">Manage your account</div>
          </div>
        </div>

        <div class="grid-2">
          <div class="card">
            <div class="card-header"><h3>👤 Profile</h3></div>
            <div class="card-body">
              <div class="form-group">
                <label>Name</label>
                <input type="text" class="form-control" id="settings-name" value="${escapeHtml(user?.name || '')}" />
              </div>
              <div class="form-group">
                <label>Email</label>
                <input type="email" class="form-control" id="settings-email" value="${escapeHtml(user?.email || '')}" disabled />
                <div class="form-hint">Email cannot be changed</div>
              </div>
              <div class="form-group">
                <label>Role</label>
                <input type="text" class="form-control" value="${escapeHtml(user?.role || 'user')}" disabled />
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><h3>🔒 Change Password</h3></div>
            <div class="card-body">
              <div class="form-group">
                <label>Current Password</label>
                <input type="password" class="form-control" id="current-password" />
              </div>
              <div class="form-group">
                <label>New Password</label>
                <input type="password" class="form-control" id="new-password" />
              </div>
              <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" class="form-control" id="confirm-password" />
              </div>
              <button class="btn btn-primary" id="change-pwd-btn">🔒 Update Password</button>
            </div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header"><h3>📧 Default Sender Settings</h3></div>
          <div class="card-body">
            <div class="form-row">
              <div class="form-group">
                <label>Default From Name</label>
                <input type="text" class="form-control" value="Newsletter" disabled />
              </div>
              <div class="form-group">
                <label>Default From Email</label>
                <input type="email" class="form-control" value="newsletter@example.com" disabled />
              </div>
            </div>
            <div class="form-hint">Sender settings are configured per-campaign</div>
          </div>
        </div>

        <div class="card mt-3">
          <div class="card-header"><h3>ℹ️ About</h3></div>
          <div class="card-body">
            <p class="text-sm text-muted">
              <strong>Email Newsletter Service</strong> v1.0.0<br>
              Built with Symfony 7, PHP 8.4, PostgreSQL 16, jQuery 4, Vite 8<br>
              <br>
              A tool to manage subscriber lists, design email templates, schedule campaigns, and track open/click rates.
            </p>
          </div>
        </div>
      </main>
    </div>
  `);
  initSidebar();

  $('#change-pwd-btn').on('click', () => {
    const newPwd = $('#new-password').val();
    const confirmPwd = $('#confirm-password').val();

    if (!newPwd) { showToast('Enter new password', 'warning'); return; }
    if (newPwd !== confirmPwd) { showToast('Passwords don\'t match', 'error'); return; }

    showToast('Password update feature coming soon', 'info');
  });
}

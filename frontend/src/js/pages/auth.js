import $ from 'jquery';
import api from '../utils/api.js';
import { navigate } from '../utils/router.js';
import { showToast, escapeHtml } from '../utils/helpers.js';

export default function authPage(container) {
  const isLogin = !window.location.hash.includes('register');

  $(container).html(`
    <div class="auth-layout">
      <div class="auth-card">
        <div class="auth-logo">
          <div class="icon">✉️</div>
          <h2>${isLogin ? 'Welcome Back' : 'Create Account'}</h2>
          <p>${isLogin ? 'Sign in to your newsletter dashboard' : 'Set up your newsletter management account'}</p>
        </div>

        <form id="auth-form">
          ${!isLogin ? `
            <div class="form-group">
              <label>Full Name</label>
              <input type="text" class="form-control" id="auth-name" placeholder="Your name" required />
            </div>
          ` : ''}

          <div class="form-group">
            <label>Email Address</label>
            <input type="email" class="form-control" id="auth-email" placeholder="admin@newsletter.app" required />
          </div>

          <div class="form-group">
            <label>Password</label>
            <input type="password" class="form-control" id="auth-password" placeholder="••••••••" required />
          </div>

          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:8px;">
            ${isLogin ? '🔐 Sign In' : '🚀 Create Account'}
          </button>
        </form>

        <div style="text-align:center;margin-top:20px;font-size:14px;color:var(--gray-500);">
          ${isLogin
            ? 'Don\'t have an account? <a href="#/register">Register</a>'
            : 'Already have an account? <a href="#/login">Sign in</a>'
          }
        </div>

        ${isLogin ? `
          <div style="margin-top:20px;padding:16px;background:var(--gray-50);border-radius:8px;font-size:13px;color:var(--gray-600);">
            <strong>Demo Accounts:</strong><br>
            Admin: admin@newsletter.app / password123<br>
            Editor: editor@newsletter.app / password123
          </div>
        ` : ''}
      </div>
    </div>
  `);

  $('#auth-form').on('submit', async (e) => {
    e.preventDefault();
    const $btn = $('#auth-form button[type="submit"]');
    $btn.prop('disabled', true).html('<div class="spinner" style="width:18px;height:18px;margin:0 auto;"></div>');

    try {
      if (isLogin) {
        const resp = await api.post('/auth/login', {
          email: $('#auth-email').val(),
          password: $('#auth-password').val()
        });
        const token = resp?.data?.token || resp?.token;
        if (!token) throw new Error('No token received');
        api.setToken(token);

        const meResp = await api.get('/auth/me');
        api.setUser(meResp?.data || meResp);

        showToast('Welcome back!', 'success');
        navigate('/dashboard');
      } else {
        await api.post('/auth/register', {
          name: $('#auth-name').val(),
          email: $('#auth-email').val(),
          password: $('#auth-password').val()
        });
        showToast('Account created! Please sign in.', 'success');
        navigate('/login');
      }
    } catch (err) {
      showToast(err.message, 'error');
      $btn.prop('disabled', false).text(isLogin ? '🔐 Sign In' : '🚀 Create Account');
    }
  });
}

/**
 * Personal Notes — main application logic
 * Requirements: 1-9, 13, 14
 */

/* ── State ─────────────────────────────────────────────────────────────── */
let currentNoteId = null;   // note being edited (null = new)
let searchDebounce = null;  // debounce timer for search input
let allTags = [];           // cached tag list for the filter dropdown

/* ── Bootstrap modal instance ──────────────────────────────────────────── */
let noteModal;

/* ── DOM helpers ────────────────────────────────────────────────────────── */
const $  = (id) => document.getElementById(id);
const show = (id) => { const el = $(id); if (el) el.style.display = ''; };
const hide = (id) => { const el = $(id); if (el) el.style.display = 'none'; };
const addClass    = (id, cls) => $(id)?.classList.add(cls);
const removeClass = (id, cls) => $(id)?.classList.remove(cls);

function showError(id, msg) {
  const el = $(id);
  if (!el) return;
  el.textContent = msg;
  removeClass(id, 'd-none');
}
function clearError(id) { addClass(id, 'd-none'); }

function setLoading(spinnerId, btnId, loading) {
  const spinner = $(spinnerId);
  const btn     = $(btnId);
  if (spinner) spinner.classList.toggle('d-none', !loading);
  if (btn)     btn.disabled = loading;
}

/* ── Init ───────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async () => {
  noteModal = new bootstrap.Modal($('note-modal'));

  // Restore session from a previous page load if token is in sessionStorage
  const savedToken = sessionStorage.getItem('session_token');
  const savedEmail = sessionStorage.getItem('user_email');
  if (savedToken) {
    Api.setToken(savedToken);
    try {
      const res = await Api.checkSession();
      $('nav-user-email').textContent = res.data.email || savedEmail || '';
      showApp();
    } catch {
      // Session expired — clear and show login
      sessionStorage.removeItem('session_token');
      sessionStorage.removeItem('user_email');
      Api.clearToken();
      showAuth();
    }
  } else {
    showAuth();
  }

  // Auth toggles
  $('link-to-register').addEventListener('click', (e) => {
    e.preventDefault();
    hide('login-form');
    show('register-form');
  });
  $('link-to-login').addEventListener('click', (e) => {
    e.preventDefault();
    hide('register-form');
    show('login-form');
  });

  // Auth actions
  $('btn-login').addEventListener('click', handleLogin);
  $('btn-register').addEventListener('click', handleRegister);
  $('btn-logout').addEventListener('click', handleLogout);

  // Note actions
  $('btn-new-note').addEventListener('click', openNewNote);
  $('btn-save-note').addEventListener('click', handleSaveNote);
  $('btn-delete-note').addEventListener('click', handleDeleteNote);

  // Character counters
  $('note-title').addEventListener('input', () => {
    $('title-count').textContent = $('note-title').value.length;
  });
  $('note-content').addEventListener('input', () => {
    $('content-count').textContent = $('note-content').value.length;
  });

  // Search — debounced
  $('search-input').addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(loadNotes, 350);
  });

  // Tag filter
  $('tag-filter').addEventListener('change', loadNotes);
});

/* ── Authentication ─────────────────────────────────────────────────────── */
async function handleLogin() {
  clearError('login-error');
  const email    = $('login-email').value.trim();
  const password = $('login-password').value;

  if (!email || !password) {
    showError('login-error', 'Email and password are required.');
    return;
  }

  setLoading('login-spinner', 'btn-login', true);
  try {
    const res = await Api.login(email, password);
    Api.setToken(res.data.session_token);
    sessionStorage.setItem('session_token', res.data.session_token);
    sessionStorage.setItem('user_email', res.data.email);
    $('nav-user-email').textContent = res.data.email;
    showApp();
  } catch (err) {
    showError('login-error', err.message);
  } finally {
    setLoading('login-spinner', 'btn-login', false);
  }
}

async function handleRegister() {
  clearError('register-error');
  const email    = $('reg-email').value.trim();
  const password = $('reg-password').value;
  const confirm  = $('reg-password-confirm').value;

  if (!email || !password || !confirm) {
    showError('register-error', 'All fields are required.');
    return;
  }

  setLoading('register-spinner', 'btn-register', true);
  try {
    await Api.register(email, password, confirm);
    // Auto-login after registration
    const res = await Api.login(email, password);
    Api.setToken(res.data.session_token);
    sessionStorage.setItem('session_token', res.data.session_token);
    sessionStorage.setItem('user_email', res.data.email);
    $('nav-user-email').textContent = res.data.email;
    showApp();
  } catch (err) {
    showError('register-error', err.message);
  } finally {
    setLoading('register-spinner', 'btn-register', false);
  }
}

async function handleLogout() {
  try { await Api.logout(); } catch { /* ignore */ }
  Api.clearToken();
  sessionStorage.removeItem('session_token');
  sessionStorage.removeItem('user_email');
  showAuth();
}

/* ── App / Auth visibility ──────────────────────────────────────────────── */
function showApp() {
  hide('auth-section');
  show('main-nav');
  $('main-nav').style.display = 'flex';
  show('app-section');
  loadNotes();
  loadTagsDropdown();
}

function showAuth() {
  hide('app-section');
  hide('main-nav');
  show('auth-section');
  show('login-form');
  hide('register-form');
  $('login-email').value    = '';
  $('login-password').value = '';
}

/* ── Notes list ─────────────────────────────────────────────────────────── */
async function loadNotes() {
  const q   = $('search-input').value.trim();
  const tag = $('tag-filter').value;

  addClass('notes-loading', 'd-none');  // reset
  addClass('notes-empty',   'd-none');
  $('notes-grid').innerHTML = '';
  removeClass('notes-loading', 'd-none');

  try {
    const params = {};
    if (q)   params.q   = q;
    if (tag) params.tag = tag;

    const res   = await Api.getNotes(params);
    const notes = res.data?.notes ?? [];

    addClass('notes-loading', 'd-none');

    if (notes.length === 0) {
      removeClass('notes-empty', 'd-none');
      return;
    }

    $('notes-grid').innerHTML = notes.map(renderNoteCard).join('');

    // Attach click handlers
    document.querySelectorAll('.note-card').forEach((card) => {
      card.addEventListener('click', () => openEditNote(parseInt(card.dataset.id, 10)));
    });
  } catch (err) {
    addClass('notes-loading', 'd-none');
    showGlobalError(err.message);
  }
}

function renderNoteCard(note) {
  const tags = (note.tags || [])
    .map((t) => `<span class="badge bg-secondary tag-badge">${escHtml(t)}</span>`)
    .join('');
  return `
    <div class="col-sm-6 col-md-4 col-lg-3">
      <div class="card note-card h-100" data-id="${note.note_id}">
        <div class="card-body">
          <h6 class="card-title">${escHtml(note.title)}</h6>
          <p class="card-text">${escHtml(note.content)}</p>
        </div>
        <div class="card-footer bg-transparent border-0 pb-2">${tags}</div>
      </div>
    </div>`;
}

/* ── Tag dropdown ───────────────────────────────────────────────────────── */
async function loadTagsDropdown() {
  try {
    const res = await Api.getTags();
    allTags   = res.data?.tags ?? [];
    const sel = $('tag-filter');
    sel.innerHTML = '<option value="">All tags</option>' +
      allTags.map((t) => `<option value="${escHtml(t.name)}">${escHtml(t.name)}</option>`).join('');
  } catch { /* non-critical */ }
}

/* ── Note editor ────────────────────────────────────────────────────────── */
function openNewNote() {
  currentNoteId = null;
  $('note-modal-label').textContent = 'New Note';
  $('note-title').value   = '';
  $('note-content').value = '';
  $('note-tags').value    = '';
  $('title-count').textContent   = '0';
  $('content-count').textContent = '0';
  clearError('note-error');
  addClass('btn-delete-note', 'd-none');
  noteModal.show();
}

async function openEditNote(id) {
  currentNoteId = id;
  clearError('note-error');
  try {
    const res  = await Api.getNote(id);
    const note = res.data;
    $('note-modal-label').textContent = 'Edit Note';
    $('note-title').value   = note.title;
    $('note-content').value = note.content;
    $('note-tags').value    = (note.tags || []).join(', ');
    $('title-count').textContent   = note.title.length;
    $('content-count').textContent = note.content.length;
    removeClass('btn-delete-note', 'd-none');
    noteModal.show();
  } catch (err) {
    showGlobalError(err.message);
  }
}

async function handleSaveNote() {
  clearError('note-error');
  const title   = $('note-title').value.trim();
  const content = $('note-content').value.trim();
  const tags    = $('note-tags').value
    .split(',')
    .map((t) => t.trim())
    .filter(Boolean);

  // Client-side validation
  if (!title)              { showError('note-error', 'Title is required.');                    return; }
  if (title.length > 200)  { showError('note-error', 'Title must be 200 characters or less.'); return; }
  if (!content)            { showError('note-error', 'Content is required.');                  return; }
  if (content.length > 10000) { showError('note-error', 'Content must be 10 000 characters or less.'); return; }

  setLoading('save-spinner', 'btn-save-note', true);
  try {
    if (currentNoteId) {
      await Api.updateNote(currentNoteId, title, content, tags);
    } else {
      await Api.createNote(title, content, tags);
    }
    noteModal.hide();
    await loadNotes();
    await loadTagsDropdown();
  } catch (err) {
    showError('note-error', err.message);
  } finally {
    setLoading('save-spinner', 'btn-save-note', false);
  }
}

async function handleDeleteNote() {
  if (!currentNoteId) return;
  if (!confirm('Delete this note?')) return;

  try {
    await Api.deleteNote(currentNoteId);
    noteModal.hide();
    await loadNotes();
    await loadTagsDropdown();
  } catch (err) {
    showError('note-error', err.message);
  }
}

/* ── Helpers ────────────────────────────────────────────────────────────── */
function escHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function showGlobalError(msg) {
  // Simple toast-style alert appended to body
  const div = document.createElement('div');
  div.className = 'alert alert-danger alert-dismissible position-fixed bottom-0 end-0 m-3';
  div.style.zIndex = 9999;
  div.innerHTML = `${escHtml(msg)}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
  document.body.appendChild(div);
  setTimeout(() => div.remove(), 5000);
}

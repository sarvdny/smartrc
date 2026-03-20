<?php /* shared CSS — include inside <style> tags */ ?>
@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400;500&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
--bg: #0a0c0f;
--surface: #0f1318;
--panel: #111720;
--border: #1c2330;
--border-hi: #263040;
--accent: #c8a84b;
--accent-dk: #8b6914;
--text: #d4dbe6;
--muted: #4a5568;
--danger: #c0392b;
--success: #27ae60;
--warning: #e67e22;
--mono: 'IBM Plex Mono', monospace;
--sans: 'IBM Plex Sans', sans-serif;
}

html, body {
background: var(--bg);
color: var(--text);
font-family: var(--sans);
font-size: 14px;
min-height: 100vh;
}

a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }

/* ── TOP BAR ── */
.top-bar {
height: 3px;
background: linear-gradient(90deg, var(--accent), var(--accent-dk));
position: fixed; top: 0; left: 0; right: 0; z-index: 100;
}

/* ── HEADER ── */
.site-header {
position: fixed; top: 3px; left: 0; right: 0; z-index: 99;
background: var(--surface);
border-bottom: 1px solid var(--border);
height: 56px;
display: flex; align-items: center;
padding: 0 32px;
gap: 24px;
}
.site-header .logo {
font-family: var(--mono);
font-size: 11px; letter-spacing: .16em;
text-transform: uppercase; color: var(--accent);
display: flex; align-items: center; gap: 10px;
white-space: nowrap;
}
.site-header .logo::before {
content: '';
width: 20px; height: 1px;
background: var(--accent);
}
.header-nav {
display: flex; align-items: center; gap: 4px;
margin-left: auto;
}
.header-nav a {
font-family: var(--mono); font-size: 11px;
letter-spacing: .1em; text-transform: uppercase;
color: var(--muted); padding: 6px 14px;
border: 1px solid transparent; border-radius: 2px;
transition: color .15s, border-color .15s;
text-decoration: none;
}
.header-nav a:hover,
.header-nav a.active {
color: var(--accent);
border-color: var(--border-hi);
}
.header-user {
font-family: var(--mono); font-size: 11px;
color: var(--muted); padding-left: 16px;
border-left: 1px solid var(--border);
letter-spacing: .06em;
white-space: nowrap;
}
.header-user span { color: var(--accent); }

/* ── PAGE BODY ── */
.page-body {
padding-top: 75px; /* header offset */
min-height: 100vh;
}
.container {
max-width: 1240px;
margin: 0 auto;
padding: 32px 32px;
}

/* ── PAGE TITLE ── */
.page-title {
font-family: var(--mono); font-size: 10px;
letter-spacing: .22em; text-transform: uppercase;
color: var(--accent); margin-bottom: 8px;
display: flex; align-items: center; gap: 10px;
}
.page-title::before { content: ''; width: 20px; height: 1px; background: var(--accent); }
.page-heading {
font-size: 22px; font-weight: 600;
letter-spacing: -.01em; color: var(--text);
margin-bottom: 28px;
}

/* ── CARDS ── */
.card {
background: var(--surface);
border: 1px solid var(--border);
padding: 24px 28px;
}
.card + .card { margin-top: 20px; }
.card-title {
font-family: var(--mono); font-size: 10px;
letter-spacing: .18em; text-transform: uppercase;
color: var(--muted); margin-bottom: 18px;
padding-bottom: 12px;
border-bottom: 1px solid var(--border);
}

/* ── STAT CARDS ── */
.stats-grid {
display: grid;
grid-template-columns: repeat(4, 1fr);
gap: 16px; margin-bottom: 28px;
}
.stat-card {
background: var(--surface);
border: 1px solid var(--border);
padding: 20px 22px;
}
.stat-card .stat-label {
font-family: var(--mono); font-size: 10px;
letter-spacing: .14em; text-transform: uppercase;
color: var(--muted); margin-bottom: 10px;
}
.stat-card .stat-value {
font-family: var(--mono); font-size: 28px;
font-weight: 500; color: var(--accent);
line-height: 1;
}
.stat-card .stat-sub {
font-family: var(--mono); font-size: 10px;
color: var(--muted); margin-top: 6px;
}

/* ── SEARCH BAR ── */
.search-bar {
display: flex; gap: 0;
margin-bottom: 24px;
}
.search-bar input {
flex: 1;
background: var(--surface);
border: 1px solid var(--border-hi);
border-right: none;
padding: 11px 16px;
font-family: var(--mono); font-size: 13px;
color: var(--text); outline: none;
caret-color: var(--accent);
transition: border-color .15s;
}
.search-bar input:focus { border-color: var(--accent); }
.search-bar input::placeholder { color: var(--muted); opacity: .6; }
.search-bar button {
padding: 11px 22px;
background: var(--accent); border: 1px solid var(--accent);
font-family: var(--mono); font-size: 11px;
letter-spacing: .14em; text-transform: uppercase;
color: #0a0c0f; cursor: pointer;
transition: background .15s;
white-space: nowrap;
}
.search-bar button:hover { background: #d9b95c; }

/* ── TABLE ── */
.data-table {
width: 100%; border-collapse: collapse;
}
.data-table th {
font-family: var(--mono); font-size: 10px;
letter-spacing: .14em; text-transform: uppercase;
color: var(--muted); text-align: left;
padding: 10px 14px;
border-bottom: 1px solid var(--border);
white-space: nowrap;
}
.data-table td {
padding: 12px 14px;
border-bottom: 1px solid var(--border);
font-size: 13px; color: var(--text);
vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: rgba(200,168,75,.03); }
.data-table .rc-no {
font-family: var(--mono); color: var(--accent);
letter-spacing: .06em;
}

/* ── BADGES ── */
.badge {
font-family: var(--mono); font-size: 10px;
letter-spacing: .1em; text-transform: uppercase;
padding: 3px 8px; border-radius: 2px;
display: inline-block;
}
.badge-ok { background: rgba(39,174,96,.1); color: #27ae60; border: 1px solid rgba(39,174,96,.3); }
.badge-warn { background: rgba(230,126,34,.1); color: #e67e22; border: 1px solid rgba(230,126,34,.3); }
.badge-danger { background: rgba(192,57,43,.1); color: #c0392b; border: 1px solid rgba(192,57,43,.3); }

/* ── DETAIL GRID ── */
.detail-grid {
display: grid;
grid-template-columns: repeat(3, 1fr);
gap: 16px 24px;
}
.detail-item .dl { font-family: var(--mono); font-size: 10px; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); margin-bottom: 5px; }
.detail-item .dv { font-size: 13px; color: var(--text); }
.detail-item .dv.mono { font-family: var(--mono); }
.detail-item .dv.accent { color: var(--accent); font-family: var(--mono); }

/* ── FORM ── */
.form-grid {
display: grid;
grid-template-columns: repeat(3, 1fr);
gap: 16px 24px;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.field label {
font-family: var(--mono); font-size: 10px;
letter-spacing: .14em; text-transform: uppercase;
color: var(--muted);
}
.field input, .field select, .field textarea {
background: var(--bg);
border: 1px solid var(--border);
border-bottom-color: var(--border-hi);
padding: 10px 12px;
font-family: var(--mono); font-size: 13px;
color: var(--text); outline: none;
transition: border-color .15s, box-shadow .15s;
caret-color: var(--accent);
}
.field input:focus, .field select:focus, .field textarea:focus {
border-color: var(--accent);
box-shadow: 0 2px 0 0 var(--accent);
}
.field input[readonly] {
color: var(--muted); cursor: not-allowed;
background: var(--panel);
}
.field select option { background: var(--surface); }
.field textarea { resize: vertical; min-height: 72px; }
.field .field-note { font-family: var(--mono); font-size: 10px; color: var(--muted); margin-top: 2px; }

/* ── BUTTONS ── */
.btn {
padding: 10px 20px;
font-family: var(--mono); font-size: 11px;
letter-spacing: .14em; text-transform: uppercase;
cursor: pointer; border: none; transition: background .15s, opacity .15s;
display: inline-flex; align-items: center; gap: 8px;
}
.btn-primary { background: var(--accent); color: #0a0c0f; }
.btn-primary:hover { background: #d9b95c; }
.btn-ghost {
background: transparent; color: var(--muted);
border: 1px solid var(--border-hi);
}
.btn-ghost:hover { color: var(--text); border-color: var(--accent); }
.btn-danger-ghost { background: transparent; color: var(--danger); border: 1px solid rgba(192,57,43,.3); }
.btn-danger-ghost:hover { background: rgba(192,57,43,.08); }
.btn:disabled { opacity: .4; cursor: not-allowed; }

/* ── ALERT ── */
.alert {
padding: 12px 16px;
font-family: var(--mono); font-size: 12px;
letter-spacing: .04em; margin-bottom: 20px;
}
.alert-success { border-left: 3px solid var(--success); background: rgba(39,174,96,.07); color: var(--success); }
.alert-error { border-left: 3px solid var(--danger); background: rgba(192,57,43,.07); color: var(--danger); }
.alert-warn { border-left: 3px solid var(--warning); background: rgba(230,126,34,.07); color: var(--warning); }

/* ── SECTION DIVIDER ── */
.section-label {
font-family: var(--mono); font-size: 10px;
letter-spacing: .2em; text-transform: uppercase;
color: var(--accent); margin-bottom: 16px;
display: flex; align-items: center; gap: 10px;
}
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── TABS ── */
.tabs { display: flex; gap: 0; border-bottom: 1px solid var(--border); margin-bottom: 24px; }
.tab-btn {
font-family: var(--mono); font-size: 11px;
letter-spacing: .12em; text-transform: uppercase;
padding: 10px 20px; background: none; border: none;
color: var(--muted); cursor: pointer;
border-bottom: 2px solid transparent; margin-bottom: -1px;
transition: color .15s;
}
.tab-btn:hover { color: var(--text); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); }
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* ── FOOTER ── */
.site-footer {
border-top: 1px solid var(--border);
padding: 16px 32px;
font-family: var(--mono); font-size: 10px;
color: var(--muted); letter-spacing: .08em;
display: flex; justify-content: space-between; align-items: center;
margin-top: 48px;
}

/* ── MISC ── */
.flex { display: flex; }
.gap-12 { gap: 12px; }
.gap-16 { gap: 16px; }
.mt-16 { margin-top: 16px; }
.mt-24 { margin-top: 24px; }
.mt-32 { margin-top: 32px; }
.text-muted { color: var(--muted); font-family: var(--mono); font-size: 12px; }
.text-right { text-align: right; }
let links = [];
let chartInstance = null;
const passwordToggle = document.getElementById('pwToggle');
const passwordInput = document.getElementById('linkPass');
const searchInput = document.getElementById('search');
let slugCheckSequence = 0;
passwordToggle.addEventListener('change', () => {
  passwordInput.hidden = !passwordToggle.checked;
  if (passwordToggle.checked) passwordInput.focus();
});
document.getElementById('customSlug').addEventListener('input', async event => {
  const slug = event.target.value.trim();
  const status = document.getElementById('slugStatus');
  const checkSequence = ++slugCheckSequence;
  if (!slug) {
    status.textContent = '';
    return;
  }
  status.textContent = 'Checking availability...';
  try {
    const response = await fetch(`${API}/?action=check-slug&slug=${encodeURIComponent(slug)}`, { credentials: 'include' });
    const data = await response.json();
    if (checkSequence !== slugCheckSequence) return;
    if (!response.ok || data.error) throw new Error(data.error || 'Could not check slug availability');
    status.textContent = data.available ? 'Available' : 'Already taken';
    status.style.color = data.available ? '#c8fa67' : '#ff9a8f';
  } catch (error) {
    if (checkSequence !== slugCheckSequence) return;
    status.textContent = 'Could not check availability';
    status.style.color = '#ff9a8f';
  }
});
document.getElementById('createBtn').addEventListener('click', async () => {
  const longUrl = document.getElementById('longUrl').value.trim();
  const customSlug = document.getElementById('customSlug').value.trim();
  const expiry = document.getElementById('expiry').value;
  const password = passwordToggle.checked ? passwordInput.value : '';
  if (!longUrl) {
    toast('Add a link before shortening it', 'error');
    document.getElementById('longUrl').focus();
    return;
  }
  const button = document.getElementById('createBtn');
  button.disabled = true;
  button.innerHTML = 'Making it brief…';
  try {
    const response = await api('create', { longUrl, customSlug, expiry, password });
    if (response.error) {
      toast(response.error, 'error');
      return;
    }
    const shortUrl = `${API}/?s=${encodeURIComponent(response.slug)}`;
    const result = document.getElementById('result');
    result.replaceChildren();
    const link = document.createElement('div');
    link.className = 'result-link';
    const slug = document.createElement('strong');
    slug.textContent = `lnkb.rf/${response.slug}`;
    const destination = document.createElement('span');
    destination.textContent = longUrl;
    link.append(slug, destination);
    const actions = document.createElement('div');
    actions.className = 'result-actions';
    const copyButton = document.createElement('button');
    copyButton.type = 'button';
    copyButton.textContent = 'Copy link';
    copyButton.addEventListener('click', () => copyText(shortUrl));
    const openLink = document.createElement('a');
    openLink.href = shortUrl;
    openLink.target = '_blank';
    openLink.rel = 'noopener noreferrer';
    openLink.textContent = 'Open ↗';
    actions.append(copyButton, openLink);
    result.append(link, actions);
    result.hidden = false;
    const qr = document.getElementById('qr');
    qr.replaceChildren();
    qr.hidden = false;
    new QRCode(qr, { text: shortUrl, width: 130, height: 130 });
    await loadLinks();
    toast('Your link is ready to go!');
  } catch (error) {
    toast(error.message || 'Could not create your link', 'error');
  } finally {
    button.disabled = false;
    button.innerHTML = 'Shorten link <span aria-hidden="true">→</span>';
  }
});
function renderLinks() {
  const container = document.getElementById('links');
  const query = searchInput.value.trim().toLowerCase();
  const filteredLinks = links.filter(link =>
    `${link.slug} ${link.long_url} ${link.status}`.toLowerCase().includes(query)
  );
  if (filteredLinks.length === 0) {
    container.innerHTML = links.length
      ? '<div class="empty-state">No links match that search.</div>'
      : '<div class="empty-state">No links yet. Shorten your first one above.</div>';
    return;
  }
  container.replaceChildren(...filteredLinks.map(link => {
    const shortUrl = `${API}/?s=${encodeURIComponent(link.slug)}`;
    const row = document.createElement('article');
    row.className = 'link-row';
    const main = document.createElement('div');
    main.className = 'link-row-main';
    const meta = document.createElement('div');
    meta.className = 'link-row-meta';
    const slug = document.createElement('strong');
    slug.textContent = `lnkb.rf/${link.slug}`;
    const status = document.createElement('span');
    status.className = 'link-status';
    status.textContent = link.status;
    const clicks = document.createElement('span');
    clicks.className = 'link-clicks';
    clicks.textContent = `${Number(link.clicks).toLocaleString()} clicks`;
    const destination = document.createElement('span');
    destination.className = 'link-destination';
    destination.textContent = link.long_url;
    meta.append(slug, status, clicks);
    main.append(meta, destination);
    const actions = document.createElement('div');
    actions.className = 'link-row-actions';
    const copyButton = document.createElement('button');
    copyButton.type = 'button';
    copyButton.setAttribute('aria-label', `Copy ${link.slug}`);
    copyButton.textContent = '▢';
    copyButton.addEventListener('click', () => copyText(shortUrl));
    const openLink = document.createElement('a');
    openLink.href = shortUrl;
    openLink.target = '_blank';
    openLink.rel = 'noopener noreferrer';
    openLink.setAttribute('aria-label', `Open ${link.slug}`);
    openLink.textContent = '↗';
    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'delete-link';
    deleteButton.setAttribute('aria-label', `Delete ${link.slug}`);
    deleteButton.textContent = '×';
    deleteButton.addEventListener('click', () => deleteLink(link.id));
    actions.append(copyButton, openLink, deleteButton);
    row.append(main, actions);
    return row;
  }));
}
async function loadLinks() {
  const me = await apiGet('me');
  if (!me.user) {
    location.href = 'login.html';
    return;
  }
  document.getElementById('userEmail').textContent = me.user;
  const data = await apiGet('my-links');
  links = data.links || [];
  document.getElementById('totalLinks').textContent = data.totalLinks || 0;
  document.getElementById('totalClicks').textContent = Number(data.totalClicks || 0).toLocaleString();
  renderLinks();
  const chart = document.getElementById('clickChart');
  if (chartInstance) chartInstance.destroy();
  chartInstance = new Chart(chart, {
    type: 'line',
    data: {
      labels: data.chart?.map(day => day.d) || ['No data'],
      datasets: [{
        data: data.chart?.map(day => day.c) || [0],
        borderColor: '#c8fa67',
        backgroundColor: 'rgba(200,250,103,0.12)',
        pointBackgroundColor: '#c8fa67',
        pointBorderColor: '#191c17',
        pointRadius: 3,
        borderWidth: 2,
        tension: 0.4,
        fill: true
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#858b80', font: { size: 9 } }, border: { display: false } },
        y: { beginAtZero: true, grid: { color: '#30362c' }, ticks: { color: '#858b80', precision: 0, font: { size: 9 } }, border: { display: false } }
      }
    }
  });
}
async function deleteLink(id) {
  if (!confirm('Delete this link? This cannot be undone.')) return;
  try {
    const response = await api('delete', { id });
    if (response.error) {
      toast(response.error, 'error');
      return;
    }
    await loadLinks();
    toast('Link deleted');
  } catch (error) {
    toast(error.message || 'Could not delete the link', 'error');
  }
}
async function logout() {
  await api('logout');
  location.href = 'login.html';
}
searchInput.addEventListener('input', renderLinks);
const pendingUrl = sessionStorage.getItem('lb_pendingUrl');
if (pendingUrl) {
  document.getElementById('longUrl').value = pendingUrl;
  sessionStorage.removeItem('lb_pendingUrl');
}
loadLinks().catch(error => {
  toast(error.message || 'Could not load your dashboard', 'error');
});

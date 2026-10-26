# LinkBrief

LinkBrief is a link shortener with a static HTML/CSS/JavaScript frontend, a PHP API, and MySQL storage. The frontend and backend are in separate folders so they can be deployed independently.

## Project layout

```text
.
├── frontend/          # Static site: homepage, login, dashboard, CSS and JavaScript
│   └── config.js       # API URL used by the frontend
├── backend/           # PHP API and Apache rewrite rules
├── db.sql             # MySQL database and table definitions
├── Dockerfile         # Builds only the PHP backend
├── docker/            # Apache configuration and container entrypoint
└── render.yaml         # Render configuration for the backend
```

## Run locally with XAMPP

1. Start Apache and MySQL in XAMPP.
2. Import `db.sql` into phpMyAdmin.
3. Keep the project under `C:\xampp\htdocs\Linkbrief`.
4. Open `http://localhost/Linkbrief/frontend/index.html`.

The frontend's default API address points to the sibling `backend/` folder, so no local URL setting is needed.

## Deploy frontend and backend separately

You need a MySQL database reachable by the backend. Import `db.sql` into it, and keep its connection details private.

### 1. Deploy the backend

The root `Dockerfile` and `render.yaml` configure a Render web service for the PHP backend. Create the service from this repository and set these environment variables in its dashboard:

| Variable | Value |
| --- | --- |
| `DB_HOST` | Your database host |
| `DB_PORT` | Your database port, usually `3306` |
| `DB_NAME` | Your database name |
| `DB_USER` | Your database username |
| `DB_PASS` | Your database password |
| `FRONTEND_ORIGIN` | The exact frontend origin, e.g. `https://linkbrief.example.com` (no trailing slash) |

The backend base URL is the service URL, for example `https://linkbrief-api.onrender.com`. Its health endpoint is `/?action=health`.

### 2. Configure and deploy the frontend

Before publishing, edit `frontend/config.js` and set the backend's base URL (no path or trailing slash), for example:

```js
window.LINKBRIEF_API_URL = 'https://linkbrief-api.onrender.com';
```

Deploy the contents of `frontend/` as a static site. On Render Static Sites, set the **Root Directory** to `frontend`; no build command is required and the publish directory is `.`.

Set `FRONTEND_ORIGIN` on the backend to the frontend's exact deployed origin, then redeploy the backend if needed. The backend only accepts browser requests from that origin.

### Important: login cookies on separate hosts

Use HTTPS for both deployments. The backend configures secure cross-origin session cookies when `FRONTEND_ORIGIN` is set. Browsers may block cookies when the frontend and backend are on unrelated domains. For reliable sign-in, use custom domains on the same site, such as `app.example.com` and `api.example.com`; support for cross-site cookies still depends on browser privacy settings.

Short links are served by the backend URL with `?s=CODE`. To use a branded short-link domain, configure that domain to serve the backend's redirect endpoint.

## Features and API

- Guests can create up to 10 links per hour per PHP session; generated codes are 6 alphanumeric characters.
- Signed-in users can choose a custom code, expiration date, and optional password.
- The dashboard shows saved links, clicks, and a seven-day chart.
- PHP sessions manage account login and the guest creation limit.

The backend is `backend/index.php`. Its actions include `health`, `register`, `login`, `logout`, `me`, `create`, `check-slug`, `my-links`, `delete`, and `analytics`. Requests with `?s=CODE` resolve and redirect short links.

## Configuration and safety

The backend reads `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, and `FRONTEND_ORIGIN` from its environment. Do not commit production database credentials. `FRONTEND_ORIGIN` must exactly match the deployed frontend origin, including scheme and port when present.

## Push changes to GitHub from VS Code

1. Save your files, then open **Source Control** in VS Code (`Ctrl+Shift+G`).
2. Review the changed-file list and each diff. Stage only the files you intend to publish using the **+** beside each file (or stage a folder's files individually).
3. Enter a commit message, such as `Separate frontend and backend deployments`, and select **Commit**.
4. Select **Sync Changes** or **Push** to send the commit to GitHub. If VS Code asks to publish a branch, follow its prompt.

You can also use the integrated terminal:

```powershell
git status
git diff
git add README.md Dockerfile render.yaml docker/apache-vhost.conf frontend backend
git diff --cached
git commit -m "Separate frontend and backend deployments"
git push
```

Only run `git add` after reviewing what it stages. If this repository has no GitHub remote yet, create an empty GitHub repository, then connect it once with `git remote add origin https://github.com/YOUR-USERNAME/YOUR-REPOSITORY.git` and push your current branch with `git push -u origin HEAD`.

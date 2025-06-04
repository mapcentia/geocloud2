# MapCentia Geocloud 2 Dashboard

Progressive web-application for Geocloud 2 REST API (https://github.com/mapcentia/geocloud2)

## Installation

1. Check out the repository;
2. Create `config.js` from `config.js.sample` (fill out the API and Vidi URL);
3. Run `npm install` to install all modules;
4. Create `.env` from `.env.production` and specify the `WEBPACK_PUBLIC_PATH` (`/` if the application is served from `https://example.com/`; `/some/folder/` if the application is served from `https://example.com/some/folder/`;
5. Run `npm start` to run development version or `npm run start:production` to run production version;
6. (optional) If the application is installed in `/public/dashboard` folder, then the `/public/.htaccess` has to be populated with following;

```
...
<IfModule mod_rewrite.c>
RewriteEngine On

# Rewrite rules for React app, located in dashboard subdirectory - return
# the index.html unless the requested file / directory exists
RewriteRule ^dashboard/index\.html$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^dashboard/(.*) dashboard/index.html [L]

...
```
7. (optional) If the application is served from web root (accessible via `https://example.com/`), then `app/.htaccess` should be copied to the web root directory.

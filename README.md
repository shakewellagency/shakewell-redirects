# Shakewell Redirects

Free, open source (GPL-2.0-or-later) WordPress plugin: manage redirects in wp-admin, serve them on WordPress, and expose them read-only over REST for headless front ends. User documentation is in `readme.txt`.

## Work on it

```sh
composer install
npm ci
npm run env:start      # http://localhost:8890, needs Docker
npm test               # PHPUnit inside the WordPress test container
composer lint          # WordPress coding standards
npm run plugin-check   # the official Plugin Check
npm run env:stop
```

## Layout

- `src/Rules.php`: pure matching and validation.
- `src/Store.php`: post type, meta, cached live list, `shakewell_redirects_changed`.
- `src/Plugin.php`: front-end redirect on `template_redirect`.
- `src/Rest.php`, `src/Admin.php`, `src/Importer.php`, `src/Cli.php`.

## Release

Bump the version in the plugin header, `readme.txt` (`Stable tag`) and the `VERSION` constant, add a changelog line, tag `vX.Y.Z`.

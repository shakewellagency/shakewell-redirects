# Shakewell Redirects

> **Made by [Shakewell](https://shakewell.agency).** Free and open source, built and maintained by the Shakewell team.

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

## Made by Shakewell

This plugin is one of a family of free WordPress plugins from [Shakewell](https://shakewell.agency). Each one does one job, keeps its dependencies small and is released under GPL-2.0-or-later.

Use it, fork it and send fixes. To have it set up on your site, or to have a plugin built, [talk to Shakewell](https://shakewell.agency).

More from Shakewell:

- [Shakewell Activity Log](https://github.com/shakewellagency/shakewell-activity-log)
- [Shakewell Multiple Authors](https://github.com/shakewellagency/shakewell-authors)
- [Shakewell Clone Posts](https://github.com/shakewellagency/shakewell-clone-posts)
- [Shakewell Connector for Klaviyo and Gravity Forms](https://github.com/shakewellagency/shakewell-klaviyo-connector)
- [Shakewell Media Offload for Amazon S3](https://github.com/shakewellagency/shakewell-media-offload)
- [Shakewell Two-way Relationships for ACF](https://github.com/shakewellagency/shakewell-relationship-sync)
- [Shakewell Single Sign-On (SAML)](https://github.com/shakewellagency/shakewell-saml-sso)
- [Shakewell Error Reporting for Sentry](https://github.com/shakewellagency/shakewell-sentry)
- [Shakewell Mailer for Amazon SES](https://github.com/shakewellagency/shakewell-ses-mailer)
- [Shakewell Sitemap](https://github.com/shakewellagency/shakewell-sitemap)
- [Shakewell plugin template](https://github.com/shakewellagency/shakewell-plugin-template), the starting point for every plugin

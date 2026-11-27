# Releasing to WordPress.org

WordPress.org hosts plugins in SVN, not git:
`https://plugins.svn.wordpress.org/international-telephone-box/`

```
trunk/    current development code (the plugin files, at the root of trunk)
tags/X.Y.Z/   a frozen copy for each release
assets/   banners, icons, and screenshots for the listing page (NOT shipped in the zip)
```

WordPress.org serves the version whose `Stable tag:` in `trunk/readme.txt` matches a folder in `tags/`.

> The 1.1.5 zip shipped a nested `trunk/` folder because a whole checkout was copied into trunk.
> Never copy by hand. Use the CI deploy below.

## One-time setup

1. Get an SVN password from https://profiles.wordpress.org/abanob0adel/profile/edit/group/3/?screen=svn-password
   (WordPress.org profile → Account & Security → SVN password).
2. Add it to GitHub secrets yourself. Never paste it into a chat or commit it:
   ```sh
   gh secret set SVN_USERNAME --body abanob0adel
   gh secret set SVN_PASSWORD   # prompts; paste the SVN password
   ```

## Release steps

1. Bump the version in **three** places, and make them match:
   - `international-telephone-box.php` → `Version:` header
   - `international-telephone-box.php` → `INTELEBOX_VERSION`
   - `readme.txt` → `Stable tag:`
2. Add a `= X.Y.Z =` entry to the top of `== Changelog ==` and to `== Upgrade Notice ==` in `readme.txt`.
3. Update `Tested up to:` to the current major WordPress version (major.minor only, e.g. `7.1`).
4. Merge to `main`, then tag and push:
   ```sh
   git tag X.Y.Z && git push origin X.Y.Z
   ```
5. `.github/workflows/deploy.yml` runs `10up/action-wordpress-plugin-deploy`. It copies the repo (minus `.distignore`) into `trunk/`, creates `tags/X.Y.Z/`, syncs `.wordpress-org/` into `assets/`, and attaches a zip to a GitHub release.
6. Check https://wordpress.org/plugins/international-telephone-box/. It can take a few minutes, and the zip cache up to about an hour.

## Readme-only changes (e.g. "Tested up to")

Merge to `main`, then run the **Update WordPress.org readme and assets** workflow from the Actions tab (`gh workflow run assets.yml`). Do this only when `Stable tag` already matches a released tag.

## Manual fallback (no CI)

```sh
brew install subversion
svn co https://plugins.svn.wordpress.org/international-telephone-box svn-itb
rsync -a --delete --exclude=.svn --exclude-from=.distignore ./ svn-itb/trunk/
cd svn-itb && svn add --force trunk && svn status   # "!" lines are deletions: svn rm them
svn cp trunk tags/X.Y.Z
svn ci -m "Release X.Y.Z" --username abanob0adel
```

# Server-to-server migration (PostgreSQL + storage)

Migrate a CMS8 / Laravel site from one Forge server to another **without** using your laptop as an intermediary.

**Requirements**

- SSH access as `forge` on both servers
- The **destination** server can SSH into the **source** server (public key of destination in source `~/.ssh/authorized_keys`)
- `postgresql-client` installed on both servers (`pg_dump` / `pg_restore`)
- `rsync` available on the destination server
- Same `APP_KEY` on destination as on source (required for encrypted DB values and cookies)

**Example used in this guide**

| Role        | Host                         | Site path                     |
|-------------|------------------------------|-------------------------------|
| Source      | `geri.revisionalpha.cloud`   | `~/humano.revisionalpha.com`  |
| Destination | `freki.revisionalpha.cloud`  | `~/admin.idoneo.dev`          |

Replace hostnames and paths with yours.

---

## 0) Prerequisites

### SSH between servers

From the **destination** server:

```bash
ssh forge@SOURCE_HOST 'echo ok'
```

If this fails with `Permission denied (publickey)`, add the destination’s public key to the source’s `~/.ssh/authorized_keys`:

```bash
# On destination
cat ~/.ssh/id_ed25519.pub || cat ~/.ssh/id_rsa.pub
```

Paste that line into source `~/.ssh/authorized_keys`, then retry.

### PostgreSQL client tools

```bash
# On both servers if missing
sudo apt install postgresql-client
```

### Same application key

Copy `APP_KEY` from the source `.env` into the destination `.env` before (or right after) restoring the database. Do not regenerate the key if you need existing encrypted data to remain readable.

---

## 1) Export the database (on the source)

```bash
cd ~/SOURCE_SITE   # e.g. ~/humano.revisionalpha.com

set -a
source <(grep -E '^DB_' .env)
set +a
export PGPASSWORD="$DB_PASSWORD"

DUMP=~/db-migration-$(date +%Y%m%d).dump

pg_dump -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" -d "$DB_DATABASE" \
  --no-owner --no-acl -Fc \
  -f "$DUMP"

ls -lh "$DUMP"
```

`-Fc` produces a compressed custom-format dump suitable for `pg_restore`.

---

## 2) Copy the dump to the destination (from the destination)

Pull the file from the source (run this on the **destination** server):

```bash
scp forge@SOURCE_HOST:~/db-migration-YYYYMMDD.dump ~/
ls -lh ~/db-migration-*.dump
```

Or with `rsync`:

```bash
rsync -avz --progress \
  forge@SOURCE_HOST:~/db-migration-YYYYMMDD.dump \
  ~/
```

---

## 3) Restore the database (on the destination)

Use the destination site’s `.env` credentials. The dump should be restored into the target database (e.g. `cms8`).

```bash
cd ~/DEST_SITE   # e.g. ~/admin.idoneo.dev

set -a
source <(grep -E '^DB_' .env)
set +a
export PGPASSWORD="$DB_PASSWORD"

echo "Restoring into $DB_DATABASE @ $DB_HOST as $DB_USERNAME"

pg_restore -h "$DB_HOST" -p "${DB_PORT:-5432}" -U "$DB_USERNAME" -d "$DB_DATABASE" \
  --no-owner --no-acl --clean --if-exists \
  ~/db-migration-YYYYMMDD.dump
```

Notes:

- Early “does not exist” warnings with `--clean --if-exists` are normal on an empty database.
- Ensure `DB_DATABASE` in the destination `.env` is the intended target database.
- After restore:

```bash
php artisan config:clear
php artisan migrate --force   # only if there are migrations newer than the dump
```

Optional cleanup on both servers:

```bash
rm -f ~/db-migration-YYYYMMDD.dump
```

---

## 4) Copy storage files (from the destination)

Pull `storage/` from the source site into the destination site. Exclude volatile cache/log directories.

```bash
# On destination
rsync -avz --progress \
  --exclude 'logs/' \
  --exclude 'framework/cache/' \
  --exclude 'framework/sessions/' \
  --exclude 'framework/views/' \
  forge@SOURCE_HOST:~/SOURCE_SITE/storage/ \
  ~/DEST_SITE/storage/
```

Example:

```bash
rsync -avz --progress \
  --exclude 'logs/' \
  --exclude 'framework/cache/' \
  --exclude 'framework/sessions/' \
  --exclude 'framework/views/' \
  forge@geri.revisionalpha.cloud:~/humano.revisionalpha.com/storage/ \
  ~/admin.idoneo.dev/storage/
```

Then on the destination:

```bash
cd ~/DEST_SITE

php artisan storage:link
chmod -R ug+rwx storage bootstrap/cache
```

---

## 5) Checklist

- [ ] Destination can `ssh` to source
- [ ] `APP_KEY` matches source
- [ ] Dump created with `pg_dump -Fc` on source
- [ ] Dump copied to destination
- [ ] Restored with `pg_restore` into destination `DB_DATABASE`
- [ ] `storage/` synced with `rsync`
- [ ] `php artisan storage:link` run on destination
- [ ] `php artisan config:clear` (and migrate if needed)
- [ ] Spot-check login, uploads, and media URLs
- [ ] Remove dump files from both servers when finished

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Permission denied (publickey)` | Destination key missing on source `authorized_keys` |
| `pg_dump` / `pg_restore: command not found` | `sudo apt install postgresql-client` |
| Encrypted values unreadable | Destination `APP_KEY` differs from source |
| Missing public media | Run `php artisan storage:link`; confirm `storage/app/public` was synced |
| Wrong database | Confirm `DB_DATABASE` in destination `.env` before `pg_restore` |

#!/usr/bin/env bash
# Nightly MySQL dump, gzip, keep 14 days. Credentials come from /root/.my.cnf ([client] user=… password=…), never from this file.
# Off-site copy (strongly recommended): install rclone and uncomment the last line.
set -euo pipefail
DIR=/var/backups/qronex
mkdir -p "$DIR"
STAMP=$(date +%Y%m%d-%H%M)
mysqldump --single-transaction --quick --routines qronex | gzip -9 > "$DIR/qronex-$STAMP.sql.gz"
tar czf "$DIR/storage-$STAMP.tar.gz" -C /var/www/client_apps/qronex/storage/app public   # uploaded logos
find "$DIR" -type f -mtime +14 -delete
echo "$(date -Is) backup ok: $(du -h "$DIR/qronex-$STAMP.sql.gz" | cut -f1)"
# rclone copy "$DIR" remote:qronex-backups --max-age 25h

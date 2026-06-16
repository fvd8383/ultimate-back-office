CLOSE AND TAG SPRINT: (POWERSHELL)

cd "C:\Users\fvd83\My Drive\Development\ultimate-back-office"

git fetch origin
git pull origin main --rebase

git tag sprint-2-5-complete
git push origin sprint-2-5-complete

git tag sprint-2-5-verified
git push origin sprint-2-5-verified


DATABASE BACKUP: (PUTTY - STAGING)

mysqldump \
-h ubo-stage-mysql-do-user-18803129-0.g.db.ondigitalocean.com \
-P 25060 \
-u ubo_stage_user \
-p \
--ssl-mode=REQUIRED \
ubo_staging > /root/sprint2_5_verified.sql

ls -lh /root/sprint2_5_verified.sql

DOWNLOAD BACKUP: (WINSCP)


SERVER HEALTH CHECK (PUTTY - STAGING)

echo "===== DISK ====="
df -h

echo
echo "===== WEB ROOT ====="
du -sh /var/www/ubo-repo

echo
echo "===== LOGS ====="
du -sh /var/www/ubo-staging/logs

echo
echo "===== BACKUPS ====="
ls -lh /root/*.sql 2>/dev/null

echo
echo "===== MEMORY ====="
free -h

echo
echo "===== LOAD ====="
uptime



# Sprint 5

## Completed

- Internal admin portal
- Admin role system
- User management
- Business management
- Website management
- Website preview controls
- Internal permissions

## Lessons Learned

- App routes must live under public/app
- Accounts routes must live under public/accounts
- Admin portal requires user_roles mapping
- Codex environment constraints reduce routing mistakes
- Product structure and deployment documentation are required for future sprints

## Backup

Database backup:
sprint5_verified.sql

Git tags:
sprint-5-complete
sprint-5-verified
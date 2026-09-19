#!/usr/bin/env bash
#
# Read-only survey of the host before anything is installed on it. Run as root:
#
#   bash deploy/audit-server.sh 2>&1 | tee /root/audit-$(date +%F).txt
#
# It changes nothing. There is no write, no install, no service action and no
# configuration edit anywhere in this file. It is safe to run on a host that is
# serving live traffic, and safe to run twice.
#
# It prints no secrets. Environment files are listed by key name only, database
# passwords are never read, and public keys are shown as fingerprints. The
# output is still a map of the machine, so treat the file as sensitive.
#
# The question it exists to answer: is this box healthy enough to put a second
# public site on, and what explains the outbound traffic that was flagged.

set -uo pipefail

h() { printf '\n\n== %s\n\n' "$*"; }
sub() { printf '\n-- %s\n' "$*"; }
none() { printf '   (none)\n'; }
have() { command -v "$1" >/dev/null 2>&1; }

[ "$(id -u)" = 0 ] || { echo "run as root: much of this is unreadable otherwise" >&2; exit 1; }

printf 'host survey  %s  %s\n' "$(hostname -f 2>/dev/null || hostname)" "$(date -u '+%F %T UTC')"

h "1. machine"
uname -a
[ -r /etc/os-release ] && . /etc/os-release && printf '\ndistribution: %s\n' "${PRETTY_NAME:-unknown}"
printf 'uptime:       %s\n' "$(uptime -p 2>/dev/null || true)"
printf 'booted:       %s\n' "$(uptime -s 2>/dev/null || true)"
printf 'virtual:      %s\n' "$(systemd-detect-virt 2>/dev/null || echo unknown)"
sub "memory"
free -h
sub "disk"
df -hT -x tmpfs -x devtmpfs
sub "load and the ten largest processes by resident memory"
cat /proc/loadavg
ps -eo user,pid,ppid,rss,etime,comm --sort=-rss | head -11

h "2. who can log in"
sub "accounts with a real shell"
awk -F: '$7 !~ /(nologin|false|sync)$/ {printf "   %-18s uid=%-6s shell=%s home=%s\n", $1, $3, $7, $6}' /etc/passwd
sub "accounts in a privileged group"
for g in sudo wheel adm docker; do
  m="$(getent group "$g" 2>/dev/null | cut -d: -f4)"
  [ -n "${m:-}" ] && printf '   %-8s %s\n' "$g" "$m"
done
sub "accounts with a password set (a locked or absent password shows as locked)"
awk -F: '{ s=substr($2,1,1); printf "   %-18s %s\n", $1, (s=="!"||s=="*"||$2=="") ? "locked" : "password set" }' /etc/shadow
sub "sudoers entries that are not comments"
grep -rhv -e '^\s*#' -e '^\s*$' /etc/sudoers /etc/sudoers.d/ 2>/dev/null || none
sub "authorized keys, as fingerprints"
found=0
while IFS= read -r f; do
  found=1
  printf '   %s\n' "$f"
  while IFS= read -r line; do
    [ -n "$line" ] || continue
    case "$line" in \#*) continue ;; esac
    printf '     %s\n' "$(printf '%s\n' "$line" | ssh-keygen -lf - 2>/dev/null || echo '(unreadable entry)')"
  done < "$f"
done < <(find /root /home -maxdepth 3 -name authorized_keys -type f 2>/dev/null)
[ "$found" = 1 ] || none

h "3. remote access configuration"
sub "sshd effective settings that matter"
if have sshd; then
  sshd -T 2>/dev/null | grep -Ei '^(port|permitrootlogin|passwordauthentication|pubkeyauthentication|permitemptypasswords|allowusers|allowgroups|x11forwarding|maxauthtries|clientalive)' || echo '   (sshd -T failed; showing the file)'
fi
grep -Ev '^\s*(#|$)' /etc/ssh/sshd_config 2>/dev/null | sed 's/^/   /'
sub "firewall"
if have ufw; then ufw status verbose; elif have firewall-cmd; then firewall-cmd --list-all; else
  iptables -S 2>/dev/null | sed 's/^/   /' || none
  nft list ruleset 2>/dev/null | head -60 | sed 's/^/   /'
fi
sub "fail2ban"
have fail2ban-client && fail2ban-client status 2>/dev/null || echo '   not installed'

h "4. authentication history"
sub "most recent logins"
last -aiw -n 25 2>/dev/null | head -30 || none
sub "accepted ssh logins in the current log"
grep -h "Accepted " /var/log/auth.log /var/log/secure 2>/dev/null | tail -40 | sed 's/^/   /' || \
  journalctl -u ssh -u sshd --no-pager 2>/dev/null | grep "Accepted " | tail -40 | sed 's/^/   /' || none
sub "failed attempts, counted by source"
{ grep -h "Failed password" /var/log/auth.log /var/log/secure 2>/dev/null || \
  journalctl -u ssh -u sshd --no-pager 2>/dev/null | grep "Failed password"; } \
  | grep -oE 'from [0-9a-fA-F:.]+' | sort | uniq -c | sort -rn | head -20 | sed 's/^/   /' || none
sub "sudo use"
grep -h "sudo:" /var/log/auth.log /var/log/secure 2>/dev/null | grep -v "pam_unix" | tail -25 | sed 's/^/   /' || none

h "5. what is listening"
if have ss; then ss -tulpnH | sort -k1,1 -k5,5 | sed 's/^/   /'
elif have netstat; then netstat -tulpn | sed 's/^/   /'
else echo '   neither ss nor netstat is installed'; fi

h "6. what is talking outbound  (this is the flagged anomaly)"
sub "established outbound connections, with the process holding them"
if have ss; then
  ss -tpnH state established 2>/dev/null | awk '{print $4, $5, $6}' | sed 's/^/   /' | head -60
else none; fi
sub "outbound connections grouped by remote address"
if have ss; then
  ss -tpnH state established 2>/dev/null | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -rn | head -25 | sed 's/^/   /'
fi
sub "processes holding a socket to somewhere that is not this machine"
if have lsof; then
  lsof -nP -iTCP -sTCP:ESTABLISHED 2>/dev/null | grep -v '127.0.0.1\|\[::1\]' | head -40 | sed 's/^/   /'
else echo '   lsof not installed; the ss output above is the record'; fi
sub "bytes moved since boot, per interface"
if have ip; then ip -s -h link 2>/dev/null | sed 's/^/   /'; fi
sub "listening or connecting processes that are not part of a package"
if have dpkg; then
  ss -tupnH 2>/dev/null | grep -oE 'users:\(\("[^"]+"' | cut -d'"' -f2 | sort -u | while read -r p; do
    bin="$(command -v "$p" 2>/dev/null)"
    if [ -n "$bin" ] && ! dpkg -S "$(readlink -f "$bin")" >/dev/null 2>&1; then
      printf '   %-20s %s   NOT OWNED BY ANY PACKAGE\n' "$p" "$bin"
    fi
  done
fi

h "7. scheduled work"
sub "root and per-user crontabs"
for u in $(cut -d: -f1 /etc/passwd); do
  c="$(crontab -l -u "$u" 2>/dev/null | grep -Ev '^\s*(#|$)')"
  [ -n "$c" ] && printf '   [%s]\n%s\n' "$u" "$(printf '%s\n' "$c" | sed 's/^/     /')"
done
sub "system cron"
grep -rhEv '^\s*(#|$)' /etc/crontab /etc/cron.d/ 2>/dev/null | sed 's/^/   /' || none
sub "cron directories"
ls -la /etc/cron.hourly /etc/cron.daily /etc/cron.weekly /etc/cron.monthly 2>/dev/null | sed 's/^/   /'
sub "systemd timers"
systemctl list-timers --all --no-pager 2>/dev/null | head -25 | sed 's/^/   /' || none
sub "at jobs"
have atq && atq 2>/dev/null || echo '   none'

h "8. services"
sub "enabled units"
systemctl list-unit-files --state=enabled --no-pager 2>/dev/null | sed 's/^/   /' || none
sub "failed units"
systemctl --failed --no-pager 2>/dev/null | sed 's/^/   /' || none
sub "unit files that did not come from a package"
if have dpkg; then
  find /etc/systemd/system /lib/systemd/system -maxdepth 2 -name '*.service' 2>/dev/null | while read -r u; do
    dpkg -S "$u" >/dev/null 2>&1 || printf '   %s\n' "$u"
  done
fi

h "9. web server"
sub "nginx version and config test"
nginx -v 2>&1 | sed 's/^/   /'
nginx -t 2>&1 | sed 's/^/   /'
sub "enabled sites and the names they claim"
for f in /etc/nginx/sites-enabled/* /etc/nginx/conf.d/*.conf; do
  [ -e "$f" ] || continue
  printf '   %s\n' "$f"
  grep -hE '^\s*(server_name|listen|root|fastcgi_pass)' "$f" 2>/dev/null | sed 's/^\s*/     /'
done
sub "document roots and who owns them"
ls -la /var/www/ 2>/dev/null | sed 's/^/   /'

h "10. PHP"
php -v 2>/dev/null | sed 's/^/   /'
sub "FPM pools"
for f in /etc/php/*/fpm/pool.d/*.conf; do
  [ -e "$f" ] || continue
  printf '   %s\n' "$f"
  grep -hE '^\s*(\[|user|group|listen|pm|pm\.|php_admin_value\[open_basedir\]|php_admin_value\[disable_functions\])' "$f" 2>/dev/null | sed 's/^\s*/     /'
done
sub "loaded extensions"
php -m 2>/dev/null | tr '\n' ' ' | fold -w 100 -s | sed 's/^/   /'

h "11. PostgreSQL"
if have psql && have sudo; then
  sudo -u postgres psql -c 'select version();' 2>/dev/null | sed 's/^/   /'
  sub "databases, sizes and owners"
  sudo -u postgres psql -c "\l+" 2>/dev/null | sed 's/^/   /'
  sub "roles"
  sudo -u postgres psql -c "\du" 2>/dev/null | sed 's/^/   /'
  sub "where it listens"
  sudo -u postgres psql -Atc "show listen_addresses; show port;" 2>/dev/null | sed 's/^/   /'
  sub "host based authentication, comments stripped"
  grep -hEv '^\s*(#|$)' /etc/postgresql/*/main/pg_hba.conf 2>/dev/null | sed 's/^/   /'
else echo '   psql or sudo not available here'; fi

h "12. containers"
if have docker; then docker ps -a 2>/dev/null | sed 's/^/   /'; else echo '   docker not installed'; fi
if have podman; then podman ps -a 2>/dev/null | sed 's/^/   /'; fi

h "13. integrity checks"
sub "package files that no longer match what was installed"
if have dpkg; then
  dpkg --verify 2>/dev/null | head -40 | sed 's/^/   /' || echo '   (dpkg --verify reported nothing)'
fi
sub "ld.so.preload  (should not exist)"
[ -e /etc/ld.so.preload ] && { echo '   PRESENT:'; cat /etc/ld.so.preload | sed 's/^/     /'; } || echo '   absent, as expected'
sub "setuid binaries outside the usual places"
find / -xdev -perm -4000 -type f 2>/dev/null | grep -vE '^/(usr/(bin|sbin|lib|libexec)|bin|sbin)/' | sed 's/^/   /' || none
sub "executables in world-writable directories"
find /tmp /var/tmp /dev/shm -xdev -type f -perm -u+x 2>/dev/null | head -25 | sed 's/^/   /' || none
sub "files in system directories changed in the last 14 days"
find /etc /usr/local/bin /usr/local/sbin -xdev -type f -mtime -14 2>/dev/null | head -40 | sed 's/^/   /' || none
sub "root shell history length  (an emptied history on a live box is worth a question)"
for f in /root/.bash_history /root/.zsh_history; do
  [ -e "$f" ] && printf '   %-24s %s lines, last written %s\n' "$f" "$(wc -l < "$f")" "$(stat -c %y "$f" 2>/dev/null)"
done

h "14. the other site's environment, by key name only"
for f in /var/www/*/.env /var/www/*/*/.env /var/www/*/current/.env; do
  [ -e "$f" ] || continue
  printf '   %s   owner %s   mode %s\n' "$f" "$(stat -c '%U:%G' "$f")" "$(stat -c '%a' "$f")"
  grep -oE '^[A-Z_][A-Z0-9_]*=' "$f" 2>/dev/null | tr -d '=' | tr '\n' ' ' | fold -w 90 -s | sed 's/^/     /'
  printf '\n'
done

h "15. resources this host has left"
printf '   cpu cores:     %s\n' "$(nproc)"
printf '   memory total:  %s\n' "$(free -h | awk '/^Mem:/{print $2}')"
printf '   memory free:   %s\n' "$(free -h | awk '/^Mem:/{print $7}')"
printf '   swap:          %s\n' "$(free -h | awk '/^Swap:/{print $2}')"
printf '   root fs free:  %s\n' "$(df -h / | awk 'NR==2{print $4}')"

printf '\n\nsurvey complete. nothing on this host was changed.\n\n'

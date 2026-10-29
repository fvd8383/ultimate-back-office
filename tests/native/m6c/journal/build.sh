#!/bin/sh
# Preparation recipe ONLY: executed later in the source-matching pinned test image.
set -eu
umask 077
source_sha=$1
printf '%s\n' "$source_sha" | grep -Eq '^[a-f0-9]{40}$'
for tool in php phpize php-config make cc; do command -v "$tool" >/dev/null; done
php -r 'exit(PHP_OS_FAMILY === "Linux" && PHP_VERSION_ID >= 80400 && PHP_VERSION_ID < 80500 && !PHP_ZTS ? 0 : 2);'
test "$(id -u)" = 0
test ! -e /usr/local/lib/ubo-m6c
test ! -L /usr/local/lib/ubo-m6c
cd "$(dirname "$0")"
phpize
./configure --enable-m6c-journal --with-php-config="$(command -v php-config)"
make -j1 CFLAGS='-O2 -Wall -Wextra -Werror=implicit-function-declaration'
install -d -m 0755 /usr/local/lib/ubo-m6c
install -m 0444 modules/m6c_journal.so /usr/local/lib/ubo-m6c/m6c_journal.so
php -r '$p="/usr/local/lib/ubo-m6c/m6c_journal.so"; $r=["version"=>1,"source_sha"=>$argv[1],"php"=>PHP_VERSION,"zts"=>PHP_ZTS,"source_hash"=>hash_file("sha256","m6c_journal.c"),"binary_hash"=>hash_file("sha256",$p)]; $bytes=json_encode($r,JSON_THROW_ON_ERROR); $h=fopen($p.".json","xb"); if(!$h||fwrite($h,$bytes)!==strlen($bytes)||!fclose($h)||!chmod($p.".json",0444))exit(2);' "$source_sha"
# Loading here proves only startup. Actual production interception requires the profile's controls/cases.
php -n -d extension=/usr/local/lib/ubo-m6c/m6c_journal.so -r 'exit(extension_loaded("m6c_journal") && function_exists("m6c_journal_arm") ? 0 : 2);'

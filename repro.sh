#!/bin/bash
cd "/Users/arifhamdani/Documents/IDN/Project Laravel/annur_management"

# Start server
php artisan serve --port=8204 2>/tmp/artisan_stderr.log &
SERVER_PID=$!
sleep 2

# Clear log
> storage/logs/laravel.log

# Login
COOKIE_JAR=$(mktemp)
CSRF=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" http://localhost:8204/login | grep -o 'name="_token"[^>]*value="[^"]*"' | grep -o 'value="[^"]*"' | head -1 | sed 's/value="//;s/"//')
curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -X POST http://localhost:8204/login -d "_token=$CSRF&email=admin@sekolah.test&password=password" -o /dev/null -w "Login: %{http_code}\n"

# Hit target
HTTP_CODE=$(curl -s -c "$COOKIE_JAR" -b "$COOKIE_JAR" -o /tmp/detail_body.txt -w "%{http_code}" http://localhost:8204/siswa/348)
echo "Detail: $HTTP_CODE"
echo "Response body (first 500 chars):"
head -c 500 /tmp/detail_body.txt
echo ""
echo "--- Log ---"
cat storage/logs/laravel.log
echo "--- Stderr ---"
tail -20 /tmp/artisan_stderr.log

kill $SERVER_PID 2>/dev/null

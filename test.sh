#!/bin/bash

set -euo pipefail

USER="testtest"
HOST="localhost:8083"

cleanup () {
	echo "Cleaning..."
	rm -f server/data/data.sqlite
	[ -f server/data/data2.sqlite ] && mv server/data/data2.sqlite server/data/data.sqlite
	[ -f cookies.txt ] && rm -f cookies.txt
	kill $PID
}

function requote() {
    local res=""
    for x in "${@}" ; do
        # try to figure out if quoting was required for the $x:
        grep -q "[[:space:]]" <<< "$x" && res="${res} '${x}'" || res="${res} ${x}"
    done
    # remove first space and print:
    sed -e 's/^ //' <<< "${res}"
}

r() {
	URL="$BASE_URL$1"
	shift
	ARGS=$(requote "${@}")
	CMD="curl -s -v -c cookies.txt $ARGS $URL"
	echo $CMD
	$CMD
	echo
}

trap "cleanup" ERR

[ -f server/data/data.sqlite ] && mv server/data/data.sqlite server/data/data2.sqlite

php -S $HOST -t server server/index.php > /dev/null 2>&1 &
PID=$!

sleep 0.5

BASE_URL="http://${USER}:testtest@${HOST}/"

r / -X GET > /dev/null

sqlite3 server/data/data.sqlite "INSERT INTO users (name, password) VALUES ('${USER}', '\$2y\$10\$taowFf8qdr23Rx13cblkQ.IBHcj2yB.ESR9Hb8OOEEDwkyVSxkiMe');"

echo "Login... "
r api/2/auth/${USER}/login.json -X POST

echo "Create device... "
r api/2/devices/${USER}/antennapod.json -d '{"caption": "Bla bla", "type": "mobile"}' -X POST

echo "Logout... "
r api/2/auth/${USER}/logout.json -X POST

exit 0
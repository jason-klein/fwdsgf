#!/bin/bash

wget \
    --user-agent="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.88 Safari/537.36" \
    -O facebook-ics-cli.ics \
    'https://www.facebook.com/events/ical/upcoming/?uid=999999999&key=ZZZZZZZZZZZZZZZZ'


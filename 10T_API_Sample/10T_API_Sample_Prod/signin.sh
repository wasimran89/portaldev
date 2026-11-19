#!/bin/bash

curl -X POST \
  --silent --show-error \
  --cacert ./ca-prod.pem \
  --key ./client.key \
  --cert ./client.crt \
  https://es2plus-prod.10ttech.com/gsma/rsp2/es2plus/user/signin \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'email=JCIUS_CBRS@jcius.com&password=Jc!uS09876' \
  -o jwt.txt


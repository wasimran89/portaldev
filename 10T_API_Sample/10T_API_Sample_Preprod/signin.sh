#!/bin/bash

curl -X POST \
  --silent --show-error \
  --cacert ./ca-prod.pem \
  --key ./client.key \
  --cert ./client.crt \
  https://es2plus-preprod.10ttech.com/gsma/rsp2/es2plus/user/signin \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'email=10T_JCI_Test_T01@jcius.com&password=Jc!uS09876' \
  -o jwt.txt



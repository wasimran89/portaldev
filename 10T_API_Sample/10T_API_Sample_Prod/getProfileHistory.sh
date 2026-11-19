bearer="Authorization: Bearer "
token="$(jq --raw-output '.token' jwt.txt)"
curl -X POST \
  --cacert ./ca-prod.pem \
  --key ./client.key \
  --cert ./client.crt \
  https://es2plus-prod.10ttech.com/gsma/rsp2/es2plus/getProfileHistory \
  -H "$bearer$token" \
  -H 'Content-Type: application/json' \
  -d '{
    "header": {
        "functionRequesterIdentifier": "JCIUS",
        "functionCallIdentifier": "JCIUS"
    },
    "iccid": "89105600000001648893"
}' | jq


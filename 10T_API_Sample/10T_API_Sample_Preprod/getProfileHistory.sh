bearer="Authorization: Bearer "
token="$(jq --raw-output '.token' jwt.txt)"
curl -X POST \
  --cacert ./ca-prod.pem \
  --key ./client.key \
  --cert ./client.crt \
  https://es2plus-preprod.10ttech.com/gsma/rsp2/es2plus/getProfileHistory \
  -H "$bearer$token" \
  -H 'Content-Type: application/json' \
  -d '{
    "header": {
        "functionRequesterIdentifier": "10T",
        "functionCallIdentifier": "10T"
    },
    "iccid": "89811890000000000219"
}' | jq


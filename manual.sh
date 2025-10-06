#!/usr/bin/env bash
set -euo pipefail

# ---------------- CONFIG ----------------
BASE_URL="${BASE_URL:-http://localhost:9501}"
ACCOUNT_ID="${ACCOUNT_ID:-11111111-1111-1111-1111-111111111111}"
RUN_PROCESS_CMD="${RUN_PROCESS_CMD:-}"   # ex: "docker compose exec -T app php bin/hyperf.php withdrawals:process"

REQ_URL="${BASE_URL}/account/${ACCOUNT_ID}/balance/withdraw"
LIST_URL="${BASE_URL}/account/${ACCOUNT_ID}/withdraws"

CURL="curl -s -i"
PHP=${PHP:-php}

BASE_PAYLOAD='{"method":"PIX","pix":{"type":"email","key":"fulano@email.com"},"amount":150.75}'

# ---------------- UTILS ----------------
hr(){ echo; printf '%s\n' "------------------------------------------------------------"; }
need(){ command -v "$1" >/dev/null 2>&1 || { echo "❌ requisito ausente: $1"; exit 1; }; }

gen_key(){
  if command -v openssl >/dev/null 2>&1; then
    openssl rand -hex 16
  else
    "$PHP" -r 'echo bin2hex(random_bytes(16));'
  fi
}

parse_http_code(){
  echo "$1" | php -r '
    $s=stream_get_contents(STDIN);
    if (!preg_match("/^HTTP\/\d\.\d\s+(\d{3})/m",$s,$m)) exit;
    echo $m[1];
  ';
}

header(){ # $1=raw, $2=Header-Name
  echo "$1" | php -r '
    $s=stream_get_contents(STDIN);
    $name=$argv[1] ?? "";
    if($name==="") exit;
    if (preg_match("/^".preg_quote($name,"/").":\s*(.+)$/mi",$s,$m)) echo trim($m[1]);
  ' "$2"
}

body(){
  echo "$1" | php -r '
    $s=stream_get_contents(STDIN);
    if (preg_match("/\r?\n\r?\n(.*)\$/s",$s,$m)) echo $m[1];
  ';
}

assert_eq(){
  local got="$1" exp="$2" msg="$3"
  if [[ "$got" == "$exp" ]]; then
    echo "✅  $msg"
  else
    echo "❌  $msg (got='$got' exp='$exp')"
    exit 1
  fi
}

assert_contains(){
  local hay="$1" needle="$2" msg="$3"
  if echo "$hay" | grep -q "$needle"; then
    echo "✅  $msg"
  else
    echo "❌  $msg (needle='$needle')"
    exit 1
  fi
}

# Pega um valor de chave simples do JSON (nível 1): withdraw_id, ok, etc.
json_get_key(){ # $1=json, $2=keyName
  echo "$1" | "$PHP" -r '
    $d=json_decode(stream_get_contents(STDIN), true);
    $k=$argv[1] ?? "";
    if (!is_array($d) || $k==="") { exit; }
    if (!array_key_exists($k,$d)) { exit; }
    $v=$d[$k];
    if (is_bool($v)) { echo $v?"true":"false"; }
    elseif (is_scalar($v)) { echo $v; }
    else { echo json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
  ' "$2"
}

# Conta quantos items há em d["items"]
json_count_items(){ # $1=json
  echo "$1" | "$PHP" -r '
    $d=json_decode(stream_get_contents(STDIN), true);
    if (!is_array($d) || !isset($d["items"]) || !is_array($d["items"])) { echo 0; exit; }
    echo count($d["items"]);
  '
}

# Constrói JSON com schedule e amount via PHP
with_schedule(){ # $1=baseJson $2="YYYY-mm-dd HH:ii" $3=amount
  echo "$1" | "$PHP" -r '
    $d=json_decode(stream_get_contents(STDIN),true);
    $d["schedule"]=$argv[1];
    if ($argv[2] !== "") $d["amount"]=(float)$argv[2];
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ' "$2" "${3:-}"
}

# ======= NOVOS HELPERS PARA EDITAR JSON =======
# Define uma chave (inclusive aninhada com ponto: ex "pix.type")
json_set(){ # $1=json $2=path.with.dots $3=valueString $4=typeHint: string|number|bool|null
  echo "$1" | "$PHP" -r '
    $d=json_decode(stream_get_contents(STDIN), true);
    $p=$argv[1]; $v=$argv[2]; $t=$argv[3] ?? "string";
    $keys=explode(".", $p);
    $ref=&$d;
    foreach($keys as $k){
      if(!isset($ref[$k]) || !is_array($ref[$k])) { $ref[$k]=[]; }
      if($k===end($keys)){
        if($t==="number"){ $ref[$k]=(float)$v; }
        elseif($t==="bool"){ $ref[$k]=($v==="true"); }
        elseif($t==="null"){ $ref[$k]=null; }
        else { $ref[$k]=$v; }
      } else {
        $ref=&$ref[$k];
      }
    }
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ' "$2" "$3" "${4:-string}"
}

# Remove uma chave (nível superior ou com ponto)
json_del(){ # $1=json $2=path.with.dots
  echo "$1" | "$PHP" -r '
    $d=json_decode(stream_get_contents(STDIN), true);
    $p=$argv[1];
    $keys=explode(".", $p);
    $ref=&$d;
    for($i=0;$i<count($keys);$i++){
      $k=$keys[$i];
      if(!isset($ref[$k])) break;
      if($i===count($keys)-1){ unset($ref[$k]); break; }
      $ref=&$ref[$k];
    }
    echo json_encode($d, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ' "$2"
}

# ---------------- CHECK TOOLS ----------------
need "$PHP"
need "curl"

echo "BASE_URL=$BASE_URL"
echo "ACCOUNT_ID=$ACCOUNT_ID"
hr

# ========== [VALIDAÇÕES] method deve ser PIX ==========
echo "V1) method inválido (espera 400)"
PAYLOAD_V1="$(json_set "$BASE_PAYLOAD" "method" "TED" "string")"
RESP_V1="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V1" "$REQ_URL")"
HTTP_V1="$(parse_http_code "$RESP_V1")"
BODY_V1="$(body "$RESP_V1")"
echo "HTTP: $HTTP_V1"; echo "Body: $BODY_V1"
assert_eq "$HTTP_V1" "400" "method != PIX deve falhar"
assert_contains "$BODY_V1" "PIX" "mensagem cita PIX"

hr
# ========== [VALIDAÇÕES] pix.type só 'email' ==========
echo "V2) pix.type inválido (espera 400)"
PAYLOAD_V2="$(json_set "$BASE_PAYLOAD" "pix.type" "phone" "string")"
RESP_V2="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V2" "$REQ_URL")"
HTTP_V2="$(parse_http_code "$RESP_V2")"
BODY_V2="$(body "$RESP_V2")"
echo "HTTP: $HTTP_V2"; echo "Body: $BODY_V2"
assert_eq "$HTTP_V2" "400" "pix.type != email deve falhar"

hr
# ========== [VALIDAÇÕES] email inválido ==========
echo "V3) pix.key e-mail inválido (espera 400)"
PAYLOAD_V3="$(json_set "$BASE_PAYLOAD" "pix.key" "not-an-email" "string")"
RESP_V3="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V3" "$REQ_URL")"
HTTP_V3="$(parse_http_code "$RESP_V3")"
BODY_V3="$(body "$RESP_V3")"
echo "HTTP: $HTTP_V3"; echo "Body: $BODY_V3"
[[ "$HTTP_V3" == "400" || "$HTTP_V3" == "422" ]] && echo "✅  e-mail inválido rejeitado" || { echo "❌  e-mail inválido aceito"; exit 1; }
ERROR_MSG_V3="$(json_get_key "$BODY_V3" "error")"
assert_contains "$ERROR_MSG_V3" "e-mail" "mensagem cita e-mail"

hr
# ========== [VALIDAÇÕES] amount zero/negativo/decimais demais ==========
echo "V4) amount == 0 (espera 400/422)"
PAYLOAD_V4="$(json_set "$BASE_PAYLOAD" "amount" "0" "number")"
RESP_V4="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V4" "$REQ_URL")"
HTTP_V4="$(parse_http_code "$RESP_V4")"; BODY_V4="$(body "$RESP_V4")"
echo "HTTP: $HTTP_V4"; echo "Body: $BODY_V4"
[[ "$HTTP_V4" == "400" || "$HTTP_V4" == "422" ]] && echo "✅  amount==0 invalida" || { echo "❌  amount==0 aceito"; exit 1; }

echo "V5) amount negativo (espera 400/422)"
PAYLOAD_V5="$(json_set "$BASE_PAYLOAD" "amount" "-10" "number")"
RESP_V5="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V5" "$REQ_URL")"
HTTP_V5="$(parse_http_code "$RESP_V5")"
[[ "$HTTP_V5" == "400" || "$HTTP_V5" == "422" ]] && echo "✅  amount<0 invalida" || { echo "❌  amount<0 aceito"; exit 1; }

echo "V6) amount com >2 casas (espera 400/422)"
PAYLOAD_V6="$(json_set "$BASE_PAYLOAD" "amount" "10.999" "number")"
RESP_V6="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V6" "$REQ_URL")"
HTTP_V6="$(parse_http_code "$RESP_V6")"
[[ "$HTTP_V6" == "400" || "$HTTP_V6" == "422" ]] && echo "✅  amount com 3 casas invalida" || { echo "❌  amount 3 casas aceito"; exit 1; }

hr
# ========== [VALIDAÇÕES] campos ausentes ==========
echo "V7) falta pix.key (espera 400/422)"
PAYLOAD_V7="$(json_del "$BASE_PAYLOAD" "pix.key")"
RESP_V7="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V7" "$REQ_URL")"
HTTP_V7="$(parse_http_code "$RESP_V7")"
[[ "$HTTP_V7" == "400" || "$HTTP_V7" == "422" ]] && echo "✅  faltando pix.key invalida" || { echo "❌  faltando pix.key aceito"; exit 1; }

echo "V8) falta amount (espera 400/422)"
PAYLOAD_V8="$(json_del "$BASE_PAYLOAD" "amount")"
RESP_V8="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V8" "$REQ_URL")"
HTTP_V8="$(parse_http_code "$RESP_V8")"
[[ "$HTTP_V8" == "400" || "$HTTP_V8" == "422" ]] && echo "✅  faltando amount invalida" || { echo "❌  faltando amount aceito"; exit 1; }

hr
# ========== [VALIDAÇÕES] schedule com formato inválido ==========
echo "V9) schedule formato inválido (espera 400/422)"
PAYLOAD_V9="$(with_schedule "$BASE_PAYLOAD" "31-12-2025 99:99" "10.00")"
RESP_V9="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_V9" "$REQ_URL")"
HTTP_V9="$(parse_http_code "$RESP_V9")"; BODY_V9="$(body "$RESP_V9")"
echo "HTTP: $HTTP_V9"; echo "Body: $BODY_V9"
[[ "$HTTP_V9" == "400" || "$HTTP_V9" == "422" ]] && echo "✅  schedule inválido rejeitado" || { echo "❌  schedule inválido aceito"; exit 1; }

hr

# =====================  BATERIA ORIGINAL  =====================

# ========== 1) Idempotência - primeira chamada ==========
echo "1) Idempotência - Primeira chamada"
KEY_1="$(gen_key)"
RESP1="$($CURL -H "Content-Type: application/json" -H "Idempotency-Key: $KEY_1" -d "$BASE_PAYLOAD" "$REQ_URL")"

HTTP1="$(parse_http_code "$RESP1")"
REPLAY1="$(header "$RESP1" "Idempotency-Replayed" || true)"
BODY1="$(body "$RESP1")"

echo "HTTP: $HTTP1"
echo "Idempotency-Replayed: ${REPLAY1:-}"
echo "Body: $BODY1"

assert_eq "$HTTP1" "200" "status é 200"
[[ -z "${REPLAY1:-}" || "${REPLAY1,,}" == "false" ]] && echo "✅  primeira chamada não é replay" || { echo "❌  primeira chamada veio como replay"; exit 1; }

WID1="$(json_get_key "$BODY1" "withdraw_id")"
[[ -n "$WID1" ]] && echo "✅  withdraw_id: $WID1" || { echo "❌  withdraw_id ausente"; exit 1; }

hr

# ========== 2) Idempotência - replay mesma key ==========
echo "2) Idempotência - Replay com mesma key"
RESP2="$($CURL -H "Content-Type: application/json" -H "Idempotency-Key: $KEY_1" -d "$BASE_PAYLOAD" "$REQ_URL")"

HTTP2="$(parse_http_code "$RESP2")"
REPLAY2="$(header "$RESP2" "Idempotency-Replayed" || true)"
BODY2="$(body "$RESP2")"

echo "HTTP: $HTTP2"
echo "Idempotency-Replayed: ${REPLAY2:-}"
echo "Body: $BODY2"

assert_eq "$HTTP2" "200" "(replay) status 200"
WID2="$(json_get_key "$BODY2" "withdraw_id")"
assert_eq "$WID2" "$WID1" "mesmo withdraw_id no replay"
if [[ -n "${REPLAY2:-}" ]]; then
  assert_eq "${REPLAY2,,}" "true" "header marca replay"
else
  echo "ℹ️  header Idempotency-Replayed ausente — usando igualdade do withdraw_id como sinal de replay (ok)."
fi

hr

# ========== 3) Listagem paginada ==========
echo "3) Listagem paginada"
RESP_LIST="$(curl -s -i "${LIST_URL}?page=1&per_page=10")"
HTTP_LIST="$(parse_http_code "$RESP_LIST")"
BODY_LIST="$(body "$RESP_LIST")"

echo "HTTP: $HTTP_LIST"
assert_eq "$HTTP_LIST" "200" "status da listagem é 200"

OK_LIST="$(json_get_key "$BODY_LIST" "ok")"
COUNT_ITEMS="$(json_count_items "$BODY_LIST")"

assert_eq "$OK_LIST" "true" "ok=true na listagem"
[[ "$COUNT_ITEMS" -ge 1 ]] && echo "✅  items presentes" || { echo "❌  items vazios"; exit 1; }
assert_contains "$BODY_LIST" '"pix":' "pix aparece"
assert_contains "$BODY_LIST" '\*\*\*' "chave PIX aparece mascarada"

hr

# ========== 4) Agendamento no passado ==========
echo "4) Agendamento no passado (espera 400)"
PAST="$("$PHP" -r 'date_default_timezone_set(getenv("APP_TIMEZONE") ?: "America/Sao_Paulo"); echo (new DateTime("-1 day"))->format("Y-m-d H:i");')"
PAYLOAD_PAST="$(with_schedule "$BASE_PAYLOAD" "$PAST" "10.00")"

RESP_PAST="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_PAST" "$REQ_URL")"
HTTP_PAST="$(parse_http_code "$RESP_PAST")"
BODY_PAST="$(body "$RESP_PAST")"
echo "HTTP: $HTTP_PAST"
echo "Body: $BODY_PAST"

assert_eq "$HTTP_PAST" "400" "passado retorna 400"
assert_contains "$BODY_PAST" "past" "mensagem clara (past)"

hr

# ========== 5) Agendamento além de 7 dias ==========
echo "5) Agendamento além de 7 dias (espera 400)"
FAR="$("$PHP" -r 'date_default_timezone_set(getenv("APP_TIMEZONE") ?: "America/Sao_Paulo"); echo (new DateTime("+8 day"))->format("Y-m-d H:i");')"
PAYLOAD_FAR="$(with_schedule "$BASE_PAYLOAD" "$FAR" "10.00")"

RESP_FAR="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_FAR" "$REQ_URL")"
HTTP_FAR="$(parse_http_code "$RESP_FAR")"
BODY_FAR="$(body "$RESP_FAR")"
echo "HTTP: $HTTP_FAR"
echo "Body: $BODY_FAR"

assert_eq "$HTTP_FAR" "400" ">7 dias retorna 400"
assert_contains "$BODY_FAR" "7" "mensagem cita 7 dias"

hr

# ========== 6) Agendamento válido (+1 min) e execução ==========
echo "6) Agendamento válido (+1 min) e execução"
SOON="$("$PHP" -r 'date_default_timezone_set(getenv("APP_TIMEZONE") ?: "America/Sao_Paulo"); echo (new DateTime("+1 minute"))->format("Y-m-d H:i");')"
PAYLOAD_SOON="$(with_schedule "$BASE_PAYLOAD" "$SOON" "11.11")"

RESP_SOON="$($CURL -H "Content-Type: application/json" -d "$PAYLOAD_SOON" "$REQ_URL")"
HTTP_SOON="$(parse_http_code "$RESP_SOON")"
BODY_SOON="$(body "$RESP_SOON")"

echo "HTTP: $HTTP_SOON"
echo "Body: $BODY_SOON"
assert_eq "$HTTP_SOON" "200" "agendamento retorna 200"

WID_SOON="$(json_get_key "$BODY_SOON" "withdraw_id")"
[[ -n "$WID_SOON" ]] && echo "✅  withdraw_id agendado: $WID_SOON" || { echo "❌  withdraw_id ausente no agendamento"; exit 1; }

if [[ -n "$RUN_PROCESS_CMD" ]]; then
  echo "⏳ aguardando ~70s para processar o agendamento..."
  sleep 70
  echo "▶️ $RUN_PROCESS_CMD"
  bash -c "$RUN_PROCESS_CMD" >/dev/null 2>&1 || true

  echo "🔎 conferindo se apareceu na listagem..."
  LIST_AFTER="$(curl -s "${LIST_URL}?page=1&per_page=20")"
  echo "$LIST_AFTER" | grep -q "$WID_SOON" \
    && echo "✅  agendamento visível na listagem" \
    || echo "ℹ️  não encontrei o ID na primeira página (pode estar em outra)."
else
  echo "ℹ️  Aguarde ~70s e rode no container: php bin/hyperf.php withdrawals:process"
  echo "ℹ️  Depois confira: ${LIST_URL}?page=1&per_page=10"
fi

hr

# ========== 7) Concorrência ==========
echo "7) Concorrência (duas requisições simultâneas, mesma key)"
KEY_C="$(gen_key)"
OUT_A="$(mktemp)"; OUT_B="$(mktemp)"
( $CURL -H "Content-Type: application/json" -H "Idempotency-Key: $KEY_C" -d "$BASE_PAYLOAD" "$REQ_URL" > "$OUT_A" ) &
( $CURL -H "Content-Type: application/json" -H "Idempotency-Key: $KEY_C" -d "$BASE_PAYLOAD" "$REQ_URL" > "$OUT_B" ) &
wait

R_A="$(cat "$OUT_A")"; R_B="$(cat "$OUT_B")"
A_HTTP="$(parse_http_code "$R_A")"; B_HTTP="$(parse_http_code "$R_B")"
A_REPLAY="$(header "$R_A" "Idempotency-Replayed" || true)"; B_REPLAY="$(header "$R_B" "Idempotency-Replayed" || true)"
A_BODY="$(body "$R_A")"; B_BODY="$(body "$R_B")"

echo "[A] HTTP=$A_HTTP  Replayed=${A_REPLAY:-}  Body=$(echo "$A_BODY" | tr -d '\n')"
echo "[B] HTTP=$B_HTTP  Replayed=${B_REPLAY:-}  Body=$(echo "$B_BODY" | tr -d '\n')"
echo "ℹ️  Esperado: (200 + 200 replay) OU 409 (IDEMPOTENCY_IN_PROGRESS)."

OK_CONC=0
if [[ "$A_HTTP" == "409" || "$B_HTTP" == "409" ]]; then
  OK_CONC=1
else
  A_ID="$(json_get_key "$A_BODY" "withdraw_id")"
  B_ID="$(json_get_key "$B_BODY" "withdraw_id")"
  if [[ "$A_HTTP" == "200" && "$B_HTTP" == "200" ]]; then
    if [[ "${A_REPLAY,,}" == "true" || "${B_REPLAY,,}" == "true" || ( -n "$A_ID" && "$A_ID" == "$B_ID" ) ]]; then
      OK_CONC=1
    fi
  fi
fi

if [[ "$OK_CONC" -eq 1 ]]; then
  echo "✅  concorrência dentro do esperado"
else
  echo "❌  comportamento inesperado na concorrência"
  exit 1
fi

hr
echo "✓ Concluído."

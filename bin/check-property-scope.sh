#!/usr/bin/env bash
#
# Scénarios de cloisonnement multi-logements (cf. PLAN-MULTI-LOGEMENTS.md § Testing).
#
#   php bin/console doctrine:fixtures:load --no-interaction
#   bash bin/check-property-scope.sh
#
# Le script ÉCRIT en base : il crée des notes et des articles d'inventaire qu'il
# ne supprime pas. Recharger les fixtures avant chaque exécution.
#
# Il retire en revanche l'appartenance qu'il crée, pour que `sophie` reste
# mono-logement : c'est le seul compte des fixtures qui permette de tester le
# repli mono-logement et le sélecteur masqué, et le laisser à deux logements
# fausse silencieusement tout ce qui tourne ensuite.
#
# Surchargeable quand le certificat TLS local n'est pas installé :
#   API=http://127.0.0.1:8000 bash bin/check-property-scope.sh
set -uo pipefail
API=${API:-https://127.0.0.1:8000}

login() {
  curl -sk -X POST "$API/api/login" -H 'Content-Type: application/json' \
    -d "{\"username\":\"$1\",\"password\":\"$1\"}" | python3 -c 'import sys,json; print(json.load(sys.stdin)["token"])'
}

# $1 label | $2 attendu | $3 obtenu
check() {
  if [ "$2" = "$3" ]; then printf '  OK   %-58s %s\n' "$1" "$3"
  else printf '  FAIL %-58s attendu=%s obtenu=%s\n' "$1" "$2" "$3"; FAILED=1; fi
}

FAILED=0
SOPHIE=$(login sophie)   # membre de « Les Tennis » uniquement
MARIE=$(login marie)     # membre de « Le Cabanon » uniquement (manager)
ANTONIN=$(login antonin) # membre des deux
ADMIN=$(login admin)     # ROLE_ADMIN, membre d'aucun

get() { curl -sk "$API$2" -H "Authorization: Bearer $1"; }
status() { curl -sk -o /dev/null -w '%{http_code}' "$API$2" -H "Authorization: Bearer $1"; }
count() { get "$1" "$2" | python3 -c 'import sys,json; print(json.load(sys.stdin).get("totalItems", -1))'; }
post() {
  curl -sk -o /dev/null -w '%{http_code}' -X POST "$API$2" -H "Authorization: Bearer $1" \
    -H 'Content-Type: application/ld+json' -d "$3"
}
post_iri() {
  curl -sk -X POST "$API$2" -H "Authorization: Bearer $1" \
    -H 'Content-Type: application/ld+json' -d "$3" \
    | python3 -c 'import sys,json; print(json.load(sys.stdin).get("@id",""))'
}

TENNIS=$(get "$ANTONIN" /api/properties | python3 -c '
import sys,json
for p in json.load(sys.stdin)["member"]:
    if p["slug"] == "les-tennis": print(p["@id"])')
CABANON=$(get "$ANTONIN" /api/properties | python3 -c '
import sys,json
for p in json.load(sys.stdin)["member"]:
    if p["slug"] == "le-cabanon": print(p["@id"])')
CABANON_ITEM=$(get "$MARIE" /api/inventory_items | python3 -c 'import sys,json; print(json.load(sys.stdin)["member"][0]["@id"])')
CABANON_NOTE=$(get "$MARIE" /api/notes | python3 -c 'import sys,json; print(json.load(sys.stdin)["member"][0]["@id"])')

echo "logements: tennis=$TENNIS cabanon=$CABANON"
echo
echo "— Cloisonnement des collections (sophie, mono-logement) —"
check "GET /api/occupations"            5 "$(count "$SOPHIE" /api/occupations)"
check "GET /api/notes"                  4 "$(count "$SOPHIE" /api/notes)"
check "GET /api/inventory_items"       22 "$(count "$SOPHIE" /api/inventory_items)"
check "GET /api/shopping_items"        10 "$(count "$SOPHIE" /api/shopping_items)"
check "GET /api/works"                  3 "$(count "$SOPHIE" /api/works)"
check "GET /api/properties"             1 "$(count "$SOPHIE" /api/properties)"
check "GET /api/property_members"       3 "$(count "$SOPHIE" /api/property_members)"

echo
echo "— Paramètre ?property= forgé —"
check "notes du logement interdit"      0 "$(count "$SOPHIE" "/api/notes?property=$CABANON")"
check "inventaire du logement interdit" 0 "$(count "$SOPHIE" "/api/inventory_items?property=$CABANON")"
check "notes du logement autorisé"      4 "$(count "$SOPHIE" "/api/notes?property=$TENNIS")"

echo
echo "— Accès item interdit —"
check "GET item d'inventaire du Cabanon" 404 "$(status "$SOPHIE" "$CABANON_ITEM")"
check "GET note du Cabanon"              404 "$(status "$SOPHIE" "$CABANON_NOTE")"
check "GET logement Le Cabanon"          404 "$(status "$SOPHIE" "$CABANON")"

echo
echo "— Écritures —"
CATEGORY=$(get "$SOPHIE" /api/categories | python3 -c 'import sys,json; print(json.load(sys.stdin)["member"][0]["@id"])')

# Les payloads sont construits dans des variables : les imbriquer directement
# dans la substitution de commande de `check` casse leur échappement JSON.
# 400 et non 403 : l'extension d'item s'applique aussi à la résolution des
# IRI du payload, si bien que le logement interdit est « Item not found ».
# Plus étanche qu'un 403, qui confirmerait son existence.
BODY='{"title":"X","content":"X","property":"'$CABANON'"}'
R=$(post "$SOPHIE" /api/notes "$BODY");            check "POST note sur logement interdit" 400 "$R"
BODY='{"title":"Sans logement","content":"Repli mono-logement"}'
R=$(post "$SOPHIE" /api/notes "$BODY");            check "POST note sans logement (mono)" 201 "$R"
BODY='{"title":"X","content":"X"}'
R=$(post "$ANTONIN" /api/notes "$BODY");           check "POST note sans logement (multi)" 422 "$R"
BODY='{"title":"Avec logement","content":"X","property":"'$CABANON'"}'
R=$(post "$ANTONIN" /api/notes "$BODY");           check "POST note sur logement autorisé" 201 "$R"
BODY='{"name":"Repli","quantity":1,"category":"'$CATEGORY'"}'
R=$(post "$SOPHIE" /api/inventory_items "$BODY");  check "POST inventaire sans logement" 201 "$R"
BODY='{"name":"X","quantity":1,"category":"'$CATEGORY'","property":"'$CABANON'"}'
R=$(post "$SOPHIE" /api/inventory_items "$BODY");  check "POST inventaire logement interdit" 400 "$R"
# `category` est devenue nullable et n'est plus envoyée par les clients à jour.
BODY='{"name":"Sans catégorie","quantity":1}'
R=$(post "$SOPHIE" /api/inventory_items "$BODY");  check "POST inventaire sans catégorie" 201 "$R"

# Écriture sur un item d'un autre logement : l'extension d'item masque la
# ressource avant même le voter, d'où un 404 et non un 403.
BODY='{"title":"Détournée"}'
R=$(curl -sk -o /dev/null -w '%{http_code}' -X PATCH "$API$CABANON_NOTE" -H "Authorization: Bearer $SOPHIE" \
      -H 'Content-Type: application/merge-patch+json' -d "$BODY")
check "PATCH note du Cabanon" 404 "$R"

echo
echo "— Rôle local : gestionnaire vs occupant, dans un logement autorisé —"
del() { curl -sk -o /dev/null -w '%{http_code}' -X DELETE "$API$2" -H "Authorization: Bearer $1"; }
TENNIS_ITEM=$(get "$SOPHIE" /api/inventory_items | python3 -c 'import sys,json; print(json.load(sys.stdin)["member"][0]["@id"])')
# sophie est occupante des Tennis, antonin en est gestionnaire.
check "DELETE inventaire (occupant)"     403 "$(del "$SOPHIE" "$TENNIS_ITEM")"
check "DELETE inventaire (gestionnaire)" 204 "$(del "$ANTONIN" "$TENNIS_ITEM")"

# antonin est simple occupant du Cabanon : il ne gère pas ses membres.
# marie, gestionnaire du Cabanon, y ajoute en revanche qui elle veut.
SOPHIE_IRI=$(get "$SOPHIE" /api/me | python3 -c 'import sys,json; print(json.load(sys.stdin)["@id"])')
BODY='{"property":"'$CABANON'","user":"'$SOPHIE_IRI'","role":"occupant"}'
R=$(post "$ANTONIN" /api/property_members "$BODY")
check "POST membre du Cabanon (occupant)"     403 "$R"
NEW_MEMBER=$(post_iri "$MARIE" /api/property_members "$BODY")
check "POST membre du Cabanon (gestionnaire)" "/api/property_members" "$(dirname "$NEW_MEMBER")"
memberships() { get "$1" /api/me | python3 -c 'import sys,json; print(len(json.load(sys.stdin)["memberships"]))'; }
# sophie voit désormais les deux logements dans son sélecteur.
check "GET /api/me après ajout (sophie)"        2 "$(memberships "$SOPHIE")"

# Nettoyage : sophie doit redevenir mono-logement, sinon le scénario
# mono-logement des fixtures est faussé pour tout ce qui tourne ensuite —
# y compris un développeur qui teste le sélecteur à la main.
check "DELETE membre (gestionnaire)"          204 "$(del "$MARIE" "$NEW_MEMBER")"
check "GET /api/me après retrait (sophie)"      1 "$(memberships "$SOPHIE")"

echo
echo "— Pièces : écritures réservées au gestionnaire —"
check "GET /api/rooms (sophie, Tennis)"   6 "$(count "$SOPHIE" /api/rooms)"
check "GET /api/rooms (marie, Cabanon)"   5 "$(count "$MARIE" /api/rooms)"
check "pièces du logement interdit"       0 "$(count "$SOPHIE" "/api/rooms?property=$CABANON")"

BODY='{"name":"Mezzanine","type":"bedroom","position":9,"property":"'$TENNIS'"}'
R=$(post "$SOPHIE"  /api/rooms "$BODY"); check "POST pièce (occupant)"     403 "$R"
R=$(post "$ANTONIN" /api/rooms "$BODY"); check "POST pièce (gestionnaire)" 201 "$R"
# Contrairement aux cinq ressources historiques, le POST d'une pièce ne tolère
# pas un logement nul : l'échappatoire dégraderait MANAGE en CONTRIBUTE, le
# repli de PropertyScopeProcessor ne revérifiant que CONTRIBUTE.
BODY='{"name":"Sans logement"}'
R=$(post "$SOPHIE"  /api/rooms "$BODY"); check "POST pièce sans logement (occupant)" 403 "$R"

echo
echo "— Cohérence pièce / logement —"
CABANON_ROOM=$(get "$MARIE"  /api/rooms | python3 -c 'import sys,json; print(json.load(sys.stdin)["member"][0]["@id"])')
TENNIS_ROOM=$(get  "$SOPHIE" /api/rooms | python3 -c 'import sys,json; print(json.load(sys.stdin)["member"][0]["@id"])')
check "GET pièce du Cabanon (sophie)" 404 "$(status "$SOPHIE" "$CABANON_ROOM")"

# antonin est le seul à voir les deux logements : c'est donc le seul compte qui
# puisse croiser deux IRI valides, et le seul à exercer réellement la contrainte
# RoomBelongsToProperty — ni l'extension Doctrine ni le voter ne le bloquent.
BODY='{"name":"Croisé","quantity":1,"property":"'$TENNIS'","room":"'$CABANON_ROOM'"}'
R=$(post "$ANTONIN" /api/inventory_items "$BODY"); check "POST article pièce d'un autre logement" 422 "$R"
# Pour sophie la pièce du Cabanon n'existe pas : l'extension d'item la masque
# avant toute validation, d'où un 400 « Item not found » et non un 422.
BODY='{"name":"Masqué","quantity":1,"room":"'$CABANON_ROOM'"}'
R=$(post "$SOPHIE"  /api/inventory_items "$BODY"); check "POST article pièce invisible"           400 "$R"
# Logement omis : c'est le filet de PropertyScopeProcessor qui tranche, la
# contrainte ayant relâché sa comparaison faute de logement à comparer.
BODY='{"name":"Repli pièce","quantity":1,"room":"'$TENNIS_ROOM'"}'
R=$(post "$SOPHIE"  /api/inventory_items "$BODY"); check "POST article sans logement + pièce"     201 "$R"

# PUT/PATCH d'un article n'ont aucun processor : seule la contrainte protège.
PATCH_ITEM=$(get "$ANTONIN" "/api/inventory_items?property=$TENNIS" | python3 -c 'import sys,json; print(json.load(sys.stdin)["member"][0]["@id"])')
BODY='{"room":"'$CABANON_ROOM'"}'
R=$(curl -sk -o /dev/null -w '%{http_code}' -X PATCH "$API$PATCH_ITEM" -H "Authorization: Bearer $ANTONIN" \
      -H 'Content-Type: application/merge-patch+json' -d "$BODY")
check "PATCH article vers pièce d'un autre logement" 422 "$R"

echo
echo "— ROLE_ADMIN traverse tous les logements —"
check "GET /api/occupations (admin)"     8 "$(count "$ADMIN" /api/occupations)"
check "GET /api/properties (admin)"      2 "$(count "$ADMIN" /api/properties)"
check "GET item du Cabanon (admin)"    200 "$(status "$ADMIN" "$CABANON_ITEM")"
# 11 pièces des fixtures + la « Mezzanine » créée plus haut.
check "GET /api/rooms (admin)"          12 "$(count "$ADMIN" /api/rooms)"

echo
[ "$FAILED" = 0 ] && echo "TOUS LES SCÉNARIOS PASSENT" || echo "DES SCÉNARIOS ÉCHOUENT"
exit "$FAILED"

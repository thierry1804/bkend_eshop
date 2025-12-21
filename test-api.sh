#!/bin/bash

# Script de test rapide pour l'API de contact
# Usage: ./test-api.sh [base_url]
# Exemple: ./test-api.sh http://localhost:8000

BASE_URL="${1:-http://localhost:8000}"
ENDPOINT="${BASE_URL}/api/mail/contact"

echo "🧪 Tests de l'API Contact Mail"
echo "================================"
echo "Endpoint: ${ENDPOINT}"
echo ""

# Couleurs pour l'affichage
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Fonction pour afficher les résultats
test_result() {
    local test_name=$1
    local status_code=$2
    local expected=$3
    
    if [ "$status_code" -eq "$expected" ]; then
        echo -e "${GREEN}✓${NC} $test_name (HTTP $status_code)"
    else
        echo -e "${RED}✗${NC} $test_name (HTTP $status_code, attendu: $expected)"
    fi
}

# Test 1: Requête valide
echo "Test 1: Requête valide"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${ENDPOINT}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "message": "Ceci est un message de test pour vérifier que l endpoint fonctionne correctement."
  }')
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')
test_result "Requête valide" "$HTTP_CODE" 202
echo "Réponse: $BODY"
echo ""

# Test 2: Email invalide
echo "Test 2: Email invalide"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${ENDPOINT}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "email-invalide",
    "message": "Message de test avec email invalide pour vérifier la validation."
  }')
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')
test_result "Email invalide" "$HTTP_CODE" 400
echo "Réponse: $BODY"
echo ""

# Test 3: Message trop court
echo "Test 3: Message trop court"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${ENDPOINT}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "message": "Court"
  }')
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')
test_result "Message trop court" "$HTTP_CODE" 400
echo "Réponse: $BODY"
echo ""

# Test 4: Champs manquants
echo "Test 4: Champs manquants"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${ENDPOINT}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com"
  }')
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')
test_result "Champs manquants" "$HTTP_CODE" 400
echo "Réponse: $BODY"
echo ""

# Test 5: JSON invalide
echo "Test 5: JSON invalide"
RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${ENDPOINT}" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "message": "Test"
  invalid json')
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')
test_result "JSON invalide" "$HTTP_CODE" 400
echo "Réponse: $BODY"
echo ""

# Test 6: Rate limiting (5 requêtes rapides)
echo "Test 6: Rate limiting (5 requêtes rapides)"
echo -e "${YELLOW}Envoi de 6 requêtes rapides...${NC}"
for i in {1..6}; do
    RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${ENDPOINT}" \
      -H "Content-Type: application/json" \
      -d "{
        \"email\": \"test@example.com\",
        \"message\": \"Message de test $i pour vérifier le rate limiting.\"
      }")
    HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
    BODY=$(echo "$RESPONSE" | sed '$d')
    
    if [ "$i" -le 5 ]; then
        if [ "$HTTP_CODE" -eq 202 ]; then
            echo -e "${GREEN}✓${NC} Requête $i: Acceptée (HTTP $HTTP_CODE)"
        else
            echo -e "${RED}✗${NC} Requête $i: Erreur (HTTP $HTTP_CODE)"
        fi
    else
        if [ "$HTTP_CODE" -eq 429 ]; then
            echo -e "${GREEN}✓${NC} Requête $i: Rate limit activé (HTTP $HTTP_CODE)"
            echo "Réponse: $BODY"
        else
            echo -e "${RED}✗${NC} Requête $i: Rate limit non activé (HTTP $HTTP_CODE, attendu: 429)"
        fi
    fi
    sleep 0.1
done
echo ""

# Test 7: CORS
echo "Test 7: En-têtes CORS"
RESPONSE=$(curl -s -I -X OPTIONS "${ENDPOINT}" \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: POST")
if echo "$RESPONSE" | grep -q "Access-Control-Allow-Origin"; then
    echo -e "${GREEN}✓${NC} En-têtes CORS présents"
    echo "$RESPONSE" | grep -i "access-control"
else
    echo -e "${RED}✗${NC} En-têtes CORS manquants"
fi
echo ""

echo "================================"
echo "Tests terminés !"
echo ""
echo "💡 Pour tester l'envoi d'emails, assurez-vous que :"
echo "   1. Le worker Messenger est lancé : php bin/console messenger:consume async"
echo "   2. MAILER_DSN est configuré dans .env.local"
echo ""


#!/bin/bash
cd /Users/santiago/Documents/Clientes/eurobelleza
docker-compose exec -T eurobelleza-back php test-generator.php 2>&1 | grep -E "(VERIFICACIÓN|Línea [0-9]+:)"

# REQ-004 — AUTH-03: anti-enumeración operativa + lockout IP|cuenta

| Campo | Valor |
|---|---|
| **ID** | REQ-004 |
| **Descripción** | Tras N fallos de login para el par `IP|email`, el login queda bloqueado durante una ventana; el contador se lee (no solo se incrementa). Éxito limpia el contador. |
| **Módulo** | AUTH-03 |
| **Estado** | cerrado |
| **Misión** | MIS-004 |

## Criterio verificable

1. Tras `lockout_max_attempts` fallos (password incorrecta o email inexistente) con la misma IP, un intento **con password correcta** sigue fallando con el **mismo** mensaje de credenciales inválidas (no filtra lockout vs bad password).
2. El contador usa clave compuesta `IP|email` (no solo email).
3. Un login exitoso limpia el contador de ese par.
4. Fallos desde otra IP no bloquean el par de la primera IP (aislamiento por fingerprint de red).
5. Tests PHPUnit cubren bloqueo, limpieza y aislamiento; `composer test` verde.

## Fuera de alcance

- AUTH-07 audit HMAC
- Trusted devices / step-up
- Cambiar el throttle HTTP de rutas (sigue en 5/min aparte)

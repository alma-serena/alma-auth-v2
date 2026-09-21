# REQ-010 — AUTH-09: roadmap y condiciones pre-firmadas

| Campo | Valor |
|---|---|
| **ID** | REQ-010 |
| **Descripción** | El repositorio declara el estado de las primitivas AUTH, lo diferido y las condiciones bajo las cuales se abren misiones futuras (sin código de producto nuevo). |
| **Módulo** | AUTH-09 |
| **Estado** | cerrado |
| **Misión** | MIS-010 |

## Criterio verificable

1. Existe `docs/ROADMAP.md` con: hecho (AUTH-01…08), siguiente (AUTH-10), diferido explícito, no-objetivos.
2. El README enlaza ese roadmap.
3. `composer test` sigue verde (sin regressión; esta misión no añade código de runtime).

## Fuera de alcance

- Implementar AUTH-10
- Nuevas dependencias o rutas

# REQ-014 — alta por correo

| Campo | Valor |
|---|---|
| **ID** | REQ-014 |
| **Descripción** | Una persona abre una cuenta con correo y contraseña. Si el correo ya tiene cuenta, la respuesta es la misma y la contraseña anterior no cambia. Una contraseña filtrada se rechaza. Si el servicio de filtraciones no responde, el alta sigue. |
| **Módulo** | alta |
| **Estado** | cerrado |
| **Misión** | MIS-014 |
| **Origen** | ESP-005 AC5 y P9-1. El paquete no tenía alta. |

## Criterio verificable

1. `POST /api/alma-auth/register` con correo y contraseña de al menos 12 caracteres deja una cuenta con ese correo en minúsculas, y el login de esa cuenta entra.
2. Repetir el alta con el mismo correo responde igual y no cambia la contraseña.
3. Otra cuenta no se entera, por el cuerpo de la respuesta, de si el correo ya existía.
4. Una contraseña marcada como filtrada responde 422 y no crea cuenta.
5. Si la consulta de filtraciones falla, el alta no se bloquea.
6. `composer test` sigue en verde, por encima de la línea base de 51 tests.
